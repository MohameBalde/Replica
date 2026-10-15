@php
    $statusClass = ['pending' => 'badge-neutral', 'confirmed' => 'badge-info', 'completed' => 'badge-success', 'cancelled' => 'badge-danger'];
    $paymentClass = ['unpaid' => 'badge-neutral', 'confirming' => 'badge-warning', 'partially_paid' => 'badge-warning', 'paid' => 'badge-success', 'refunded' => 'badge-danger'];
@endphp
<span class="badge {{ $statusClass[$order->status] ?? 'badge-neutral' }}">{{ \App\Models\Order::STATUSES[$order->status] }}</span>
@if ($order->isCashOnDelivery() && $order->payment_status === 'unpaid')
    <span class="badge badge-accent">À la livraison</span>
@else
    <span class="badge {{ $paymentClass[$order->payment_status] ?? 'badge-neutral' }}">{{ \App\Models\Order::PAYMENT_STATUSES[$order->payment_status] }}</span>
@endif
