<?php
require_once "auth.php";
require_once "../../config/database.php";

$message = "";
$message_type = ""; // Permet de distinguer succès ou erreur

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $lien = trim($_POST["lien"] ?? "");
    $titre = trim($_POST["titre"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $categorie = trim($_POST["categorie"] ?? "");

    if (empty($lien) || empty($titre)) {
        $message = "Veuillez remplir tous les champs obligatoires.";
        $message_type = "error";
    } else {

        // Extraction de l'ID YouTube
        $youtube_id = "";

        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
            '/youtu\.be\/([a-zA-Z0-9_-]+)/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
            '/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lien, $matches)) {
                $youtube_id = $matches[1];
                break;
            }
        }

        if (empty($youtube_id)) {
            $message = "Le lien YouTube est invalide.";
            $message_type = "error";
        } else {

            // Création automatique de la miniature
            $miniature = "https://img.youtube.com/vi/" . $youtube_id . "/maxresdefault.jpg";

            // Vérifier si la vidéo existe déjà
            $check = $pdo->prepare("SELECT id FROM youtube_videos WHERE youtube_id = ?");
            $check->execute([$youtube_id]);

            if ($check->fetch()) {
                $message = "Cette vidéo existe déjà dans la base de données.";
                $message_type = "error";
            } else {

                // Ajouter la vidéo
                $sql = "
                    INSERT INTO youtube_videos
                    (youtube_id, titre, description, miniature, categorie)
                    VALUES (?, ?, ?, ?, ?)
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $youtube_id,
                    $titre,
                    $description,
                    $miniature,
                    $categorie
                ]);

                $message = "Vidéo ajoutée avec succès !";
                $message_type = "success";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une vidéo - Studio Administration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: #151c2c;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #242f47;
            --input-bg: #0f172a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .container {
            width: 100%;
            max-width: 650px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 20px;
            transition: color 0.2s;
        }

        .btn-back:hover {
            color: var(--text-main);
        }

        .card {
            background: var(--card-bg);
            padding: 35px;
            border-radius: 20px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .card h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .alert.error {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert.success {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #cbd5e1;
        }

        label span {
            color: #ef4444;
        }

        input[type="url"],
        input[type="text"],
        textarea {
            width: 100%;
            padding: 14px 16px;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input[type="url"]:focus,
        input[type="text"]:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        button[type="submit"] {
            width: 100%;
            padding: 15px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            margin-top: 10px;
        }

        button[type="submit"]:hover {
            background: var(--accent-hover);
        }

        button[type="submit"]:active {
            transform: scale(0.99);
        }
    </style>
</head>

<body>

<div class="container">

    <a href="index.php" class="btn-back">← Retour au tableau de bord</a>

    <div class="card">
        <h1>➕ Ajouter une vidéo</h1>

        <?php if (!empty($message)): ?>
            <div class="alert <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="form-group">
                <label>Lien YouTube <span>*</span></label>
                <input
                    type="url"
                    name="lien"
                    placeholder="https://www.youtube.com/watch?v=..."
                    required
                >
            </div>

            <div class="form-group">
                <label>Titre <span>*</span></label>
                <input
                    type="text"
                    name="titre"
                    placeholder="Titre explicatif de la vidéo"
                    required
                >
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea
                    name="description"
                    placeholder="Résumez le contenu de la vidéo..."
                ></textarea>
            </div>

            <div class="form-group">
                <label>Catégorie</label>
                <input
                    type="text"
                    name="categorie"
                    placeholder="Exemple : Actualité, Gaming, Tutoriel..."
                >
            </div>

            <button type="submit">🚀 Publier la vidéo</button>

        </form>
    </div>

</div>

</body>

</html>
