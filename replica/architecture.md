# Architecture : boutique WhatsApp pour la Guinée (rebuild des fonctions principales de Take App)

Nom provisoire du projet : **boutique-wa**. Le vrai nom viendra avec `/replica-brand`.

## Stack

Choix imposé : ce que Mohamed connaît déjà (Laravel, MySQL, Railway). Pas de microservices, une seule base.

| couche | choix | pourquoi |
| --- | --- | --- |
| web | **Laravel 12** + **Blade** + **Alpine.js** | Déjà connu. Pages rendues côté serveur : légères, rapides en 3G, marchent même si le JavaScript charge mal. |
| styles | **Tailwind CSS** (compilé, purgé) | CSS final de quelques Ko. Les couleurs viendront de `/replica-design`. |
| admin marchand | Blade + **Livewire 3** | Formulaires et listes réactifs sans écrire une API séparée. |
| base | **MySQL 8** sur Railway | Déjà utilisé pour Makiti. Schéma : `replica/schema.sql`. |
| auth | **Laravel Breeze** (Blade), connexion par **téléphone + mot de passe** | Les commerçants ont tous un numéro, pas toujours un e-mail. E-mail optionnel. |
| panier | **session Laravel** (driver base de données) | Pas de compte client. Marche sans JavaScript. |
| images | **Intervention Image** : conversion WebP, 1080 px + vignette 400 px | Une photo de téléphone (3 à 5 Mo) devient environ 80 à 150 Ko. Indispensable en 3G. |
| fichiers | **volume Railway** en V1, **Cloudflare R2** quand ça grandit | Le volume est le plus simple pour démarrer. Laravel passe à R2 (compatible S3) en changeant la config. |
| paiements clients | **manuels** en V1 : numéro Orange Money / MTN + capture de preuve | Pas d'API mobile money nécessaire pour démarrer. Paiement automatique : brique 4. |
| abonnements marchands | **manuels** en V1 : le marchand paie par Orange Money, Mohamed active le compte | Pas besoin de Stripe (cartes rares en Guinée). |
| WhatsApp | liens **`wa.me`** préremplis | Gratuit, sans validation Meta. L'API WhatsApp Business viendra plus tard. |
| e-mail | aucun en V1 | Pas nécessaire au parcours. Réinitialisation du mot de passe via le support WhatsApp. |
| jobs | **file `sync`** en V1 | Une seule tâche lourde (compression d'image), faite pendant l'envoi. Passage à une file `database` + un worker Railway si ça ralentit. |
| hébergement | ~~Railway~~ → **AWS Lightsail Paris** (décision du 16 oct. 2026) : un serveur Ubuntu avec Nginx, PHP-FPM, MySQL et les fichiers sur le disque | Prix fixe, sauvegardes quotidiennes, code inchangé. Comparatif : `replica/hosting.md`. Les mentions de Railway plus haut (base, fichiers, jobs) valent pour ce serveur. |
| fuseau | `Africa/Conakry` (UTC+0, pas d'heure d'été) | Aucun piège d'heure d'été. On stocke en UTC, on affiche en heure de Conakry. |
| langue / monnaie | `fr`, GNF sans décimales, espace pour les milliers : `150 000 GNF` | Un seul helper `gnf()` partout. |

## Schéma

Tables : **14** (dont users, stores, categories, products, variants, product_images, customers, delivery_methods, payment_methods, orders, order_items, payment_proofs, stock_movements, daily_visits). Plus les tables Laravel standard (sessions, cache, jobs).

Fichier complet : [`schema.sql`](schema.sql). **Vérifié** : chargé dans MariaDB, et les contraintes testées (lien de boutique invalide refusé, total incohérent refusé, survente bloquée).

Décisions clés :
- **Chaque produit a au moins une variante** (« Standard »). Prix et stock vivent sur la variante. Une seule règle de calcul dans tout le code.
- **Argent en entiers GNF** (`BIGINT`), jamais de décimales.
- **Copies au moment de la commande** : nom du produit, prix, mode de livraison et frais sont recopiés dans la commande. Si le marchand change ses prix demain, les anciennes commandes ne bougent pas.
- **Lien de facture non devinable** : `/c/{ULID}`, jamais l'id de la base.
- **N° de commande lisible par boutique** (#1001, #1002…) via un compteur sur `stores`, pris sous verrou.
- **Source de chaque commande** (`orders.source`, `orders.campaign`) et **visites par jour et par source** (`daily_visits`) : on sait quelle pub a fait vendre.

Accès : **contrôles dans la couche données**. Un scope global `BelongsToStore` sur tous les modèles marchands, plus une policy Laravel par ressource. Toute requête admin passe par la boutique de l'utilisateur connecté.

## Paiement à la livraison (activable par le commerçant)

Demande de Mohamed : le commerçant doit pouvoir **activer ou bloquer** le paiement à la livraison.

| niveau de contrôle | réglage | écran | priorité |
| --- | --- | --- | --- |
| Boutique | interrupteur **« Accepter le paiement à la livraison »** (`payment_methods.is_active` pour le type `cash_on_delivery`) | S19 | must |
| Montant | **plafond** optionnel : « seulement pour les commandes jusqu'à 500 000 GNF » (`max_order_total`). Au-delà, le client doit payer avant | S19 | should |
| Mode de livraison | case **« Paiement à la livraison possible »** par mode (`delivery_methods.allows_cash_on_delivery`). Ex. oui pour Conakry, non pour l'intérieur du pays | S18 | should |
| Client | bouton **« Bloquer le paiement à la livraison »** sur la fiche d'un client qui a refusé un colis (`customers.cash_on_delivery_blocked`) | S17 | could |

Règles :
- À la création de la boutique, le paiement à la livraison est **activé par défaut** (c'est l'usage en Guinée). Le commerçant peut le couper à tout moment.
- Au checkout (S04), l'option n'apparaît que si **toutes** les conditions sont remplies : activée, total sous le plafond, mode de livraison compatible, client non bloqué (repéré par son numéro).
- **Vérifiée côté serveur** à la création de la commande, pas seulement cachée à l'écran.
- Si c'est le seul moyen de paiement et qu'il est refusé, le client voit « Paiement avant livraison requis pour cette commande » avec les moyens disponibles.
- Une commande payée à la livraison **saute l'étape paiement (S05)** : le client passe directement à l'envoi WhatsApp. Elle reste « non payée » jusqu'à ce que le commerçant la marque « payée » après la livraison.
- Le stock est réservé dès la commande, comme pour les autres paiements.
- Le message WhatsApp affiche clairement : **« Paiement : à la livraison (150 000 GNF à préparer) »**.

## Liens partageables et suivi des pubs

C'est la réponse à « comment le client arrive dans la boutique depuis une pub Facebook ».

| lien | où le mettre |
| --- | --- |
| `site/{boutique}?src=facebook&c=promo-tabaski` | bouton de la pub Facebook / Instagram (« En savoir plus » ou « Acheter ») |
| `site/{boutique}/p/{produit}?src=facebook` | pub pour **un seul produit** : le client arrive directement sur la fiche |
| `site/{boutique}?src=tiktok` | bio TikTok, commentaire épinglé |
| `site/{boutique}?src=whatsapp` | message d'accueil WhatsApp Business, statut, groupes |
| `site/{boutique}?src=messenger` | réponse automatique Messenger de la page Facebook |

- Le paramètre `src` est gardé en session à l'arrivée, puis enregistré sur la commande.
- Valeurs acceptées : `facebook`, `instagram`, `tiktok`, `whatsapp`, `messenger`, `direct`. Toute autre valeur devient `direct` (pas de texte libre en base).
- **Écran « Partager ma boutique » (S22)** : le marchand copie en un clic ses liens par réseau, le lien de chaque produit, un QR code à imprimer, et son **message d'accueil prêt à coller** dans WhatsApp Business (Outils pro > Message d'accueil) et dans la réponse automatique Messenger. Exemple généré :
  > Bonjour 👋 Merci de contacter NimStore ! Voir tous nos produits et commander ici : site/nimstore?src=whatsapp — Livraison à Conakry. Paiement Orange Money, MTN ou à la livraison.
- **Tableau de bord** : « Cette semaine : Facebook 120 visites → 6 commandes · TikTok 45 visites → 3 commandes ».

## Routes

Routes web Laravel (pas d'API JSON séparée en V1).

### F01 Le client commande (public)

| méthode chemin | fait | qui | entrée | sortie | écran |
| --- | --- | --- | --- | --- | --- |
| GET `/{store}` | vitrine, catégories, produits ; note la source | tous | `?src`, `?c`, `?cat` | page | S01 |
| GET `/{store}/p/{product}` | fiche produit | tous | `?src` | page | S02 |
| POST `/{store}/panier` | ajouter au panier | tous | variant_id, quantité | redirection + toast | S02 |
| GET `/{store}/panier` | voir le panier | tous | — | page | S03 |
| PATCH / DELETE `/{store}/panier/{ligne}` | changer la quantité, retirer | tous | quantité | redirection | S03 |
| GET `/{store}/commande` | formulaire de commande | tous | — | page | S04 |
| POST `/{store}/commande` | **crée la commande** (transaction : stock, n°, client) | tous, limité à 10 par min et par IP | nom, téléphone, livraison, adresse, paiement, note | redirection vers `/c/{token}` | S04 |

### F02 Le client paie et envoie sur WhatsApp (public, via jeton)

| méthode chemin | fait | qui | entrée | sortie | écran |
| --- | --- | --- | --- | --- | --- |
| GET `/c/{token}` | facture + instructions de paiement + bouton WhatsApp | détenteur du lien | — | page | S05, S07 |
| POST `/c/{token}/preuve` | envoyer la capture du paiement → statut « en vérification » | détenteur du lien, limité à 5 par heure | image (max 8 Mo), référence | redirection | S05 |
| GET `/c/{token}/whatsapp` | note `whatsapp_sent_at`, redirige vers `wa.me` avec le message prérempli | détenteur du lien | — | redirection 302 | S06 |

### F04 Inscription et création de boutique

| méthode chemin | fait | qui | écran |
| --- | --- | --- | --- |
| GET/POST `/inscription`, `/connexion`, POST `/deconnexion` | Breeze adapté au téléphone | invité | S10 |
| GET/POST `/admin/boutique/creer` | nom, lien, numéro WhatsApp, ville ; crée « Retrait en boutique » et « Paiement à la livraison » par défaut | marchand sans boutique | S11 |

### F03 Le marchand traite les commandes (admin)

| méthode chemin | fait | qui | écran |
| --- | --- | --- | --- |
| GET `/admin/commandes` | liste ; onglets statut ; filtres paiement et source | propriétaire | S12 |
| GET `/admin/commandes/{order}` | détail, preuve de paiement, bouton « Écrire au client » (wa.me) | propriétaire | S13 |
| PATCH `/admin/commandes/{order}/statuts` | changer statut, paiement, livraison | propriétaire | S13 |
| POST `/admin/commandes/{order}/annuler` | annule et **remet en stock** (transaction) | propriétaire | S13 |

### F05 Produits et stock (admin)

| méthode chemin | fait | qui | écran |
| --- | --- | --- | --- |
| GET `/admin/produits` | liste avec stock | propriétaire | S14 |
| GET/POST `/admin/produits/creer`, GET/PUT `/admin/produits/{product}` | formulaire : infos, photos, variantes | propriétaire | S15 |
| PATCH `/admin/produits/{product}/visibilite` | afficher / masquer | propriétaire | S14 |
| PATCH `/admin/variantes/{variant}/stock` | réassort ou correction → `stock_movements` | propriétaire | S14 |
| GET/POST/PUT/DELETE `/admin/categories` | catégories et ordre | propriétaire | S16 |

### F06 Réglages et partage (admin)

| méthode chemin | fait | qui | écran |
| --- | --- | --- | --- |
| GET/PUT `/admin/reglages` | nom, logo, description, ouverte / fermée | propriétaire | S20 |
| GET/POST/PUT `/admin/livraison` | modes de livraison, frais, minimum | propriétaire | S18 |
| GET/POST/PUT `/admin/paiement` | Orange Money, MTN, virement, espèces : numéro + nom + instructions | propriétaire | S19 |
| GET `/admin/partager` | liens par réseau, liens produits, QR code, message d'accueil prêt à copier | propriétaire | **S22 (nouveau)** |
| GET `/admin` | tableau de bord : commandes du jour, à traiter, visites et ventes par source | propriétaire | S12 en-tête |

Total : **environ 32 routes**.

Webhooks entrants : aucun en V1. Webhooks sortants : aucun.
Jobs : aucun planifié en V1. Plus tard : rappel de paiement, annulation automatique des commandes non payées après X heures (réglage marchand).

## Les parties qui piquent

- **Survente** : le stock baisse à la création de la commande, dans une transaction, avec `UPDATE variants SET stock = stock - ? WHERE id = ? AND stock >= ?`. Si aucune ligne n'est touchée → « Plus que N en stock ». Testé sur la base. L'annulation remet le stock et écrit dans `stock_movements`.
- **Commandes jamais payées** : elles bloquent du stock. V1 : le marchand annule. Plus tard : annulation automatique après un délai.
- **N° de commande en double** : compteur `stores.next_order_number` lu avec `SELECT … FOR UPDATE` dans la même transaction, plus l'index unique `(store_id, number)`.
- **Double envoi du formulaire** (bouton appuyé deux fois en 3G) : jeton anti-doublon dans le formulaire, bouton désactivé après le premier appui, limite de débit.
- **Numéros de téléphone** : normaliser tout en `224XXXXXXXXX` (retirer espaces, `+`, `00`, ajouter `224` aux numéros à 9 chiffres). `wa.me` exige des chiffres seuls.
- **Message WhatsApp trop long** : les liens `wa.me` très longs échouent sur certains téléphones. Message court (n°, articles, total, client, livraison, paiement) + **lien de la facture** pour le détail. Encodage des accents et emojis avec `rawurlencode`.
- **Photos lourdes** : refuser au-delà de 8 Mo, convertir en WebP, vignettes pour la grille, `loading="lazy"`.
- **Multi-boutiques** (multi-tenant) : scope global sur `store_id` partout dans l'admin, tests qui vérifient qu'un marchand ne voit jamais la commande d'un autre.
- **Liens de boutique** : mots réservés interdits (`admin`, `c`, `connexion`, `inscription`, `api`…), format vérifié en base.
- **Preuves de paiement** : ce sont des images privées. Servies seulement au propriétaire de la boutique et au détenteur du lien de facture, jamais dans un dossier public.
- **Faux paiements** : une capture se falsifie. Le marchand doit toujours vérifier la réception sur son téléphone Orange Money / MTN avant de marquer « payé ». Message affiché dans l'admin.
- **Données personnelles** : noms et numéros des clients. Pas de partage entre boutiques ; suppression d'une boutique = suppression en cascade.

## Ordre de construction

**1. Tranche verticale (2 au 5 nov)** : la boucle complète, même moche.
- Écrans : S10, S11, S15 (minimal), S01, S03, S04, S07, S06, S12
- Tables : users, stores, products, variants, orders, order_items, delivery_methods, payment_methods
- Routes : inscription, création de boutique, création de produit, vitrine, panier, commande, facture, redirection WhatsApp, liste des commandes
- Preuve que ça marche : Mohamed crée une boutique, ajoute un produit, commande depuis un autre téléphone, le message arrive sur WhatsApp, la commande est dans l'admin.

**2. Fonctions indispensables (6 au 15 nov)**, par zone :
- Produits : photos compressées, variantes, catégories, stock et rupture, masquer (S14, S15, S16)
- Commandes : détail, statuts, annulation avec remise en stock (S13)
- Paiement : instructions Orange Money / MTN, envoi de preuve (S05, S19)
- Livraison : modes et frais (S18)
- Partage : liens par réseau, message d'accueil, suivi de source (S22)
- Guinée : français partout, `gnf()`, téléphones normalisés

**3. Fonctions utiles ensuite (should)** : zones de livraison par commune, carnet de crédit clients, liste clients, reçu WhatsApp au client, commande manuelle, statuts en masse, boutique ouverte / fermée, pages 3G optimisées.

**4. Corrections issues de `/replica-entrepreneur`** : à venir, après lecture des avis sur Take App.
