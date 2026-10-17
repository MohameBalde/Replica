# Hébergement de Wanllan : étude et choix

**Décision (16 octobre 2026) : AWS Lightsail, région Paris, serveur Linux de 1 Go à 7 $/mois.**
On passe au serveur de 2 Go (12 $/mois) dès qu'il le faut, en 15 minutes.

Recherches faites le 16 octobre 2026. Les prix changent : à revérifier le jour de l'achat.

## Ce dont Wanllan a besoin

- Laravel 12 (PHP 8.3) et MySQL 8. Le schéma utilise des contraintes CHECK de MySQL.
- Des fichiers à garder pour de bon : photos des produits (publiques) et captures de paiement (privées).
- Le HTTPS sur wanllan.com, et des pages rapides en 3G pour des clients en Guinée.
- Des sauvegardes : ce sont les commandes et les clients des commerçants.
- Une mise en ligne automatique depuis GitHub, pour que les corrections de la bêta partent toutes seules.
- Peu de travail pour Mohamed : environ 1 h par jour, et **personne de disponible en décembre** (projet de fin de cycle).
- Un coût fixe et bas, payé en dollars mais pensé en GNF. Mohamed a une carte qui paie en dollars.

## Les 5 finalistes

| | **AWS Lightsail** | DigitalOcean | Laravel Cloud | Railway | Hetzner |
| --- | --- | --- | --- | --- | --- |
| Prix par mois | **≈ 8 $ fixe** (7 $ + sauvegardes) ≈ 70 000 GNF | ≈ 8 $ fixe (6 $ + 30 % de sauvegardes) | ≈ 14 à 20 $, variable | ≈ 8 à 12 $, variable | ≈ 6 à 7 €, fixe |
| Serveur | 2 processeurs, 1 Go, 40 Go, 2 To de trafic | 1 processeur, 1 Go, 25 Go, 1 To | géré | géré | 2 processeurs, 4 Go |
| Région la plus proche | **Paris** | Amsterdam, Londres, Francfort | Londres, Francfort | Amsterdam | Allemagne, Finlande |
| Sauvegardes | **auto chaque jour, 7 gardées** (0,05 $/Go) | +20 à 30 % du prix | incluses (base) | pas de sauvegarde de volume en Hobby d'après le forum Railway, il faut l'offre Pro (20 $) | +20 % |
| MySQL | sur le serveur | sur le serveur | « aperçu, pas recommandé en production » (doc officielle) | service Railway | sur le serveur |
| Travail pour Mohamed | serveur à suivre (presque tout automatisé) | idem | presque rien | presque rien | idem |
| Risque principal | panne non surveillée | idem, sans Paris | coût qui varie, base pas prête, code à adapter (fichiers) | sauvegardes payantes | offre affichée « indisponible » en août 2026, deux hausses de prix en 2026, comptes refusés hors Europe signalés |
| Pour le CV de Mohamed | **AWS + Linux en production** | Linux | — | — | Linux |

## Et Hostinger ? (ajouté le 16 octobre, à la demande de Mohamed)

**Hostinger VPS** : un serveur Linux comme Lightsail, avec un centre de données en France (Paris, annoncé en 2023). C'est **le meilleur rapport puissance/prix**, mais seulement en payant d'avance.

| | AWS Lightsail 1 Go | Hostinger KVM 1 |
| --- | --- | --- |
| Serveur | 2 processeurs, 1 Go, 40 Go | 1 processeur, **4 Go**, 50 Go NVMe |
| Paiement | **chaque mois**, ≈ 8 à 9 $ avec les sauvegardes | **24 mois d'avance** : ≈ 156 $ (≈ 1 370 000 GNF) d'un coup, soit 6,49 $/mois |
| Sans engagement | 7 $/mois | ≈ 13,99 $/mois |
| Au renouvellement | même prix | 11,99 $/mois |
| Sur 2 ans | ≈ 204 $ sans crédit AWS, beaucoup moins si les crédits couvrent Lightsail | ≈ 156 $ |
| Sauvegardes | chaque jour, 7 gardées, 1 à 2 $/mois | **chaque semaine** ; chaque jour = option payante (6 à 12 $/mois selon les sources) |
| Arrêter | quand on veut | remboursé seulement pendant les 30 premiers jours |
| Plus | AWS sur le CV, crédits de bienvenue possibles | 4 fois plus de mémoire, site et support en français |

Le KVM 2 (2 processeurs, 8 Go, 100 Go) coûte 8,99 $/mois sur 24 mois (≈ 216 $ d'avance) et 14,99 $/mois au renouvellement.

**Verdict : Lightsail reste le choix pour Wanllan aujourd'hui.**
- Le produit n'est pas encore validé : payer 2 ans d'avance (≈ 1,37 million de GNF) avant la bêta est un pari. Lightsail se paie mois par mois et s'arrête quand on veut.
- Une sauvegarde par semaine, c'est jusqu'à 7 jours de commandes perdues en cas de panne. Les sauvegardes quotidiennes de Lightsail coûtent 1 à 2 $ ; celles de Hostinger effacent son avance de prix.
- Si les crédits AWS couvrent Lightsail, il revient moins cher que Hostinger sur 2 ans. Sans crédit, Hostinger KVM 1 économise environ 50 $ sur 2 ans.

**Hostinger hébergement web (mutualisé)** : écarté. Pas d'accès administrateur, ressources partagées avec d'autres sites et limitées par une règle d'usage raisonnable, versions de PHP différentes entre le site et la ligne de commande qui cassent Composer. Bien pour un site vitrine ou WordPress, pas pour une app avec des commerçants abonnés.

## Écartés tout de suite

- **AWS EC2 + RDS + répartiteur de charge** : l'AWS des grandes entreprises. Facilement 40 à 50 $/mois, une facture qui change chaque mois, beaucoup de réglages.
- **Azure** (pourtant dans le parcours de Mohamed) : 100 $ de crédit étudiant sans carte, mais il faut une adresse e-mail d'université reconnue (UNC non vérifiée). Après le crédit, application + base MySQL ≈ 25 $/mois ou plus. Bon pour apprendre, pas pour héberger Wanllan.
- **Oracle Cloud gratuit** : gros serveur gratuit, mais Oracle reprend les serveurs gratuits peu utilisés (moins de 20 % sur 7 jours), ce qui sera le cas au début. Aucune garantie pour des commerçants qui paient.
- **Hébergement en Guinée** (GUILAB, Kipé) : colocation et cloud sur devis seulement, pas de serveur à louer en ligne. À revoir le jour où les données devront rester en Guinée.
- **Render, Fly.io, Vercel** : pas de MySQL géré ou PHP mal pris en charge.
- **AWS Cape Town** : Lightsail n'y existe pas. Le câble sous-marin ACE qui relie Conakry arrive en France (Penmarc'h) : Paris est sur le chemin.

## Pourquoi Lightsail gagne

1. **Le moins cher à qualité égale** : 2 processeurs et 40 Go pour 7 $, où DigitalOcean donne 1 processeur et 25 Go pour 6 $. Le trafic est inclus : pas de surprise.
2. **Prix fixe** : la même promesse que Wanllan fait aux commerçants. Deux abonnements Commerçant (100 000 GNF) paient le serveur.
3. **Sauvegardes quotidiennes en un clic**, gardées 7 jours, pour 1 à 2 $ par mois.
4. **Paris**, la région AWS la plus proche de l'arrivée du câble ACE.
5. **Le code ne change pas** : les fichiers restent sur le disque du serveur, comme en développement. Laravel Cloud obligerait à les déplacer dans un stockage S3.
6. **Le métier de Mohamed** : administrer un serveur AWS en production va sur le portfolio, et peut servir au projet de fin de cycle (superviser un vrai service avec Zabbix et Grafana).
7. **Un compte pour les autres projets** : plus tard, un deuxième petit serveur pour le VPN du réseau Wi-Fi ou le portail de tickets, séparé de Wanllan.

**Le second : Laravel Cloud** (il tourne aussi sur AWS). À reprendre si Mohamed ne veut plus du tout gérer de serveur, une fois que sa base MySQL sera déclarée prête pour la production.

## Ce que le choix implique

- **Serveur** : Ubuntu 24.04 LTS, Nginx, PHP 8.3-FPM, MySQL 8 réglé pour 1 Go, 2 Go de mémoire d'appoint (swap), HTTPS par Let's Encrypt renouvelé tout seul.
- **Sécurité** : pare-feu (ports 22, 80 et 443 seulement), mises à jour de sécurité automatiques, connexion SSH par clé uniquement, double authentification sur le compte AWS.
- **Mise en ligne** : GitHub Actions se connecte au serveur en SSH à chaque mise à jour de la branche de production. Claude n'a jamais besoin du mot de passe AWS.
- **Sauvegardes** : instantanés automatiques Lightsail chaque jour, plus une copie de la base chaque nuit.
- **Surveillance** : une alerte sur le téléphone de Mohamed si le site ne répond plus, une alerte de budget AWS à 10 $.
- **Dans le code, au déploiement** : `bootstrap/app.php` fait confiance à tous les proxys (`trustProxies(at: '*')`), un réglage pensé pour Railway. Sans proxy devant Nginx, un visiteur pourrait fausser son adresse IP et contourner la limite de tentatives de connexion. À corriger avant la mise en ligne. Ajouter aussi les pages « Confidentialité » et « Conditions ».

## Coût prévu

| poste | $ / mois | GNF / mois (≈ 8 790 GNF / $) |
| --- | --- | --- |
| Serveur Lightsail 1 Go | 7 | ≈ 61 500 |
| Sauvegardes (instantanés) | 1 à 2 | ≈ 9 000 à 17 500 |
| **Total** | **≈ 8 à 9** | **≈ 70 000 à 79 000** |
| Plus tard : serveur 2 Go | 12 | ≈ 105 500 |

Le nom de domaine est en plus : wanllan.com acheté chez Spaceship le 17 octobre 2026, 9,08 $ la première année puis 10,18 $ par an (≈ 89 500 GNF). AWS le vendait environ 15 $ par an, et les crédits AWS ne couvrent pas les noms de domaine.

**Crédits AWS** : les nouveaux comptes reçoivent 100 $, et jusqu'à 100 $ de plus en essayant des services (20 $ pour la création d'une alerte de budget, qu'on fera de toute façon). Le plan gratuit n'ouvre pas tous les services : passer au plan payant à l'ouverture, les crédits restent valables 12 mois. La documentation de Lightsail renvoie à ces crédits ; **à confirmer dans la console** qu'ils couvrent bien Lightsail. Si oui, le serveur est payé pendant plus d'un an.

## Quand revoir ce choix

- Le serveur sature (mémoire ou processeur au-dessus de 80 % plusieurs jours) : passer au serveur de 2 Go.
- Plus de 200 boutiques actives : sortir la base de données sur son propre serveur.
- Mohamed ne veut plus gérer de serveur : Laravel Cloud, si sa base MySQL est prête pour la production.
- Wanllan est validé (vers février 2027) et Mohamed veut regrouper plusieurs projets sur un gros serveur pas cher : Hostinger KVM 2 payé 2 ans d'avance, avec une copie de la base chaque nuit hors du serveur.
- Une loi impose de garder les données en Guinée : reprendre contact avec GUILAB.

## Sources (lues le 16 octobre 2026)

- AWS, offres de Lightsail : https://docs.aws.amazon.com/lightsail/latest/userguide/amazon-lightsail-bundles.html
- AWS, FAQ facturation de Lightsail : https://docs.aws.amazon.com/lightsail/latest/userguide/amazon-lightsail-frequently-asked-questions-faq-billing-and-account-management.html
- AWS, instantanés de Lightsail : https://docs.aws.amazon.com/lightsail/latest/userguide/amazon-lightsail-faq-snapshots.html
- AWS, régions de Lightsail : https://docs.aws.amazon.com/lightsail/latest/userguide/understanding-regions-and-availability-zones-in-amazon-lightsail.html
- AWS, crédits du Free Tier : https://aws.amazon.com/about-aws/whats-new/2025/07/aws-free-tier-credits-month-free-plan/ et https://www.neowin.net/news/aws-free-tier-overhauled-new-users-get-200-in-credits-but-theres-a-catch/
- Laravel Cloud, prix : https://cloud.laravel.com/docs/pricing et calculateur https://app.laravel.cloud/pricing/calculator
- Laravel Cloud, bases de données (MySQL en aperçu) : https://cloud.laravel.com/docs/resources/databases
- Laravel Cloud, régions (liste d'un paquet communautaire) : https://root.packagist.org/packages/nativephp/laravel-cloud-deploy
- Railway, prix (sites tiers) : https://temps.sh/blog/railway-pricing-2026 ; sauvegardes : https://docs.railway.com/volumes/backups et https://station.railway.com/questions/hard-limit-resource-usage-backups-2ca70e7b
- DigitalOcean, prix (sites tiers) : https://www.temperstack.com/plans/digitalocean/ et https://diyai.io/ai-tools/hosting/digitalocean-pricing/
- Hetzner, hausses de 2026 : https://wz-it.com/en/blog/hetzner-price-increase-june-2026-cpx-ccx-alternatives/ et https://findstack.com/resources/hetzner-price-increase-2026 ; comptes refusés : https://lowendtalk.com/discussion/comment/2879718/
- Hostinger, prix des VPS (sites tiers) : https://hereflowai.com/hostinger-vps-pricing-2026/ , https://bearhost.com/blogs/hostinger-pricing , sans engagement : https://vpsbenchmarks.com/hosters/hostinger
- Hostinger, centre de données de Paris : https://www.hostinger.com/blog/?p=4440 ; sauvegardes des VPS : https://www.hostinger.com/support/1583232-how-to-back-up-or-restore-a-vps/ et https://hostadvice.com/blog/web-hosting/hostinger-backups/
- Hostinger mutualisé et Laravel (versions de PHP) : https://neura.market/directories/copilot/blog/devto-3792034 ; règle d'usage : https://www.websiteplanet.com/uk/blog/найкращих-веб-хостингів-для-laravel/
- Azure for Students : https://learn.microsoft.com/en-us/azure/education-hub/faq
- Oracle, ressources gratuites : https://docs.oracle.com/en-us/iaas/Content/FreeTier/resourceref.htm
- GUILAB : https://www.datacentermap.com/guinea/conakry/guilab-data-center/
- Câble ACE à Penmarc'h : https://www.lightreading.com/optical-networking/ace-subsea-cable-lands-in-france
