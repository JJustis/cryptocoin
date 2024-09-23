<?php
session_start();

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'loggedIn' => true,
        'username' => $_SESSION['username'],
        'walletAddress' => $_SESSION['wallet_address']
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}
?>