<?php
session_start();
require_once 'config.php';

// Verify token and get purchase details
$token = $_GET['token'] ?? '';
if (empty($token)) {
    die("Invalid token");
}

// Verify purchase and token
$stmt = $pdo->prepare("
    SELECT p.*, pr.script_path 
    FROM purchases p 
    JOIN products pr ON p.product_id = pr.id 
    WHERE p.redemption_token = ? AND p.executed = 0
");
$stmt->execute([$token]);
$purchase = $stmt->fetch();

if (!$purchase || empty($purchase['script_path'])) {
    die("Invalid or expired token");
}

// Mark as executed
$stmt = $pdo->prepare("UPDATE purchases SET executed = 1 WHERE id = ?");
$stmt->execute([$purchase['id']]);

// Execute the script
$scriptPath = 'scripts/' . basename($purchase['script_path']);
if (!file_exists($scriptPath)) {
    die("Script not found");
}

// Execute script in read-only mode
try {
    include $scriptPath;
} catch (Exception $e) {
    error_log("Script execution error: " . $e->getMessage());
    die("Error executing script");
}
?>