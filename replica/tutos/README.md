# Tutos vidéo Wanllan (motion design)

Vidéos verticales 1080 × 1920 (statut WhatsApp, TikTok), en français, avec voix-off (texte : `voix-off.md`) :

| Fichier | Pour qui | Durée |
| --- | --- | --- |
| `wanllan-tuto-commercant.mp4` | commerçant / vendeur : compte, boutique, produits et flocage, Orange Money, partage, commande reçue, suivi | 76 s |
| `wanllan-tuto-client.mp4` | client (à partager par le commerçant) : produit, panier, commande, envoi WhatsApp, paiement | 57 s |
| `wanllan-tuto-equipe.mp4` | équipe Wanllan : accès Gestion, relance, paiement d'abonnement, suspension, historique | 41 s |

Écrans réels de l'app, sur la base de démonstration (noms et numéros fictifs).

## Refaire les vidéos (après un changement d'écran)

1. Base de démo + serveur local (port 8130), compte démo 620 00 00 00 / password avec accès Gestion.
2. `node source/capture2.mjs <dossier>` : captures 390 × 844 et position des boutons (`boxes.json`).
3. `node source/render.mjs source/commercant.json <dossier> sortie.mp4` (idem `client`, `equipe`). Le texte de chaque étape est dans les fichiers JSON.

Voix-off : `python3 source/align.py voix-off.md <section> <mots.json> <repères.json>` (transcription mot à mot avec faster-whisper), puis `python3 source/voiceover.py source/<vidéo>.json <voix.wav> <dossier> sortie.mp4 <repères.json>` : chaque scène dure le temps de sa phrase.

Rendu image par image (30 images/s) avec Chromium, puis assemblage avec ffmpeg.
