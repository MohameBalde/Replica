{{-- Récapitulatif : lignes d'articles, livraison, total, paiement. --}}
<ul class="space-y-2">
    @foreach ($items as $item)
        <li class="flex gap-3 text-sm">
            <span class="w-8 shrink-0 text-text-muted">{{ $item->quantity }} ×</span>
            <span class="min-w-0 flex-1 break-words">{{ $item->label() }}@if ($item->options)<span class="block text-text-muted">+ {{ $item->optionsLabel() }}</span>@endif</span>
            <span class="price">{{ gnf($item->line_total) }}</span>
        </li>
    @endforeach
</ul>
<div class="mt-3 space-y-1 border-t border-border pt-3 text-sm">
    <div class="flex justify-between"><span>Articles</span><span class="whitespace-nowrap">{{ gnf($order->subtotal) }}</span></div>
    <div class="flex justify-between"><span>{{ $order->delivery_method_name }}</span><span class="whitespace-nowrap">{{ $order->delivery_fee ? gnf($order->delivery_fee) : 'Gratuit' }}</span></div>
    <div class="flex justify-between border-t border-border pt-2 text-lg font-bold"><span>Total</span><span class="whitespace-nowrap">{{ gnf($order->total) }}</span></div>
    <div class="flex justify-between pt-1 text-text-muted"><span>Paiement</span><span>{{ $order->paymentLabel() }}</span></div>
</div>
