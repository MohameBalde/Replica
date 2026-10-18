{{-- Facture à imprimer ou à enregistrer en PDF (menu Imprimer du navigateur). Page seule, sans menu. --}}
@php
    $date = $order->created_at->timezone(\App\Support\Period::TZ);
    $paid = $order->payment_status === 'paid';
    $payment = match (true) {
        $order->isCancelled() => 'Commande annulée',
        $paid => 'Payée',
        $order->payment_status === 'refunded' => 'Remboursée',
        $order->isCashOnDelivery() => 'À payer à la livraison : '.gnf($order->total),
        $order->payment_status === 'partially_paid' => 'Payée en partie',
        default => 'À payer : '.gnf($order->total),
    };
@endphp
<x-layouts.base :title="'Facture #'.$order->number.' · '.$store->name" class="bg-surface print:bg-bg">
    <style>@page { size: A4; margin: 14mm; }</style>

    <div class="mx-auto flex max-w-[210mm] gap-2 px-4 pt-4 print:hidden">
        <button type="button" onclick="window.print()" class="btn btn-primary w-auto flex-1 px-6 md:flex-none">Imprimer ou enregistrer en PDF</button>
        <a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-secondary w-auto px-6">Retour</a>
    </div>
    <p class="mx-auto max-w-[210mm] px-4 pt-2 text-sm text-text-muted print:hidden">Sur téléphone : Imprimer, puis « Enregistrer au format PDF ».</p>

    <main class="mx-auto my-4 max-w-[210mm] bg-bg p-6 text-sm shadow-card md:p-10 print:my-0 print:p-0 print:shadow-none">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-border pb-5">
            <div class="min-w-0">
                <p class="text-xl font-bold">{{ $store->name }}</p>
                @if ($store->city)<p class="text-text-muted">{{ $store->city }}</p>@endif
                <p class="text-text-muted">WhatsApp : {{ phone_display($store->whatsapp_number) }}</p>
                <p class="break-all text-text-muted">{{ route('shop.show', $store) }}</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-extrabold tracking-wide">FACTURE</p>
                <p class="font-semibold">N° {{ $order->number }}</p>
                <p class="text-text-muted">Du {{ $date->format('d/m/Y') }}</p>
            </div>
        </header>

        <section class="grid gap-4 border-b border-border py-5 md:grid-cols-2">
            <div>
                <p class="text-xs font-semibold tracking-wide text-text-muted uppercase">Client</p>
                <p class="font-semibold">{{ $order->customer_name }}</p>
                <p>{{ phone_display($order->customer_phone) }}</p>
                @if ($order->address)<p>{{ $order->address }}</p>@endif
            </div>
            <div class="md:text-right">
                <p class="text-xs font-semibold tracking-wide text-text-muted uppercase">Livraison et paiement</p>
                <p>{{ $order->delivery_method_name }}</p>
                <p>{{ $order->paymentLabel() }}</p>
                <p class="font-semibold">{{ $payment }}</p>
            </div>
        </section>

        <table class="mt-5 w-full">
            <thead class="border-b border-border text-left text-xs tracking-wide text-text-muted uppercase">
                <tr>
                    <th class="py-2 font-semibold">Article</th>
                    <th class="py-2 text-right font-semibold">Qté</th>
                    <th class="hidden py-2 text-right font-semibold sm:table-cell print:table-cell">Prix</th>
                    <th class="py-2 text-right font-semibold">Montant</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach ($order->items as $item)
                    <tr class="align-top">
                        <td class="py-2 pr-3">{{ $item->label() }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $item->quantity }}</td>
                        <td class="hidden py-2 pl-3 text-right whitespace-nowrap tabular-nums sm:table-cell print:table-cell">{{ gnf($item->unit_price) }}</td>
                        <td class="py-2 pl-3 text-right whitespace-nowrap tabular-nums">{{ gnf($item->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <dl class="mt-4 ml-auto max-w-xs space-y-1 border-t border-border pt-3">
            <div class="flex justify-between gap-4"><dt>Articles</dt><dd class="whitespace-nowrap tabular-nums">{{ gnf($order->subtotal) }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ $order->delivery_method_name }}</dt><dd class="whitespace-nowrap tabular-nums">{{ $order->delivery_fee ? gnf($order->delivery_fee) : 'Gratuit' }}</dd></div>
            <div class="flex justify-between gap-4 border-t border-border pt-2 text-lg font-bold"><dt>Total</dt><dd class="whitespace-nowrap tabular-nums">{{ gnf($order->total) }}</dd></div>
        </dl>

        @if ($order->note)
            <p class="mt-5 rounded-md bg-surface p-3">Note du client : « {{ $order->note }} »</p>
        @endif

        <footer class="mt-8 border-t border-border pt-4 text-center text-text-muted">
            <p>Merci pour votre achat !</p>
            @if ($store->showsBadge())<p class="mt-1 text-xs">Facture créée avec Wanllan · wanllan.com</p>@endif
        </footer>
    </main>
</x-layouts.base>
