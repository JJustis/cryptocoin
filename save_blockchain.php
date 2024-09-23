<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(["success" => false, "message" => "Not logged in"]));
}

// Get the blockchain data from the POST request
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['chain']) && isset($data['pendingTransactions'])) {
    $blockchain = [
        'chain' => $data['chain'],
        'pendingTransactions' => $data['pendingTransactions']
    ];
    
    // Save the blockchain data to a file
    $file = 'blockchain_data.json';
    if (file_put_contents($file, json_encode($blockchain))) {
        echo json_encode(["success" => true, "message" => "Blockchain saved successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to save blockchain"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid data received"]);
}
?>