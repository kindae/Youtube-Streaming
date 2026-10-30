# Congonet 24 + — plus.congonet24.com

Site vitrine de la chaîne YouTube **Congonet 24 +** : les vidéos se regardent directement sur le site, sans aller sur YouTube.

## Pages

| Fichier | Rôle |
|---|---|
| `index.php` | Accueil : chaîne, vidéo à la une (lecture sur place), les plus vues, liste avec catégories, tri, recherche et « Afficher plus » |
| `watch.php?v=ID` | Lecteur intégré, description (liens et horodatages cliquables), partage (WhatsApp, Facebook, X, Telegram, copier le lien), « À suivre » et lecture automatique de la vidéo suivante |
| `api.php` | Suggestions de recherche en direct (JSON) |
| `admin/youtube/` | Administration (inchangée, code d'accès déplacé dans `config/secrets.php`) |
| `youtube/index.php` | Ancienne adresse, redirige vers l'accueil |

## Installation

1. Copier `config/secrets.example.php` en `config/secrets.php` et le remplir (base de données, clé API YouTube, code admin).
2. Envoyer tous les fichiers à la racine de `plus.congonet24.com`.

`config/secrets.php` est ignoré par Git : les mots de passe ne sont jamais publiés.

Le nom, la description, le logo et le lien de la chaîne se modifient dans `includes/app.php` (`$site`).
