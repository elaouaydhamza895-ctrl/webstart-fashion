<?php
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'error' => 'Méthode non autorisée.'
    ]);

    exit;
}

try {

    $stmt = $pdo->query("
        SELECT
            id,
            name,
            description,
            price,
            image,
            category,
            stock
        FROM products
        ORDER BY created_at DESC
    ");

    $products = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'products' => $products
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Impossible de récupérer les produits.'
    ]);
}