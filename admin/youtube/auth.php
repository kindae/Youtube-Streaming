<?php
/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION DE L'ADMINISTRATION
|--------------------------------------------------------------------------
| Code d'accès : config/secrets.php => 'admin_code'
| 5 essais ratés => blocage de 10 minutes pour l'adresse IP
*/
require_once __DIR__ . "/../../config/secrets_loader.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');

// Déconnexion
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit;
}

// Déjà connecté (session expirée après 12 h d'inactivité)
if (!empty($_SESSION['youtube_admin']) && (time() - ($_SESSION['admin_activite'] ?? 0)) < 43200) {
    $_SESSION['admin_activite'] = time();
    return;
}
unset($_SESSION['youtube_admin']);

/*
| Limitation des tentatives
*/
$fichier_essais = sys_get_temp_dir() . '/congonet24_admin_' . md5($_SERVER['REMOTE_ADDR'] ?? 'cli') . '.json';
$essais = is_file($fichier_essais) ? (json_decode((string) file_get_contents($fichier_essais), true) ?: []) : [];
$essais = array_values(array_filter($essais, function ($t) { return $t > time() - 600; }));
$bloque = count($essais) >= 5;

$erreur = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['code_acces'])) {
    $code = trim((string) $_POST['code_acces']);

    if ($bloque) {
        $erreur = "Trop de tentatives. Réessayez dans quelques minutes.";
    } elseif (ADMIN_CODE !== "" && hash_equals(ADMIN_CODE, $code)) {
        session_regenerate_id(true);
        $_SESSION['youtube_admin'] = true;
        $_SESSION['admin_activite'] = time();
        @unlink($fichier_essais);
        header("Location: " . ($_SERVER['REQUEST_URI'] ?? 'index.php'));
        exit;
    } else {
        $essais[] = time();
        @file_put_contents($fichier_essais, json_encode($essais));
        $restants = max(0, 5 - count($essais));
        $erreur = $restants > 0
            ? "Code incorrect. Encore $restants essai" . ($restants > 1 ? "s" : "") . "."
            : "Trop de tentatives. Réessayez dans 10 minutes.";
        usleep(400000);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion – Studio Congonet 24 +</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css?v=1">
</head>
<body class="login-page">
    <form method="post" class="login-card" autocomplete="off">
        <img src="../../auto.jpg" alt="" class="login-logo">
        <h1>Studio Congonet 24 +</h1>
        <p class="muted">Entrez votre code d'accès pour gérer les vidéos.</p>

        <?php if ($erreur): ?>
            <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <label class="field">
            <span>Code d'accès</span>
            <input type="password" name="code_acces" inputmode="numeric" required autofocus <?= $bloque ? 'disabled' : '' ?>>
        </label>
        <button type="submit" class="btn btn-primary btn-block" <?= $bloque ? 'disabled' : '' ?>>Se connecter</button>
        <a href="../../index.php" class="login-back">← Retour au site</a>
    </form>
</body>
</html>
<?php
exit;
