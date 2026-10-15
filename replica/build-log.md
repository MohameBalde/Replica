# Journal de construction

App : `boutique/` (Laravel 12.70, PHP 8.3, MySQL 8 / MariaDB, Tailwind 4, CSS final 5,8 Ko gzip, aucun JavaScript chargé hors un petit script sur S04).
Tests : 17 tests, 130 vérifications, verts sur SQLite et sur MariaDB. Parcours complet rejoué dans Chromium à 390 px, zéro erreur console.
Parité (`replica-diff/parity.py`) : **62,4 / 100**, indispensables 15 sur 22.

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
