# Mise en ligne de Wanllan

Date : 2026-10-16 · Commit : `875ce41` · **Feu vert de Mohamed : pas encore.** Il le donne le 22 octobre en fusionnant la pull request #1 dans `main`.

Hébergement : AWS Lightsail, région Paris, serveur Ubuntu 24.04 de 1 Go à 7 $/mois (choix expliqué dans `replica/hosting.md`).

## Contrôles avant la mise en ligne (tous passés)

| contrôle | résultat |
| --- | --- |
| Tests automatiques | **44 / 44** (357 vérifications), sur SQLite et sur MariaDB/MySQL |
| Parcours réel sur un serveur de répétition (Nginx + PHP-FPM + MySQL, mêmes scripts) | inscription, création de boutique, envoi d'une photo de 3,8 Mo (traitée en 0,7 s), aperçu WhatsApp de 87 Ko, HTTPS, redirections, abonnement en ligne de commande, copie de la base |
| Bugs bloquants (S1/S2) ouverts | aucun |
| Parité | 22 fonctions indispensables sur 22, score **82,8 / 100** |
| Traces de l'original (`sweep.py`) | **propre** sur tout le code livré (`boutique/app`, `resources`, `routes`, `config`, `public`, `database`, `tests`, `deploy`, `.github`). Les seuls résultats sont dans `boutique/vendor/` (bibliothèques tierces, « take appropriate action »), qui n'est ni dans le dépôt ni écrit par nous |
| Fiche des stores (`listing.py`) | 0 erreur, 0 avertissement (pas de publication sur les stores pour l'instant) |
| Build de production (`npm run build`) | OK, CSS 45 Ko, 8 Ko compressé |
| Pages Confidentialité et Conditions | en ligne (`/confidentialite`, `/conditions`), liées depuis l'accueil, l'inscription, la commande et les réglages. Sous-traitant listé : Amazon Web Services (Paris). WhatsApp, Orange Money et MTN sont décrits comme services indépendants |
| Bandeau cookies | pas nécessaire : deux cookies indispensables seulement (session, protection des formulaires), aucune mesure d'audience |
| Suppression de compte | Réglages → « Supprimer mon compte », avec mot de passe. Efface boutique, produits, photos, commandes, clients et captures. Testé |
| Favicon, titres, image d'aperçu | à Wanllan. Aperçu WhatsApp/Facebook en JPEG 1200 × 630 : la photo du produit (ou de la boutique), sinon la carte Wanllan (`public/og-wanllan.jpg`, 47 Ko) |

**À relire par Mohamed avant le 22 octobre** : les pages Conditions et Confidentialité. Elles le nomment comme éditeur (variable `WANLLAN_LEGAL_NAME`) et fixent deux règles commerciales : âge minimum 18 ans ; pas de remboursement d'une période entamée, mais la période est prolongée si Wanllan tombe en panne plus de 3 jours de suite.

## Production

| point | état |
| --- | --- |
| Base de production séparée | MySQL 8 sur le serveur, base `wanllan`, utilisateur dédié, mot de passe aléatoire. La base de démonstration n'y va jamais |
| Sauvegardes | instantané Lightsail automatique chaque nuit (7 gardés, à activer dans la console) + copie de la base à 2 h 30 (`wanllan-backup`, 7 jours) |
| Migrations | lancées par chaque mise en ligne (`php artisan migrate --force`), jamais à la main |
| Variables d'environnement | `/var/www/wanllan/shared/.env`, écrit depuis `deploy/lightsail/conf/env.production` (mêmes noms que `.env.example`). Les numéros se règlent avec `sudo wanllan-config` |
| Stripe | sans objet : abonnements prépayés par Orange Money, activés avec `artisan wanllan:abonnement` |
| OAuth | sans objet : connexion par téléphone et mot de passe |
| E-mails | sans objet en V1 : aucun e-mail envoyé |

## Comment une mise à jour part en ligne

1. Le code arrive sur la branche `main` (fusion d'une pull request, validée par Mohamed).
2. Toutes les 2 minutes, le serveur regarde `main`. S'il y a du nouveau, il prépare une nouvelle version à côté de celle en ligne.
3. **Il lance les 44 tests sur le serveur**, isolés de la production (base en mémoire, sans le `.env` de production). Un seul test en échec : rien ne change.
4. Il met à jour la base, bascule sur la nouvelle version, puis vérifie que le site répond. S'il ne répond pas, **retour automatique à la version précédente** en quelques secondes.
5. Une version refusée n'est pas retentée ; la suivante l'est. Les 5 dernières versions restent sur le disque.

Testé sur le serveur de répétition : mise à jour normale (20 s), code cassé refusé, test en échec refusé, site cassé après bascule remis en arrière, base de production intacte.

GitHub Actions (`.github/workflows/ci.yml`) teste aussi sur MySQL 8, mais **ne tourne pas pour l'instant** (passé en lancement manuel pour éviter les e-mails d'échec) : GitHub indique « The job was not started because your account is locked due to a billing issue ». Le serveur ne dépend donc pas de GitHub Actions. Quand le compte sera débloqué, mettre `CHECK_NAME=tests` dans `/etc/wanllan/deploy.conf` pour une double vérification.

## Domaine

**wanllan.com acheté chez Spaceship le 17 octobre 2026** (9,08 $ la première année, puis 10,18 $ par an, renouvellement automatique, protection de la vie privée incluse). Le DNS se gère chez Spaceship.

Enregistrements à créer chez Spaceship (gestionnaire de domaines → wanllan.com → DNS) :

| type | nom | valeur |
| --- | --- | --- |
| A | @ | l'IP statique Lightsail du serveur |
| A | www | la même IP statique |

- Adresse principale : `https://wanllan.com`. `www.wanllan.com` et `http://` redirigent vers elle (301).
- HTTPS : Let's Encrypt, renouvelé tout seul, demandé par `sudo wanllan-https` quand le domaine pointe vers le serveur. HSTS d'un an.
- E-mail : **Zoho Mail (formule gratuite), configuré le 18 octobre 2026** sur wanllan.com : contact@wanllan.com (administrateur) et mohamedbalde@wanllan.com. Enregistrements chez Spaceship et visibles sur Internet : vérification Zoho (TXT @), MX mx.zoho.com 10 / mx2.zoho.com 20 / mx3.zoho.com 50, SPF `v=spf1 include:zohomail.com ~all`, DKIM `zmail._domainkey`, DMARC `v=DMARC1; p=none; rua=mailto:contact@wanllan.com` (passer à `p=quarantine` quand les rapports sont propres). Le 22 octobre, répondre **contact@wanllan.com** à la question « E-mail de contact » de `wanllan-config`.

## Surveillance

| point | état |
| --- | --- |
| Suivi des erreurs | journal Laravel quotidien (14 jours) dans `/var/www/wanllan/shared/storage/logs`, journal des mises en ligne dans `/var/www/wanllan/state/deploy.log`, résumé avec `sudo wanllan-status` |
| Disponibilité | **à créer par Mohamed** : UptimeRobot gratuit sur `https://wanllan.com/up` toutes les 5 minutes, alerte par e-mail et sur l'app ; alarme Lightsail « échec de contrôle d'état » |
| Mesure d'audience | les visites et ventes par réseau sont dans l'app (« Partager ma boutique ») ; pas d'outil externe, donc pas de bandeau cookies |
| Parcours complet sur le site en ligne | à faire le 23 octobre, sur ordinateur et sur le téléphone de Mohamed |

## Le 22 octobre, pas à pas (environ 1 h, sur téléphone)

**Avant** : le domaine est acheté, le compte AWS est ouvert (double authentification, plan payant, alerte de budget à 10 $), et la pull request #1 est fusionnée dans `main`.

1. **Créer le serveur.** Console Lightsail → « Créer une instance » : région **Paris (eu-west-3)** ; plateforme **Linux/Unix** ; « Système d'exploitation uniquement » → **Ubuntu 24.04 LTS** ; réseau **double pile** ; offre **7 $** (1 Go) ; nom `wanllan-1` → Créer.
2. **IP fixe.** Onglet « Mise en réseau » → « Créer une adresse IP statique » → l'attacher à `wanllan-1`. Noter l'adresse.
3. **Ouvrir le HTTPS.** Même onglet, pare-feu IPv4 → « Ajouter une règle » → **HTTPS (TCP 443)**. Faire de même pour IPv6.
4. **Sauvegardes.** Onglet « Instantanés » → activer les **instantanés automatiques**, heure 03:00.
5. **Domaine.** Chez Spaceship (gestionnaire de domaines → wanllan.com → DNS) : enregistrements A `@` et `www` vers l'IP fixe.
6. **Installer.** Onglet « Se connecter » → « Se connecter avec SSH », puis coller :
   ```
   curl -fsSL https://raw.githubusercontent.com/MohameBalde/Replica/main/deploy/lightsail/install.sh | sudo bash -s -- wanllan.com
   ```
   Compter 10 à 15 minutes. À la fin, répondre aux questions : numéro Orange Money des abonnements, nom du compte, numéro WhatsApp du support.
7. **HTTPS.** Quand le domaine pointe vers le serveur (10 à 30 minutes après l'étape 5) : `sudo wanllan-https`.
8. **Vérifier.** `sudo wanllan-status` : tout doit être vert. Puis ouvrir `https://wanllan.com` sur le téléphone.

## Commandes du quotidien (dans le terminal Lightsail)

| commande | à quoi elle sert |
| --- | --- |
| `sudo wanllan-status` | état du site, version en ligne, disque, mémoire, dernière sauvegarde |
| `artisan wanllan:abonnement <boutique> merchant 1 --montant=50000 --ref=<n° transaction>` | activer un abonnement payé |
| `sudo wanllan-config` | changer les numéros Orange Money et WhatsApp du support |
| `sudo wanllan-deploy` | regarder `main` tout de suite au lieu d'attendre 2 minutes |
| `sudo wanllan-backup` | copie de la base tout de suite (avant une grosse opération) |

## Première semaine : à surveiller

- `sudo wanllan-status` chaque soir : mémoire et swap (au-delà de 300 Mo de swap utilisés plusieurs jours de suite, passer au serveur de 2 Go), disque, sauvegardes.
- Le journal des erreurs Laravel, à m'envoyer s'il n'est pas vide.
- Les aperçus WhatsApp des liens de boutique sur de vrais téléphones (Android et iPhone).
- La facture AWS : environ 8 à 9 $ par mois prévus, alerte à 10 $.

## Points ouverts

- **Compte GitHub bloqué pour facturation** : GitHub Actions ne tourne pas. À régler dans GitHub → Settings → Billing and licensing. La mise en ligne n'en dépend pas.
- **Branche `test-deploy`** poussée par erreur le 16 octobre pendant les essais : un commit vide, aucun code. À supprimer dans GitHub (Branches), cette session ne peut pas supprimer de branche.
- Relire les pages Conditions et Confidentialité (voir plus haut).
