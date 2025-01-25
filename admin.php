<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = 'localhost';
$db   = 'cybercoin_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Passphrase file
$passphrase_file = 'admin_passphrase.php';

// Functions
function is_admin_authenticated() {
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

function is_passphrase_set() {
    global $passphrase_file;
    return file_exists($passphrase_file);
}

function set_passphrase($passphrase) {
    global $passphrase_file;
    $hashed_passphrase = password_hash($passphrase, PASSWORD_DEFAULT);
    $content = "<?php\n\$correct_passphrase_hash = '$hashed_passphrase';\n";
    return file_put_contents($passphrase_file, $content) !== false;
}

function get_passphrase_hash() {
    global $passphrase_file;
    if (is_passphrase_set()) {
        require $passphrase_file;
        return $correct_passphrase_hash ?? null;
    }
    return null;
}

function getProducts() {
    global $pdo;
    return $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
}

function getTransactions($limit = 10) {
    global $pdo;
    $query = "SELECT t.id, t.from_address, t.to_address, t.amount, t.created_at 
              FROM transactions t 
              ORDER BY t.created_at DESC LIMIT :limit";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getWebsiteStats() {
    global $pdo;
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_transactions' => $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
        'total_revenue' => $pdo->query("SELECT COALESCE(SUM(p.price), 0) FROM purchases pu JOIN products p ON pu.product_id = p.id")->fetchColumn(),
        'total_products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn()
    ];
    return $stats;
}

function getShopWallet() {
    global $pdo;
    $walletFile = 'shop_wallet.txt';
    try {
        if (file_exists($walletFile)) {
            $address = trim(file_get_contents($walletFile));
        } else {
            $address = '';
        }

        if ($address) {
            $stmt = $pdo->prepare("SELECT balance FROM user_balances WHERE wallet_address = ?");
            $stmt->execute([$address]);
            $balance = $stmt->fetchColumn();
        } else {
            $balance = 0;
        }

        return [
            'address' => $address ?: 'Not set',
            'balance' => $balance ?: 0
        ];
    } catch (Exception $e) {
        error_log("Error getting shop wallet: " . $e->getMessage());
        return [
            'address' => 'Error retrieving address',
            'balance' => 'Error retrieving balance'
        ];
    }
}

function setShopWalletAddress($address) {
    $walletFile = 'shop_wallet.txt';
    try {
        if (file_put_contents($walletFile, $address) !== false) {
            global $pdo;
            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES ('shop_wallet_address', :address) ON DUPLICATE KEY UPDATE `value` = :address");
            $stmt->execute([':address' => $address]);
            return true;
        } else {
            throw new Exception("Failed to write to wallet file");
        }
    } catch (Exception $e) {
        error_log("Error setting shop wallet address: " . $e->getMessage());
        return false;
    }
}

function deleteProduct($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $result = $stmt->execute([$id]);
        if ($result === false) {
            throw new Exception("Execute failed: " . implode(", ", $stmt->errorInfo()));
        }
        if ($stmt->rowCount() === 0) {
            throw new Exception("No product found with ID: $id");
        }
        return true;
    } catch (Exception $e) {
        error_log("Error deleting product: " . $e->getMessage());
        return $e->getMessage();
    }
}

function setProductImage($productId, $image) {
    global $pdo;
    $target_dir = "product_images/";

    if (is_array($image) && array_key_exists("name", $image)) {
        // File upload
        $target_file = $target_dir . basename($image["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($image["tmp_name"]);
        if ($check === false) {
            throw new Exception("File is not an image.");
        }

        // Check file size (optional, adjust the limit as needed)
        if ($image["size"] > 50000000) {
            throw new Exception("Sorry, your file is too large.");
        }

        // Allow certain file formats
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" && $imageFileType != "webp" ) {
            throw new Exception("Sorry, only JPG, JPEG, PNG, GIF & WebP files are allowed.");
        }

        // Generate a unique filename to avoid conflicts
        $uniqueFileName = uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $uniqueFileName;

        // Upload the image
        if (!move_uploaded_file($image["tmp_name"], $target_file)) {
            throw new Exception("Sorry, there was an error uploading your image file.");
        }
    } elseif (is_string($image)) {
        // URL-based image
        $target_file = $image;
    } else {
        throw new Exception("Invalid image format.");
    }

    $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE id = ?");
    $result = $stmt->execute([$target_file, $productId]);

    if ($result) {
        return true;
    } else {
        return false;
    }
}

function addProduct($name, $description, $price, $stock, $image, $product_type, $download_file = null, $minecraft_command = null, $ign_placeholder = null, $script_path = null) {
    global $pdo;
    
    // Handle image upload
    $target_dir = "product_images/";
    if (is_array($image) && $image["tmp_name"]) {
        $target_file = $target_dir . basename($image["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        $check = getimagesize($image["tmp_name"]);
        if ($check === false) {
            throw new Exception("File is not an image.");
        }
        
        if ($image["size"] > 50000000) {
            throw new Exception("Image file is too large.");
        }
        
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
            throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed.");
        }
        
        if (!move_uploaded_file($image["tmp_name"], $target_file)) {
            throw new Exception("Failed to upload image file.");
        }
    } else {
        $target_file = $image;
    }
    
    // Determine if product is virtual
    $is_virtual = in_array($product_type, ['virtual', 'minecraft_command', 'script']);
    
    // Handle download file for virtual products
    $download_file_path = null;
    if ($product_type === 'virtual' && $download_file) {
        $target_dir = "product_downloads/";
        $download_target = $target_dir . basename($download_file["name"]);
        
        if (!move_uploaded_file($download_file["tmp_name"], $download_target)) {
            throw new Exception("Failed to upload download file.");
        }
        $download_file_path = $download_target;
    }
    
    // For script type products, validate script path
    if ($product_type === 'script' && $script_path) {
        if (!file_exists('scripts/' . basename($script_path))) {
            throw new Exception("Script file not found in scripts directory.");
        }
        $download_file_path = 'scripts/' . basename($script_path);
    }

    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, image, is_virtual, download_file, minecraft_command, ign_placeholder, script_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    return $stmt->execute([
        $name, 
        $description, 
        $price, 
        $stock, 
        $target_file, 
        $is_virtual ? 1 : 0, 
        $download_file_path,
        $minecraft_command,
        $ign_placeholder,
        $product_type === 'script' ? $script_path : null
    ]);
}

function sendEmail($to, $subject, $message) {
    $headers = "From: noreply@cryptocoinshop.com\r\n";
    $headers .= "Reply-To: support@cryptocoinshop.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    if (mail($to, $subject, $message, $headers)) {
        return true;
    } else {
        error_log("Failed to send email to: " . $to);
        return false;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!is_passphrase_set() && isset($_POST['new_passphrase'])) {
        if (strlen($_POST['new_passphrase']) < 8) {
            $error_message = "Passphrase must be at least 8 characters long.";
        } elseif ($_POST['new_passphrase'] !== $_POST['confirm_passphrase']) {
            $error_message = "Passphrases do not match.";
        } elseif (set_passphrase($_POST['new_passphrase'])) {
            $_SESSION['admin_authenticated'] = true;
            $success_message = "Passphrase set successfully.";
        } else {
            $error_message = "Failed to set passphrase. Please check file permissions.";
        }
    } elseif (is_passphrase_set() && isset($_POST['passphrase'])) {
        $correct_passphrase_hash = get_passphrase_hash();
        if (password_verify($_POST['passphrase'], $correct_passphrase_hash)) {
            $_SESSION['admin_authenticated'] = true;
        } else {
            $error_message = "Invalid passphrase";
        }
    } elseif (is_admin_authenticated()) {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'logout':
                    session_destroy();
                    header("Location: admin.php");
                    exit;
case 'add_product':
    try {
        $product_type = $_POST['product_type'] ?? 'physical';
        $download_file = isset($_FILES['download_file']) ? $_FILES['download_file'] : null;
        $image = isset($_POST['imageUrl']) ? $_POST['imageUrl'] : (isset($_FILES['image']) ? $_FILES['image'] : null);
        $minecraft_command = $_POST['minecraft_command'] ?? null;
        $ign_placeholder = $_POST['ign_placeholder'] ?? null;
        $script_path = $_POST['script_path'] ?? null;

        if (addProduct(
            $_POST['name'],
            $_POST['description'],
            $_POST['price'],
            $_POST['stock'],
            $image,
            $product_type,
            $download_file,
            $minecraft_command,
            $ign_placeholder,
            $script_path
        )) {
            $success_message = "Product added successfully";
        } else {
            $error_message = "Failed to add product";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
    break;
                case 'update_tracking':
                    $purchaseId = intval($_POST['purchase_id'] ?? 0);
                    $trackingMessage = $_POST['tracking_message'] ?? '';
                    if ($purchaseId > 0 && !empty($trackingMessage)) {
                        try {
                            $stmt = $pdo->prepare("UPDATE purchases SET tracking_message = ? WHERE id = ?");
                            $stmt->execute([$trackingMessage, $purchaseId]);
                            if ($stmt->rowCount() > 0) {
                                $success_message = "Tracking message updated successfully";
                            } else {
                                $error_message = "No purchase found with the given ID";
                            }
                        } catch (PDOException $e) {
                            error_log("Failed to update tracking message: " . $e->getMessage());
                            $error_message = "Failed to update tracking message";
                        }
                    } else {
                        $error_message = "Invalid purchase ID or tracking message";
                    }
                    break;
                case 'delete_product':
                    $productId = intval($_POST['id'] ?? 0);
                    if ($productId > 0) {
                        $result = deleteProduct($productId);
                        if ($result === true) {
                            $success_message = "Product deleted successfully";
                        } else {
                            $error_message = "Failed to delete product: $result";
                        }
                    } else {
                        $error_message = "Invalid product ID";
                    }
                    break;
                case 'set_shop_wallet':
                    $address = $_POST['wallet_address'] ?? '';
                    if (!empty($address)) {
                        if (setShopWalletAddress($address)) {
                            $success_message = "Shop wallet address updated successfully";
                        } else {
                            $error_message = "Failed to update shop wallet address";
                        }
                    } else {
                        $error_message = "Invalid wallet address";
                    }
                    break;
                case 'set_product_image':
                    try {
                        $productId = $_POST['product_id'];
                        $image = isset($_POST['image_url']) ? $_POST['image_url'] : $_FILES['image'];
                        if (setProductImage($productId, $image)) {
                            $success_message = "Product image updated successfully";
                        } else {
$error_message = "Failed to update product image";
                        }
                    } catch (Exception $e) {
                        $error_message = $e->getMessage();
                    }
                    break;
                case 'send_email':
                    $purchaseId = intval($_POST['purchase_id'] ?? 0);
                    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
                    $subject = $_POST['subject'] ?? '';
                    $message = $_POST['message'] ?? '';

                    if ($purchaseId > 0 && $email && $subject && $message) {
                        if (sendEmail($email, $subject, $message)) {
                            $success_message = "Email sent successfully to " . $email;
                        } else {
                            $error_message = "Failed to send email to " . $email;
                        }
                    } else {
                        $error_message = "Invalid email data";
                    }
                    break;
            }
        }
    }
}

// Get data for display if authenticated
if (is_admin_authenticated()) {
    $products = getProducts();
    $transactions = getTransactions();
    $websiteStats = getWebsiteStats();
    $shopWallet = getShopWallet();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CryptoCoin Shop Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .bg-gradient-primary { background: linear-gradient(87deg, #11cdef 0, #1171ef 100%) !important; }
        .bg-gradient-success { background: linear-gradient(87deg, #2dce89 0, #2dcecc 100%) !important; }
        .bg-gradient-danger { background: linear-gradient(87deg, #f5365c 0, #f56036 100%) !important; }
        .bg-gradient-warning { background: linear-gradient(87deg, #fb6340 0, #fbb140 100%) !important; }
        .text-white { color: white !important; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h1 class="mt-4 mb-4">CryptoCoin Shop Admin Panel</h1>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (!is_passphrase_set()): ?>
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title">First-time Setup</h2>
                    <form method="post" action="">
                        <div class="mb-3">
                            <input type="password" class="form-control" name="new_passphrase" placeholder="Enter new passphrase" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <input type="password" class="form-control" name="confirm_passphrase" placeholder="Confirm new passphrase" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-primary">Set Passphrase</button>
                    </form>
                </div>
            </div>
        <?php elseif (!is_admin_authenticated()): ?>
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title">Admin Login</h2>
                    <form method="post" action="">
                        <div class="mb-3">
                            <input type="password" class="form-control" name="passphrase" placeholder="Enter passphrase" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Login</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <form method="post" action="" class="mb-4">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-danger">Logout</button>
            </form>

            <div class="card mb-4 bg-gradient-primary text-white">
                <div class="card-body">
                    <h3 class="card-title">Shop Wallet</h3>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($shopWallet['address']); ?></p>
                    <p><strong>Balance:</strong> <?php echo number_format($shopWallet['balance'], 2); ?> CryptoCoins</p>
                    <form method="post" action="" class="mt-3">
                        <input type="hidden" name="action" value="set_shop_wallet">
                        <div class="input-group">
                            <input type="text" class="form-control" name="wallet_address" placeholder="New wallet address" required>
                            <button type="submit" class="btn btn-light">Update Wallet Address</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-gradient-success text-white mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Total Users</h5>
                            <h2 class="mb-0"><?php echo $websiteStats['total_users']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-gradient-info text-white mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Total Transactions</h5>
                            <h2 class="mb-0"><?php echo $websiteStats['total_transactions']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-gradient-danger text-white mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Total Revenue</h5>
                            <h2 class="mb-0"><?php echo number_format($websiteStats['total_revenue'], 2); ?> CryptoCoins</h2>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-gradient-warning text-white mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Total Products</h5>
                            <h2 class="mb-0"><?php echo $websiteStats['total_products']; ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    Product Management
                </div>
                <div class="card-body">
                    <h3>Add New Product</h3>
                    <form method="post" action="" class="mb-4" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_product">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <input type="text" class="form-control" name="name" placeholder="Product Name" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <input type="number" class="form-control" name="price" placeholder="Price" step="0.01" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <input type="number" class="form-control" name="stock" placeholder="Stock" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <select class="form-control" name="product_type" id="product_type">
                                    <option value="physical">Physical Product</option>
                                    <option value="virtual">Virtual Product</option>
                                    <option value="minecraft_command">Minecraft Command</option>
									 <option value="script">Run Script</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <textarea class="form-control" name="description" placeholder="Description"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <input type="file" class="form-control" name="image" accept="image/*">
                            </div>
                        </div>
						<div class="row">
    <div class="col-md-6 mb-3" id="script_fields" style="display: none;">
        <input type="text" class="form-control" name="script_path" placeholder="Script path (e.g., scripts/example.php)">
    </div>
</div>
                        <div class="row">
                            <div class="col-md-6 mb-3" id="virtual_product_fields" style="display: none;">
                                <input type="file" class="form-control" name="download_file" id="download_file">
                            </div>
                            <div class="col-md-6 mb-3" id="minecraft_command_fields" style="display: none;">
                                <input type="text" class="form-control mb-2" name="minecraft_command" placeholder="Minecraft Command">
                                <input type="text" class="form-control" name="ign_placeholder" placeholder="IGN Placeholder (e.g., {player_name})">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </form>

                    <h3>Product List</h3>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['id']); ?></td>
                                    <td>
                                        <?php if ($product['image']): ?>
                                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" width="50">
                                        <?php else: ?>
                                            <button type="button" class="btn btn-primary btn-sm set-image-btn" data-product-id="<?php echo $product['id']; ?>">Set Image</button>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['description']); ?></td>
                                    <td><?php echo number_format($product['price'], 2); ?></td>
                                    <td><?php echo $product['stock']; ?></td>
                                    <td><?php echo $product['is_virtual'] ? ($product['minecraft_command'] ? 'Minecraft Command' : 'Virtual') : 'Physical'; ?></td>
                                    <td>
                                        <form method="post" action="" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-shopping-cart me-1"></i>
                    Purchases
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Email</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $pdo->query("SELECT p.*, u.username, pr.name as product_name, pr.price as product_price 
                                                     FROM purchases p
                                                     JOIN users u ON p.user_id = u.id
                                                     JOIN products pr ON p.product_id = pr.id
                                                     ORDER BY p.id DESC");
                                $purchases = $stmt->fetchAll();

                                foreach ($purchases as $purchase) {
                                    echo '<tr>';
                                    echo '<td>' . $purchase['id'] . '</td>';
                                    echo '<td>' . htmlspecialchars($purchase['username']) . '</td>';
                                    echo '<td>' . htmlspecialchars($purchase['product_name']) . '</td>';
                                    echo '<td>' . number_format($purchase['product_price'], 2) . '</td>';
                                    echo '<td>' . htmlspecialchars($purchase['email']) . '</td>';
                                    echo '<td>' . date('Y-m-d H:i:s', strtotime($purchase['created_at'])) . '</td>';
                                    echo '<td>
                                            <form method="post" action="" style="display:inline;">
                                                <input type="hidden" name="action" value="update_tracking">
                                                <input type="hidden" name="purchase_id" value="' . $purchase['id'] . '">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="tracking_message" placeholder="Tracking Message">
                                                    <button type="submit" class="btn btn-primary">Update</button>
                                                </div>
                                            </form>
                                            <button type="button" class="btn btn-info mt-2" onclick="showEmailForm(' . $purchase['id'] . ', \'' . htmlspecialchars($purchase['email']) . '\')">Send Email</button>
                                          </td>';
                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-history me-1"></i>
                    Recent Transactions
                </div>
               

<div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>From Address</th>
                                <th>To Address</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['from_address']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['to_address']); ?></td>
                                    <td><?php echo number_format($transaction['amount'], 2); ?> CryptoCoins</td>
                                    <td><?php echo htmlspecialchars($transaction['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal for setting product image -->
            <div class="modal fade" id="setImageModal" tabindex="-1" aria-labelledby="setImageModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="setImageModalLabel">Set Product Image</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="setImageForm" method="post" action="" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="set_product_image">
                                <input type="hidden" name="product_id" id="product_id">
                                <div class="mb-3">
                                    <label for="image" class="form-label">Image</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                                </div>
                                <div class="mb-3">
                                    <label for="image_url" class="form-label">Image URL</label>
                                    <input type="text" class="form-control" id="image_url" name="image_url" placeholder="Enter image URL">
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" form="setImageForm">Save</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal for sending email -->
            <div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="emailModalLabel">Send Email to Customer</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="emailForm" method="post" action="">
                                <input type="hidden" name="action" value="send_email">
                                <input type="hidden" name="purchase_id" id="emailPurchaseId">
                                <div class="mb-3">
                                    <label for="emailTo" class="form-label">To:</label>
                                    <input type="email" class="form-control" id="emailTo" name="email" required readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="emailSubject" class="form-label">Subject:</label>
                                    <input type="text" class="form-control" id="emailSubject" name="subject" required>
                                </div>
                                <div class="mb-3">
                                    <label for="emailMessage" class="form-label">Message:</label>
                                    <textarea class="form-control" id="emailMessage" name="message" rows="5" required></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" form="emailForm">Send Email</button>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productTypeSelect = document.getElementById('product_type');
            const virtualProductFields = document.getElementById('virtual_product_fields');
            const minecraftCommandFields = document.getElementById('minecraft_command_fields');

            productTypeSelect.addEventListener('change', function() {
                if (this.value === 'virtual') {
                    virtualProductFields.style.display = 'block';
                    minecraftCommandFields.style.display = 'none';
                } else if (this.value === 'minecraft_command') {
                    virtualProductFields.style.display = 'none';
                    minecraftCommandFields.style.display = 'block';
                } else {
                    virtualProductFields.style.display = 'none';
                    minecraftCommandFields.style.display = 'none';
                }
            });

            document.querySelectorAll('.set-image-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const productId = btn.dataset.productId;
                    document.getElementById('product_id').value = productId;
                    const setImageModal = new bootstrap.Modal(document.getElementById('setImageModal'));
                    setImageModal.show();
                });
            });
        });

        function showEmailForm(purchaseId, email) {
            document.getElementById('emailPurchaseId').value = purchaseId;
            document.getElementById('emailTo').value = email;
            document.getElementById('emailSubject').value = 'Your CryptoCoin Shop Purchase';
            document.getElementById('emailMessage').value = 'Dear Customer,\n\nThank you for your purchase from CryptoCoin Shop. We wanted to update you on your order status.\n\nBest regards,\nCryptoCoin Shop Team';
            
            const emailModal = new bootstrap.Modal(document.getElementById('emailModal'));
            emailModal.show();
        }
		
		document.getElementById('product_type').addEventListener('change', function() {
    const scriptFields = document.getElementById('script_fields');
    scriptFields.style.display = this.value === 'script' ? 'block' : 'none';
});
    </script>
</body>
</html>