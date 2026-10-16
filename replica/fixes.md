# Ce qui rendra la boutique plus vendable que Take App

Date : 2026-10-16. Données : `replica/reviews.csv` → `replica/feedback.md`.

## L'échantillon, honnêtement

- **9 avis**, tous copiés mot pour mot avec leur lien : 6 sur l'App Store (Indonésie, Inde, Malaisie, Singapour, Afrique du Sud ×2) via le flux RSS officiel d'Apple, 3 sur Google Play (les avis affichés sur la fiche, qui en annonce 148 pour une note de 4,3).
- **Pas pu lire** : la liste complète des avis Google Play (la fenêtre « See all reviews » ne s'ouvre pas dans le navigateur de la session), Trustpilot, Reddit, G2 et Product Hunt (bloqués par le réseau de la session), Capterra et Hacker News (rien sur Take App).
- **Moins de 30 avis = une direction, pas un classement.** Rien ici n'est une tendance prouvée. Les interviews de Mohamed (frère, sœur, Nimaga, boutiques de Madina) comptent plus que ces avis pour la Guinée.
- Pour compléter : autoriser `www.trustpilot.com` et `www.reddit.com` dans l'accès réseau de l'environnement, puis relancer `/replica-entrepreneur`.

## 1. Ce qu'ils détestent

| # | problème | avis | sources | citations |
| --- | --- | --- | --- | --- |
| 1 | **Prélèvements d'argent non voulus, résiliation qui ne s'arrête pas** | 3 | 3 (App Store ZA, App Store IN, Google Play) | « TWO MONTHS DOWN THE LINE THEY ARE STILL DEDUCTING MONEY FROM MY BANK ACCOUNT, EVEN THOUGH THEY SENT ME A CONFIRMATION EMAIL TO SAY MY ACCOUNT HAS BEEN CANCELLED. » ([App Store ZA, 1★](https://apps.apple.com/za/app/take-app/id6754038245?see-all=reviews)) · « i never agree to take any paid subscription, out of sudden this scam comp charge thru my google pay. » ([Google Play, 1★](https://play.google.com/store/apps/details?id=com.takeapp.mobile&hl=en&gl=US)) |
| 2 | **Le support ne répond pas quand il y a un problème d'argent** *(mince)* | 2 | 2 | « I TRIED TO REACH OUT TO THEM AND THEY DONT CARE TO ASSIST. » ([App Store ZA, 1★](https://apps.apple.com/za/app/take-app/id6754038245?see-all=reviews)) · « I did not receive any support from your team when I needed assistance. » ([App Store IN, 1★](https://apps.apple.com/in/app/take-app/id6754038245?see-all=reviews)) |
| 3 | **L'abonnement est cher** *(mince)* | 1 | 1 | « Just subscription could be more affordable. » ([App Store SG, 5★](https://apps.apple.com/sg/app/take-app/id6754038245?see-all=reviews)). Fait vérifié par ailleurs : 50 $ par mois pour l'offre Business ([take.app/pricing](https://www.take.app/pricing)). |
| 4 | **Les catégories s'affichent mal sur la vitrine** *(mince)* | 1 | 1 | « the category should only be displayed on menu button not the front page or frontstore of the website, they don't appear nicely » ([Google Play, 2★](https://play.google.com/store/apps/details?id=com.takeapp.mobile&hl=en&gl=US)) |

## 2. Ce qui manque

| # | demande | avis | sources | citation |
| --- | --- | --- | --- | --- |
| 1 | **Payer selon les ventes au lieu d'un abonnement fixe** *(mince)* | 1 | 1 | « adding a package that includes all the features of the program and you take a percentage on every item sold from it. Your profit and our profit at the start. » ([Google Play, 4★](https://play.google.com/store/apps/details?id=com.takeapp.mobile&hl=en&gl=US)) |
| 2 | **Beaucoup de photos, tailles et couleurs pour les habits, commandes illimitées** *(mince)* | 1 | 1 | « adding a large number of pictures for one product and adding different sizes and colors for clothes An unlimited orders » (même avis) |
| 3 | **D'autres moyens de paiement** *(mince)* | 1 | 1 | « please consider adding different payment methods in the future » ([App Store ZA, 5★](https://apps.apple.com/za/app/take-app/id6754038245?see-all=reviews)) |
| 4 | **Voir le stock restant au moment de vendre** *(mince)* | 1 | 1 | « for the POS, on the app, I cannot see how many stocks I have left » ([App Store MY, 5★](https://apps.apple.com/my/app/take-app/id6754038245?see-all=reviews)) |

## 3. Ce qui n'est pas résolu (le positionnement)

- **Les commerçants sans carte bancaire.** L'abonnement de Take App se paie par carte ou solde prépayé, en dollars, et les seuls avis très négatifs parlent de prélèvements. En Guinée, la plupart des petits commerçants paient par Orange Money et n'ont pas de carte. *(Déduction, pas un thème d'avis.)*
- **La Guinée.** Take App ne cite ni la Guinée ni le GNF parmi ses pays mis en avant, alors que le Sénégal et la Côte d'Ivoire y sont ([take.app/countries](https://www.take.app/countries)). *(Fait vérifié, pas un avis.)*
- **Le commerçant qui démarre.** L'avis 4★ décrit quelqu'un qui « n'a pas l'argent au début » : l'offre gratuite limitée à 50 commandes enregistrées ne lui suffit pas, et 50 $ par mois est hors de portée.

## 4. Le plan de corrections

Classé par preuve × coût. Chaque point est ajouté à `features.csv` (`original = no`).

| # | quoi | taille | qui | preuve | état |
| --- | --- | --- | --- | --- | --- |
| 1 | **Abonnement prépayé en GNF par Orange Money / MTN, sans carte et sans prélèvement automatique.** On paie un mois, il s'arrête tout seul ; rappel WhatsApp 3 jours avant la fin ; aucune donnée bancaire stockée | M | `/replica-launch` (offre) puis `/replica-backend` | thème 1 : 3 avis, 3 sources | à faire |
| 2 | **Arrêter = un bouton**, sans demande au support, avec confirmation à l'écran | S | `/replica-backend` | thème 1 | à faire (découle du 1) |
| 3 | **Support WhatsApp humain à Conakry**, délai de réponse affiché (ex. « réponse en moins de 2 h, 8 h–20 h ») | S | `/replica-launch` | thème 2 : 2 avis, 2 sources (mince) | à faire |
| 4 | **Démarrage gratuit sans plafond caché**, puis une offre mensuelle en GNF ; étudier une formule « à la commande » pour les débutants | M | `/replica-launch` | thème 3 + demande 1 (minces) | à décider avec les interviews |
| 5 | **Plus de photos par produit** : de 5 à 10 | S | `/replica-build` | demande 2 (mince) | **fait** |
| 6 | Tailles et couleurs, commandes illimitées | — | — | demande 2 | **déjà fait** |
| 7 | Moyens de paiement locaux (Orange Money, MTN, paiement à la livraison contrôlé) | — | — | demande 3 | **déjà fait** |
| 8 | Stock visible pour le commerçant à chaque produit et taille | — | — | demande 4 | **déjà fait** |
| 9 | Catégories en onglets propres sur la vitrine | — | — | haine 4 | **déjà fait** |

## 5. L'angle

**Option A : la confiance sur l'argent.**
> Pour les commerçants qui ont peur d'être prélevés sans le vouloir, **[ta boutique]** se paie en GNF par Orange Money, un mois à la fois : pas de carte, pas de prélèvement, vous arrêtez quand vous voulez.
> Preuve : prélèvements et résiliation, 3 avis sur 3 sources, les seuls avis à 1★ de l'échantillon.

**Option B : fait pour la Guinée.**
> Pour les commerçants guinéens qui vendent sur WhatsApp avec des outils pensés pour ailleurs, **[ta boutique]** parle français, compte en GNF, accepte Orange Money, MTN et le paiement à la livraison que vous contrôlez.
> Preuve : la Guinée absente des pays de Take App (fait vérifié) ; demande de moyens de paiement locaux, 1 avis (mince).

**Option C : on gagne quand vous gagnez.**
> Pour les commerçants qui démarrent sans budget, **[ta boutique]** est gratuite pour commencer et reste abordable quand vous vendez.
> Preuve : prix et formule « au pourcentage », 2 avis sur 2 sources (mince).

### Recommandation : **B en titre, A comme promesse**

Le titre dit à qui on parle (« fait pour la Guinée »), la promesse lève la peur qui ressort des avis (« payé en GNF par Orange Money, sans carte, sans prélèvement »). Exemple pour la page d'accueil :

> **Votre boutique WhatsApp, faite pour la Guinée.**
> Vos clients commandent en 1 minute, vous recevez tout sur WhatsApp. Payé en GNF par Orange Money, un mois à la fois : pas de carte, pas de mauvaise surprise.

À ne jamais faire : citer Take App dans le nom, les pubs ou la fiche store, ni reprendre les avis ci-dessus comme témoignages.
