<x-layouts.guest title="Conditions d'utilisation" wide>
    @php($support = phone_display(config('wanllan.support_whatsapp')))
    <article class="legal">
        <h1>Conditions d'utilisation</h1>
        <p class="text-text-muted">Mise à jour : 16 octobre 2026</p>

        <h2>1. Le service</h2>
        <p>Wanllan permet à un commerçant de créer une boutique en ligne, de partager son lien et de recevoir ses commandes sur WhatsApp. Wanllan n'est pas le vendeur : chaque vente se fait entre le commerçant et son client.</p>

        <h2>2. Votre compte</h2>
        <ul>
            <li>Il faut avoir au moins 18 ans pour ouvrir une boutique.</li>
            <li>Les informations du compte doivent être exactes.</li>
            <li>Vous gardez votre mot de passe pour vous. Tout ce qui se fait avec votre compte est sous votre responsabilité.</li>
        </ul>

        <h2>3. Ce que le commerçant s'engage à faire</h2>
        <ul>
            <li>Vendre seulement des produits autorisés en Guinée. Interdit : armes, drogues, médicaments sans autorisation, contrefaçons, produits volés, contenus trompeurs ou choquants.</li>
            <li>Afficher des prix, des photos et des stocks honnêtes.</li>
            <li>Livrer ce qui a été commandé et payé, et régler lui-même les retours et les remboursements avec ses clients.</li>
            <li>Utiliser les numéros et adresses de ses clients seulement pour leurs commandes : pas de messages en masse non demandés.</li>
        </ul>

        <h2>4. L'argent des ventes</h2>
        <p>Les clients paient le commerçant directement : Orange Money, MTN MoMo ou à la livraison. Wanllan ne touche pas cet argent et ne prend aucune commission. En cas de désaccord sur une vente, c'est au commerçant et au client de s'entendre. Wanllan peut suspendre une boutique signalée pour fraude.</p>

        <h2>5. Formules et abonnements</h2>
        <ul>
            <li>La formule Découverte est gratuite, avec les limites indiquées dans « Mon abonnement ».</li>
            <li>Les formules payantes se paient d'avance en GNF, par Orange Money, pour 1 mois ou 1 an.</li>
            <li><strong>Rien ne se renouvelle tout seul</strong> : aucune carte, aucun prélèvement. Pour arrêter, il suffit de ne pas repayer.</li>
            <li>À la fin d'une période payée, la boutique repasse en Découverte. Rien n'est supprimé.</li>
            <li>Un changement de prix ne touche jamais une période déjà payée.</li>
            <li>Une période commencée n'est pas remboursée. Si Wanllan est indisponible par notre faute plus de 3 jours de suite, la période est prolongée d'autant.</li>
        </ul>

        <h2>6. Disponibilité</h2>
        <p>Nous faisons de notre mieux pour que Wanllan marche tout le temps, et nous sauvegardons les données chaque jour. Le service peut quand même s'arrêter pour une maintenance ou une panne. WhatsApp, Orange Money et MTN MoMo sont des services indépendants de Wanllan.</p>

        <h2>7. Suspension</h2>
        <p>Une boutique qui ne respecte pas ces conditions peut être suspendue, après un avertissement sur WhatsApp, sauf en cas de fraude ou de danger.</p>

        <h2>8. Responsabilité</h2>
        <p>Wanllan répond du bon fonctionnement de son service, pas des ventes entre commerçants et clients. Sa responsabilité est limitée aux sommes payées par le commerçant à Wanllan pendant les 12 derniers mois.</p>

        <h2>9. Vos contenus, notre marque</h2>
        <p>Vos photos et vos textes restent à vous. Vous autorisez Wanllan à les afficher, seulement pour faire fonctionner votre boutique. Le nom Wanllan, son logo et l'application appartiennent à leur éditeur.</p>

        <h2>10. Fermer son compte</h2>
        <p>Vous pouvez supprimer votre compte à tout moment : Réglages, puis « Supprimer mon compte ». Tout est effacé (voir la <a href="{{ route('legal.privacy') }}">politique de confidentialité</a>).</p>

        <h2>11. Droit applicable</h2>
        <p>Ces conditions relèvent du droit guinéen. En cas de litige, on cherche d'abord une solution à l'amiable ; sinon, les tribunaux de Conakry sont compétents.</p>

        <h2>12. Éditeur et contact</h2>
        <p>{{ config('wanllan.legal_name') }}, {{ config('wanllan.legal_city') }}. WhatsApp {{ $support }}@if (config('wanllan.contact_email')), e-mail {{ config('wanllan.contact_email') }}@endif.</p>

        <p class="mt-8"><a href="{{ route('legal.privacy') }}">Confidentialité</a> · <a href="{{ route('home') }}">Accueil</a></p>
    </article>
</x-layouts.guest>
