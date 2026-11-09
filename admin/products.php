<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        die('Requête invalide.');
    }

    $action = $_POST['action'] ?? '';

    // AJOUT / MODIFICATION
    if ($action === 'add' || $action === 'update') {

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
        $image = trim($_POST['image'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $stock = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);

        $imageIsValid = true;

        if ($image !== '') {
            $scheme = strtolower(parse_url($image, PHP_URL_SCHEME) ?? '');

            $imageIsValid =
                filter_var($image, FILTER_VALIDATE_URL) !== false
                && in_array($scheme, ['http', 'https'], true);
        }

        if (
            $name === '' ||
            $category === '' ||
            $price === false ||
            $price < 0 ||
            $stock === false ||
            $stock < 0 ||
            strlen($name) > 255 ||
            strlen($category) > 100 ||
            strlen($description) > 5000 ||
            strlen($image) > 2048 ||
            !$imageIsValid
        ) {
            $message = 'Veuillez vérifier les informations du produit.';
        } else {

            if ($action === 'add') {

                $stmt = $pdo->prepare(
                    "INSERT INTO products
                    (name, description, price, image, category, stock)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $name,
                    $description,
                    $price,
                    $image,
                    $category,
                    $stock
                ]);

                $message = 'Produit ajouté avec succès.';

            } else {

                $id = filter_var(
                    $_POST['id'] ?? '',
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if ($id === false) {
                    $message = 'Identifiant invalide.';
                } else {

                    $check = $pdo->prepare(
                        "SELECT id FROM products WHERE id = ?"
                    );
                    $check->execute([$id]);

                    if (!$check->fetch()) {
                        $message = 'Produit introuvable.';
                    } else {

                        $stmt = $pdo->prepare(
                            "UPDATE products
                             SET name=?, description=?, price=?,
                                 image=?, category=?, stock=?
                             WHERE id=?"
                        );

                        $stmt->execute([
                            $name,
                            $description,
                            $price,
                            $image,
                            $category,
                            $stock,
                            $id
                        ]);

                        $message = 'Produit modifié avec succès.';
                    }
                }
            }
        }
    }

    // SUPPRESSION
    elseif ($action === 'delete') {

        $id = filter_var(
            $_POST['id'] ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            $message = 'Identifiant invalide.';
        } else {

            $check = $pdo->prepare(
                "SELECT id FROM products WHERE id = ?"
            );
            $check->execute([$id]);

            if (!$check->fetch()) {
                $message = 'Produit introuvable.';
            } else {

                try {
                    $stmt = $pdo->prepare(
                        "DELETE FROM products WHERE id = ?"
                    );
                    $stmt->execute([$id]);

                    $message = 'Produit supprimé avec succès.';

                } catch (PDOException $error) {
                    $message = 'Impossible de supprimer ce produit.';
                }
            }
        }
    }
}

$editProduct = null;

if (isset($_GET['edit'])) {
    $id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $editProduct = $stmt->fetch();
    }
}

$products = $pdo->query(
    "SELECT * FROM products ORDER BY id DESC"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Produits | WebStart Fashion</title>

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
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .04);
        }

        .panel h2 {
            margin-top: 0;
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font-size: 14px;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #333;
        }

        button {
            border: none;
            padding: 11px 17px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
        }

        .primary-btn {
            background: #171717;
            color: white;
        }

        .secondary-btn {
            background: #eee;
            color: #222;
            text-decoration: none;
            padding: 11px 17px;
            border-radius: 7px;
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
            min-width: 650px;
            border-collapse: collapse;
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
            background: #fafafa;
        }

        .stock {
            background: #e9f8ee;
            color: #18743b;
            padding: 6px 10px;
            border-radius: 20px;
        }

        .edit-btn {
            background: #eee;
            color: #222;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            display: inline-block;
            margin-bottom: 6px;
        }

        .delete-btn {
            background: #ffe9e9;
            color: #c0392b;
        }

        .logout {
            color: #ff9999 !important;
            margin-top: 25px;
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
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
        <a href="products.php" class="active">Produits</a>
        <a href="orders.php">Commandes</a>
        <a href="logout.php" class="logout">Déconnexion</a>
    </aside>

    <main class="main">

        <div class="header">
            <div>
                <h1>Gestion des produits</h1>
                <p>Gérez votre catalogue de vêtements.</p>
            </div>
            <strong>Administration</strong>
        </div>

        <?php if ($message): ?>
            <div class="message"><?= e($message) ?></div>
        <?php endif; ?>

        <section class="panel">

            <h2>
                <?= $editProduct ? 'Modifier le produit' : 'Ajouter un produit' ?>
            </h2>

            <form method="POST">

                <input type="hidden" name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>">

                <input type="hidden" name="action"
                    value="<?= $editProduct ? 'update' : 'add' ?>">

                <?php if ($editProduct): ?>
                    <input type="hidden" name="id"
                        value="<?= e($editProduct['id']) ?>">
                <?php endif; ?>

                <div class="form-grid">

                    <div class="field">
                        <label>Nom du produit</label>
                        <input type="text" name="name"
                            placeholder="Ex: T-shirt Basic"
                            value="<?= e($editProduct['name'] ?? '') ?>"
                            required>
                    </div>

                    <div class="field">
                        <label>Catégorie</label>
                        <input type="text" name="category"
                            placeholder="Ex: T-shirts"
                            value="<?= e($editProduct['category'] ?? '') ?>"
                            required>
                    </div>

                    <div class="field">
                        <label>Prix (DH)</label>
                        <input type="number" name="price"
                            min="0" step="0.01"
                            value="<?= e($editProduct['price'] ?? '') ?>"
                            required>
                    </div>

                    <div class="field">
                        <label>Stock</label>
                        <input type="number" name="stock"
                            min="0"
                            value="<?= e($editProduct['stock'] ?? '') ?>"
                            required>
                    </div>

                    <div class="field full">
                        <label>URL de l'image</label>
                        <input type="text" name="image"
                            placeholder="https://..."
                            value="<?= e($editProduct['image'] ?? '') ?>">
                    </div>

                    <div class="field full">
                        <label>Description</label>
                        <textarea name="description"
                            placeholder="Description du produit..."><?= e($editProduct['description'] ?? '') ?></textarea>
                    </div>

                </div>

                <br>

                <button type="submit" class="primary-btn">
                    <?= $editProduct ? 'Enregistrer les modifications' : '+ Ajouter le produit' ?>
                </button>

                <?php if ($editProduct): ?>
                    <a href="products.php" class="secondary-btn">Annuler</a>
                <?php endif; ?>

            </form>

        </section>

        <section class="panel">

            <h2>Liste des produits (<?= count($products) ?>)</h2>

            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Prix</th>
                            <th>Catégorie</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>#<?= e($product['id']) ?></td>
                                <td><?= e($product['name']) ?></td>
                                <td><?= e($product['price']) ?> DH</td>
                                <td><?= e($product['category']) ?></td>
                                <td>
                                    <span class="stock">
                                        <?= e($product['stock']) ?>
                                    </span>
                                </td>

                                <td>
                                    <a class="edit-btn"
                                        href="products.php?edit=<?= e($product['id']) ?>">
                                        Modifier
                                    </a>

                                    <form method="POST"
                                        onsubmit="return confirm('Supprimer ce produit ?');">

                                        <input type="hidden" name="csrf_token"
                                            value="<?= e($_SESSION['csrf_token']) ?>">

                                        <input type="hidden" name="action"
                                            value="delete">

                                        <input type="hidden" name="id"
                                            value="<?= e($product['id']) ?>">

                                        <button type="submit" class="delete-btn">
                                            Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        </section>

    </main>

</body>

</html>