
<?php
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function sendJson(int $status, array $data): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

// 1. Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    sendJson(405, [
        'success' => false,
        'error' => 'Méthode non autorisée.'
    ]);
}

// 2. Lire et vérifier le JSON
try {
    $data = json_decode(
        file_get_contents('php://input'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
} catch (JsonException $e) {
    sendJson(400, [
        'success' => false,
        'error' => 'Format JSON invalide.'
    ]);
}

if (!is_array($data)) {
    sendJson(400, [
        'success' => false,
        'error' => 'Données invalides.'
    ]);
}

// 3. Vérifier les informations du client
$name = trim($data['customer_name'] ?? '');
$phone = trim($data['phone'] ?? '');
$address = trim($data['address'] ?? '');

if (
    !is_string($name) ||
    !is_string($phone) ||
    !is_string($address)
) {
    sendJson(400, [
        'success' => false,
        'error' => 'Informations client invalides.'
    ]);
}

if (
    strlen($name) < 2 ||
    strlen($name) > 100 ||
    strlen($address) < 5 ||
    strlen($address) > 500
) {
    sendJson(400, [
        'success' => false,
        'error' => 'Nom ou adresse invalide.'
    ]);
}

$digits = preg_replace('/\D/', '', $phone);

if (
    !preg_match('/^[0-9+\s().-]{8,20}$/', $phone) ||
    strlen($digits) < 8 ||
    strlen($digits) > 15
) {
    sendJson(400, [
        'success' => false,
        'error' => 'Numéro de téléphone invalide.'
    ]);
}

// 4. Vérifier les produits
$items = $data['items'] ?? null;

if (
    !is_array($items) ||
    count($items) < 1 ||
    count($items) > 30
) {
    sendJson(400, [
        'success' => false,
        'error' => 'Liste de produits invalide.'
    ]);
}

$orderItems = [];

try {

    $pdo->beginTransaction();

    $totalCents = 0;

    foreach ($items as $item) {

        if (!is_array($item)) {
            throw new InvalidArgumentException(
                'Produit invalide.'
            );
        }

        $productId = filter_var(
            $item['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $quantity = filter_var(
            $item['quantity'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $productId === false ||
            $productId === null ||
            $productId < 1 ||
            $quantity === false ||
            $quantity === null ||
            $quantity < 1 ||
            $quantity > 50
        ) {
            throw new InvalidArgumentException(
                'Produit ou quantité invalide.'
            );
        }

        // Vérifier taille et couleur
        $size = $item['size'] ?? null;
        $color = $item['color'] ?? null;

        if (
            ($size !== null && !is_string($size)) ||
            ($color !== null && !is_string($color))
        ) {
            throw new InvalidArgumentException(
                'Options du produit invalides.'
            );
        }

        $size = $size !== null ? trim($size) : null;
        $color = $color !== null ? trim($color) : null;

        if (
            ($size !== null && strlen($size) > 30) ||
            ($color !== null && strlen($color) > 30)
        ) {
            throw new InvalidArgumentException(
                'Options du produit trop longues.'
            );
        }

        // Récupérer le produit et verrouiller sa ligne
        $stmt = $pdo->prepare(
            "SELECT id, name, price, stock
             FROM products
             WHERE id = ?
             FOR UPDATE"
        );

        $stmt->execute([$productId]);

        $product = $stmt->fetch();

        if (!$product) {
            throw new InvalidArgumentException(
                'Produit introuvable.'
            );
        }

        if ((int)$product['stock'] < $quantity) {
            throw new DomainException(
                'Stock insuffisant pour : ' . $product['name']
            );
        }

        // Calculer le prix en centimes
        $priceCents = (int) round(
            (float)$product['price'] * 100
        );

        $totalCents += $priceCents * $quantity;

        // Décrémenter le stock immédiatement.
        // Cela protège aussi contre les produits répétés
        // dans plusieurs lignes de la même commande.
        $updateStock = $pdo->prepare(
            "UPDATE products
             SET stock = stock - ?
             WHERE id = ? AND stock >= ?"
        );

        $updateStock->execute([
            $quantity,
            $productId,
            $quantity
        ]);

        if ($updateStock->rowCount() !== 1) {
            throw new DomainException(
                'Stock insuffisant.'
            );
        }

        $orderItems[] = [
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity,
            'size' => $size,
            'color' => $color
        ];
    }

    // 5. Enregistrer la commande
    $total = $totalCents / 100;

    $stmt = $pdo->prepare(
        "INSERT INTO orders
        (customer_name, phone, address, total)
        VALUES (?, ?, ?, ?)"
    );

    $stmt->execute([
        $name,
        $phone,
        $address,
        $total
    ]);

    $orderId = $pdo->lastInsertId();

    // 6. Enregistrer les détails de la commande
    $stmt = $pdo->prepare(
        "INSERT INTO order_items
        (order_id, product_id, product_name,
         price, quantity, size, color)
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($orderItems as $item) {

        $stmt->execute([
            $orderId,
            $item['product_id'],
            $item['product_name'],
            $item['price'],
            $item['quantity'],
            $item['size'],
            $item['color']
        ]);
    }

    // 7. Valider la transaction
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);

    if ($contentLength > 100000) {
        sendJson(413, [
            'success' => false,
            'error' => 'Requête trop volumineuse.'
        ]);
    }
    $pdo->commit();

    sendJson(200, [
        'success' => true,
        'message' => 'Commande enregistrée avec succès.',
        'order_id' => $orderId,
        'total' => $total
    ]);
} catch (DomainException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendJson(409, [
        'success' => false,
        'error' => 'Stock insuffisant ou produit indisponible.'
    ]);
} catch (InvalidArgumentException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendJson(400, [
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // En production, enregistrer l'erreur dans un journal privé.
    error_log($e->getMessage());

    sendJson(500, [
        'success' => false,
        'error' => 'Une erreur est survenue. Veuillez réessayer.'
    ]);
}
