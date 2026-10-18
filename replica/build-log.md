# Journal de construction

App : `boutique/` (Laravel 12.70, PHP 8.3, MySQL 8 / MariaDB, Tailwind 4, CSS final 7,8 Ko gzip, pas de framework JS : quelques petits scripts en ligne).
Tests : 107 tests, 1 032 vérifications, verts sur SQLite et sur MariaDB (au 18 octobre). Parcours rejoués dans Chromium de 320 à 1366 px, zéro erreur console.
Parité (`replica-diff/parity.py`) : **95,3 / 100**, indispensables **22 sur 22**.

## 2026-10-18 (suite) : commandes, messages, saisie, réglages « Commande »

| écran | état | ce qui manque | plus dur que prévu |
| --- | --- | --- | --- |
| S12 commandes | fait | export de la liste | cases à cocher hors du formulaire (`form="bulk"`) pour garder une seule liste qui devient un tableau à 1200 px ; une action en masse ne fait jamais reculer une commande |
| S13 détail | fait | — | messages WhatsApp prêts selon l'état : paiement partiel (on ne connaît pas le montant reçu, donc « le reste du montant »), livrée mais pas payée, capture à vérifier |
| Facture imprimable | fait | — | lignes et totaux qui ne se coupent pas entre deux pages A4 ; QR code de suivi intégré en image |
| S21 commande manuelle | fait | — | même numérotation, même fiche client, même stock que la vitrine (`OrderPlacer::persist` partagé) ; prix négocié par ligne |
| Réglages > Commande | fait | — | horaires à l'heure de Conakry ; par défaut les commandes restent possibles quand c'est fermé, avec un mot au client |

- **Commandes** : actions en masse (confirmer, terminer, annuler avec remise du stock, payée, prête, en livraison, livrée), tableau sur grand écran, retour en page 1 après l'action.
- **Fiche commande** : messages WhatsApp en un clic (confirmation, rappel du paiement, paiement reçu, prête ou en route, merci, facture, annulation), facture imprimable ou en PDF avec QR code.
- **Nouvelle commande** à la main (téléphone, message, comptoir), note interne.
- **Questions au client** (ex. point de repère), facultatives ou obligatoires ; réponses dans WhatsApp, la facture et la commande.
- **Horaires d'ouverture** par jour ; la vitrine dit « Ouvert jusqu'à 20 h » ou « Fermé · ouvre demain à 8 h ».
- **Pas fait (choix)** : vrais suppléments payants. Le panier devrait changer de structure à une semaine de la bêta. En attendant : un choix avec son prix (ex. « L + flocage ») et une question pour le texte du flocage.
- Revue adversariale des actions en masse, messages et facture : 4 angles, chaque point contre-vérifié ; points confirmés corrigés (paiement partiel, actions qui reculaient, colonne Client écrasée à 1200 px, facture qui débordait à 360 px, salutation « Bonjour El »).
- **Bug ancien corrigé** : la pagination affichait « pagination.next » en production (pas de fichiers de langue français). Ajout de `lang/fr/pagination.php` et `lang/fr/validation.php`.

## 2026-10-18 : Accueil, le poste de pilotage du commerçant

| écran | état | ce qui manque | plus dur que prévu |
| --- | --- | --- | --- |
| Accueil (`/admin`) | fait | — | chaque nombre « à faire » doit ouvrir une liste qui affiche le même nombre : les filtres de la liste Commandes excluent maintenant les commandes annulées pour « à vérifier », « non payées » et « à la livraison » |
| Bien démarrer | fait | — | étapes cochées d'après les vraies données ; une commande d'essai annulée ne fait pas quitter le guide |
| Menu à 5 onglets | fait | — | « Commandes » coupé sur les écrans de 320 à 360 px : lettres resserrées sur ces écrans |

- **À faire maintenant** : paiements à vérifier, commandes en attente (depuis plus de 2 jours, ou WhatsApp jamais ouvert), argent à encaisser à la livraison, stock bas par taille / couleur.
- **Vos ventes** sur aujourd'hui, 7 jours ou 30 jours (jours de Conakry) : commandes, visites, déjà payé / pas encore payé, valeur des commandes, barres par jour, réseau qui apporte le plus de commandes. Hors commandes annulées et remboursées, même règle que la page Partager.
- **Dernières commandes** et **Ce qui se vend** (3 produits, en quantités).
- 8 requêtes par affichage, toutes limitées à la boutique, sans JavaScript.
- Revue adversariale (5 angles : chiffres, SQL, affichage, régressions, sécurité ; chaque point vérifié par un second relecteur) : 13 points confirmés, tous corrigés. Les principaux : une commande d'essai annulée cochait « Partagez votre lien » ; à égalité, un seul réseau était nommé ; la page Partager ne suivait pas la période de l'Accueil ; la grille à 3 colonnes débordait entre 960 et 1200 px (elle n'apparaît plus qu'à partir de 1200 px, nouveau point de rupture `xl`) ; « Ouvrir ma boutique » pouvait la refermer sur un double appui ; 3 tests ne vérifiaient rien.

**Bug corrigé (en production depuis le 17 octobre)** : la colonne `payment_status` faisait 12 caractères, mais « partially_paid » en fait 14. Sur MySQL, choisir « Payée en partie » sur une commande donnait une page d'erreur. SQLite (tests) ne vérifie pas la longueur, d'où le passage inaperçu. Migration `2026_10_19_000001` : colonne portée à 20 caractères. Les tests de l'Accueil tournent aussi sur MariaDB.

## 2026-10-16 : étape 2

| écran | état | ce qui manque | plus dur que prévu |
| --- | --- | --- | --- |
| S15 produit | fait | réordonner les photos | variantes : garder les ids pour ne pas casser les commandes, journaliser chaque changement de stock |
| S02 fiche produit | fait | — | photos en défilement natif, prix et stock qui suivent la taille choisie |
| S05 preuve de paiement | fait | — | captures sur disque privé, servies uniquement au commerçant de la boutique |
| S12 commandes | fait | actions en masse | — |
| S13 détail | fait | note interne | — |
| S16 catégories | fait | — | — |
| S17 clients | fait | — | — |
| S18 livraison | fait | horaires de livraison | — |
| S20 boutique | partiel | horaires d'ouverture, logo | — |
| S22 partager | fait | — | message d'accueil : le lien était figé dans le texte enregistré ; remplacé par un repère `{lien}` complété à la copie |

**Bug corrigé** : les limites anti-abus (`throttle:10,1`…) partageaient un seul compteur par IP pour toutes les routes. Un client qui ajoutait une dizaine d'articles se faisait bloquer (429) à la commande ou à l'envoi de la capture. Chaque route a maintenant son propre limiteur nommé, avec un test de non-régression.

**Ce qui reste (should / could)** : questions personnalisées au checkout, actions en masse, commande manuelle, reçu WhatsApp au client, carnet de crédit, rapport du jour, horaires, plusieurs boutiques par compte.

## 2026-10-15 : tranche verticale

| écran | état | ce qui manque | plus dur que prévu |
| --- | --- | --- | --- |
| S10 inscription / connexion | fait | réinitialisation du mot de passe (support WhatsApp en V1) | relation « boutique » mise en cache sur l'utilisateur : le middleware relit la boutique à chaque requête |
| S11 création de boutique | fait | logo | — |
| S01 vitrine | fait | photos produits | — |
| S02 fiche produit | partiel | photos, choix de variante côté admin | — |
| S03 panier | fait | — | — |
| S04 commande | fait | questions personnalisées | masquer le paiement à la livraison selon le mode choisi, sans framework JS |
| S05 instructions de paiement | partiel | envoi de la capture dans l'app (le client l'envoie sur WhatsApp en attendant) | — |
| S06 envoi WhatsApp | fait | — | message court + lien de facture pour rester sous les limites des liens wa.me |
| S07 facture | fait | — | — |
| S12 liste des commandes | partiel | filtre par paiement, actions en masse | — |
| S13 détail commande | fait | note interne, numéro de suivi | — |
| S14 / S15 produits | partiel | photos, variantes multiples | — |
| S19 paiements | fait | — | interdire de couper le dernier moyen de paiement actif |
| S16, S17, S18, S20, S21, S22 | à faire | étape 2 | — |

**Paiement à la livraison (demande de Mohamed)** : activable / bloquable, plafond, règle par mode de livraison, blocage par client. Les quatre règles sont appliquées et testées côté serveur ; les écrans S18 (case par mode de livraison) et S17 (bouton par client) arrivent à l'étape 2.

Captures : `replica/clone-screens/` (390 px, rendu ×2).
