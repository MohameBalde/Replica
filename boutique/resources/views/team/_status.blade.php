{{-- État d'une boutique pour l'équipe : suspendue, formule en cours, terminée ou Découverte. --}}
@php
    $plan = \App\Support\Plans::get($store->plan);
    $end = $store->plan_expires_at?->timezone('Africa/Conakry');
@endphp
@if ($store->isSuspended())
    <span class="badge badge-danger">Suspendue</span>
@endif
@if ($store->activePlan() !== \App\Support\Plans::FREE)
    <span class="badge {{ $store->planDaysLeft() <= \App\Models\Store::REMIND_BEFORE_DAYS ? 'badge-warning' : 'badge-success' }}">{{ $plan['name'] }} · fin {{ $end->format('d/m/Y') }}</span>
@elseif ($store->plan !== \App\Support\Plans::FREE && $end)
    <span class="badge badge-warning">{{ $plan['name'] }} terminée le {{ $end->format('d/m/Y') }}</span>
@else
    <span class="badge badge-neutral">Découverte</span>
@endif
