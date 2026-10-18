{{-- Accueil : variantes suivies à Product::LOW_STOCK ou moins (5 affichées, la 6e dit qu'il y en a d'autres). --}}
@if ($lowStock->isNotEmpty())
    <div class="mt-4">
        <h3 class="text-sm font-bold">Stock bas</h3>
        <ul class="mt-1 divide-y divide-border text-sm">
            @foreach ($lowStock->take(5) as $row)
                <li>
                    <a href="{{ route('admin.products.edit', $row->product_id) }}" class="flex min-h-tap items-center gap-3">
                        {{-- La taille ou la couleur ne se coupe jamais : c'est elle qui distingue les lignes. --}}
                        <span class="min-w-0 flex-1 truncate">{{ $row->product_name }}</span>
                        @if ($row->variant_name !== 'Standard')<span class="max-w-[40%] shrink-0 truncate font-semibold">{{ $row->variant_name }}</span>@endif
                        @if ($row->stock <= 0)
                            <span class="badge badge-danger">Épuisé</span>
                        @else
                            <span class="badge badge-warning">Plus que {{ $row->stock }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
        <a href="{{ route('admin.products.index') }}" class="mt-1 inline-flex min-h-tap items-center text-sm font-semibold text-accent">
            {{ $lowStock->count() > 5 ? 'Et d\'autres : voir mes produits →' : 'Voir mes produits →' }}
        </a>
        <p class="text-xs text-text-muted">Une commande en attente garde son stock jusqu'à ce qu'elle soit annulée.</p>
    </div>
@endif
