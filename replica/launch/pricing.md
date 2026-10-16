# Prix de Wanllan

**Statut : hypothèses à tester** avec les premiers commerçants (aucune réponse d'interview reçue au 2026-10-16). Les prix se changent sans toucher au code : variables `WANLLAN_PRICE_MERCHANT`, `WANLLAN_PRICE_PRO`, `WANLLAN_PRICE_SETUP` dans `.env` (voir `boutique/config/wanllan.php`).

## Repères (lus le 2026-10-15 / 16)

| offre | prix public | en GNF (≈ 8 790 GNF / $) | paiement | source |
| --- | --- | --- | --- | --- |
| Take App Basic | 0 $ | 0 | — | [take.app/pricing](https://www.take.app/pricing), lu le 2026-10-15 : 50 commandes enregistrées par mois |
| Take App Business | 50 $ / mois (1 $ le 1er mois) | ≈ 440 000 GNF / mois | carte ou solde prépayé | même page |
| Shopify Starter | 5 $ / mois | ≈ 44 000 GNF / mois | carte | sites tiers, 2026 ([NerdWallet](https://www.nerdwallet.com/article/small-business/shopify-pricing), [Style Factory](https://www.stylefactoryproductions.com/fr/shopify-prix)) : pas de vraie boutique, vente par liens |
| Ce que font les commerçants aujourd'hui | gratuit | 0 | — | WhatsApp, statuts et photos à la main |

- Taux USD/GNF : environ 8 772 à 8 792 GNF pour 1 $ fin juillet – début août 2026 (sources tierces : [ValutaFX](https://www.valutafx.com/history/usd-gnf-2026-08-03), [kurs.com.ua](https://kurs.com.ua/en/mastercard/gnf)). Cours officiel à vérifier sur [bcrg-guinee.org](https://www.bcrg-guinee.org).
- Repère de pouvoir d'achat : SMIG à 550 000 GNF par mois depuis le décret de 2022 ([Guinéenews](https://guineenews.org/2022/06/01/guinee-le-smig-passe-de-440-a-550-mille-gnf-decret/?amp=1)). L'offre Business de Take App coûte donc presque un SMIG par mois.

## Ce que disent les avis (`replica/feedback.md`, 9 avis)

- Prélèvements non voulus, résiliation qui ne s'arrête pas : **3 avis, 3 sources** (les seuls à 1★).
- Abonnement trop cher : 1 avis (mince). Demande d'une formule « au pourcentage » pour démarrer : 1 avis (mince).

## Le modèle

**Gratuit pour commencer, puis prépayé en GNF. Aucune carte, aucun prélèvement automatique.**

| formule | pour qui | prix | contenu |
| --- | --- | --- | --- |
| **Découverte** | essayer, petite boutique | **Gratuit** | 15 produits, commandes illimitées (pas de plafond caché), paiement à la livraison contrôlé, Orange Money / MTN, mention « Boutique créée avec Wanllan » |
| **Commerçant** | la boutique qui vend chaque semaine | **50 000 GNF / mois** ou **500 000 GNF / an** (2 mois offerts) | produits illimités, « Ce qui fait vendre », support WhatsApp |
| **Pro** | grandes boutiques, grossistes | **100 000 GNF / mois** ou **1 000 000 GNF / an** | sans la mention Wanllan, support prioritaire, mise en place offerte avec l'année |
| Mise en place par l'équipe | qui n'a pas le temps | **100 000 GNF** une fois | Mohamed (ou l'équipe) ajoute les produits, les photos et règle la livraison |

Pourquoi 50 000 GNF : environ 9 % d'un SMIG, le prix de Shopify Starter mais en GNF et par Orange Money, environ 9 fois moins cher que l'offre complète de Take App. **Pas de commission sur les ventes** : l'argent va directement au commerçant.

## Les problèmes de facturation, réglés dans le produit (fait)

- **Prépayé** : on paie 1 mois ou 1 an ; rien ne se renouvelle tout seul. Arrêter = ne pas repayer.
- **Rappel** : bandeau dans l'espace commerçant 3 jours avant la fin.
- **À l'échéance** : retour automatique en Découverte, **rien n'est supprimé** (les produits au-delà de 15 restent en ligne, on ne peut juste plus en ajouter).
- **Historique** : chaque paiement visible dans « Mon abonnement » (montant, période).
- **Encaissement** : Orange Money vers le numéro Wanllan, capture sur WhatsApp, puis activation par l'équipe :
  `php artisan wanllan:abonnement <boutique> merchant 1 --montant=50000 --ref=<n° transaction>`
- Pas de Stripe : les cartes bancaires sont rares chez la cible. L'API de paiement mobile money viendra plus tard (brique 4).

## Comment tester le prix (semaine du 19 octobre)

À chaque commerçant interrogé, après la démo :
1. « À partir de quel prix par mois ce serait trop cher pour toi ? »
2. « À quel prix tu trouverais ça une bonne affaire ? »
3. « Tu préfères payer chaque mois, ou une fois pour l'année avec 2 mois offerts ? »
4. « Tu paierais 100 000 GNF pour qu'on installe la boutique pour toi ? »

Si la majorité trouve 50 000 trop cher : passer à 30 000 (une seule variable à changer). Si plusieurs disent « bonne affaire » : garder 50 000 et tester 75 000 sur les suivants.

## Offre de lancement (proposée)

**« Commerçants fondateurs » : les 20 premières boutiques ont 3 mois Commerçant offerts**, en échange d'un retour honnête après 1 mois. Pas de faux avis : leurs témoignages ne seront utilisés qu'avec leur accord écrit.
