<x-layouts.admin title="Produits">
    <div class="p-4">
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary"><x-icon name="plus" :size="20" /> Ajouter un produit</a>
    </div>

    @if ($products->isEmpty())
        <div class="px-4 py-12 text-center">
            <p class="text-lg font-semibold">Ajoutez votre premier produit en 1 minute.</p>
            <p class="mt-2 text-text-muted">Un nom, un prix, et vos clients peuvent déjà commander.</p>
        </div>
    @else
        <ul class="divide-y divide-border border-y border-border">
            @foreach ($products as $product)
                @php $stock = $product->totalStock(); @endphp
                <li>
                    <a href="{{ route('admin.products.edit', $product->id) }}" class="flex items-center gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $product->name }}</p>
                            <p class="text-sm text-text-muted">{{ $product->category?->name ?? 'Sans catégorie' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="price">{{ gnf($product->fromPrice()) }}</p>
                            <div class="mt-1 flex justify-end gap-1">
                                @unless ($product->is_visible)<span class="badge badge-neutral">Masqué</span>@endunless
                                @if ($stock === null)
                                    <span class="badge badge-neutral">Stock non suivi</span>
                                @elseif ($stock <= 0)
                                    <span class="badge badge-danger">Épuisé</span>
                                @elseif ($stock <= 3)
                                    <span class="badge badge-warning">Plus que {{ $stock }}</span>
                                @else
                                    <span class="badge badge-success">{{ $stock }} en stock</span>
                                @endif
                            </div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
