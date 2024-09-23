<?php
include 'config.php';

$amount = $_POST['amount'];
$price = $_POST['price'];
$paypal_email = $_POST['paypal_email'];
$user_id = 1; // In a real app, get this from the session after user login

// Validate input
if (!filter_var($paypal_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

if (!is_numeric($amount) || !is_numeric($price) || $amount <= 0 || $price <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid amount or price']);
    exit;
}

$sql = "INSERT INTO listings (user_id, amount, price, paypal_email) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iids", $user_id, $amount, $price, $paypal_email);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$stmt->close();
$conn->close();
?>