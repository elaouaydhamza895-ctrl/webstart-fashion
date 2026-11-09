<?php

require_once __DIR__ . '/auth.php';

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

// Vérifier CSRF Token
$token = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['logout_csrf']) ||
    !hash_equals($_SESSION['logout_csrf'], $token)
) {
    http_response_code(403);
    exit('Requête invalide.');
}

// Supprimer les données de session
$_SESSION = [];

// Supprimer le cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax'
    ]);
}

session_destroy();

header('Location: login.php');
exit;