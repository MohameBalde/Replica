<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** S16 : catégories (onglets de la vitrine) : ajouter, renommer, ordonner, supprimer. */
class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.categories.index', [
            'categories' => $this->currentStore($request)->categories()->withCount('products')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $store = $this->currentStore($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('categories')->where('store_id', $store->id)]],
            ['name.unique' => 'Cette catégorie existe déjà.']);
        $store->categories()->create($data + ['position' => (int) $store->categories()->max('position') + 1]);

        return back()->with('status', 'Catégorie ajoutée.');
    }

    public function update(Request $request, int $category): RedirectResponse
    {
        $store = $this->currentStore($request);
        $model = $store->categories()->findOrFail($category);
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('categories')->where('store_id', $store->id)->ignore($model->id)]],
            ['name.unique' => 'Cette catégorie existe déjà.']);
        $model->update($data);

        return back()->with('status', 'Catégorie renommée.');
    }

    /** Monter ou descendre d'un cran dans les onglets de la vitrine. */
    public function move(Request $request, int $category): RedirectResponse
    {
        $store = $this->currentStore($request);
        $direction = $request->input('direction') === 'up' ? -1 : 1;

        DB::transaction(function () use ($store, $category, $direction) {
            $list = $store->categories()->lockForUpdate()->get()->values();
            $i = $list->search(fn ($c) => $c->id === $category);
            abort_if($i === false, 404);
            $j = $i + $direction;
            if ($j < 0 || $j >= $list->count()) {
                return;
            }
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
            foreach ($list as $position => $c) {
                $c->update(['position' => $position]);
            }
        });

        return back();
    }

    /** Les produits de la catégorie restent en vente, simplement sans catégorie. */
    public function destroy(Request $request, int $category): RedirectResponse
    {
        $model = $this->currentStore($request)->categories()->findOrFail($category);
        $model->delete();

        return back()->with('status', '« '.$model->name.' » supprimée. Ses produits restent en vente, sans catégorie.');
    }

    private function currentStore(Request $request): Store
    {
        return $request->attributes->get('store');
    }
}
