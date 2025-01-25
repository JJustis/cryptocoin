<?php

// Error reporting for debugging (remove in production)

error_reporting(E_ALL);

ini_set('display_errors', 1);



// Set headers for API responses

header("Content-Type: application/json");

header("Access-Control-Allow-Origin: *");

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

header("Access-Control-Allow-Headers: Content-Type, Authorization");



// Start session for user authentication

session_start();



// Custom error handler

function customErrorHandler($errno, $errstr, $errfile, $errline) {

    $errorMessage = "Error [$errno] $errstr in $errfile on line $errline";

    error_log($errorMessage);

    if (in_array($errno, [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {

        response(false, "A critical error occurred. Please try again later.");

    }

}

set_error_handler("customErrorHandler");



// Function to send JSON response

function response($success, $message, $data = null) {

    $response = [

        'success' => $success,

        'message' => $message,

        'data' => $data

    ];

    error_log("API Response: " . json_encode($response));

    echo json_encode($response);

    exit;

}



// Database connection

$db_host = 'localhost';

$db_user = 'root';

$db_pass = '';

$db_name = 'cybercoin_db';



$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);



if ($conn->connect_error) {

    error_log("Database connection failed: " . $conn->connect_error);

    response(false, 'Database connection failed. Please try again later.');

}

$host = 'localhost';
$dbname = 'cybercoin_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Block class definition

class Block {

    public $index;

    public $timestamp;

    public $transactions;

    public $previousHash;

    public $hash;

    public $nonce;

    public $difficulty;



    public function __construct($index, $timestamp, $transactions, $previousHash, $difficulty, $hash = '', $nonce = 0) {

        $this->index = $index;

        $this->timestamp = $timestamp;

        $this->transactions = $transactions;

        $this->previousHash = $previousHash;

        $this->difficulty = $difficulty;

        $this->nonce = $nonce;

        $this->hash = $hash ?: $this->calculateHash();

    }



    public function calculateHash() {

        return hash('sha256', $this->index . $this->previousHash . $this->timestamp . json_encode($this->transactions) . $this->nonce . $this->difficulty);

    }



    public function mineBlock($difficulty) {

        $target = str_repeat("0", $difficulty);

        while (substr($this->hash, 0, $difficulty) !== $target) {

            $this->nonce++;

            $this->hash = $this->calculateHash();

        }

        error_log("Block mined: " . $this->hash);

    }



    public function getBlockData() {

        return [

            'index' => $this->index,

            'timestamp' => $this->timestamp,

            'transactions' => $this->transactions,

            'previousHash' => $this->previousHash,

            'hash' => $this->hash,

            'nonce' => $this->nonce,

            'difficulty' => floatval($this->difficulty)

        ];

    }

}

require_once 'rcon.php';

// Add this function to execute Minecraft commands
function executeMinecraftCommand($command, $playerName) {
    $rconHost = 'betahut.bounceme.net';
    $rconPort = 200; // Default RCON port, change if needed
    $rconPassword = '000';
    $rconTimeout = 3;

    $rcon = new \Thedudeguy\Rcon($rconHost, $rconPort, $rconPassword, $rconTimeout);

    if ($rcon->connect()) {
        $command = str_replace('{player_name}', $playerName, $command);
        $response = $rcon->sendCommand($command);
        $rcon->disconnect();
        return $response;
    } else {
        throw new Exception("Failed to connect to Minecraft server");
    }
}

// Blockchain class definition

class Blockchain {

    public $chain;

    public $miningReward;

    private $maxBlocks = 1000000000000000;

    private $initialDifficulty = 6;

    private $difficultyIncreaseFactor = 0.0001;



    public function __construct() {

        $this->miningReward = 100;

        $this->chain = [];

        $this->loadLatestBlocks();

    }



    private function loadLatestBlocks() {

        global $conn;

        try {

            $result = $conn->query("SELECT * FROM blocks ORDER BY `index` DESC LIMIT 1000");

            $blocks = [];

            while ($row = $result->fetch_assoc()) {

                $blocks[] = new Block(

                    $row['index'],

                    $row['timestamp'],

                    json_decode($row['transactions'], true),

                    $row['previous_hash'],

                    $row['difficulty'],

                    $row['hash'],

                    $row['nonce']

                );

            }

            $this->chain = array_reverse($blocks);



            if (empty($this->chain)) {

                $genesisBlock = new Block(0, time(), [], "0", $this->initialDifficulty);

                $this->chain[] = $genesisBlock;

                $this->saveBlock($genesisBlock);

            }

        } catch (Exception $e) {

            error_log("Error loading blockchain: " . $e->getMessage());

            throw $e;

        }

    }



    public function saveBlock($block) {

        global $conn;

        $stmt = $conn->prepare("INSERT INTO blocks (`index`, timestamp, transactions, previous_hash, hash, nonce, difficulty) VALUES (?, ?, ?, ?, ?, ?, ?)");

        $transactions = json_encode($block->transactions);

        $stmt->bind_param("iisssid", $block->index, $block->timestamp, $transactions, $block->previousHash, $block->hash, $block->nonce, $block->difficulty);

        try {

            $stmt->execute();

        } catch (mysqli_sql_exception $e) {

            if ($e->getCode() == 1062) {

                error_log("Duplicate block entry detected. Skipping block " . $block->index);

                return false;

            } else {

                throw new Exception("Failed to save block: " . $e->getMessage());

            }

        } finally {

            $stmt->close();

        }

        return true;

    }



    public function getLatestBlock() {

        if (empty($this->chain)) {

            throw new Exception("Blockchain is empty");

        }

        return end($this->chain);

    }



    public function getCurrentDifficulty() {

        $latestBlock = $this->getLatestBlock();

        return floatval($this->initialDifficulty + ($latestBlock->index * $this->difficultyIncreaseFactor));

    }



    public function getRemainingBlocks() {

        $latestBlock = $this->getLatestBlock();

        return max(0, $this->maxBlocks - $latestBlock->index - 1);

    }



    public function getLatestBlocks($count = 20) {

        return array_slice($this->chain, -$count);

    }



    public function minePendingTransactions($miningRewardAddress) {

        $rewardTx = ['fromAddress' => null, 'toAddress' => $miningRewardAddress, 'amount' => $this->miningReward];

        $transactions = [$rewardTx];



        $latestBlock = $this->getLatestBlock();

        $newIndex = $latestBlock->index + 1;

        $difficulty = $this->getCurrentDifficulty();

        

        $newBlock = new Block($newIndex, time(), $transactions, $latestBlock->hash, $difficulty);

        $newBlock->mineBlock($difficulty);



        error_log("Mining new block: " . json_encode($newBlock->getBlockData()));



        if ($this->saveBlock($newBlock)) {

            $this->chain[] = $newBlock;

            $this->updateBalances($newBlock->transactions);

            return $newBlock;

        } else {

            error_log("Block " . $newBlock->index . " already exists. Skipping.");

            return null;

        }

    }



    public function processTransaction($transaction) {

        $fromAddress = $transaction['fromAddress'];

        $toAddress = $transaction['toAddress'];

        $amount = $transaction['amount'];



        $senderBalance = $this->getBalanceOfAddress($fromAddress);

        if ($senderBalance < $amount) {

            throw new Exception("Insufficient balance");

        }



        $this->updateBalance($fromAddress, -$amount);

        $this->updateBalance($toAddress, $amount);



        $latestBlock = $this->getLatestBlock();

        $latestBlock->transactions[] = $transaction;

        $this->saveBlock($latestBlock);



        return true;

    }



    public function getBalanceOfAddress($address) {

        global $conn;

        $stmt = $conn->prepare("SELECT balance FROM user_balances WHERE wallet_address = ?");

        $stmt->bind_param("s", $address);

        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {

            return floatval($row['balance']);

        }

        return 0;

    }



    private function updateBalances($transactions) {

        global $conn;

        foreach ($transactions as $transaction) {

            if ($transaction['fromAddress'] !== null) {

                $this->updateBalance($transaction['fromAddress'], -$transaction['amount']);

            }

            $this->updateBalance($transaction['toAddress'], $transaction['amount']);

        }

    }



    public function updateBalance($address, $amount) {

        global $conn;

        $stmt = $conn->prepare("INSERT INTO user_balances (wallet_address, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + ?");

        $stmt->bind_param("sdd", $address, $amount, $amount);

        $stmt->execute();

    }

}



// Initialize blockchain

$blockchain = new Blockchain();



// Main API handler

try {

    $action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');



    switch ($action) {

        case 'register':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

                response(false, "Invalid request method");

            }

            $username = $_POST['username'] ?? '';

            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {

                response(false, "Username and password are required");

            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $walletAddress = bin2hex(random_bytes(32));

            $stmt = $conn->prepare("INSERT INTO users (username, password, wallet_address) VALUES (?, ?, ?)");

            $stmt->bind_param("sss", $username, $hashedPassword, $walletAddress);

            if ($stmt->execute()) {

                response(true, "User registered successfully");

            } else {

                response(false, "Registration failed: " . $conn->error);

            }

            break;



        case 'login':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

                response(false, "Invalid request method");

            }

            $username = $_POST['username'] ?? '';

            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {

                response(false, "Username and password are required");

            }

            $stmt = $conn->prepare("SELECT id, password, wallet_address FROM users WHERE username = ?");

            $stmt->bind_param("s", $username);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {

                if (password_verify($password, $user['password'])) {

                    $_SESSION['user_id'] = $user['id'];

                    $_SESSION['username'] = $username;

                    $_SESSION['wallet_address'] = $user['wallet_address'];

                    response(true, "Login successful", ['username' => $username, 'walletAddress' => $user['wallet_address']]);

                } else {

                    response(false, "Invalid username or password");

                }

            } else {

                response(false, "Invalid username or password");

            }

            break;



        case 'logout':

            session_destroy();

            response(true, "Logout successful");

            break;



        case 'get_blockchain':

            $latestBlocks = $blockchain->getLatestBlocks(20);

            $chainData = array_map(function($block) {

                return $block->getBlockData();

            }, $latestBlocks);

            $currentDifficulty = $blockchain->getCurrentDifficulty();

            $remainingBlocks = $blockchain->getRemainingBlocks();

            response(true, "Latest blockchain data retrieved", [

                'blocks' => $chainData,

                'currentDifficulty' => $currentDifficulty,

                'remainingBlocks' => $remainingBlocks

            ]);

            break;



        case 'mine':

            if (!isset($_SESSION['user_id'])) {

                response(false, "User not authenticated");

            }

            try {

                $minedBlock = $blockchain->minePendingTransactions($_SESSION['wallet_address']);

                if ($minedBlock) {

                    $newBalance = $blockchain->getBalanceOfAddress($_SESSION['wallet_address']);

                    $currentDifficulty = $blockchain->getCurrentDifficulty();

                    $remainingBlocks = $blockchain->getRemainingBlocks();

                    response(true, "Block mined and added to the chain", [

                        'newBalance' => $newBalance,

                        'minedBlock' => $minedBlock->getBlockData(),

                        'currentDifficulty' => $currentDifficulty,

                        'remainingBlocks' => $remainingBlocks

                    ]);

                } else {

                    response(false, "Mining failed: Block already exists");

                }

            } catch (Exception $e) {

                error_log("Mining exception: " . $e->getMessage());

                response(false, "Mining failed: " . $e->getMessage());

            }

            break;



        case 'create_transaction':

            if (!isset($_SESSION['user_id'])) {

                response(false, "User not authenticated");

            }

            $fromAddress = $_SESSION['wallet_address'];

            $toAddress = $_POST['toAddress'] ?? '';

            $amount = floatval($_POST['amount'] ?? 0);

            $message = $_POST['message'] ?? '';

            if (empty($toAddress) || $amount <= 0) {

                response(false, "Invalid transaction data");

            }

            try {

                $transaction = [

                    'fromAddress' => $fromAddress,

                    'toAddress' => $toAddress,

                    'amount' => $amount,

                    'message' => $message,

                    'timestamp' => time()

                ];

                $blockchain->processTransaction($transaction);

                $newBalance = $blockchain->getBalanceOfAddress($fromAddress);

                response(true, "Transaction processed successfully", ['newBalance' => $newBalance]);

            } catch (Exception $e) {

                response(false, "Transaction failed: " . $e->getMessage());

            }

            break;



        case 'get_balance':

            if (!isset($_SESSION['user_id'])) {

                response(false, "User not authenticated");

            }

            $balance = $blockchain->getBalanceOfAddress($_SESSION['wallet_address']);

            response(true, "Balance retrieved", ['balance' => $balance]);

            break;



case 'create_listing':

            if (!isset($_SESSION['user_id'])) {

                response(false, "User not authenticated");

            }

            $userId = $_SESSION['user_id'];

            $amount = floatval($_POST['amount'] ?? 0);

            $price = floatval($_POST['price'] ?? 0);

            $paypalEmail = $_POST['paypalEmail'] ?? '';

            

            if ($amount <= 0 || $price <= 0 || empty($paypalEmail)) {

                response(false, "Invalid listing data");

            }

            

            if (!filter_var($paypalEmail, FILTER_VALIDATE_EMAIL)) {

                response(false, "Invalid PayPal email address");

            }

            

            $userBalance = $blockchain->getBalanceOfAddress($_SESSION['wallet_address']);

            if ($userBalance < $amount) {

                response(false, "Insufficient balance to create listing");

            }

            

            $conn->begin_transaction();

            

            try {

                // Deduct coins from user's balance

                $blockchain->updateBalance($_SESSION['wallet_address'], -$amount);

                

                // Create listing

                $stmt = $conn->prepare("INSERT INTO listings (user_id, amount, price, paypal_email, status) VALUES (?, ?, ?, ?, 'active')");

                $stmt->bind_param("idds", $userId, $amount, $price, $paypalEmail);

                $stmt->execute();

                

                $conn->commit();

                response(true, "Listing created successfully");

            } catch (Exception $e) {

                $conn->rollback();

                $blockchain->updateBalance($_SESSION['wallet_address'], $amount); // Refund the coins

                response(false, "Failed to create listing: " . $e->getMessage());

            }

            break;



        case 'get_listings':

            $stmt = $conn->prepare("SELECT l.*, u.username as seller_username, u.wallet_address as seller_wallet_address FROM listings l JOIN users u ON l.user_id = u.id WHERE l.status = 'active'");

            $stmt->execute();

            $result = $stmt->get_result();

            $listings = [];

            while ($row = $result->fetch_assoc()) {

                $listings[] = [

                    'id' => $row['id'],

                    'amount' => $row['amount'],

                    'price' => $row['price'],

                    'seller_username' => $row['seller_username'],

                    'seller_wallet_address' => $row['seller_wallet_address']

                ];

            }

            response(true, "Listings retrieved successfully", ['listings' => $listings]);

            break;



        case 'cancel_listing':

            if (!isset($_SESSION['user_id'])) {

                response(false, "User not authenticated");

            }

            $listingId = intval($_POST['listing_id'] ?? 0);

            if ($listingId <= 0) {

                response(false, "Invalid listing ID");

            }



            $conn->begin_transaction();

            try {

                $stmt = $conn->prepare("SELECT * FROM listings WHERE id = ? AND user_id = ? AND status = 'active'");

                $stmt->bind_param("ii", $listingId, $_SESSION['user_id']);

                $stmt->execute();

                $listing = $stmt->get_result()->fetch_assoc();



                if (!$listing) {

                    throw new Exception("Listing not found or not owned by user");

                }



                // Refund coins to user

                $blockchain->updateBalance($_SESSION['wallet_address'], $listing['amount']);



                // Update listing status

                $stmt = $conn->prepare("UPDATE listings SET status = 'cancelled' WHERE id = ?");

                $stmt->bind_param("i", $listingId);

                $stmt->execute();



                $conn->commit();

                response(true, "Listing cancelled and coins refunded");

            } catch (Exception $e) {

                $conn->rollback();

                response(false, "Failed to cancel listing: " . $e->getMessage());

            }

            break;



        case 'initiate_payment':

            if (!isset($_SESSION['user_id'])) {

                response(false, "User not authenticated");

            }

            $listingId = intval($_POST['listing_id'] ?? 0);

            

            $stmt = $conn->prepare("SELECT * FROM listings WHERE id = ? AND status = 'active'");

            $stmt->bind_param("i", $listingId);

            $stmt->execute();

            $result = $stmt->get_result();

            $listing = $result->fetch_assoc();

            

            if (!$listing) {

                response(false, "Listing not found or not active");

            }

            

            $_SESSION['last_listing_id'] = $listingId;

            

            require_once 'gateway-config.php';

            

            $paypalForm = '

            <form action="'.PAYPAL_URL.'" method="post" id="paypal_form">

                <input type="hidden" name="cmd" value="_xclick">

                <input type="hidden" name="business" value="'.htmlspecialchars($listing['paypal_email']).'">

                <input type="hidden" name="item_name" value="CryptoCoin Purchase - Listing #'.$listingId.'">

                <input type="hidden" name="item_number" value="'.$listingId.'">

                <input type="hidden" name="amount" value="'.$listing['price'].'">

                <input type="hidden" name="currency_code" value="'.PAYPAL_CURRENCY.'">

                <input type="hidden" name="return" value="'.PAYPAL_RETURN_URL.'">

                <input type="hidden" name="cancel_return" value="'.PAYPAL_CANCEL_URL.'">

                <input type="hidden" name="notify_url" value="'.PAYPAL_NOTIFY_URL.'">

                <input type="hidden" name="custom" value="'.$listingId.'|'.$_SESSION['user_id'].'">

            </form>';

            

            response(true, "Payment initiated", ['paypal_form' => $paypalForm]);

            break;

case 'get_products':
        $stmt = $conn->prepare("SELECT * FROM products WHERE stock > 0");
        $stmt->execute();
        $result = $stmt->get_result();
        $products = $result->fetch_all(MYSQLI_ASSOC);
        response(true, "Products retrieved successfully", ['products' => $products]);
        break;

    case 'get_balance':
        if (!isset($_SESSION['user_id'])) {
            response(false, "User not authenticated");
        }
        $balance = $blockchain->getBalanceOfAddress($_SESSION['wallet_address']);
        response(true, "Balance retrieved successfully", ['balance' => $balance]);
        break;

case 'get_product_details':
    if (!isset($_GET['product_id'])) {
        response(false, "Product ID is required");
    }
    $productId = intval($_GET['product_id']);
    
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    
    if (!$product) {
        response(false, "Product not found");
    }
    
    response(true, "Product details retrieved successfully", $product);
    break;

case 'buy_product':
    if (!isset($_SESSION['user_id'])) {
        response(false, "User not authenticated");
    }
    
    $productId = intval($_POST['product_id'] ?? 0);
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $ign = $_POST['ign'] ?? '';
    
    if ($productId <= 0 || !$email) {
        response(false, "Invalid product ID or email");
    }
    
    $conn->begin_transaction();
    try {
        // Get product details
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND stock > 0");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        
        if (!$product) {
            throw new Exception("Product not available");
        }
        
        if ($product['minecraft_command'] && empty($ign)) {
            throw new Exception("IGN is required for Minecraft command products");
        }
        
        // Check user balance
        $userBalance = $blockchain->getBalanceOfAddress($_SESSION['wallet_address']);
        if ($userBalance < $product['price']) {
            throw new Exception("Insufficient balance");
        }
        
        // Process transaction
        $shopWalletAddress = "5aaf3614ce907d56e566772fcc67d6ae07e8466775a09c9efeec0284fa608573";
        $transaction = [
            'fromAddress' => $_SESSION['wallet_address'],
            'toAddress' => $shopWalletAddress,
            'amount' => $product['price'],
            'message' => "Purchase of " . $product['name'],
            'timestamp' => time()
        ];
        $transactionHash = $blockchain->processTransaction($transaction);
        
        // Update product stock
        $stmt = $conn->prepare("UPDATE products SET stock = stock - 1 WHERE id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        
        // Generate redemption token for Minecraft command products
        $redemptionToken = null;
        if ($product['minecraft_command']) {
            $redemptionToken = bin2hex(random_bytes(16));
        }
        
        // Record purchase
        $stmt = $conn->prepare("INSERT INTO purchases (user_id, product_id, email, transaction_hash, redemption_token, ign) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $_SESSION['user_id'], $productId, $email, $transactionHash, $redemptionToken, $ign);
        $stmt->execute();
        
        $conn->commit();
        
        // Prepare response data
        $responseData = [
            'product' => $product['name'],
            'transaction_hash' => $transactionHash,
            'new_balance' => $userBalance - $product['price']
			//Add to the responseData section in buy_product case
        ];
        //Add to the responseData section in buy_product case
if ($product['script_path']) {
    $redemptionToken = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("UPDATE purchases SET redemption_token = ? WHERE id = ?");
    $stmt->execute([$redemptionToken, $purchaseId]);
    $responseData['script_url'] = 'execute_script.php?token=' . urlencode($redemptionToken);
}
        if ($product['minecraft_command']) {
            $responseData['redemption_url'] = 'redeem.php?token=' . urlencode($redemptionToken);
        } elseif ($product['is_virtual']) {
            $responseData['download_url'] = 'download.php?token=' . urlencode($redemptionToken);
        }
        
        response(true, "Purchase successful", $responseData);
    } catch (Exception $e) {
        $conn->rollback();
        response(false, "Purchase failed: " . $e->getMessage());
    }
    break;
case 'get_tracking_messages':
    if (!isset($_SESSION['user_id'])) {
        response(false, "User not authenticated");
    }
    
    $stmt = $conn->prepare("
        SELECT 
            p.id AS purchase_id,
            p.user_id,
            p.product_id,
            p.email,
            p.transaction_hash,
            p.created_at AS purchase_date,
            p.download_code,
            p.download_token,
            p.download_count,
            p.last_download_date,
            p.tracking_message,
            pr.name AS product_name,
            pr.description AS product_description,
            pr.price AS product_price,
            pr.stock AS product_stock,
            pr.created_at AS product_created_at,
            pr.updated_at AS product_updated_at,
            pr.is_virtual,
            pr.image AS product_image,
            pr.download_file
        FROM 
            purchases p
        JOIN 
            products pr ON p.product_id = pr.id
        WHERE 
            p.user_id = ?
        ORDER BY 
            p.created_at DESC
    ");
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $purchases = $result->fetch_all(MYSQLI_ASSOC);
    
    // Process the results
    $purchaseDetails = array_map(function($purchase) {
        return [
            'id' => $purchase['purchase_id'],
            'date' => $purchase['purchase_date'],
            'email' => $purchase['email'],
            'transaction_hash' => $purchase['transaction_hash'],
            'download_code' => $purchase['download_code'],
            'download_token' => $purchase['download_token'],
            'download_count' => $purchase['download_count'],
            'last_download_date' => $purchase['last_download_date'],
            'tracking_message' => $purchase['tracking_message'],
            'product' => [
                'id' => $purchase['product_id'],
                'name' => $purchase['product_name'],
                'description' => $purchase['product_description'],
                'price' => floatval($purchase['product_price']),
                'stock' => intval($purchase['product_stock']),
                'created_at' => $purchase['product_created_at'],
                'updated_at' => $purchase['product_updated_at'],
                'is_virtual' => (bool)$purchase['is_virtual'],
                'image' => $purchase['product_image'],
                'download_file' => $purchase['download_file']
            ]
        ];
    }, $purchases);
    
    response(true, "Purchase details and tracking messages retrieved successfully", ['purchases' => $purchaseDetails]);
    break;
        case 'verify_payment':

            // This should be called by PayPal IPN or by the success.php page

            if (!isset($_POST['custom'])) {

                response(false, "Invalid payment data");

            }

            list($listingId, $buyerId) = explode('|', $_POST['custom']);

            $listingId = intval($listingId);

            $buyerId = intval($buyerId);



            $conn->begin_transaction();

            try {

                $stmt = $conn->prepare("SELECT * FROM listings WHERE id = ? AND status = 'active'");

                $stmt->bind_param("i", $listingId);

                $stmt->execute();

                $listing = $stmt->get_result()->fetch_assoc();



                if (!$listing) {

                    throw new Exception("Listing not found or not active");

                }



                // Update listing status

                $stmt = $conn->prepare("UPDATE listings SET status = 'sold' WHERE id = ?");

                $stmt->bind_param("i", $listingId);

                $stmt->execute();



                // Transfer coins from seller to buyer

                $blockchain->updateBalance($listing['seller_wallet_address'], -$listing['amount']);

                $stmt = $conn->prepare("SELECT wallet_address FROM users WHERE id = ?");

                $stmt->bind_param("i", $buyerId);

                $stmt->execute();

                $buyerWallet = $stmt->get_result()->fetch_assoc()['wallet_address'];

                $blockchain->updateBalance($buyerWallet, $listing['amount']);



                // Log the transaction

                $stmt = $conn->prepare("INSERT INTO transactions (seller_id, buyer_id, listing_id, amount, price) VALUES (?, ?, ?, ?, ?)");

                $stmt->bind_param("iiidi", $listing['user_id'], $buyerId, $listingId, $listing['amount'], $listing['price']);

                $stmt->execute();



                $conn->commit();

                response(true, "Payment verified and coins transferred");

            } catch (Exception $e) {

                $conn->rollback();

                response(false, "Failed to process payment: " . $e->getMessage());

            }

            break;



        default:

            response(false, "Invalid action");

    }

} catch (Exception $e) {

    error_log('An error occurred: ' . $e->getMessage());

    response(false, 'An error occurred: ' . $e->getMessage());

}



$conn->close();

?>