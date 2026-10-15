# Recon map : Take App (web mobile + app marchand iOS/Android)

**Périmètre cloné :** la boucle principale uniquement. Le marchand crée sa boutique et son catalogue, le client compose son panier, la commande part sur le WhatsApp du marchand, et le marchand suit commandes, paiements et stock.
**Pour qui :** les petits commerçants guinéens (boutiques familiales, Nimaga, vendeurs Makiti, NimStore…). Produit à vendre en abonnement.
**Adaptations Guinée :** français, prix en GNF, Orange Money / MTN MoMo (preuve de paiement en V1), livraison par commune ou quartier de Conakry et par ville, pensé pour un téléphone d'abord, pages légères pour connexion 3G.
**Date :** 2026-10-15

> **Limite de cette recon.** Première passe faite par recherche web (site bloqué). Deuxième passe : pages lues directement (accueil, tarifs, pays, centre d'aide, « How it works », « Manage Orders »). Les écrans côté marchand restent **déduits** de l'aide ; l'écran client est confirmé par la démo animée de la page d'accueil. À compléter avec des captures d'un compte gratuit de Mohamed.

## Concurrence et positionnement (vérifié le 2026-10-15)

- **Take App accepte déjà Orange Money, MTN MoMo, Wave, CinetPay, PawaPay** (page d'accueil, liste des moyens de paiement).
- **La Guinée n'apparaît pas** dans sa liste de pays mis en avant (`/countries`), alors que le **Sénégal et la Côte d'Ivoire** y sont, en XOF. Aucune mention de GNF ou de Guinée sur l'accueil et la page pays. Take App annonce « 180+ pays » : une inscription depuis la Guinée est sans doute possible, mais rien n'y est adapté.
- **Tarifs** (`/pricing`) : Basic gratuit limité à **50 commandes enregistrées par mois** (au-delà, elles arrivent sur WhatsApp mais ne sont plus enregistrées) ; Business **50 $ par mois** (1 $ le premier mois), payé en dollars. Paiement de l'abonnement par carte ou solde prépayé.
- **Angle pour la version guinéenne :** prix en GNF payable par Orange Money, bien moins cher que 50 $, sans limite cachée de 50 commandes ; support en français et en local (WhatsApp, présence physique à Conakry) ; livraison par commune et ville guinéennes ; carnet de crédit clients.
- Take App publie aussi une page « Take App vs Loyverse » : il couvre en partie la caisse (app POS mobile). Cela confirme le bon couplage boutique WhatsApp + caisse pour la suite.

## Sources

| # | source | URL | notes |
| --- | --- | --- | --- |
| 1 | marketing / fonctionnement | https://www.take.app/help/how-take-app-works | boucle principale : panier envoyé en un message WhatsApp |
| 2 | page fonctionnalité WhatsApp | https://www.take.app/features/whatsapp | catalogue avec tailles/couleurs/options, tableau de bord commandes, stock, paiements locaux, prix |
| 3 | page commandes | https://www.take.app/features/orders | chaque commande arrive avec articles, adresse, contact client |
| 4 | aide : créer un produit | https://help.take.app/en/articles/10632078-create-product | nom, prix, description, images, variantes, options |
| 5 | aide : stock | https://www.take.app/help/products-inventory | suivi par produit, par variante, décrément à la commande, remise en stock à l'annulation |
| 6 | aide : livraison | https://www.take.app/help/add-delivery-method | frais forfait, distance, code postal, zone, poids ; questions personnalisées ; minimum de commande |
| 7 | aide : gérer les commandes | https://www.take.app/help/manage-orders | statuts commande, paiement, livraison |
| 8 | aide : statuts en masse | https://www.take.app/help/modify-order-status | sélection multiple et changement de statut |
| 9 | aide : preuve de paiement | https://www.take.app/help/payment-proof-upload | le client envoie une capture du paiement |
| 10 | changelog | https://www.take.app/changelog/wallet-for-payments-payment-status-shortcuts-qr-shipping-labels-orders-across-all-stores | filtres de paiement, étiquettes QR, multi-boutiques |
| 11 | aide : crédit client | https://www.take.app/help/store-credit | Admin > Customers, crédit ajouté ou retiré |
| 12 | aide : catalogue WhatsApp | https://www.take.app/fr/help/whatsapp-catalog | synchro catalogue WhatsApp Business toutes les 10 min, sans catégories |
| 13 | aide : taxes | https://www.take.app/help/tax-settings | taxe sur produits et frais de livraison |
| 14 | aide : FAQ | https://www.take.app/help/faq | app marchand iOS/Android, notifications, reçus WhatsApp |
| 15 | page d'accueil (lue) | https://www.take.app/ | démo des écrans client : boutique, fiche produit avec options, checkout, paiement, « Send WhatsApp », facture ; liste des moyens de paiement |
| 16 | tarifs (lue) | https://www.take.app/pricing | Basic 0 $ / 50 commandes, Business 50 $ / mois, tableau comparatif complet |
| 17 | pays (lue) | https://www.take.app/countries | 180+ pays annoncés ; Afrique mise en avant : Afrique du Sud, Côte d'Ivoire, Kenya, Nigeria, Ghana, Sénégal, Rwanda… pas la Guinée |
| 18 | aide : centre d'aide (lue) | https://www.take.app/help | 14 rubriques, environ 330 articles |

## Boucle principale

Le client ouvre le lien de la boutique, ajoute des produits au panier et envoie la commande en **un seul message WhatsApp** au marchand, qui la confirme, encaisse et livre depuis son tableau de bord.

## Écrans

Côté client (boutique publique) : S01 à S07. Côté marchand (admin) : S10 à S21.

| ID | écran | accès | rôle | composants clés | états |
| --- | --- | --- | --- | --- | --- |
| S01 | Accueil boutique | `/{boutique}` | logo, nom, catégories, produits | en-tête boutique, onglets catégories, grille produits, bouton panier flottant | vide (aucun produit), rempli, boutique fermée |
| S02 | Fiche produit | clic produit | photos, prix, choix variante/options, quantité | carrousel, sélecteur variante, cases options, stepper quantité, bouton « Ajouter » | en stock, stock faible (« plus que 3 »), rupture |
| S03 | Panier | bouton panier | revoir les articles | lignes panier, stepper, total | vide, rempli |
| S04 | Checkout | « Commander » | nom, numéro WhatsApp, service (livraison payante / retrait gratuit), adresse, date et heure, code promo, récapitulatif | formulaire, choix du service, sélecteur date/heure, récapitulatif avec frais et taxe, bouton « Commander et payer » | erreur champ, minimum non atteint |
| S05 | Paiement | après « Commander et payer » | « Commande reçue, finalisez le paiement » : montant, choix du moyen (mobile money, virement, espèces à la livraison…), envoi de la preuve | liste des moyens, QR ou numéro, envoi d'image | paiement auto réussi, preuve envoyée en attente |
| S06 | Envoi WhatsApp | après paiement | « Envoyez-nous le détail pour confirmer » : ouvre WhatsApp avec le message prérempli (n°, articles, options, frais, total, client, service, paiement, lien facture) | bouton « Envoyer sur WhatsApp », lien wa.me | WhatsApp absent (ouverture web) |
| S07 | Facture / suivi commande | lien « Voir la facture » du message | n° facture, date, articles, frais, total, client, livraison, badges statut | badges statut, blocs détail | en attente, paiement en vérification, payé, annulé |
| S10 | Inscription / connexion marchand | `/admin` | créer un compte | formulaire téléphone/e-mail | erreur |
| S11 | Création de boutique (onboarding) | après inscription | nom, lien, numéro WhatsApp, devise | assistant en étapes | lien déjà pris |
| S12 | Tableau de bord commandes | Admin > Orders | toutes les commandes, filtres paiement | liste, filtres (Tous, Non payé, En vérification, Payé), recherche, sélection multiple | vide, rempli |
| S13 | Détail commande | clic commande | articles, client, adresse, preuve de paiement, changer les statuts | badges statut, boutons d'action, image preuve, bouton « Reçu WhatsApp » | brouillon, en attente, confirmée, terminée, annulée |
| S14 | Liste produits | Admin > Products | catalogue, stock en colonne | tableau/liste, champ stock éditable, interrupteur visible/caché | vide, rempli |
| S15 | Formulaire produit | « Ajouter un produit » | nom, prix, description, photos, catégorie, variantes, options, stock | champs, envoi d'images, éditeur de variantes | création, édition, erreur |
| S16 | Catégories | Admin > Products | ordre et nom des catégories | liste triable | vide |
| S17 | Clients | Admin > Customers | liste clients, historique, crédit | liste, fiche client | vide |
| S18 | Réglages livraison | Settings > Delivery | modes de livraison, frais, zones, minimum | liste, formulaire mode de livraison | aucun mode |
| S19 | Réglages paiement | Settings > Payments | moyens de paiement et instructions | liste, interrupteurs | aucun moyen |
| S20 | Réglages boutique | Settings | nom, logo, horaires, ouverte/fermée, infos facture | formulaire | — |
| S21 | Créer une commande manuelle | Admin > Orders > nouveau | saisir une commande reçue par téléphone | même formulaire que S04 côté marchand | — |

## Parcours

```
F01 Le client commande
    S01 -> S02 -> S03 -> S04 -> S05 (paiement) -> S06 (WhatsApp) -> S07
    clics, chemin heureux : environ 7 (produit, ajouter, panier, remplir, commander et payer, payer, envoyer)
    note : chez Take App, la commande est enregistrée AVANT l'envoi WhatsApp ; le message sert de confirmation.
    cas limites : rupture pendant la commande, minimum de commande non atteint,
                  WhatsApp non installé, boutique fermée, prix modifié entre-temps

F02 Le client paie par mobile money
    S06 -> instructions Orange Money / MTN -> envoi capture -> statut « en vérification »
    cas limites : mauvaise image, montant incorrect, paiement jamais envoyé

F03 Le marchand traite une commande
    S12 -> S13 -> confirmer -> marquer payé -> marquer livré
    cas limites : annulation (remise en stock), plusieurs commandes d'un coup

F04 Le marchand ouvre sa boutique
    S10 -> S11 -> S15 (premier produit) -> partager le lien
    clics, chemin heureux : environ 8
    cas limites : lien déjà pris, photo trop lourde

F05 Le marchand gère son stock
    S14 -> modifier quantité -> S02 affiche « stock faible » ou « rupture »

F06 Le marchand règle livraison et paiement
    S18, S19
```

## Composants

| composant | variantes | états | utilisé sur |
| --- | --- | --- | --- |
| Bouton | principal, secondaire, discret, danger, WhatsApp | normal, appuyé, désactivé, chargement | partout |
| Carte produit | grille, liste | normal, rupture, stock faible | S01, S14 |
| Sélecteur de variante | puces, liste | sélectionné, indisponible | S02 |
| Stepper quantité | — | min, max atteint | S02, S03 |
| Bouton panier flottant | — | caché (vide), avec total | S01, S02 |
| Badge statut | commande, paiement, livraison | une couleur par statut | S06, S12, S13 |
| Filtres en onglets | — | actif | S12 |
| Formulaire | texte, téléphone, adresse, choix, envoi d'image | erreur, désactivé | S04, S15, S18 |
| Envoi d'images | une, plusieurs | chargement, erreur, trop lourd | S06, S15 |
| Barre de sélection multiple | — | n sélectionnés | S12 |
| Toast | succès, erreur | — | admin |
| Navigation admin | barre du bas sur mobile | onglet actif | S12 à S20 |
| Feuille du bas (bottom sheet) | — | ouverte, fermée | S02, S04 |

## Modèle de données déduit

```
Merchant     id, nom, téléphone, e-mail, créé_le
             preuve : S10, FAQ                               confiance : haute

Store        id, merchant_id, nom, slug (lien), logo, numéro_whatsapp, devise,
             ouverte (bool), horaires, infos_facture
             preuve : S11, S20, source 1                      confiance : moyenne

Category     id, store_id, nom, ordre
             preuve : S01, source 12                          confiance : haute

Product      id, store_id, category_id, nom, description, prix, images[], visible,
             suivi_stock (bool), sku
             preuve : source 4, S14, S15                      confiance : haute

Variant      id, product_id, nom (ex. « 42 / Noir »), prix, stock, sku
             preuve : source 4 et 5 (stock par variante)      confiance : haute

OptionGroup  id, product_id, nom, obligatoire, choix_max ; Option : id, group_id, nom, prix_en_plus
             preuve : source 2 (« add-ons »), source 4         confiance : moyenne

Customer     id, store_id, nom, téléphone, adresse, crédit
             preuve : S17, source 11                          confiance : haute

Order        id, store_id, customer_id, numéro, statut (brouillon | en_attente | confirmée | terminée | annulée),
             statut_paiement (non_payé | en_vérification | partiellement_payé | payé | remboursé),
             statut_livraison (non_préparée | prête | en_livraison | livrée), mode_livraison_id, adresse,
             date_heure_livraison, note_interne, employé_id, numéro_suivi,
             réponses (json), sous_total, frais_livraison, taxe, total, créée_le
             preuve : source 7, 8, 9                          confiance : haute

OrderItem    id, order_id, product_id, variant_id, nom_au_moment, prix_au_moment, quantité, options (json)
             preuve : S13, source 3                           confiance : haute (champs « au moment » : supposition)

DeliveryMethod id, store_id, nom, type (retrait | livraison), frais, mode_calcul (forfait | zone),
             zones (json), minimum_commande, questions (json)
             preuve : source 6                                confiance : haute

PaymentMethod id, store_id, type (cash | orange_money | mtn_momo | virement), instructions, actif
             preuve : source 2, 9 (moyens locaux)             confiance : moyenne

PaymentProof id, order_id, image, envoyée_le
             preuve : source 9                                confiance : haute
```

Relations : Merchant 1-n Store, Store 1-n Category, Category 1-n Product, Product 1-n Variant, Product 1-n OptionGroup 1-n Option, Store 1-n Customer, Customer 1-n Order, Order 1-n OrderItem, Order 0-n PaymentProof, Store 1-n DeliveryMethod, Store 1-n PaymentMethod.

## Matrice des fonctionnalités

Voir `features.csv` (48 lignes). Must : 23, should : 12, could : 8, skip : 5. Dont 8 ajouts propres à la Guinée (original = no). Le score de parité porte sur les 35 fonctions de l’original (20 must).

## Hors périmètre (ne peut ou ne doit pas être cloné)

- **Paiement automatique par API Orange Money / MTN** en V1 : demande un compte marchand ou un agrégateur qui couvre la Guinée. En V1, le client envoie une capture et le marchand valide.
- **Synchronisation du catalogue WhatsApp Business** : passe par Meta, avec validation du compte. Plus tard.
- **API WhatsApp Business pour envoyer des messages automatiques** : payante et soumise à validation Meta. En V1, on utilise des liens `wa.me` préremplis, gratuits.
- **Devis de livreurs en temps réel, étiquettes d'expédition, abonnements** : partenariats et usages absents en Guinée pour l'instant.
- Le nom, le logo, les textes, les illustrations de Take App : jamais repris.

## Taille

Écrans 19, parcours 6, entités 12.
Parties difficiles : (1) le message WhatsApp prérempli, bien formaté et fiable sur tous les téléphones ; (2) le stock juste (décrément à la commande, remise à l'annulation, pas de survente) ; (3) les pages rapides et légères sur 3G avec des photos produits compressées.
**Taille : M** (quelques semaines pour la V1 « must »).
