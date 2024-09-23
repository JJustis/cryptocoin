<?php
session_start();
require_once 'config.php'; // Include your database connection
require_once 'gateway-config.php'; // Include your PayPal configuration

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to sanitize input
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Function to log debug information
function debug_log($message) {
    error_log("DEBUG: " . $message);
}

// Verify that the user is logged in
if (!isset($_SESSION['user_id'])) {
    debug_log("User not authenticated. Session data: " . print_r($_SESSION, true));
    die("Error: User not authenticated.");
}

// Log all received GET parameters
debug_log("Received GET parameters: " . print_r($_GET, true));

// Retrieve PayPal data
$paypal_payer_id = sanitize_input($_GET['PayerID'] ?? '');
$paypal_token = sanitize_input($_GET['token'] ?? '');

// Verify the payment with PayPal
if (empty($paypal_payer_id) || empty($paypal_token)) {
    debug_log("Missing PayPal PayerID or token.");
    die("Error: Invalid PayPal data received. Please contact support.");
}

// Retrieve the listing details from the database
$listing_id = $_SESSION['last_listing_id'] ?? 0;
debug_log("Retrieved listing ID from session: " . $listing_id);

if ($listing_id == 0) {
    debug_log("Listing ID not found in session.");
    die("Error: Transaction details not found. Please contact support.");
}

$conn->begin_transaction();

try {
    // Fetch and lock the listing
    $stmt = $conn->prepare("SELECT * FROM listings WHERE id = ? AND status = 'active' FOR UPDATE");
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $listing = $result->fetch_assoc();

    if (!$listing) {
        throw new Exception("Listing not found or no longer active.");
    }

    debug_log("Listing data: " . print_r($listing, true));

    // TODO: Implement PayPal payment verification here
    // For now, we'll assume the payment is verified if we have a PayerID and token

    // Update listing status
    $update_stmt = $conn->prepare("UPDATE listings SET status = 'sold' WHERE id = ?");
    $update_stmt->bind_param("i", $listing_id);
    $update_stmt->execute();

    // Transfer coins to buyer
    $transfer_to_stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $transfer_to_stmt->bind_param("di", $listing['amount'], $_SESSION['user_id']);
    $transfer_to_stmt->execute();

    // Log the transaction
    $log_stmt = $conn->prepare("INSERT INTO transactions (seller_id, buyer_id, amount, price, paypal_payer_id, paypal_token) VALUES (?, ?, ?, ?, ?, ?)");
    $log_stmt->bind_param("iiddss", $listing['user_id'], $_SESSION['user_id'], $listing['amount'], $listing['price'], $paypal_payer_id, $paypal_token);
    $log_stmt->execute();

    $conn->commit();
    $success = true;
    debug_log("Transaction completed successfully.");
} catch (Exception $e) {
    $conn->rollback();
    $success = false;
    debug_log("Transaction failed: " . $e->getMessage());
}

// Close database connection
$conn->close();

// Display result to user
if ($success) {
    echo "<h1>Payment Successful!</h1>";
    echo "<p>You have successfully purchased {$listing['amount']} CryptoCoins.</p>";
    echo "<p>PayPal Transaction ID: {$paypal_token}</p>";
    echo "<a href='index.php'>Return to Dashboard</a>";
} else {
    echo "<h1>Transaction Error</h1>";
    echo "<p>There was an error processing your transaction. Please contact support.</p>";
    echo "<a href='index.php'>Return to Dashboard</a>";
}
?>