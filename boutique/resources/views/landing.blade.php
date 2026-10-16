@php $plans = \App\Support\Plans::all(); @endphp
<x-layouts.base title="Votre boutique sur WhatsApp, faite pour la Guinée"
    description="Créez votre boutique en 2 minutes. Vos clients commandent, la commande arrive sur votre WhatsApp. Payé en GNF par Orange Money, sans carte, sans prélèvement." class="bg-bg">

    <header class="mx-auto flex h-16 max-w-admin items-center gap-2 px-4">
        <img src="{{ asset('favicon.svg') }}" alt="" class="size-8" width="32" height="32">
        <span class="text-lg font-extrabold text-accent">Wanllan</span>
        <a href="{{ route('login') }}" class="ml-auto text-sm font-semibold text-accent">Se connecter</a>
    </header>

    <main class="mx-auto max-w-admin">
        {{-- 1. Hero --}}
        <section class="px-4 pt-6 pb-10">
            <p class="badge badge-accent mb-3">Fait pour la Guinée 🇬🇳</p>
            <h1 class="text-[34px] leading-[40px] font-extrabold">Votre boutique sur WhatsApp, en 2 minutes.</h1>
            <p class="mt-4 text-lg text-text-muted">Vos clients voient vos produits et vos prix, choisissent, et la commande arrive toute prête sur votre WhatsApp. Plus besoin de répondre 50 fois « c'est combien ? ».</p>
            <a href="{{ route('register') }}" class="btn btn-primary btn-lg mt-6">Créer ma boutique gratuitement</a>
            <p class="help text-center">Sans carte bancaire. En GNF. Paiement Orange Money ou MTN.</p>
            <div class="mt-8 flex justify-center gap-3">
                <img src="{{ asset('img/vitrine.webp') }}" alt="Une vitrine Wanllan sur téléphone : produits, prix en GNF, panier" width="195" class="w-[48%] max-w-[240px] rounded-lg border border-border shadow-card">
                <img src="{{ asset('img/facture.webp') }}" alt="La commande prête à être envoyée sur WhatsApp, paiement à la livraison" width="195" class="mt-8 w-[48%] max-w-[240px] rounded-lg border border-border shadow-card" loading="lazy">
            </div>
        </section>

        {{-- 2. Le problème --}}
        <section class="bg-surface px-4 py-10">
            <h2 class="text-xl font-bold">Vendre sur WhatsApp, c'est épuisant</h2>
            <ul class="mt-4 space-y-3">
                <li class="card p-4"><strong>Les mêmes questions toute la journée.</strong> « C'est combien ? », « Il y a ma taille ? », « Vous livrez à Ratoma ? »… et la vente se perd dans la discussion.</li>
                <li class="card p-4"><strong>Les outils d'ailleurs ne sont pas faits pour nous.</strong> Prix en dollars, paiement par carte, prélèvements automatiques difficiles à arrêter. Pas d'Orange Money, pas de GNF.</li>
            </ul>
        </section>

        {{-- 3. Comment ça marche --}}
        <section class="px-4 py-10">
            <h2 class="text-xl font-bold">Comment ça marche</h2>
            <ol class="mt-4 space-y-4">
                @foreach ([
                    ['Créez votre boutique', 'Votre nom, votre numéro WhatsApp, vos produits avec photos, tailles et prix en GNF.'],
                    ['Partagez votre lien', 'Dans vos pubs Facebook et TikTok, votre statut, vos groupes, et le message d\'accueil de WhatsApp Business.'],
                    ['Recevez des commandes complètes', 'Articles, total, adresse, livraison et paiement arrivent en un message sur votre WhatsApp.'],
                ] as $i => [$t, $d])
                    <li class="flex gap-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-pill bg-accent text-lg font-bold text-on-accent">{{ $i + 1 }}</span>
                        <div><p class="font-bold">{{ $t }}</p><p class="text-text-muted">{{ $d }}</p></div>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- 4. Ce qui change avec Wanllan --}}
        <section class="bg-surface px-4 py-10">
            <h2 class="text-xl font-bold">Ce qui change avec Wanllan</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach ([
                    ['💵', 'Pas de mauvaise surprise sur votre argent', 'Vous payez un mois ou un an à la fois, par Orange Money. Aucune carte, aucun prélèvement automatique. Vous arrêtez quand vous voulez.'],
                    ['🛵', 'Le paiement à la livraison, sous votre contrôle', 'Activez-le ou bloquez-le, fixez un montant maximum, choisissez les zones, bloquez un client qui a déjà refusé un colis.'],
                    ['📍', 'La Guinée dans l\'app', 'Prix en GNF, numéros +224, livraison par commune de Conakry et par ville : Kankan, Labé, Nzérékoré…'],
                    ['📊', 'Savoir quelle pub fait vendre', 'Chaque lien est étiqueté : vous voyez combien de visites et de ventes viennent de Facebook, TikTok ou WhatsApp.'],
                    ['📶', 'Rapide même en 3G', 'Pages légères et photos compressées automatiquement : vos clients ne s\'impatientent pas.'],
                    ['🔒', 'Captures de paiement privées', 'Le client envoie sa capture Orange Money ; vous seul la voyez, avec un rappel de vérifier sur votre téléphone.'],
                ] as [$icon, $t, $d])
                    <div class="card p-4">
                        <p class="text-2xl" aria-hidden="true">{{ $icon }}</p>
                        <p class="mt-1 font-bold">{{ $t }}</p>
                        <p class="text-sm text-text-muted">{{ $d }}</p>
                    </div>
                @endforeach
            </div>
            <img src="{{ asset('img/paiements.webp') }}" alt="Réglage du paiement à la livraison : interrupteur et montant maximum en GNF" width="240" class="mx-auto mt-6 w-[60%] max-w-[260px] rounded-lg border border-border shadow-card" loading="lazy">
        </section>

        {{-- 5. Prix --}}
        <section class="px-4 py-10" id="prix">
            <h2 class="text-xl font-bold">Des prix en GNF, sans engagement</h2>
            <p class="mt-1 text-text-muted">Commencez gratuitement. Payez seulement quand votre boutique vend.</p>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                @foreach ($plans as $key => $p)
                    <div class="card flex flex-col p-4 {{ $key === 'merchant' ? 'border-2 border-accent' : '' }}">
                        @if ($key === 'merchant')<span class="badge badge-accent mb-2 self-start">Recommandé</span>@endif
                        <p class="text-lg font-bold">{{ $p['name'] }}</p>
                        <p class="text-sm text-text-muted">{{ $p['for'] }}</p>
                        <p class="mt-3 text-2xl font-extrabold">{{ $p['monthly'] ? gnf($p['monthly']) : 'Gratuit' }}@if ($p['monthly'])<span class="text-sm font-normal text-text-muted"> / mois</span>@endif</p>
                        @if ($p['monthly'])<p class="text-sm text-text-muted">ou {{ gnf(\App\Support\Plans::yearly($key)) }} / an : 2 mois offerts</p>@endif
                        <ul class="mt-3 flex-1 space-y-1 text-sm">
                            @foreach ($p['features'] as $f)<li class="flex gap-2"><x-icon name="check" :size="16" class="mt-0.5 shrink-0 text-success" /> {{ $f }}</li>@endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <p class="help">Mise en place de votre boutique par notre équipe (produits et photos) : {{ gnf(config('wanllan.prices.setup')) }}, une seule fois.</p>
        </section>

        {{-- 6. Questions --}}
        <section class="bg-surface px-4 py-10">
            <h2 class="text-xl font-bold">Vos questions</h2>
            <div class="mt-4 space-y-2">
                @foreach ([
                    ['Mes clients doivent-ils installer une application ?', 'Non. Ils ouvrent simplement votre lien depuis WhatsApp, Facebook ou TikTok.'],
                    ['Est-ce que je dois avoir WhatsApp Business ?', 'Non, WhatsApp normal suffit. Avec WhatsApp Business, vous pouvez en plus mettre votre lien dans le message d\'accueil automatique.'],
                    ['Comment je paie l\'abonnement ?', 'Par Orange Money ou MTN, un mois ou un an à la fois. Rien n\'est prélevé automatiquement : à la fin, votre boutique repasse en Découverte, sans rien perdre.'],
                    ['Et si un client paie à la livraison puis refuse le colis ?', 'Vous pouvez limiter le paiement à la livraison à un montant maximum, à certaines zones, et le bloquer pour ce client.'],
                    ['Wanllan prend-il une commission sur mes ventes ?', 'Non. L\'argent de vos ventes va directement sur votre Orange Money, votre MTN ou dans votre main.'],
                    ['Ça marche avec une petite connexion ?', 'Oui. Les pages sont légères et les photos compressées automatiquement.'],
                ] as [$q, $a])
                    <details class="card p-4">
                        <summary class="cursor-pointer font-semibold">{{ $q }}</summary>
                        <p class="mt-2 text-text-muted">{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </section>

        {{-- 7. Appel final --}}
        <section class="px-4 py-12 text-center">
            <h2 class="text-2xl font-extrabold">Wanllan, « aide-moi » en poular.</h2>
            <p class="mt-2 text-text-muted">C'est exactement ce qu'on fait : vous aider à vendre.</p>
            <a href="{{ route('register') }}" class="btn btn-primary btn-lg mt-6">Créer ma boutique gratuitement</a>
            <a href="{{ \App\Services\WhatsAppMessage::url(config('wanllan.support_whatsapp'), 'Bonjour Wanllan, j\'ai une question sur la boutique WhatsApp.') }}"
               class="mt-3 block text-sm font-semibold text-accent" target="_blank" rel="noopener">Une question ? Écrivez-nous sur WhatsApp</a>
        </section>
    </main>

    <footer class="border-t border-border px-4 py-6 text-center text-xs text-text-muted">
        Wanllan · Conakry, Guinée · Support {{ config('wanllan.support_hours') }}
    </footer>
</x-layouts.base>
