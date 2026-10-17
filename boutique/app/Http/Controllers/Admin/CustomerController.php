<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** S17 : clients, historique, et blocage du paiement à la livraison. */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $store = $this->currentStore($request);
        $search = trim((string) $request->query('q'));

        $customers = $store->customers()
            ->when($search !== '', function ($q) use ($search) {
                $digits = preg_replace('/\D+/', '', $search);
                $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                    ->when($digits !== '', fn ($w) => $w->orWhere('phone', 'like', "%{$digits}%")));
            })
            ->withCount(['orders' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->withSum(['orders as spent' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total')
            ->withCount(['orders as cancelled_count' => fn ($q) => $q->where('status', 'cancelled')])
            ->latest('updated_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.customers.index', compact('customers', 'search'));
    }

    public function show(Request $request, int $customer): View
    {
        $model = $this->currentStore($request)->customers()->findOrFail($customer);

        return view('admin.customers.show', [
            'customer' => $model,
            'orders' => $model->orders()->latest()->limit(50)->get(),
        ]);
    }

    public function toggleCashOnDelivery(Request $request, int $customer): RedirectResponse
    {
        $model = $this->currentStore($request)->customers()->findOrFail($customer);
        $model->update(['cash_on_delivery_blocked' => ! $model->cash_on_delivery_blocked]);

        return back()->with('status', $model->cash_on_delivery_blocked
            ? $model->name.' devra payer avant la livraison.'
            : $model->name.' peut de nouveau payer à la livraison.');
    }

    private function currentStore(Request $request): Store
    {
        return $request->attributes->get('store');
    }
}
