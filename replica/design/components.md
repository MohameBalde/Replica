# Composants : boutique WhatsApp guinéenne

Système neuf, **mobile d'abord** (360 à 412 px), pensé pour les Android d'entrée de gamme et la 3G.
Couleurs : uniquement par rôle (`bg-surface`, `text-muted`…), jamais de code couleur en dur. Voir `tokens.json`.

## Principes

- **Une main, un pouce** : actions principales en bas de l'écran, zones tactiles de 44 px minimum.
- **Léger** : polices du téléphone (aucune police téléchargée), icônes SVG en ligne (seulement celles utilisées), images WebP en `loading="lazy"`, pas de carrousel animé.
- **Lisible** : texte de base en 16 px, prix toujours en gras au format `150 000 GNF`.
- **Clair pour tous** : des mots simples, une action principale par écran, des icônes toujours accompagnées d'un mot.
- **Accessible** : contraste AA vérifié (0 échec), anneau de focus visible (`focus`, 2 px), vrais éléments HTML (`<button>`, `<label>`, `<input type="checkbox" role="switch">`).
- **Icônes** : [Lucide](https://lucide.dev) (licence ISC), copiées en SVG en ligne. Aucune icône, illustration ni texte de Take App.

---

## Bouton

```
variants  principal (accent), secondaire (contour), discret (texte), danger, whatsapp
tailles   md 44 px, lg 52 px (bouton d'action en bas d'écran, pleine largeur)
états     normal, appuyé (accent-strong), focus-visible (anneau focus 2 px),
          désactivé (opacité 50 %, pas de clic), chargement (spinner + libellé conservé)
tokens    fond accent, texte on-accent, radius md, police base/600
a11y      vrai <button> ou <a> ; en chargement : aria-busy="true", libellé conservé
          pour le lecteur d'écran ; double appui bloqué (commandes en 3G)
utilisé   partout ; variante whatsapp : S06, S13
```

Variante **WhatsApp** : fond `whatsapp`, icône bulle + « Envoyer ma commande sur WhatsApp ». Réservée aux actions qui ouvrent WhatsApp.

## Champ de formulaire

```
types     texte, téléphone (préfixe fixe +224, clavier numérique), montant (suffixe GNF,
          espaces de milliers à la saisie), zone de texte, liste, envoi d'image
anatomie  libellé au-dessus (jamais seulement un placeholder), champ, aide, erreur
états     normal (bordure border-input), focus (anneau focus), erreur (bordure danger
          + message danger sous le champ), désactivé
tokens    hauteur 48 px, police 16 px (évite le zoom), radius md
a11y      <label for>, aria-describedby vers l'aide et l'erreur, aria-invalid en erreur,
          autocomplete="name" / "tel" / "street-address"
utilisé   S04, S10, S11, S15, S18, S19, S20
```

## Interrupteur (switch)

```
anatomie  libellé à gauche + description en text-muted, interrupteur à droite
états     activé (fond accent, pastille à droite), désactivé (fond border-input),
          focus-visible, verrouillé (désactivé + explication)
tokens    44 × 26 px, pastille 22 px, motion fast
a11y      <input type="checkbox" role="switch">, toute la ligne est cliquable,
          l'état est annoncé (« activé » / « désactivé »)
utilisé   S14 (visible), S18 (paiement à la livraison possible), S19, S20 (boutique ouverte)
```

## Bloc « Paiement à la livraison » (S19)

Demande de Mohamed : le commerçant **active ou bloque** le paiement à la livraison.

```
┌──────────────────────────────────────────────┐
│ 💵 Paiement à la livraison           [ ●━━ ] │  ← interrupteur, activé par défaut
│ Le client paie en espèces quand il reçoit    │
│ sa commande.                                 │
│ ──────────────────────────────────────────── │
│ ☐ Limiter aux commandes jusqu'à…             │  ← case, visible si activé
│   [ 500 000          ] GNF                   │  ← champ montant, si la case est cochée
│ Au-delà, le client devra payer avant         │
│ (Orange Money, MTN…).                        │
│ ──────────────────────────────────────────── │
│ Modes de livraison concernés : Kaloum,       │
│ Ratoma, Retrait en boutique. Modifier ›      │  ← lien vers S18
└──────────────────────────────────────────────┘
```

```
états     activé (détails visibles), désactivé (détails masqués + texte « Vos clients
          devront payer avant la livraison »), erreur (plafond vide ou à 0 alors que la
          case est cochée), dernier moyen actif (on ne peut pas tout couper : message
          « Gardez au moins un moyen de paiement »)
enregistr. au changement (toast « Paiement à la livraison activé » / « bloqué »)
a11y      la case et le champ montant sont désactivés et masqués aux lecteurs d'écran
          quand l'interrupteur est coupé
```

Côté client (S04), l'option **n'apparaît que si elle est permise** (activée, montant sous le plafond, mode de livraison compatible, client non bloqué). Si elle est refusée à cause du plafond : texte `text-muted` « Paiement à la livraison possible jusqu'à 500 000 GNF ».

## Carte moyen de paiement (S19)

```
anatomie  icône + nom (Orange Money, MTN MoMo, Virement, Paiement à la livraison),
          numéro marchand + nom du titulaire, interrupteur actif
états     actif, inactif, incomplet (numéro manquant : badge avertissement, ne peut pas
          être activé)
utilisé   S19
```

## Carte produit

```
variants  grille (2 colonnes mobile, 3 tablette), liste (admin S14)
anatomie  photo carrée (vignette 400 px WebP), nom (2 lignes max), prix en gras,
          badge stock éventuel
états     normal, stock faible (badge warning « Plus que 3 »), rupture (photo grisée,
          badge neutre « Épuisé », non cliquable pour ajouter), sans photo (fond surface
          + initiale du produit)
a11y      toute la carte est un lien ; alt de l'image = nom du produit
utilisé   S01, S14
```

## Sélecteur de variante

```
variants  puces (≤ 8 choix), liste déroulante (> 8)
états     normal, sélectionné (bordure accent 2 px + fond accent-soft), indisponible
          (barré, text-muted, non sélectionnable mais visible)
a11y      groupe radio (<fieldset> + <legend> « Taille »), flèches clavier
utilisé   S02
```

## Stepper quantité

```
anatomie  [ − ]  2  [ + ], boutons 44 px
états     min atteint (− désactivé à 1), max atteint (+ désactivé au stock dispo)
a11y      boutons avec aria-label « Retirer un » / « Ajouter un », valeur en aria-live
utilisé   S02, S03
```

## Barre d'action du bas (sticky)

```
usage     bouton principal toujours visible en bas : « Ajouter au panier · 150 000 GNF »,
          « Voir le panier (3) · 450 000 GNF », « Commander »
états     cachée (panier vide sur S01), visible, chargement
tokens    fond bg, ombre pop, marge de sécurité en bas (env(safe-area-inset-bottom))
utilisé   S01, S02, S03, S04
```

## Badge de statut

Une couleur par sens, **toujours avec un mot** (jamais la couleur seule) :

| statut | badge |
| --- | --- |
| En attente, Non payé, Non préparée | `neutral-soft` / `on-neutral-soft` |
| Paiement à vérifier, Prête, Stock faible | `warning-soft` / `on-warning-soft` |
| Confirmée, En livraison | `info-soft` / `on-info-soft` |
| Payée, Livrée, Terminée | `success-soft` / `on-success-soft` |
| Annulée, Remboursée, Épuisé | `danger-soft` / `on-danger-soft` |
| À la livraison (paiement) | `accent-soft` / `accent-strong` |

```
tokens    police xs/600, radius pill, padding 2 × 8
utilisé   S07, S12, S13, S14
```

## Onglets filtres

```
usage     S12 : Toutes · En attente · Confirmées · Terminées ; puis puce « Paiement »
états     actif (texte accent + soulignement 2 px), inactif (text-muted), avec compteur
a11y      role="tablist" ou liens avec aria-current
défilement horizontal sur petit écran
```

## Ligne de commande (S12)

```
anatomie  #1043 · nom du client · total en gras · heure ; badges statut + paiement ;
          petite étiquette source (Facebook, TikTok, WhatsApp…)
états     normal, nouvelle (point accent), sélectionnée (case à cocher, fond accent-soft)
```

## Envoi d'image

```
variants  une image (preuve de paiement S05, logo S20), plusieurs (photos produit S15)
états     vide (zone en pointillés + « Ajouter une photo »), envoi en cours (barre de
          progression), terminé (aperçu + « Retirer »), erreur (« Photo trop lourde
          (max 8 Mo) »)
a11y      vrai <input type="file" accept="image/*" capture="environment"> avec libellé
```

## Récapitulatif de commande

```
anatomie  lignes articles (nom, variante, quantité × prix), sous-total, livraison,
          total en gras lg ; ligne paiement (« À la livraison : préparez 150 000 GNF »)
utilisé   S03, S04, S07, S13
```

## Instructions de paiement mobile money (S05)

```
anatomie  montant à envoyer (display), numéro marchand avec bouton « Copier »,
          nom du titulaire, étapes numérotées (1. Composez… 2. Envoyez… 3. Ajoutez la
          capture), envoi d'image, champ « N° de transaction » (optionnel)
états     en attente de preuve, preuve envoyée (badge warning « Paiement à vérifier »)
```

## Bloc partage (S22)

```
anatomie  par réseau : icône + nom + lien raccourci + bouton « Copier » ;
          message d'accueil dans une zone de texte + « Copier le message » ;
          QR code + « Télécharger »
états     copié (toast « Lien copié »)
```

## Toast

```
variants  succès, erreur
position  en bas, au-dessus de la barre d'action ; disparaît après 3 s
a11y      role="status" (succès) ou role="alert" (erreur)
```

## Navigation admin (barre du bas)

```
onglets   Commandes · Produits · Partager · Réglages (icône + mot)
états     actif (accent), inactif (text-muted), badge compteur sur Commandes
a11y      <nav aria-label="Menu">, aria-current="page"
```

## En-tête boutique (S01)

```
anatomie  logo rond 48 px (ou initiale), nom, ville, badge « Ouvert » / « Fermé »,
          bouton WhatsApp discret (contacter la boutique)
états     ouverte, fermée (bandeau warning « Boutique fermée, commandes reprises
          demain » ; commandes désactivées)
```

## États vides

Textes écrits pour ce produit :
- S01 sans produit : « Cette boutique prépare ses produits. Revenez bientôt ! »
- S03 panier vide : « Votre panier est vide. » + bouton « Voir les produits »
- S12 aucune commande : « Pas encore de commande. Partagez votre lien pour recevoir les premières ! » + bouton « Partager ma boutique »
- S14 aucun produit : « Ajoutez votre premier produit en 1 minute. » + bouton « Ajouter un produit »
