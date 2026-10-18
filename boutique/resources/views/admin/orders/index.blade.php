<x-layouts.admin title="Commandes" wide>
    {{-- Commande reçue par téléphone ou vendue au comptoir : saisie en une page. --}}
    <div class="flex justify-end px-4 pt-3">
        <a href="{{ route('admin.orders.create') }}" class="btn btn-primary w-auto gap-1 px-4"><x-icon name="plus" :size="18" /> Nouvelle commande</a>
    </div>
    <nav class="flex gap-5 overflow-x-auto border-b border-border px-4" aria-label="Filtrer les commandes">
        @foreach (\App\Http\Controllers\Admin\OrderController::TABS as $key => $label)
            @php $n = $key === 'all' ? $counts->sum() : ($counts[$key] ?? 0); @endphp
            <a href="{{ route('admin.orders.index', $key === 'all' ? [] : ['statut' => $key]) }}"
               @if ($tab === $key) aria-current="page" @endif
               class="whitespace-nowrap py-3 text-sm font-semibold {{ $tab === $key ? 'text-accent shadow-[inset_0_-2px_0_var(--color-accent)]' : 'text-text-muted' }}">
                {{ $label }} @if ($n)<span class="font-normal">({{ $n }})</span>@endif
            </a>
        @endforeach
    </nav>

    <div class="flex gap-2 overflow-x-auto px-4 py-3" role="group" aria-label="Filtrer par paiement">
        @foreach (\App\Http\Controllers\Admin\OrderController::PAYMENT_FILTERS as $key => $label)
            @php $on = $payment === $key; @endphp
            <a href="{{ route('admin.orders.index', array_filter(['statut' => $tab === 'all' ? null : $tab, 'paiement' => $on ? null : $key])) }}"
               @if ($on) aria-current="true" @endif
               class="badge min-h-8 shrink-0 px-3 text-sm {{ $on ? 'bg-accent text-on-accent' : 'badge-neutral' }}">
                {{ $label }}@if ($key === 'confirming' && $toVerify) ({{ $toVerify }})@endif
            </a>
        @endforeach
        <a href="{{ route('admin.customers.index') }}" class="badge badge-neutral ml-auto min-h-8 shrink-0 gap-1 px-3 text-sm"><x-icon name="users" :size="14" /> Clients</a>
    </div>

    @if ($toVerify && $payment !== 'confirming')
        <a href="{{ route('admin.orders.index', ['paiement' => 'confirming']) }}" class="mx-4 mb-2 block rounded-md bg-warning-soft px-3 py-2 text-sm font-semibold text-on-warning-soft">
            {{ $toVerify }} paiement{{ $toVerify > 1 ? 's' : '' }} à vérifier →
        </a>
    @endif

    @if ($orders->isEmpty())
        <div class="px-4 py-12 text-center">
            <p class="text-lg font-semibold">Pas encore de commande ici.</p>
            <p class="mt-2 text-text-muted">Partagez le lien de votre boutique pour recevoir les premières.</p>
            <p class="mt-4 rounded-md bg-surface px-3 py-2 font-mono text-sm break-all">{{ route('shop.show', $currentStore) }}</p>
        </div>
    @else
        @php
            // Grand écran : une ligne de tableau. Case | N° | Client | Reçue | Commande | Paiement | Livraison | Total.
            // Client garde au moins 8rem : à 1200 px, les colonnes fixes ne l'écrasent pas.
            $grid = 'xl:grid xl:grid-cols-[2.75rem_4.5rem_minmax(8rem,1fr)_5.5rem_6.5rem_minmax(0,8.5rem)_6.5rem_8rem] xl:items-center xl:gap-2';
        @endphp
        <div class="flex items-center gap-1 border-y border-border bg-surface/50 py-0.5 pr-4 pl-1 text-sm font-semibold text-text-muted {{ $grid }}">
            <label class="flex min-h-tap shrink-0 cursor-pointer items-center xl:grid xl:min-w-tap xl:place-items-center">
                <span class="grid min-w-tap place-items-center"><input type="checkbox" data-bulk-all class="size-5 accent-accent" autocomplete="off"></span>
                <span class="xl:sr-only">Tout sélectionner<span class="sr-only"> sur cette page</span></span>
            </label>
            @foreach (['N°', 'Client', 'Reçue', 'Commande', 'Paiement', 'Livraison', 'Total'] as $col)
                <span class="hidden xl:block {{ $col === 'Total' ? 'text-right' : '' }}">{{ $col }}</span>
            @endforeach
        </div>
        <ul class="divide-y divide-border">
            @foreach ($orders as $order)
                @include('admin.orders._list_row')
            @endforeach
        </ul>
        <div class="p-4">{{ $orders->links('pagination::simple-tailwind') }}</div>

        {{-- Actions en masse : la barre apparaît dès qu'une commande est cochée (au-dessus du menu sur téléphone). --}}
        <p class="sr-only" aria-live="polite" data-bulk-live></p>
        <form id="bulk" method="POST" action="{{ route('admin.orders.bulk') }}" data-bulk-bar hidden
              class="fixed inset-x-0 bottom-16 z-30 mx-auto max-w-admin border-t border-border bg-bg p-3 shadow-pop lg:sticky lg:bottom-0 lg:max-w-none lg:shadow-none">
            @csrf
            <p class="mb-2 text-sm font-semibold" data-bulk-count></p>
            <div class="flex gap-2">
                <label class="sr-only" for="bulk-action">Action</label>
                <select class="input min-w-0 flex-1" id="bulk-action" name="action" required>
                    <option value="">Choisir une action…</option>
                    @foreach (collect(\App\Http\Controllers\Admin\OrderController::BULK_ACTIONS)->groupBy(fn ($a) => $a[2], true) as $group => $actions)
                        <optgroup label="{{ $group }}">
                            @foreach ($actions as $key => $a)<option value="{{ $key }}">{{ $a[3] }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
                <button class="btn btn-primary w-auto shrink-0 px-5" type="submit">Appliquer</button>
            </div>
        </form>
        <div class="h-28 lg:hidden" aria-hidden="true"></div>

        <script>
            (() => {
                const form = document.getElementById('bulk');
                const items = [...document.querySelectorAll('[data-bulk-item]')];
                const all = document.querySelector('[data-bulk-all]');
                const count = form.querySelector('[data-bulk-count]');
                const live = document.querySelector('[data-bulk-live]'); // toujours présent : lu par les lecteurs d'écran
                function sync() {
                    const n = items.filter(i => i.checked).length;
                    form.hidden = n === 0;
                    count.textContent = live.textContent = n ? n + (n > 1 ? ' commandes sélectionnées' : ' commande sélectionnée') : '';
                    all.checked = n > 0 && n === items.length;
                    all.indeterminate = n > 0 && n < items.length;
                }
                items.forEach(i => i.addEventListener('change', sync));
                all.addEventListener('change', () => { items.forEach(i => { i.checked = all.checked; }); sync(); });
                // Annuler ou marquer « Payée » se vérifie avant : ces deux actions ne se défont pas d'un clic.
                form.addEventListener('submit', e => {
                    const n = items.filter(i => i.checked).length;
                    const warn = {
                        cancel: 'Annuler ' + n + ' commande(s) ? Le stock sera remis.',
                        paid: 'Marquer ' + n + ' commande(s) « Payée » ? Vérifiez d\'abord sur votre téléphone que l\'argent est arrivé.',
                    }[form.elements['action'].value]; // form.action serait l'adresse du formulaire
                    if (warn && !confirm(warn)) e.preventDefault();
                });
                // Retour arrière : le navigateur peut recocher les cases après ce script.
                window.addEventListener('pageshow', sync);
                sync();
            })();
        </script>
    @endif
</x-layouts.admin>
