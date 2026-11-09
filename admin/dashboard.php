<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Statistiques
$totalProducts = $pdo->query(
    "SELECT COUNT(*) FROM products"
)->fetchColumn();

$totalOrders = $pdo->query(
    "SELECT COUNT(*) FROM orders"
)->fetchColumn();

$pendingOrders = $pdo->query(
    "SELECT COUNT(*) FROM orders WHERE status = 'En attente'"
)->fetchColumn();

$totalSales = $pdo->query(
    "SELECT COALESCE(SUM(total), 0)
     FROM orders WHERE status = 'Livrée'"
)->fetchColumn();

// Dernières commandes
$stmt = $pdo->query("
    SELECT
        id,
        customer_name,
        phone,
        total,
        status,
        created_at
    FROM orders
    ORDER BY created_at DESC
    LIMIT 5
");


$latestOrders = $stmt->fetchAll();
?>
<?php
if (empty($_SESSION['logout_csrf'])) {
    $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
}
?>

<html lang="fr">

<head>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard - WebStart Fashion</title>

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
            width: 230px;
            height: 100vh;
            background: #171717;
            color: white;
            padding: 25px 18px;
        }

        .sidebar h2 {
            margin-bottom: 35px;
            font-size: 22px;
        }

        .sidebar a {
            display: block;
            color: #ccc;
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

        .main {
            margin-left: 230px;
            padding: 35px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 15px;
        }

        .topbar h1 {
            margin: 0;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 35px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .04);
        }

        .card p {
            color: #777;
            margin: 0 0 15px;
        }

        .card h2 {
            margin: 0;
            font-size: 28px;
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 550px;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            color: #777;
            font-size: 13px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #eee;
            font-size: 12px;
        }

        .logout {
            margin-top: 25px;
            color: #ff9999 !important;
        }

        @media (max-width: 1000px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

    <aside class="sidebar">
        <h2>WebStart Fashion</h2>

        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="products.php">Produits</a>
        <a href="orders.php">Commandes</a>
        <form action="logout.php" method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['logout_csrf'], ENT_QUOTES, 'UTF-8') ?>">

            <button type="submit" class="logout">
                Déconnexion
            </button>
        </form>
    </aside>

    <main class="main">

        <div class="topbar">
            <div>
                <h1>Dashboard</h1>
                <p>Bienvenue, <?= e($_SESSION['admin_username']) ?> !</p>
            </div>

            <strong>Administration</strong>
        </div>

        <div class="cards">

            <div class="card">
                <p>Total des produits</p>
                <h2><?= number_format((int)$totalProducts) ?></h2>
            </div>

            <div class="card">
                <p>Total des commandes</p>
                <h2><?= number_format((int)$totalOrders) ?></h2>
            </div>

            <div class="card">
                <p>Commandes en attente</p>
                <h2><?= number_format((int)$pendingOrders) ?></h2>
            </div>

            <div class="card">
                <p>Ventes livrées</p>
                <h2><?= number_format((float)$totalSales, 2) ?> DH</h2>
            </div>

        </div>

        <section class="section">

            <h2>Dernières commandes</h2>

            <?php if (empty($recentOrders)): ?>

                <p>Aucune commande disponible.</p>

            <?php else: ?>

                <table>
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Client</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>#<?= e($order['id']) ?></td>
                                <td><?= e($order['customer_name']) ?></td>
                                <td><?= number_format((float)$order['total'], 2) ?> DH</td>
                                <td>
                                    <span class="status">
                                        <?= e($order['status']) ?>
                                    </span>
                                </td>
                                <td><?= e($order['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>

            <p>
                <a href="orders.php">Voir toutes les commandes →</a>
            </p>

        </section>

    </main>

</body>

</html>