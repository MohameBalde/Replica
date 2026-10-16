# Marque : **Wanllan**

> **Décidé par Mohamed le 2026-10-16.** « Wanllan » signifie « aide-moi » en poular : l'app qui aide les commerçants guinéens à vendre.
> Orthographe officielle : **Wanllan** (W-a-n-l-l-a-n). Signature : **Wanllan · Votre boutique sur WhatsApp**.

## Vérifications de « Wanllan » (2026-10-16)

| vérification | résultat |
| --- | --- |
| domaines | ✅ **`wanllan.com`**, `wanllan.app`, `wanllan.shop`, `wanllan.store`, `wanllangn.com`, `wanllan224.com` : tous libres (registre RDAP) |
| App Store | ✅ aucun résultat |
| Google Play | ✅ aucun résultat |
| web | ✅ aucun résultat exact « Wanllan » (seulement des mots voisins sans rapport : Wanling, WLAN…) |
| orthographes voisines | « Wallan » est le nom d'un groupe saoudien (`wallan.com`, concession automobile) ; « Wanlan » : `wanlan.com` est une page parking. Aucune confusion avec « Wanllan », mais à l'oral certains écriront « Wanlan » ou « Wallan » : prendre `wanlan.app` et le rediriger si le budget le permet |
| TikTok, Instagram, Facebook | ⏳ à vérifier sur le téléphone de Mohamed, et réserver `@wanllan` partout tout de suite |
| marque OAPI (via l'OGPI à Conakry) | ⏳ recherche officielle avant tout dépôt ou dépense |

**Domaine conseillé : `wanllan.com`** (le plus facile à retenir pour les clients) ; `wanllan.app` en complément si possible. Les deux étaient libres le 2026-10-16 : les acheter vite, un nom libre peut partir.

## Palette

| rôle | couleur | pourquoi |
| --- | --- | --- |
| accent | `#3730a3` indigo | l'indigo du *leppi*, le tissu teint du Fouta : une couleur guinéenne, loin du vert canard de Take App (`#027A6D`) |
| accent-strong | `#312e81` | boutons appuyés, textes sur fond clair |
| accent-soft | `#e0e7ff` | fonds de sélection |
| gold | `#f59e0b` | touches de marque (point du logo, badges) ; texte foncé `#1c1407` dessus |
| focus | `#b45309` | anneau de sélection clavier, distinct de l'indigo |
| whatsapp | `#15803d` | réservé au bouton « Envoyer sur WhatsApp » |

Contraste vérifié : **21 combinaisons, 0 échec** (`contrast.py`).

## Brief du logo

- **L'idée** : un « W » qui tend la main (W de Wanllan). Les deux creux du W comme deux mains qui se rejoignent, et un point or au-dessus : l'aide qui arrive, la notification de commande.
- **Type** : symbole + mot « Wanllan ».
- **Doit marcher** à 16 px (favicon) et en icône d'app 1024 px.
- **Livrables** : SVG, icône 1024 × 1024 sans transparence (iOS), favicons, image de partage 1200 × 630.
- **Interdit** : rien qui rappelle Take App ni le logo WhatsApp (pas de bulle verte, pas de combinaison vert + blanc).
- **Provisoire en place** : `boutique/public/favicon.svg` (monogramme W indigo + point or), `apple-touch-icon.png`, `icon-1024.png`. À remplacer par le logo final d'un graphiste ou réalisé sur Canva.

## Ton de voix

| mot | ce que ça veut dire | ce que ça ne veut pas dire |
| --- | --- | --- |
| **Chaleureux** | on parle comme un voisin de boutique qui rend service | familier : on vouvoie toujours |
| **Clair** | une idée par phrase, des mots de tous les jours | technique : jamais « API », « checkout », « dashboard » |
| **Rassurant** | on dit ce qui va se passer, surtout pour l'argent | mielleux : pas de promesses vides |

| à faire | à éviter |
| --- | --- |
| « Votre commande est bien partie sur WhatsApp. » | « Order submitted successfully. » |
| « Vous paierez 650 000 GNF à la livraison. » | « Paiement : COD » |
| « Gardez au moins un moyen de paiement actif. » | « Erreur de validation. » |
| « Pas encore de commande. Partagez votre lien ! » | « Aucune donnée. » |
| « Une capture peut être fausse : vérifiez sur votre téléphone. » | « Attention fraude !!! » |

### Les 10 textes les plus vus

| écran | texte | état |
| --- | --- | --- |
| inscription (titre) | « Vendez sur WhatsApp, sans répéter vos prix 50 fois. » | gardé : déjà dans le ton |
| inscription (en-tête) | « Wanllan · Votre boutique sur WhatsApp » | **nouveau** |
| création de boutique | « Le retrait en boutique et le paiement à la livraison sont activés au départ. » | gardé |
| vitrine vide | « Cette boutique prépare ses produits. Revenez bientôt ! » | gardé |
| bas de chaque vitrine | « Boutique créée avec Wanllan » (lien d'inscription, `?src=vitrine`) | **nouveau** : chaque vitrine fait connaître Wanllan |
| bouton principal client | « Valider ma commande » puis « Envoyer ma commande sur WhatsApp » | gardé |
| facture, paiement à la livraison | « Vous paierez 650 000 GNF en espèces à la réception. » | gardé |
| facture, capture envoyée | « Capture reçue 👍 La boutique vérifie votre paiement. » | gardé |
| commandes vides | « Pas encore de commande ici. Partagez le lien de votre boutique… » | gardé |
| erreur de numéro | « Entrez un numéro guinéen valable, par exemple 620 00 00 00. » | gardé |

Tous les textes ont été écrits pour Wanllan ; aucun ne reprend une phrase de Take App.

## Vérification automatique (`sweep.py`)

`python3 .claude/skills/replica-brand/sweep.py boutique --config replica/brand.json`

- Code de l'app : **propre** (plus de « Boutique WA » ni de « Wallan », aucune mention de Take App, aucune de ses couleurs).
- Restent 14 faux positifs dans `vendor/` (bibliothèques tierces : « take *app*ropriate action » dans leurs codes de conduite), qui ne font pas partie de notre code ni du dépôt.

---

# Historique : comment on a choisi


Date des vérifications : **2026-10-16**. Angle (`replica/fixes.md`) : « Votre boutique WhatsApp, faite pour la Guinée » + « payé en GNF par Orange Money, sans carte, sans prélèvement ».
Contrainte de Mohamed : un **nouveau nom**, pas Makiti.

## 20 idées

| style | idées |
| --- | --- |
| descriptif | Boutik224, Panier224, Commande224, LienBoutik, MaVitrine |
| composé | Vendo224, WaBoutik, KioskWa, CommandeVite, Vitrina |
| mot local / image | Tabalé, Kola, Nafa, Dioula, Baara, Jaango |
| inventé | Sanko, Kobee, Sabou, Bouti |

## Écartées, et pourquoi

| nom | raison |
| --- | --- |
| **Boutik224** | `boutik224.com` est déjà une boutique en ligne guinéenne (titre du site : « Boutique de Vente en ligne pour tout type de produits ») : risque de confusion |
| Nafa | plusieurs apps existent déjà sous ce nom (« MyNafa », « Nafa: PSX Stocks & Trading »…) ; `nafa.com`, `nafa.app`, `nafa224.com` pris |
| Kola, Vendi, Vitrina, Jaango, Baara | `.com` et `.app` déjà pris ; noms génériques déjà utilisés |
| Dioula | désigne aussi un peuple et une langue : à éviter pour une marque commerciale |
| Sanko, Kobee, Sabou | pas de sens clair pour les commerçants ; domaines `.com` pris |

## Les 5 retenues et les vérifications faites

Légende : ✅ libre / rien trouvé · ❌ pris · ⏳ à faire. « Libre » = aucune réponse d'enregistrement au registre (RDAP) le 2026-10-16, pas une garantie : vérifier au moment d'acheter.

| vérification | **Tabalé** | **Panier224** | **Commande224** | **LienBoutik** | **Vendo224** |
| --- | --- | --- | --- | --- | --- |
| sens | grand tambour qui annonce les nouvelles (image : « on annonce ta boutique ») | le panier du client, 224 = Guinée | dit exactement ce que fait l'app | le lien de la boutique | « je vends », 224 = Guinée |
| `.com` | ❌ `tabale.com` pris · ✅ `tabalegn.com` | ✅ | ✅ | ✅ | ✅ |
| `.app` | ✅ `tabale.app` | ✅ | ✅ | ✅ | ✅ |
| App Store (nom exact) | ✅ rien de proche | ✅ rien de proche | ✅ rien | ✅ rien | ⏳ |
| Google Play (nom exact) | ✅ 2 résultats sans rapport (« Tabali Bistro », « Tabalong ») | ✅ rien | ✅ rien | ✅ rien | ✅ rien |
| web (recherche) | ✅ aucune app ni boutique « Tabalé » trouvée | ✅ rien trouvé | ✅ rien trouvé | ⏳ | ⏳ |
| TikTok, Instagram, Facebook | ⏳ (pages inaccessibles depuis la session : à chercher sur ton téléphone) | ⏳ | ⏳ | ⏳ | ⏳ |
| marque **OAPI** (Guinée) | ⏳ | ⏳ | ⏳ | ⏳ | ⏳ |
| marques internationales (WIPO Global Brand Database) | ⏳ | ⏳ | ⏳ | ⏳ | ⏳ |
| facilité de protection | forte (mot distinctif) | faible (mots descriptifs) | très faible | faible | moyenne |

La Guinée dépose ses marques à l'**OAPI** (Organisation africaine de la propriété intellectuelle, Yaoundé), via l'OGPI à Conakry. Ces vérifications sont un premier tri, pas un avis juridique : avant de dépenser de l'argent sur le nom (enseigne, logo imprimé, dépôt), faire une recherche officielle à l'OGPI.

## Proposition de Mohamed : **Wanlan** (« aide-moi » en poular)

Vérifié le 2026-10-16 :

| vérification | résultat |
| --- | --- |
| sens | « aide-moi » en poular (selon Mohamed) : l'app qui aide le commerçant à vendre |
| `.com` | ❌ `wanlan.com` pris, mais c'est une page de parking (redirection vers `/lander`) : peut-être rachetable · ✅ `wanlangn.com` et `wanlan224.com` libres |
| `.app` | ✅ `wanlan.app` libre |
| App Store (nom exact) | ✅ aucun résultat |
| Google Play | ✅ un seul résultat proche, sans rapport : « Wanlanid: Book Summary Insight » |
| web | ✅ aucune app ni boutique « Wanlan » trouvée en Guinée |
| variantes d'orthographe | `wallan.com` = une société d'investissement (Wallan Holding) ; `wallan.app` et `wallam.app` libres |
| TikTok, Instagram, Facebook | ⏳ à chercher sur le téléphone |
| marque OAPI / OGPI | ⏳ |

Points forts : distinctif donc protégeable, sens chaleureux qui colle au produit (« l'app qui t'aide à vendre »), libre en `.app` et sans concurrent du même nom.
Points à tester : (1) le sens n'est compris qu'en poular : vérifier que les commerçants soussou, malinkés et forestiers l'adoptent aussi bien, puisque la cible est toute la Guinée ; (2) l'orthographe : à l'oral, certains écriront « Wallan » ou « Wanlane ». Prendre aussi le domaine de la variante la plus fréquente et le rediriger.

## Recommandation initiale : **Tabalé**

- **Distinctif** : un vrai mot de la culture mandingue, facile à dire en français, soussou, poular et malinké. Un nom descriptif comme « Panier224 » se copie facilement et se protège mal.
- **Il raconte l'angle** : le tabalé annonce les nouvelles au village. Ton app annonce la boutique du commerçant sur WhatsApp, TikTok et Facebook. C'est une histoire toute prête pour Balde Media.
- **Libre là où ça compte** : `tabale.app` et `tabalegn.com` libres, aucune app concurrente du même nom.
- **Signature proposée** : « **Tabalé** · Ta boutique sur WhatsApp ».

À confirmer par Mohamed : que le mot sonne bien et n'a pas de sens gênant pour ses proches et ses clients (Soussou, Peuls, Malinkés, Forestiers).

## Après le choix du nom (à faire)

1. Palette de couleurs de la marque, contrôle des contrastes (0 échec).
2. Brief du logo (idée, formats : SVG, icône 1024 px, favicon, image de partage 1200×630).
3. Ton de voix et réécriture des 10 textes les plus vus de l'app.
4. Remplacement du nom provisoire « Boutique WA » dans tout le code, puis vérification automatique (`sweep.py`) qu'il ne reste aucune trace de Take App.
