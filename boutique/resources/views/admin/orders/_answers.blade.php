{{-- Réponses du client aux questions de la boutique (ex. point de repère). --}}
@foreach ($order->answers ?? [] as $qa)
    <p class="{{ $class ?? 'mt-1' }} [overflow-wrap:anywhere]">{{-- un lien Google Maps ne fait pas déborder la page --}}<span class="text-text-muted">{{ $qa['q'] }} :</span> {{ $qa['a'] }}</p>
@endforeach
