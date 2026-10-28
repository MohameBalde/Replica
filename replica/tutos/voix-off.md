# Voix-off des tutos (ElevenLabs)

Où : elevenlabs.io > Text to Speech. Modèle **Eleven Multilingual v2** (il respecte les balises de pause). Une seule voix française pour les 3 vidéos (bibliothèque de voix, filtre « French »). Réglages : stabilité ≈ 50 %, similarité ≈ 75 %, vitesse ≈ 0,95.

Chaque balise `<break time="1.2s" />` sépare deux scènes : le montage (`source/voiceover.py`) découpe la voix à ces pauses.

Licence : la formule gratuite d'ElevenLabs n'autorise pas l'usage commercial et demande de citer ElevenLabs. Pour des tutos qui servent à vendre Wanllan, prendre **Starter (5 $) un mois**, puis résilier.

## commerçant (13 scènes, 1040 caractères) : fichier `voix-commerçant`

```
Bienvenue sur Wanllan ! Voici comment créer votre boutique WhatsApp, en sept étapes.
<break time="1.2s" />
Étape un : créez votre compte, avec votre nom, votre numéro et un mot de passe.
<break time="1.2s" />
Étape deux : créez votre boutique, et choisissez votre lien.
<break time="1.2s" />
Étape trois : ajoutez vos produits, avec les photos, le prix en francs guinéens et le stock.
<break time="1.2s" />
Ajoutez aussi les tailles, et des suppléments comme le flocage, avec le nom et le numéro du client.
<break time="1.2s" />
Étape quatre : ajoutez votre numéro Orange Money, pour être payé.
<break time="1.2s" />
Étape cinq : partagez votre lien, et votre image pour le statut.
<break time="1.2s" />
Étape six : la commande arrive sur votre WhatsApp, avec les articles, le total, l'adresse et le point de repère.
<break time="1.2s" />
Étape sept : suivez tout depuis l'Accueil, et installez Wanllan sur votre téléphone.
<break time="1.2s" />
Répondez au client en un appui, grâce aux messages prêts.
<break time="1.2s" />
Puis mettez la commande à jour : payée, prête, livrée.
<break time="1.2s" />
Astuce : « WhatsApp non envoyé » veut dire que le client n'a pas envoyé son message. Écrivez-lui depuis la commande.
<break time="1.2s" />
À vous de vendre ! Créez votre boutique sur wanllan point com.
```

## client (12 scènes, 801 caractères) : fichier `voix-client`

```
Commander sur une boutique Wanllan, c'est simple. Suivez le guide !
<break time="1.2s" />
Ouvrez le lien de la boutique, et choisissez un produit.
<break time="1.2s" />
Choisissez la taille et les options. Pour un flocage, écrivez le nom et le numéro.
<break time="1.2s" />
Ajoutez au panier : le prix suit vos choix.
<break time="1.2s" />
Vérifiez votre panier, puis appuyez sur « Commander ».
<break time="1.2s" />
Entrez votre nom et votre numéro WhatsApp. Pas de compte à créer.
<break time="1.2s" />
Choisissez la livraison et le paiement : Orange Money, MTN, ou à la livraison.
<break time="1.2s" />
Validez votre commande.
<break time="1.2s" />
Appuyez sur « Envoyer ma commande sur WhatsApp ». Le message est déjà écrit.
<break time="1.2s" />
Très important : dans WhatsApp, appuyez sur « Envoyer ». Sinon, la boutique ne reçoit pas votre commande.
<break time="1.2s" />
Payez, puis envoyez la capture et le numéro de transaction reçu par SMS.
<break time="1.2s" />
Merci pour votre commande ! Suivez-la avec le lien reçu sur WhatsApp.
```

## équipe (8 scènes, 546 caractères) : fichier `voix-équipe`

```
Tuto équipe Wanllan : l'espace Gestion, sur téléphone.
<break time="1.2s" />
Pour donner l'accès, une seule commande sur le serveur. Rien ne se fait depuis le site.
<break time="1.2s" />
Ici, toutes les boutiques : les abonnements, les paiements à relancer, et la recherche.
<break time="1.2s" />
Pour relancer un commerçant, le message WhatsApp est prêt en un appui.
<break time="1.2s" />
Quand il paie, enregistrez la formule, la durée, et le numéro de transaction Orange Money.
<break time="1.2s" />
En cas d'abus, suspendez la boutique. Rien n'est effacé, et la réactivation est immédiate.
<break time="1.2s" />
Et tout est tracé : qui a fait quoi, et quand.
<break time="1.2s" />
Bonne gestion !
```

Total : 2387 caractères (la formule gratuite donne 10 000 crédits par mois).
