<?php
require_once "auth.php";
require_once "../../config/database.php";

// Récupérer l'ID
$id = intval($_GET['id'] ?? 0);

// Vérifier l'ID
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// Récupérer la vidéo
$stmt = $pdo->prepare("
    SELECT *
    FROM youtube_videos
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);
$video = $stmt->fetch();

// Vérifier si la vidéo existe
if (!$video) {
    die("Vidéo introuvable.");
}

$message = "";
$message_type = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $titre = trim($_POST["titre"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $categorie = trim($_POST["categorie"] ?? "");
    $statut = $_POST["statut"] ?? "publie";
    $vedette = isset($_POST["vedette"]) ? 1 : 0;

    // Vérification du titre
    if (empty($titre)) {
        $message = "Le titre est obligatoire.";
        $message_type = "error";
    } else {

        // Si on met cette vidéo en vedette,
        // toutes les autres vidéos ne seront plus vedettes.
        if ($vedette == 1) {
            $pdo->exec("
                UPDATE youtube_videos
                SET vedette = 0
            ");
        }

        // Mise à jour
        $update = $pdo->prepare("
            UPDATE youtube_videos
            SET
                titre = ?,
                description = ?,
                categorie = ?,
                statut = ?,
                vedette = ?
            WHERE id = ?
        ");

        $update->execute([
            $titre,
            $description,
            $categorie,
            $statut,
            $vedette,
            $id
        ]);

        $message = "Vidéo modifiée avec succès.";
        $message_type = "success";

        // Actualiser les données affichées
        $stmt = $pdo->prepare("
            SELECT *
            FROM youtube_videos
            WHERE id = ?
        ");

        $stmt->execute([$id]);
        $video = $stmt->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier la vidéo - Studio Administration</title>
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
            max-width: 680px;
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

        .preview-container {
            position: relative;
            margin-bottom: 25px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            background: #000;
        }

        .preview {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            display: block;
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

        input[type="text"],
        select,
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

        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%3C94a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
        }

        input[type="text"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
        }

        textarea {
            min-height: 140px;
            resize: vertical;
        }

        .checkbox-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--input-bg);
            padding: 16px;
            border-radius: 12px;
            border: 1px solid var(--border);
            cursor: pointer;
            user-select: none;
            transition: border-color 0.2s;
        }

        .checkbox-card:hover {
            border-color: #3b82f6;
        }

        .checkbox-card input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #ff0055;
            cursor: pointer;
        }

        .checkbox-card span {
            font-weight: 500;
            font-size: 0.95rem;
            color: var(--text-main);
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
        <h1>✏️ Modifier la vidéo</h1>

        <?php if (!empty($message)): ?>
            <div class="alert <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="preview-container">
            <img
                src="<?= htmlspecialchars($video["miniature"]) ?>"
                class="preview"
                alt="Miniature de la vidéo"
            >
        </div>

        <form method="POST">

            <div class="form-group">
                <label>Titre</label>
                <input
                    type="text"
                    name="titre"
                    value="<?= htmlspecialchars($video["titre"]) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description"><?= htmlspecialchars($video["description"]) ?></textarea>
            </div>

            <div class="form-group">
                <label>Catégorie</label>
                <input
                    type="text"
                    name="categorie"
                    value="<?= htmlspecialchars($video["categorie"]) ?>"
                    placeholder="Actualité, Sport, Éducation..."
                >
            </div>

            <div class="form-group">
                <label>Statut</label>
                <select name="statut">
                    <option value="publie" <?= $video["statut"] === "publie" ? "selected" : "" ?>>
                        Publié
                    </option>
                    <option value="brouillon" <?= $video["statut"] === "brouillon" ? "selected" : "" ?>>
                        Brouillon
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label class="checkbox-card">
                    <input
                        type="checkbox"
                        name="vedette"
                        value="1"
                        <?= $video["vedette"] == 1 ? "checked" : "" ?>
                    >
                    <span>⭐ Mettre cette vidéo à la une</span>
                </label>
            </div>

            <button type="submit">💾 Enregistrer les modifications</button>

        </form>
    </div>

</div>

</body>

</html>
