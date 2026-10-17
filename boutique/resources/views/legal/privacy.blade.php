<x-layouts.guest title="Confidentialité" wide>
    @php($support = phone_display(config('wanllan.support_whatsapp')))
    <article class="legal">
        <h1>Politique de confidentialité</h1>
        <p class="text-text-muted">Mise à jour : 16 octobre 2026</p>

        <p>Wanllan permet aux commerçants de créer une boutique en ligne et de recevoir leurs commandes sur WhatsApp. Cette page dit quelles informations nous gardons, pourquoi, qui les voit, et comment les supprimer.</p>

        <h2>1. Qui est responsable</h2>
        <p>Wanllan est édité par {{ config('wanllan.legal_name') }}, {{ config('wanllan.legal_city') }}. Contact : WhatsApp {{ $support }}@if (config('wanllan.contact_email')), e-mail {{ config('wanllan.contact_email') }}@endif.</p>
        <p>Pour les commandes, chaque commerçant est responsable de l'usage qu'il fait des informations de ses clients. Wanllan les conserve pour lui.</p>

        <h2>2. Ce que nous gardons</h2>
        <p><strong>Commerçants</strong> : nom, numéro de téléphone, mot de passe (chiffré : personne ne peut le lire, pas même nous), e-mail si vous le donnez ; votre boutique (nom, numéro WhatsApp, ville, produits, photos, prix, stock, livraison, numéros Orange Money et MTN) ; l'historique de vos abonnements.</p>
        <p><strong>Clients des boutiques</strong> : nom, numéro de téléphone, adresse ou repère de livraison, note éventuelle, contenu de la commande, et la capture de paiement si vous en envoyez une. Aucun compte ni mot de passe n'est demandé aux clients.</p>
        <p><strong>Statistiques</strong> : le nombre de visites et de commandes par jour et par réseau d'origine (Facebook, TikTok, WhatsApp…). Ce sont des nombres : personne n'est suivi d'un site à l'autre.</p>
        <p><strong>Journaux techniques</strong> : l'adresse IP et l'heure de chaque visite, pour la sécurité. Ils sont effacés après 14 jours.</p>

        <h2>3. Pourquoi</h2>
        <ul>
            <li>Afficher la boutique et transmettre chaque commande au commerçant.</li>
            <li>Permettre au commerçant de suivre ses commandes et ses clients.</li>
            <li>Gérer les abonnements des commerçants.</li>
            <li>Protéger le service : limiter les tentatives de connexion, bloquer les abus.</li>
        </ul>
        <p>Pas de publicité. Nous ne vendons et ne louons aucune donnée.</p>

        <h2>4. Qui les voit</h2>
        <ul>
            <li><strong>Le commerçant</strong> : seulement les commandes et les clients de sa boutique. Il est le seul à voir les captures de paiement.</li>
            <li><strong>L'équipe Wanllan</strong> : seulement pour vous aider quand vous le demandez, ou pour la sécurité.</li>
            <li><strong>Notre hébergeur, Amazon Web Services</strong> : les données sont stockées sur un serveur à Paris (France). AWS les garde pour nous et n'a pas le droit de s'en servir.</li>
            <li><strong>WhatsApp</strong> : le message de commande part de votre propre application WhatsApp, quand vous appuyez sur « Envoyer ».</li>
            <li><strong>Orange Money et MTN MoMo</strong> : les paiements se font dans leurs applications. Wanllan ne voit jamais votre code secret.</li>
        </ul>

        <h2>5. Combien de temps</h2>
        <p>Tant que le compte du commerçant existe. Quand un commerçant supprime son compte, tout est effacé tout de suite : boutique, produits, photos, commandes, clients et captures. Les copies de sauvegarde s'effacent d'elles-mêmes dans les 7 jours.</p>

        <h2>6. Cookies</h2>
        <p>Deux cookies seulement, indispensables : un pour garder votre panier ou votre connexion, un contre les faux formulaires. Aucun cookie de publicité ni de mesure d'audience.</p>

        <h2>7. Vos droits</h2>
        <p>Vous pouvez voir, corriger ou faire supprimer vos informations.</p>
        <ul>
            <li><strong>Commerçant</strong> : tout se modifie dans l'espace commerçant. Pour tout effacer : Réglages, puis « Supprimer mon compte ».</li>
            <li><strong>Client</strong> : demandez au commerçant (son numéro WhatsApp est sur sa boutique), ou écrivez-nous sur WhatsApp au {{ $support }}.</li>
        </ul>

        <h2>8. Sécurité</h2>
        <p>Connexion chiffrée (HTTPS), mots de passe chiffrés, captures de paiement privées, sauvegarde chaque jour.</p>

        <h2>9. Changements</h2>
        <p>Si cette page change, les commerçants seront prévenus dans leur espace avant que le changement s'applique.</p>

        <p class="mt-8"><a href="{{ route('legal.terms') }}">Conditions d'utilisation</a> · <a href="{{ route('home') }}">Accueil</a></p>
    </article>
</x-layouts.guest>
