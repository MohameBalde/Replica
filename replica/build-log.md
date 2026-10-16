# Journal de construction

App : `boutique/` (Laravel 12.70, PHP 8.3, MySQL 8 / MariaDB, Tailwind 4, CSS final 7,8 Ko gzip, pas de framework JS : quelques petits scripts en ligne).
Tests : 33 tests, 270 vérifications, verts sur SQLite et sur MariaDB. Parcours rejoués dans Chromium à 390 px, zéro erreur console.
Parité (`replica-diff/parity.py`) : **82,8 / 100**, indispensables **22 sur 22**.

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
