<x-layouts.admin title="Partager ma boutique">
    {{-- Copier : marche avec l'API presse-papiers, et retombe sur la sélection du texte sinon. --}}
    <script>
        function copyText(btn, text) {
            const done = () => { const t = btn.dataset.label; btn.querySelector('span').textContent = 'Copié ✓'; setTimeout(() => btn.querySelector('span').textContent = t, 1800); };
            if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
            const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); done(); } finally { ta.remove(); }
        }
    </script>

    @if ($stats->isNotEmpty())
        <section class="section">
            <h2 class="section-title">Ce qui fait vendre ({{ [1 => 'aujourd\'hui', 7 => '7 derniers jours', 30 => '30 derniers jours'][$days] }})</h2>
            <table class="w-full text-sm">
                <thead class="text-left text-text-muted">
                    <tr><th class="py-1 font-semibold">Réseau</th><th class="py-1 text-right font-semibold">Visites</th><th class="py-1 text-right font-semibold">Commandes</th><th class="py-1 text-right font-semibold">Ventes</th></tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($stats->sortByDesc('revenue') as $s)
                        <tr>
                            <td class="py-2 font-semibold">{{ $s['label'] }}</td>
                            <td class="py-2 text-right">{{ $s['visits'] }}</td>
                            <td class="py-2 text-right">{{ $s['orders'] }}</td>
                            <td class="py-2 text-right whitespace-nowrap">{{ gnf($s['revenue']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="help">Chaque lien ci-dessous porte une étiquette : vous savez quelle pub vous rapporte.</p>
        </section>
    @endif

    <section class="section">
        <h2 class="section-title">1. Le message d'accueil WhatsApp</h2>
        <p class="mb-3 text-sm text-text-muted">Quand un client vous écrit après une pub, il reçoit ce message tout seul avec le lien de la boutique.
            À coller dans <strong class="text-text">WhatsApp Business → Outils professionnels → Message d'accueil</strong>, et dans la réponse automatique de votre page Facebook.</p>
        <form method="POST" action="{{ route('admin.share.welcome') }}">
            @csrf @method('PUT')
            <label class="sr-only" for="welcome_message">Message d'accueil</label>
            <textarea class="input" id="welcome_message" name="welcome_message" rows="5" maxlength="1000">{{ old('welcome_message', $store->welcome_message) }}</textarea>
            <p class="help">Écrivez <strong class="font-mono">{lien}</strong> là où doit apparaître le lien de votre boutique.</p>
            @error('welcome_message')<p class="error">{{ $message }}</p>@enderror
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" class="btn btn-primary" data-label="Copier"
                        onclick="copyText(this, document.getElementById('welcome_message').value.replaceAll('{lien}', @js(route('shop.show', $store).'?src=whatsapp')))"><x-icon name="copy" :size="18" /><span>Copier</span></button>
                <button type="submit" class="btn btn-secondary">Enregistrer</button>
            </div>
        </form>
    </section>

    <section class="section">
        <h2 class="section-title">2. Un lien par réseau</h2>
        <ul class="space-y-3">
            @foreach ($links as $link)
                <li class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $link['label'] }}</p>
                        <p class="text-sm text-text-muted">{{ $link['where'] }}</p>
                        <p class="truncate font-mono text-xs text-text-muted">{{ $link['url'] }}</p>
                    </div>
                    <button type="button" class="btn btn-secondary w-auto shrink-0" data-label="Copier" onclick="copyText(this, @js($link['url']))" aria-label="Copier le lien {{ $link['label'] }}">
                        <x-icon name="copy" :size="18" /><span>Copier</span>
                    </button>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="section">
        <h2 class="section-title">3. Un lien par produit</h2>
        <p class="mb-3 text-sm text-text-muted">Pour une pub sur un seul produit : le client arrive directement sur la bonne page.</p>
        @if ($products->isEmpty())
            <p class="text-sm text-text-muted">Ajoutez d'abord des produits.</p>
        @else
            <ul class="divide-y divide-border">
                @foreach ($products as $product)
                    @php $url = route('shop.product', [$store, $product]).'?src=facebook'; @endphp
                    <li class="flex items-center gap-3 py-2">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate">{{ $product->name }}</span>
                            <span class="block truncate font-mono text-xs text-text-muted">{{ $url }}</span>
                        </span>
                        <button type="button" class="btn btn-ghost w-auto shrink-0 min-h-tap px-2" data-label="Copier" onclick="copyText(this, @js($url))" aria-label="Copier le lien de {{ $product->name }}">
                            <x-icon name="copy" :size="18" /><span>Copier</span>
                        </button>
                    </li>
                @endforeach
            </ul>
            <p class="help">Ces liens sont étiquetés Facebook. Pour TikTok, remplacez « facebook » par « tiktok » à la fin.</p>
        @endif
    </section>

    <section class="section">
        <h2 class="section-title">4. Une image pour votre statut</h2>
        <p class="mb-3 text-sm text-text-muted">Au format du statut WhatsApp et de TikTok : vos produits, vos prix, le lien et un QR code. Téléchargez-la, puis publiez-la.</p>
        <img src="{{ route('admin.share.image') }}" alt="Image de statut de {{ $store->name }}" width="270" height="480" loading="lazy" class="mx-auto h-auto w-56 rounded-md border border-border">
        <a href="{{ route('admin.share.image', ['download' => 1]) }}" class="btn btn-primary mt-3">Télécharger l'image</a>
        @if ($products->isNotEmpty())
            <details class="mt-3">
                <summary class="inline-flex min-h-tap cursor-pointer items-center text-sm font-semibold text-accent">Une image par produit ▾</summary>
                <ul class="divide-y divide-border">
                    @foreach ($products as $product)
                        <li class="flex items-center gap-3 py-2">
                            <span class="min-w-0 flex-1 truncate">{{ $product->name }}</span>
                            <a href="{{ route('admin.share.image', ['product' => $product->id, 'download' => 1]) }}" class="btn btn-ghost w-auto shrink-0 min-h-tap px-2 text-sm">Télécharger</a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>

    <section class="section border-b-0">
        <h2 class="section-title">5. Le QR code du comptoir</h2>
        <p class="mb-3 text-sm text-text-muted">À imprimer et coller dans la boutique : les clients scannent et commandent.</p>
        <img src="{{ route('admin.share.qr') }}" alt="QR code de la boutique {{ $store->name }}" class="mx-auto size-56 rounded-md border border-border bg-white p-2">
        <a href="{{ route('admin.share.qr', ['download' => 1]) }}" class="btn btn-secondary mt-3">Télécharger le QR code</a>
    </section>
</x-layouts.admin>
