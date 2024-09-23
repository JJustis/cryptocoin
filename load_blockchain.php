<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(["success" => false, "message" => "Not logged in"]));
}

$file = 'blockchain_data.json';

if (file_exists($file)) {
    // Load the blockchain data from the file
    $blockchain = json_decode(file_get_contents($file), true);
    echo json_encode($blockchain);
} else {
    // If the file doesn't exist, return an empty blockchain
    echo json_encode([
        "chain" => [],
        "pendingTransactions" => []
    ]);
}
?>