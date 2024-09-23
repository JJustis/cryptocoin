<?php
session_start();
require_once 'config.php'; // Include your database connection

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Redirect to login page if not logged in
    exit();
}

$walletAddress = $_SESSION['wallet_address'] ?? '';
$username = $_SESSION['username'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CryptoCoin Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2 {
            color: #1a73e8;
        }
        .shop-panel {
            background: linear-gradient(135deg, #43cea2, #185a9d);
            color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .product-item {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        button {
            background-color: #1a73e8;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 1em;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #1557b0;
        }
        #walletInfo {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
    </style>
	    <style>
        .card { margin-bottom: 20px; }
        .bg-gradient-primary { background: linear-gradient(87deg, #11cdef 0, #1171ef 100%) !important; }
        .bg-gradient-success { background: linear-gradient(87deg, #2dce89 0, #2dcecc 100%) !important; }
        .bg-gradient-danger { background: linear-gradient(87deg, #f5365c 0, #f56036 100%) !important; }
        .bg-gradient-warning { background: linear-gradient(87deg, #fb6340 0, #fbb140 100%) !important; }
        .text-white { color: white !important; }
.product-image {
    max-width: 100%;
    height: auto;
    margin-bottom: 10px;
}
    </style>
</head>
<body>
    <div class="container">
        <div class="shop-panel">
            <h1>CryptoCoin Shop</h1>
            <div id="walletInfo">
                <p>Welcome, <?php echo htmlspecialchars($username); ?></p>
                <p>Wallet Address: <?php echo htmlspecialchars($walletAddress); ?></p>
                <p>Balance: <span id="walletBalance">Loading...</span> CryptoCoins</p>
            </div>
            <button onclick="location.href='index.php'">Back to Main Page</button>
        </div>

        <h2>Available Products</h2>
        <div id="productList" class="product-grid">
            <!-- Products will be loaded here dynamically -->
        </div>
    </div>

    <script src="shop.js"></script>
	<script>
    function downloadFile(url) {
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', '');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
</body>
</html>