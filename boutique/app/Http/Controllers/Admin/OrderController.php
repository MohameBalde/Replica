<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentProof;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Store;
use App\Services\OrderPlacer;
use App\Services\WhatsAppMessage;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public const TABS = ['all' => 'Toutes', 'pending' => 'En attente', 'confirmed' => 'Confirmées', 'completed' => 'Terminées', 'cancelled' => 'Annulées'];

    public const PAYMENT_FILTERS = ['confirming' => 'À vérifier', 'unpaid' => 'Non payées', 'cod' => 'À la livraison', 'paid' => 'Payées'];

    /** Actions en masse : [champ, valeur, groupe du menu, libellé du menu, message (une / plusieurs)]. Annuler remet le stock. */
    public const BULK_ACTIONS = [
        'confirmed' => ['status', 'confirmed', 'Commande', 'Confirmer', ['confirmée', 'confirmées']],
        'completed' => ['status', 'completed', 'Commande', 'Terminer', ['terminée', 'terminées']],
        'cancel' => [null, null, 'Commande', 'Annuler (le stock est remis)', ['annulée, stock remis', 'annulées, stock remis']],
        'paid' => ['payment_status', 'paid', 'Paiement', 'Marquer « Payée »', ['marquée « Payée »', 'marquées « Payée »']],
        'ready' => ['fulfillment_status', 'ready', 'Livraison', 'Prête', ['marquée « Prête »', 'marquées « Prête »']],
        'out_for_delivery' => ['fulfillment_status', 'out_for_delivery', 'Livraison', 'En livraison', ['marquée « En livraison »', 'marquées « En livraison »']],
        'fulfilled' => ['fulfillment_status', 'fulfilled', 'Livraison', 'Livrée', ['marquée « Livrée »', 'marquées « Livrée »']],
    ];

    public const BULK_MAX = 100;

    public function index(Request $request): View
    {
        $store = $this->currentStore($request);
        $tab = array_key_exists($request->query('statut'), self::TABS) ? $request->query('statut') : 'all';
        $payment = array_key_exists($request->query('paiement'), self::PAYMENT_FILTERS) ? $request->query('paiement') : null;

        $orders = $store->orders()
            ->when($tab !== 'all', fn ($q) => $q->where('status', $tab))
            ->when($tab === 'all' && in_array($payment, ['confirming', 'unpaid', 'cod'], true), fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->when($payment === 'cod', fn ($q) => $q->where('payment_method_type', 'cash_on_delivery')->where('payment_status', 'unpaid'))
            ->when($payment && $payment !== 'cod', fn ($q) => $q->where('payment_status', $payment))
            ->withCount('items')
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $counts = $store->orders()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $toVerify = $store->orders()->where('payment_status', 'confirming')->where('status', '!=', 'cancelled')->count();

        return view('admin.orders.index', compact('orders', 'tab', 'counts', 'payment', 'toVerify'));
    }

    public function show(Request $request, int $order): View
    {
        $model = $this->find($request, $order);
        $model->load('items', 'paymentProofs', 'customer');

        $customerMessage = 'Bonjour '.$model->customer_name.', ici '.$this->currentStore($request)->name.'. '
            .'Merci pour votre commande #'.$model->number.' ('.gnf($model->total).').';

        return view('admin.orders.show', [
            'order' => $model,
            'writeToCustomerUrl' => WhatsAppMessage::url($model->customer_phone, $customerMessage),
            'messages' => collect(WhatsAppMessage::toCustomer($model))
                ->map(fn ($m) => ['label' => $m[0], 'text' => $m[1], 'url' => WhatsAppMessage::url($model->customer_phone, $m[1])]),
        ]);
    }

    /** Facture imprimable (ou à enregistrer en PDF depuis le navigateur). */
    public function print(Request $request, int $order): View
    {
        $model = $this->find($request, $order);
        $model->load('items', 'paymentMethod');

        // QR code vers la page de suivi de la commande (/c/…), en image intégrée : imprimé, il se scanne.
        $svg = (new Writer(new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd())))->writeString(route('invoice.show', $model));

        return view('admin.orders.print', [
            'order' => $model,
            'store' => $this->currentStore($request),
            'qr' => 'data:image/svg+xml;base64,'.base64_encode($svg),
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

    /**
     * Même changement sur plusieurs commandes cochées dans la liste. Seules les
     * commandes de la boutique sont touchées ; les annulées ne bougent plus.
     */
    public function bulk(Request $request, OrderPlacer $placer): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(self::BULK_ACTIONS))],
            'orders' => ['required', 'array', 'max:'.self::BULK_MAX],
            'orders.*' => ['integer'],
        ], [
            'action.required' => 'Choisissez une action.',
            'action.in' => 'Choisissez une action.',
            'orders.required' => 'Cochez au moins une commande.',
            'orders.max' => 'Au plus '.self::BULK_MAX.' commandes à la fois.',
        ]);

        $orders = $this->currentStore($request)->orders()->whereIn('id', array_unique($data['orders']))->get();
        $active = $orders->reject(fn (Order $order) => $order->isCancelled());
        [$field, $value, , , [$one, $many]] = self::BULK_ACTIONS[$data['action']];

        if ($active->isEmpty()) {
            return back()->withErrors(['orders' => $orders->isEmpty()
                ? 'Aucune commande modifiée.'
                : 'Aucune commande modifiée : les commandes choisies sont annulées.']);
        }

        if ($field === null) {
            $active->each(fn (Order $order) => $placer->cancel($order));
        } else {
            Order::whereKey($active->modelKeys())->update([$field => $value]);
        }

        $n = $active->count();
        $skipped = $orders->count() - $n;
        $message = $n.' '.($n > 1 ? 'commandes '.$many : 'commande '.$one).'.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' '.($skipped > 1 ? 'commandes annulées n\'ont pas été modifiées.' : 'commande annulée n\'a pas été modifiée.');
        }

        return back()->with('status', $message);
    }

    /** Capture de paiement privée : servie seulement au commerçant de la boutique. */
    public function proof(Request $request, int $order, int $proof): StreamedResponse
    {
        $model = $this->find($request, $order)->paymentProofs()->findOrFail($proof);

        return Storage::disk(PaymentProof::DISK)->response($model->path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
