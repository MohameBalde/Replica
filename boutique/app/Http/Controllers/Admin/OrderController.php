<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Services\OrderPlacer;
use App\Services\WhatsAppMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public const TABS = ['all' => 'Toutes', 'pending' => 'En attente', 'confirmed' => 'Confirmées', 'completed' => 'Terminées', 'cancelled' => 'Annulées'];

    public function index(Request $request): View
    {
        $store = $this->currentStore($request);
        $tab = array_key_exists($request->query('statut'), self::TABS) ? $request->query('statut') : 'all';

        $orders = $store->orders()
            ->when($tab !== 'all', fn ($q) => $q->where('status', $tab))
            ->withCount('items')
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $counts = $store->orders()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.orders.index', compact('orders', 'tab', 'counts'));
    }

    public function show(Request $request, int $order): View
    {
        $model = $this->find($request, $order);
        $model->load('items');

        $customerMessage = 'Bonjour '.$model->customer_name.', ici '.$this->currentStore($request)->name.'. '
            .'Merci pour votre commande #'.$model->number.' ('.gnf($model->total).').';

        return view('admin.orders.show', [
            'order' => $model,
            'writeToCustomerUrl' => WhatsAppMessage::url($model->customer_phone, $customerMessage),
        ]);
    }

    public function updateStatuses(Request $request, int $order): RedirectResponse
    {
        $model = $this->find($request, $order);

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'confirmed', 'completed'])],
            'payment_status' => ['required', Rule::in(array_keys(Order::PAYMENT_STATUSES))],
            'fulfillment_status' => ['required', Rule::in(array_keys(Order::FULFILLMENT_STATUSES))],
        ]);

        if ($model->isCancelled()) {
            return back()->withErrors(['status' => 'Cette commande est annulée.']);
        }

        $model->update($data);

        return back()->with('status', 'Commande #'.$model->number.' mise à jour.');
    }

    public function cancel(Request $request, int $order, OrderPlacer $placer): RedirectResponse
    {
        $model = $this->find($request, $order);
        $placer->cancel($model);

        return back()->with('status', 'Commande #'.$model->number.' annulée. Le stock a été remis.');
    }

    /** Une commande d'une autre boutique donne un 404, jamais un accès. */
    private function find(Request $request, int $order): Order
    {
        return $this->currentStore($request)->orders()->findOrFail($order);
    }

    private function currentStore(Request $request): Store
    {
        return $request->attributes->get('store');
    }
}
