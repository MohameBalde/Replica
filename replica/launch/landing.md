# Page d'accueil de Wanllan

**Construite** : `boutique/resources/views/landing.blade.php`, servie à l'adresse `/` (un commerçant connecté est redirigé vers ses commandes). Captures réelles de l'app en WebP (`boutique/public/img/`, 9 à 27 Ko chacune).

| section | contenu | lien avec `fixes.md` |
| --- | --- | --- |
| 1. Accroche | « Votre boutique sur WhatsApp, en 2 minutes. » + badge « Fait pour la Guinée » + bouton « Créer ma boutique gratuitement » + « Sans carte bancaire. En GNF. Paiement Orange Money ou MTN. » + 2 vraies captures | angle B (Guinée) en titre, angle A (argent) dans la promesse |
| 2. Le problème | les mêmes questions toute la journée ; les outils d'ailleurs (dollars, carte, prélèvements difficiles à arrêter) | thème 1 des avis, reformulé, aucune citation |
| 3. Comment ça marche | créer → partager le lien → recevoir des commandes complètes | parcours F01 |
| 4. Ce qui change | pas de mauvaise surprise sur l'argent ; paiement à la livraison contrôlé ; la Guinée dans l'app ; quelle pub fait vendre ; rapide en 3G ; captures privées | corrections 1–4 en tête, la parité ensuite |
| 5. Prix | les 3 formules lues depuis `App\Support\Plans` (une seule source de vérité) + mise en place | `pricing.md` |
| 6. Questions | installer une app ? WhatsApp Business ? payer l'abonnement ? colis refusé ? commission ? petite connexion ? | vraies objections attendues |
| 7. Appel final | « Wanllan, “aide-moi” en poular » + bouton + contact WhatsApp | marque |

Règles respectées : **aucune fausse preuve** (pas de nombre d'utilisateurs, pas d'étoiles, pas de « le plus choisi » : remplacé par « Recommandé »), aucun avis de Take App utilisé, le nom Take App n'apparaît nulle part (vérifié par un test). Contraste : tokens inchangés, 0 échec.

À ajouter quand ils existeront, avec accord écrit : témoignages réels des commerçants fondateurs.
