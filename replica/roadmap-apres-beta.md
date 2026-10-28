# Feuille de route après la bêta (27 octobre → 30 novembre 2026)

Source : le « Plan d'amélioration » de Mohamed (22 octobre), comparé à ce que Wanllan fait déjà.
Règle : rien de nouveau avant la bêta du 26 octobre (code gelé, seulement des corrections).
Rythme : environ 1 h par jour en semaine et 3 h le week-end. Décembre reste réservé au projet de fin de cycle.

## Verdict par chantier

| Chantier du plan | Ce qui existe déjà | Décision |
| --- | --- | --- |
| 0. Alerte « nouvelle commande » (ajout du 22 octobre) | la commande arrive sur WhatsApp **seulement si le client appuie sur « Envoyer »** ; sinon elle attend dans Wanllan (badge « WhatsApp non envoyé ») | **Oui, en premier** : notification sur le téléphone du commerçant (app installée), gratuite, sans Meta. Android tout de suite ; iPhone seulement si Wanllan est installé sur l'écran d'accueil. **Non** à l'API WhatsApp Business de Meta pour l'instant (vérification d'entreprise, numéro dédié, coût par message) et **non** aux robots WhatsApp non officiels (numéro bloqué, contraire aux règles de WhatsApp) |
| 1. Fiche course livreur | adresse, mode de livraison (zone), repère (Réglages > Commande), montant à encaisser, messages WhatsApp prêts | **Oui, juste après l'alerte** : un message « livreur » + une page `/course/{jeton}` sans connexion |
| 2. ID de transaction Mobile Money | champ « N° de transaction » **facultatif** à l'envoi de la capture | **Oui** : le rendre obligatoire, l'afficher en gros chez le commerçant et dans le message WhatsApp, avec une aide visuelle (exemple de SMS fabriqué par nous, sans données réelles) |
| 4. Paniers abandonnés | la commande est créée **avant** WhatsApp, avec le numéro du client ; badge « WhatsApp non envoyé » | **Oui, version courte** : un bouton « Relancer » sur ces commandes. **Non** au numéro demandé avant le panier : c'est une friction de plus pour l'acheteur, contraire au principe n° 2 du plan |
| 3. Google Sheets en temps réel | export Excel (CSV) avec filtres | **Seulement si 2 commerçants de la bêta le demandent** (script Google Apps Script + envoi en file d'attente) |
| 5. Descriptions par IA | — | **Plus tard (2027)** : coût d'API à payer chaque mois, à lancer quand Wanllan aura des abonnés payants |
| 6. FCFA et autres pays | la devise est déjà enregistrée par boutique et par commande | **Plus tard (2027)** : d'abord réussir en Guinée. Pas besoin de table « communes » : les zones de livraison du commerçant suffisent |

## Points à corriger dans le document

- Le paiement à la livraison et Orange Money / MTN existent déjà : ce ne sont plus des chantiers.
- Un ID de transaction se fabrique aussi : il ne supprime pas la fraude, il permet au commerçant de **vérifier en 5 secondes** dans son historique Orange Money. Ne pas promettre « zéro fraude ».
- « +15 à 25 % de conversion » : chiffre non mesuré chez nous. On mesurera pendant la bêta (commandes « WhatsApp non envoyé » / commandes totales).
- Les prompts du document laissent la stack à remplir : c'est Laravel 12 + MySQL.

## Plan daté

| Semaine | Travail | Livrable |
| --- | --- | --- |
| **Ven. 23 → dim. 26 oct.** | Tests sur téléphone, préparation et lancement de la bêta | bêta ouverte |
| **27 oct. → 1er nov.** | Alerte « nouvelle commande » sur le téléphone du commerçant, puis n° de transaction obligatoire | PR « Alertes et paiement vérifié » |
| **2 → 8 nov.** | Fiche course livreur (message + page livreur) et bouton « Relancer » ; mesure du taux « WhatsApp non envoyé » | PR « Livreur et relance » |
| **9 → 15 nov.** | Retours de la bêta ; Google Sheets seulement si demandé | corrections bêta |
| **16 → 30 nov.** | Marge, stabilisation, bilan de la bêta | bilan + décision sur les prix |
| **Décembre** | Projet de fin de cycle (pas de Wanllan, sauf panne) | — |
