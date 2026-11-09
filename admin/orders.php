<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

$statuses = [
    'En attente',
    'Confirmée',
    'Expédiée',
    'Livrée',
    'Annulée'
];

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        die('Requête invalide.');
    }

    $orderId = filter_var(
        $_POST['order_id'] ?? '',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $status = $_POST['status'] ?? '';

    if ($orderId === false || !in_array($status, $statuses, true)) {

        header('Location: orders.php?error=invalid');
        exit;

    }

    // Vérifier si la commande existe
    $check = $pdo->prepare(
        "SELECT id FROM orders WHERE id = ?"
    );

    $check->execute([$orderId]);

    if (!$check->fetch()) {

        header('Location: orders.php?error=notfound');
        exit;

    }

    // Modifier le statut
    $stmt = $pdo->prepare(
        "UPDATE orders SET status = ? WHERE id = ?"
    );

    $stmt->execute([$status, $orderId]);

    header('Location: orders.php?updated=1');
    exit;
}


if (isset($_GET['updated'])) {
    $message = 'Statut modifié avec succès.';
}

if (isset($_GET['error'])) {
    $message = match ($_GET['error']) {
        'invalid' => 'Données invalides.',
        'notfound' => 'Commande introuvable.',
        default => 'Une erreur est survenue.'
    };
}

$orders = $pdo->query(
    "SELECT * FROM orders ORDER BY created_at DESC"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Commandes | WebStart Fashion</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #222;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 240px;
            height: 100vh;
            background: #171717;
            padding: 30px 18px;
            color: white;
        }

        .sidebar h2 {
            margin-bottom: 40px;
            font-size: 21px;
        }

        .sidebar a {
            display: block;
            color: #bbb;
            text-decoration: none;
            padding: 14px;
            margin-bottom: 8px;
            border-radius: 8px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #333;
            color: white;
        }

        .logout {
            color: #ff9999 !important;
            margin-top: 25px;
        }

        .main {
            margin-left: 240px;
            padding: 35px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
        }

        .header p {
            color: #777;
        }

        .panel {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .04);
        }

        .message {
            background: #e9f8ee;
            color: #18743b;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        th {
            background: #fafafa;
            color: #777;
            font-size: 13px;
        }

        .customer {
            font-weight: bold;
        }

        .details {
            color: #777;
            font-size: 13px;
            line-height: 1.7;
        }

        .status-form {
            display: flex;
            gap: 7px;
            flex-direction: column;
        }

        select {
            padding: 9px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        button {
            padding: 9px;
            border: none;
            border-radius: 6px;
            background: #171717;
            color: white;
            cursor: pointer;
        }

        .empty {
            padding: 30px;
            text-align: center;
            color: #777;
        }

        @media (max-width: 750px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

</head>

<body>

    <aside class="sidebar">

        <h2>WebStart Fashion</h2>

        <a href="dashboard.php">Dashboard</a>
        <a href="products.php">Produits</a>
        <a href="orders.php" class="active">Commandes</a>
        <a href="logout.php" class="logout">Déconnexion</a>

    </aside>

    <main class="main">

        <div class="header">
            <div>
                <h1>Gestion des commandes</h1>
                <p>Suivez et gérez les commandes de vos clients.</p>
            </div>

            <strong>Administration</strong>
        </div>

        <?php if ($message): ?>
            <div class="message">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <section class="panel">

            <h2>Liste des commandes (<?= count($orders) ?>)</h2>

            <?php if (empty($orders)): ?>

                <div class="empty">
                    Aucune commande disponible.
                </div>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Client</th>
                                <th>Adresse</th>
                                <th>Produits</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($orders as $order): ?>

                                <tr>

                                    <td>#<?= e($order['id']) ?></td>

                                    <td>
                                        <div class="customer">
                                            <?= e($order['customer_name']) ?>
                                        </div>

                                        <div class="details">
                                            <?= e($order['phone']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= e($order['address']) ?>
                                    </td>

                                    <td>
                                        <?php
                                        $itemsStmt = $pdo->prepare(
                                            "SELECT product_name, quantity, size, color
                                 FROM order_items
                                 WHERE order_id = ?"
                                        );

                                        $itemsStmt->execute([$order['id']]);
                                        $items = $itemsStmt->fetchAll();

                                        foreach ($items as $item):
                                        ?>
                                            <div class="details">
                                                <?= e($item['product_name']) ?>
                                                × <?= e($item['quantity']) ?>
                                                <br>
                                                Taille : <?= e($item['size']) ?>
                                                <br>
                                                Couleur : <?= e($item['color']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= number_format((float)$order['total'], 2) ?> DH
                                        </strong>
                                    </td>

                                    <td>
                                        <?= e($order['created_at']) ?>
                                    </td>

                                    <td>
                                        <?= e($order['status']) ?>
                                    </td>

                                    <td>

                                        <form method="POST" class="status-form">

                                            <input type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_token']) ?>">

                                            <input type="hidden"
                                                name="order_id"
                                                value="<?= e($order['id']) ?>">

                                            <select name="status">

                                                <?php foreach ($statuses as $status): ?>

                                                    <option
                                                        value="<?= e($status) ?>"
                                                        <?= $order['status'] === $status ? 'selected' : '' ?>>
                                                        <?= e($status) ?>
                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                            <button type="submit">
                                                Modifier
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    </main>

</body>

</html>