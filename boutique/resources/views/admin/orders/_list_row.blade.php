{{--
    Une commande de la liste Commandes, avec sa case à cocher pour les actions en masse.
    Téléphone : deux lignes. Grand écran (xl) : une ligne de tableau, colonnes dans $grid.
    Toute la ligne ouvre la commande ; la case reste au-dessus du lien.
--}}
@php $link = route('admin.orders.show', $order->id); $date = $order->created_at->timezone(\App\Support\Period::TZ); @endphp
<li class="relative flex items-start gap-1 py-1 pr-4 pl-1 hover:bg-surface/60 has-checked:bg-accent-soft/40 {{ $grid }} xl:py-0">
    <label class="relative z-10 grid min-h-tap min-w-tap shrink-0 cursor-pointer place-items-center">
        <input type="checkbox" form="bulk" name="orders[]" value="{{ $order->id }}" data-bulk-item class="size-5 accent-accent"
               aria-label="Sélectionner la commande #{{ $order->number }}">
    </label>

    {{-- Téléphone et tablette --}}
    <div class="min-w-0 flex-1 py-2 xl:hidden">
        <div class="flex items-baseline gap-2">
            <a href="{{ $link }}" class="font-bold after:absolute after:inset-0">#{{ $order->number }}</a>
            <span class="min-w-0 flex-1 truncate">{{ $order->customer_name }}</span>
            <span class="price">{{ gnf($order->total) }}</span>
        </div>
        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
            @include('admin.orders._badges')
            @if ($order->status === 'pending' && ! $order->whatsapp_sent_at)
                <span class="badge badge-warning">WhatsApp non envoyé</span>
            @endif
            @if ($order->sourceLabel())
                <span class="text-xs text-text-muted">{{ $order->sourceLabel() }}</span>
            @endif
            <span class="ml-auto text-xs text-text-muted">{{ $date->format('d/m H:i') }}</span>
        </div>
    </div>

    {{-- Grand écran : une cellule par colonne --}}
    <a href="{{ $link }}" class="hidden font-bold after:absolute after:inset-0 xl:block">#{{ $order->number }}</a>
    <span class="hidden min-w-0 xl:block">
        <span class="block truncate">{{ $order->customer_name }}</span>
        <span class="block truncate text-xs text-text-muted">{{ phone_display($order->customer_phone) }}@if ($order->sourceLabel()) · {{ $order->sourceLabel() }}@endif</span>
    </span>
    <span class="hidden text-sm text-text-muted xl:block">{{ $date->format('d/m H:i') }}</span>
    <span class="hidden xl:block">@include('admin.orders._badges', ['part' => 'status'])</span>
    <span class="hidden flex-wrap gap-1 xl:flex">
        @include('admin.orders._badges', ['part' => 'payment'])
        @if ($order->status === 'pending' && ! $order->whatsapp_sent_at)
            <span class="badge badge-warning">WhatsApp non envoyé</span>
        @endif
    </span>
    <span class="hidden text-sm xl:block">{{ \App\Models\Order::FULFILLMENT_STATUSES[$order->fulfillment_status] ?? '' }}</span>
    <span class="price hidden text-right xl:block">{{ gnf($order->total) }}</span>
</li>
