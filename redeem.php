<?php
session_start();
require_once 'config.php';
require_once 'rcon.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$redemptionToken = $_GET['token'] ?? '';

if (empty($redemptionToken)) {
    die("Invalid redemption token");
}

try {
    $stmt = $conn->prepare("
        SELECT p.*, pr.name AS product_name, pr.minecraft_command 
        FROM purchases p 
        JOIN products pr ON p.product_id = pr.id 
        WHERE p.redemption_token = ? AND p.user_id = ? AND p.redeemed = 0
    ");
    $stmt->bind_param("si", $redemptionToken, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $purchase = $result->fetch_assoc();

    if (!$purchase) {
        die("Invalid, expired, or already redeemed token");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Execute the Minecraft command
        $rcon = new \Thedudeguy\Rcon('localhost', '200', '000', '3');
        
        if ($rcon->connect()) {
            $command = str_replace('@p', $purchase['ign'], $purchase['minecraft_command']);
            $response = $rcon->sendCommand($command);
            $rcon->disconnect();
            
            // Mark the purchase as redeemed
            $stmt = $conn->prepare("UPDATE purchases SET redeemed = 1 WHERE id = ?");
            $stmt->bind_param("i", $purchase['id']);
            $stmt->execute();
            
            $success = "Command executed successfully for player " . htmlspecialchars($purchase['ign']) . ": " . htmlspecialchars($response);
        } else {
            $error = "Failed to connect to the Minecraft server";
        }
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redeem Minecraft Command</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Redeem Minecraft Command</h1>
        <p>Product: <?php echo htmlspecialchars($purchase['product_name']); ?></p>
        <p>Player: <?php echo htmlspecialchars($purchase['ign']); ?></p>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="post" action="">
                <p>Click the button below to execute the Minecraft command for player <?php echo htmlspecialchars($purchase['ign']); ?>.</p>
                <button type="submit" class="btn btn-primary">Execute Command</button>
            </form>
        <?php endif; ?>
        
        <a href="index.php" class="btn btn-secondary mt-3">Back to Home</a>
    </div>
</body>
</html>