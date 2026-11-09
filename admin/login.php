<?php

require_once __DIR__ . '/session.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../config/database.php';

$error = '';

// CSRF Token
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['login_csrf'], $token)) {

        $error = 'Requête invalide.';
    } else {

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Vérifier les tentatives précédentes
        $stmt = $pdo->prepare(
            "SELECT attempts, last_attempt
             FROM login_attempts
             WHERE username = ? AND ip_address = ?"
        );

        $stmt->execute([$username, $ipAddress]);

        $attempt = $stmt->fetch();

        $isLocked = false;

        if ($attempt && (int)$attempt['attempts'] >= 5) {

            $elapsed = time() - strtotime($attempt['last_attempt']);

            if ($elapsed < 900) {

                $isLocked = true;
            } else {

                $stmt = $pdo->prepare(
                    "DELETE FROM login_attempts
                     WHERE username = ? AND ip_address = ?"
                );

                $stmt->execute([$username, $ipAddress]);
            }
        }

        if ($isLocked) {

            $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
        } else {

            // Vérifier les identifiants
            $stmt = $pdo->prepare(
                "SELECT id, username, password
                 FROM admins
                 WHERE username = ?"
            );

            $stmt->execute([$username]);

            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {

                // Réinitialiser les tentatives
                $stmt = $pdo->prepare(
                    "DELETE FROM login_attempts
                     WHERE username = ? AND ip_address = ?"
                );

                $stmt->execute([$username, $ipAddress]);

                // Nouvelle session
                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];

                unset($_SESSION['login_csrf']);

                header('Location: dashboard.php');
                exit;
            } else {

                // Enregistrer une tentative échouée
                $stmt = $pdo->prepare(
                    "INSERT INTO login_attempts
     (username, ip_address, attempts, last_attempt)
     VALUES (?, ?, 1, NOW())
     ON DUPLICATE KEY UPDATE
     attempts = attempts + 1,
     last_attempt = NOW()"
                );

                $stmt->execute([$username, $ipAddress]);

                // Récupérer le nombre de tentatives
                $stmt = $pdo->prepare(
                    "SELECT attempts
     FROM login_attempts
     WHERE username = ? AND ip_address = ?"
                );

                $stmt->execute([$username, $ipAddress]);

                $attempts = (int) $stmt->fetchColumn();

                if ($attempts >= 5) {
                    $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
                } else {
                    $error = 'Identifiants incorrects.';
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - WebStart Fashion</title>
</head>

<body>

    <h1>WebStart Fashion</h1>
    <h2>Connexion Admin</h2>

    <?php if ($error): ?>
        <p style="color:red;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8') ?>">

        <input
            type="text"
            name="username"
            placeholder="Username"
            required>

        <br><br>

        <input
            type="password"
            name="password"
            placeholder="Password"
            required>

        <br><br>

        <button type="submit">Se connecter</button>

    </form>

</body>

</html>