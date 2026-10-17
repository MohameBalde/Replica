# CLAUDE.md

## Qui je suis

- **Mohamed Baldé** (pseudo Houston23), Conakry, Guinée. Appelle-moi **Mohamed**.
- Réponds **toujours en français**, de façon structurée et concrète.
- Sois mon guide en **marketing** et en **développement de mes business**.

## Comment travailler avec moi

- J'utilise surtout mon **téléphone**. Je partage mes documents sur **WhatsApp Business** : les PDF et DOCX s'y affichent mal, donc propose une **version PNG** des plans et des documents à partager.
- J'ai du mal à planifier mes semaines : donne-moi des **plans par étapes avec des dates claires**.
- Recommande une option au lieu de lister toutes les possibilités.

## Formation et profil

- Étudiant en Génie Informatique et Télécommunications (GIT), Université Nongo Conakry (UNC). En L4 (S7), concentration **Réseaux et Télécommunications**.
- **Projet de fin de cycle** (en binôme) : supervision réseau centralisée et détection d'anomalies (Zabbix/SNMP, Grafana/Prometheus, Python/Flask, VM Debian sous VMware).
  - Partie technique : **décembre 2026**. Mémoire : **mars 2027**. Soutenance : **avril 2027**.
- Stage au Département digital d'une banque guinéenne (septembre 2026) : services Bank to Wallet et Wallet to Bank avec Orange Money, ouverture de comptes, caisse, prospection.
- Portfolio : https://mohamed-balde.vercel.app/ — Administrateur Réseaux & Systèmes (réseaux, supervision, Windows Server, VoIP). Je cherche un emploi dans ce domaine.

## Apprentissage en cours

- Google Cybersecurity Professional Certificate (Coursera), puis CompTIA Security+.
- Parcours visé : CCNA → Linux → Windows Server → Cloud/Azure → VoIP.
- Anglais (cours Coursera).

## Projets et activités

- **Réseau Wi-Fi perso** : Starlink → MikroTik Hotspot (tickets via Mikhmon) → antennes Grandstream. Objectif : VPN ou VPS pour le gérer depuis mon téléphone.
- **Makiti** (« le marché ») : marketplace e-commerce guinéenne multi-villes (Conakry, Kankan, Labé, Nzérékoré), sur Railway avec MySQL. C'est aussi la marque de mon activité d'intermédiaire.
- **Intermédiaire commercial** (marge par vente) : maillots, PC et sacs (boutique familiale), chaussures et habits (avec mon partenaire Nimaga).
- **Services informatiques** : installation de logiciels, licences, mises à jour, dépannage logiciel.
- **E-commerce** : NimStore, SoumamoShop, NimsDecore (dropshipping). Promotion via TikTok et Canva.
- **Balde Tech** (choisi le 18 octobre 2026) : nom de l'entreprise qui porte tous les projets logiciels (Wanllan, caisse, portail Wi-Fi, micro-logiciels). Signature : « Wanllan, un produit Balde Tech ». Domaine **baldetech.com** libre au 18/10, à acheter plus tard (≈ 9 $), quand Wanllan aura ses premiers abonnés. Pas encore d'entreprise déclarée.
- **Balde Media** : média digital (TikTok @balde_media), présentateur virtuel 3D « Monsieur Macro ».
- **Développement** : application Laravel pour un centre de formation.
- Stack que je connais : **Laravel, MySQL, Railway, Python/Flask, Vercel**.

## Ambitions

- Devenir un « géant de la Tech » en partant de la Guinée.
- Repérer des projets qui marchent aux États-Unis, en Europe ou en Asie et les **adapter au marché guinéen** (français, GNF, Orange Money / MTN MoMo, mobile d'abord).
- Monter une équipe et vendre des **micro-logiciels sur mesure**.
- Ouvrir une boutique physique de chaussures importées de Chine.
- Faire de Balde Media une vraie plateforme média.
- Idées en réflexion : antivol pour téléphones Android, chansons personnalisées.

## Ce dépôt : Replica

Le dépôt contient les 11 skills **Replica** dans `.claude/skills/` (MIT, copiés depuis `github.com/Jakeschincariol/replica-skill` @ `77c9436`). Ordre :

`recon → architect → design → build → backend → test → diff → entrepreneur → brand → launch → deploy`

Chaque skill écrit dans `replica/`. Règle : on clone **les fonctionnalités**, jamais le code, le logo, les textes ni le contenu de l'original.

### Projet en cours : **Wanllan**, boutique WhatsApp pour la Guinée (modèle : Take App)

- **Nom : Wanllan** (orthographe exacte : W-a-n-l-l-a-n), « aide-moi » en poular. Signature : « Wanllan · Votre boutique sur WhatsApp ». Couleurs : indigo du leppi `#3730a3` + or `#f59e0b`. Détails : `replica/brand.md`.

- Code : `boutique/` (Laravel 12, MySQL, Tailwind 4). Tests : `cd boutique && php artisan test`. Démo : `php artisan migrate:fresh --seed` (connexion 620 00 00 00 / password).
- Fait : recon, architecture, design, construction (22 fonctions indispensables sur 22), avis des utilisateurs de Take App (`replica/fixes.md`).
- Demande de Mohamed : le paiement à la livraison est activable / bloquable par le commerçant (boutique, plafond, zone, client).
- Angle retenu : « Votre boutique WhatsApp, faite pour la Guinée » + promesse « payé en GNF par Orange Money, sans carte, sans prélèvement ».
- Lancement préparé : prix en GNF (`replica/launch/pricing.md`), page d'accueil à `/`, plan daté (`replica/launch/launch-plan.md`). Activer un abonnement : `php artisan wanllan:abonnement <boutique> merchant 1 --ref=...`.
- Hébergement choisi : **AWS Lightsail, région Paris** (serveur 1 Go à 7 $/mois, sauvegardes quotidiennes). Étude comparative et raisons : `replica/hosting.md`. Mohamed a une carte qui paie en dollars. Compte AWS ouvert le 16 octobre (double authentification par passkey, alerte de budget à 10 $).
- Domaine **wanllan.com** acheté chez **Spaceship** le 17 octobre 2026 (9,08 $, puis 10,18 $/an, renouvellement automatique) ; DNS chez Spaceship. E-mail pro : Zoho Mail formule gratuite (disponible), sur wanllan.com en attendant baldetech.com ; adresse principale contact@wanllan.com.
- Déploiement préparé (`replica/deploy.md`, scripts dans `deploy/lightsail/`) : mise en ligne le **22 octobre** en fusionnant la PR #1 dans `main`. Ensuite, le serveur regarde `main` toutes les 2 minutes, lance les 44 tests sur place et revient seul à la version précédente si le site ne répond plus. Les mises à jour passent par une PR que Mohamed fusionne.
- Le compte GitHub de Mohamed est bloqué pour facturation : GitHub Actions ne tourne pas (le serveur n'en dépend pas).
- Suite : mise en ligne, bêta du 26 octobre, puis brique 2 (caisse et stock hors ligne) en janvier 2027.

### Autres pistes

1. **App de caisse et de stock pour boutiques guinéennes**, sur le modèle de Loyverse. En plus : français et GNF, carnet de crédit clients, reçu par WhatsApp, Orange Money / MTN MoMo. Premiers clients : la boutique familiale et Nimaga. Stack : Laravel + MySQL sur Railway.
2. **Portail de vente de tickets Wi-Fi**, sur le modèle de XenFi (références secondaires : Powerlynx, PHPNuxBill). Le client paie par Orange Money / MTN MoMo et le compte est créé automatiquement sur le MikroTik. À vérifier d'abord : l'accès à une API de paiement qui couvre la Guinée. Plan B : génération et envoi automatique du ticket après confirmation manuelle du paiement.
3. **Améliorer Makiti** avec `/replica-entrepreneur` sur les avis de Jumia et Coin Afrique.

### Contraintes de calendrier

- Les projets Replica doivent tenir **avant décembre 2026** : décembre est réservé à la partie technique du projet de fin de cycle.
- Rythme réaliste : environ 1 h par jour en semaine et 3 h le week-end.
