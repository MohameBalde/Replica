# Plan de lancement de Wanllan (17 octobre → 30 novembre 2026)

Rythme : environ 1 h par jour en semaine, 3 h le week-end. **Décembre réservé au projet de fin de cycle.**

## Objectifs au 30 novembre

- **20 boutiques actives** (au moins 5 produits et 1 commande reçue)
- **5 boutiques payantes** (ou fondateurs qui disent « je paierai »)
- **Un prix validé** par au moins 10 commerçants interrogés

## Semaine par semaine

| quand | quoi | qui |
| --- | --- | --- |
| **17–18 oct** (week-end) | Acheter **wanllan.com** (+ wanllan.app si possible). Réserver **@wanllan** sur TikTok, Instagram, Facebook. Créer le **WhatsApp Business « Wanllan »** (numéro support) et décider du numéro Orange Money qui reçoit les abonnements. **Créer le compte AWS** (double authentification, plan payant, alerte de budget à 10 $) | Mohamed |
| **19–25 oct** | **Interviews de 5 commerçants** (frère, sœur, Nimaga, 2 à Madina) avec l'image « app-etape-2 » et la page d'accueil : les 4 questions de prix de `pricing.md`. Noter les réponses et me les envoyer | Mohamed |
| 19–25 oct | **Mise en ligne de test** sur **AWS Lightsail Paris** (`/replica-deploy`, choix expliqué dans `replica/hosting.md`) : scripts prêts le 21, serveur créé et domaine branché le 22, tests sur téléphone du 23 au 25 | Claude + Mohamed (compte AWS) |
| **26 oct – 1er nov** | **Bêta fermée : 5 boutiques installées à la main** (Mohamed ajoute les produits et les photos avec eux). Leur lien mis dans leur statut et leur message d'accueil WhatsApp | Mohamed |
| 26 oct – 1er nov | Corrections à partir des retours de la bêta | Claude |
| **2–8 nov** | **3 vidéos TikTok Balde Media** (Monsieur Macro) : 1) « C'est combien ? » × 50 : le problème ; 2) démo 30 s : du lien à la commande WhatsApp ; 3) le paiement à la livraison sous contrôle (plafond, client bloqué). Chaque vidéo avec le lien `?src=tiktok` | Mohamed |
| **9–15 nov** | **Lancement public** : offre **« Commerçants fondateurs »** (3 mois Commerçant offerts aux 20 premiers). Tournée à Madina et au marché Niger avec le QR code imprimé et des boutiques de la bêta à montrer | Mohamed |
| 9–15 nov | Publications dans les groupes Facebook et WhatsApp de vendeurs guinéens (une démo, pas du spam ; demander l'accord des administrateurs) | Mohamed |
| **16–29 nov** | Suivi des 20 boutiques : un message WhatsApp par semaine à chacune (« combien de commandes ? qu'est-ce qui bloque ? »). Ajuster le prix si besoin (variable `.env`) | Mohamed |
| 30 nov | Bilan : boutiques actives, commandes, avis, prix. On décide de la suite pour janvier (brique 2 : caisse et stock) | Mohamed + Claude |
| **Décembre** | Pause : projet de fin de cycle. Seulement le support WhatsApp | Mohamed |

## Les 10 premiers commerçants, à contacter soi-même

1. Ton frère (PC et sacs) · 2. Ta sœur · 3. Nimaga (chaussures et habits) · 4. NimStore (toi) · 5. SoumamoShop (toi) · 6 à 10. Cinq boutiques de Madina choisies parce qu'elles vendent déjà sur WhatsApp ou Facebook.

Pour chacun : installer la boutique avec lui (20 minutes), mettre le lien dans son statut, revenir 3 jours après.

## Où sont les commerçants

Pas de Product Hunt ni de Hacker News : la cible n'y est pas. À la place : TikTok (Balde Media), groupes Facebook de vente en Guinée, groupes WhatsApp de commerçants, et le terrain (Madina, marché Niger, Kankan via le réseau de Makiti).

## Suivi

- Dans l'app : commandes par boutique, visites et ventes par source (« Partager ma boutique »).
- Erreurs : journal Laravel sur le serveur au départ ; une alerte sur le téléphone si le site ne répond plus et un outil de suivi d'erreurs gratuit, installés au déploiement (`/replica-deploy`).
- Un tableau simple (boutiques, date d'installation, nombre de commandes, payé oui/non), mis à jour chaque dimanche.

## Règles

Aucun faux avis, aucun chiffre inventé. Les témoignages des fondateurs seulement avec leur accord écrit. Le nom Take App n'apparaît dans aucune pub ni aucune vidéo.
