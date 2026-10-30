<?php
require_once "auth.php";
require_once "../../config/database.php";

// =====================================================
// CODE D'ACCÈS
// =====================================================
$code_acces = ADMIN_CODE;

// =====================================================
// DÉCONNEXION
// =====================================================
if (isset($_GET['logout'])) {
    unset($_SESSION['youtube_admin']);
    header("Location: index.php");
    exit;
}

// =====================================================
// VÉRIFICATION DU CODE
// =====================================================
$erreur = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $code = trim($_POST["code"] ?? "");

    if ($code_acces !== "" && hash_equals($code_acces, $code)) {
        $_SESSION["youtube_admin"] = true;
        header("Location: index.php");
        exit;
    } else {
        $erreur = "Code d'accès incorrect.";
    }
}

// =====================================================
// SI PAS CONNECTÉ : AFFICHER LA PAGE DE CONNEXION
// =====================================================
if (!isset($_SESSION["youtube_admin"]) || $_SESSION["youtube_admin"] !== true) {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès Studio Administration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --accent: #ff0055;
            --accent-hover: #ff2a70;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            background-image: radial-gradient(circle at 50% 0%, #1e1b4b 0%, var(--bg-color) 70%);
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            background: var(--card-bg);
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border);
            text-align: center;
        }

        .login-box h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff 0%, #a5f3fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-box p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 25px;
        }

        input {
            width: 100%;
            padding: 16px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 18px;
            color: var(--text-main);
            text-align: center;
            letter-spacing: 4px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(255, 0, 85, 0.2);
        }

        button {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            background: var(--accent);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }

        button:hover {
            background: var(--accent-hover);
        }

        button:active {
            transform: scale(0.98);
        }

        .erreur {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h1>⚡ Studio Admin</h1>
    <p>Accès restreint, entrez votre code</p>

    <?php if (!empty($erreur)): ?>
        <div class="erreur">
            <?= htmlspecialchars($erreur) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input
            type="password"
            name="code"
            placeholder="••••"
            inputmode="numeric"
            autocomplete="off"
            required
        >
        <button type="submit">⚡ Déverrouiller</button>
    </form>
</div>

</body>
</html>
<?php
    exit;
}

// =====================================================
// À PARTIR D'ICI : ADMINISTRATION PROTÉGÉE
// =====================================================

// Statistiques
$stmt = $pdo->query("SELECT COUNT(*) FROM youtube_videos WHERE statut = 'publie'");
$total_videos = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(vues_youtube), 0) FROM youtube_videos WHERE statut = 'publie'");
$total_vues = $stmt->fetchColumn();

// Vidéo à la une
$stmt = $pdo->query("SELECT * FROM youtube_videos WHERE vedette = 1 AND statut = 'publie' ORDER BY id DESC LIMIT 1");
$video_vedette = $stmt->fetch();

// Liste des vidéos
$stmt = $pdo->query("SELECT * FROM youtube_videos ORDER BY id DESC");
$videos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administration YouTube</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: #151c2c;
            --accent: #ff0055;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #242f47;
            --primary-btn: #2563eb;
            --primary-btn-hover: #1d4ed8;
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
            padding: 30px 20px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            background: var(--card-bg);
            padding: 20px 30px;
            border-radius: 16px;
            border: 1px solid var(--border);
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logout {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .logout:hover {
            background: #ef4444;
            color: white;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 35px;
        }

        .card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        .stats .card h2 {
            font-size: 2.2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 5px;
        }

        .stats .card p {
            color: var(--text-muted);
            font-weight: 500;
        }

        .featured-card {
            margin-bottom: 35px;
            position: relative;
            overflow: hidden;
        }

        .featured-card h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .featured-content {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .featured-content img {
            width: 220px;
            aspect-ratio: 16/9;
            object-fit: cover;
            border-radius: 10px;
        }

        .featured-content h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-btn);
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-add:hover {
            background: var(--primary-btn-hover);
        }

        .video-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .video {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 20px;
            background: var(--card-bg);
            padding: 18px;
            border-radius: 16px;
            border: 1px solid var(--border);
            transition: border-color 0.2s;
        }

        .video:hover {
            border-color: #3b82f6;
        }

        .video img {
            width: 100%;
            aspect-ratio: 16/9;
            object-fit: cover;
            border-radius: 10px;
        }

        .video-info {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .video-info h3 {
            font-size: 1.1rem;
            margin-bottom: 8px;

        }

        .meta {
            display: flex;
            gap: 15px;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-publie { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .badge-brouillon { background: rgba(234, 179, 8, 0.15); color: #facc15; }
        .badge-vedette { background: rgba(255, 0, 85, 0.15); color: #ff0055; border: 1px solid rgba(255, 0, 85, 0.3); }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .actions a {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 14px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            background: #1e293b;
            color: #cbd5e1;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .actions a:hover {
            background: #334155;
            color: #fff;
        }

        .actions a.delete:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.4);
        }

        @media(max-width:768px) {
            .stats { grid-template-columns: 1fr; }
            .video { grid-template-columns: 1fr; }
            .header { flex-direction: column; gap: 15px; text-align: center; }
            .featured-content { flex-direction: column; align-items: flex-start; }
            .featured-content img { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="header">
        <h1>🎬 Studio Dashboard</h1>
        <a href="?logout=1" class="logout">Déconnexion</a>
    </div>

    <!-- STATISTIQUES -->
    <div class="stats">
        <div class="card">
            <h2><?= number_format($total_videos) ?></h2>
            <p>Vidéos publiées</p>
        </div>
        <div class="card">
            <h2><?= number_format($total_vues, 0, ',', ' ') ?></h2>
            <p>Vues totales</p>
        </div>
    </div>

    <!-- VIDÉO À LA UNE -->
    <div class="card featured-card">
        <h2>⭐ Vidéo à la une</h2>
        <?php if ($video_vedette): ?>
            <div class="featured-content">
                <img src="<?= htmlspecialchars($video_vedette['miniature']) ?>" alt="Miniature Vedette">
                <div>
                    <h3><?= htmlspecialchars($video_vedette['titre']) ?></h3>
                </div>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted);">Aucune vidéo actuellement mise en vedette.</p>
        <?php endif; ?>
    </div>

    <div class="section-header">
        <h2>Toutes les vidéos</h2>
        <a href="ajouter.php" class="btn-add">➕ Ajouter une vidéo</a>
    </div>

    <!-- LISTE DES VIDÉOS -->
    <div class="video-list">
        <?php foreach ($videos as $video): ?>
            <div class="video">
                <div>
                    <img src="<?= htmlspecialchars($video['miniature']) ?>" alt="Miniature">
                </div>
                <div class="video-info">
                    <div>
                        <h3><?= htmlspecialchars($video['titre']) ?></h3>
                        <div class="meta">
                            <span>👁️ <?= number_format($video['vues_youtube'], 0, ',', ' ') ?> vues</span>
                            
                            <span class="badge <?= $video['statut'] === 'publie' ? 'badge-publie' : 'badge-brouillon' ?>">
                                <?= htmlspecialchars($video['statut']) ?>
                            </span>

                            <?php if ($video['vedette'] == 1): ?>
                                <span class="badge badge-vedette">★ Vedette</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="actions">
                        <a href="modifier.php?id=<?= $video['id'] ?>">✏️ Modifier</a>
                        <a href="vedette.php?id=<?= $video['id'] ?>">⭐ Mettre en vedette</a>
                        <a href="supprimer.php?id=<?= $video['id'] ?>" class="delete" onclick="return confirm('Voulez-vous vraiment supprimer cette vidéo ?');">🗑️ Supprimer</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

</body>
</html>
