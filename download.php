<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access denied. Please log in.');
}

if (isset($_GET['token'])) {
    $token = $_GET['token'];
try {
    $pdo = new PDO("mysql:host=localhost;dbname=cybercoin_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.download_file, pu.id AS purchase_id
            FROM products p 
            JOIN purchases pu ON p.id = pu.product_id 
            WHERE pu.download_token = ? AND pu.user_id = ? AND pu.download_count = 0
            FOR UPDATE
        ");
        $stmt->execute([$token, $_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $filePath = $result['download_file'];
            if (file_exists($filePath)) {
                // Increment the download count to prevent future downloads
                $updateStmt = $pdo->prepare("UPDATE purchases SET download_count = 1, last_download_date = NOW() WHERE id = ?");
                $updateStmt->execute([$result['purchase_id']]);

                $pdo->commit();

                // Set headers for file download
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
                header('Content-Length: ' . filesize($filePath));
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Expires: 0');
                
                // Output file contents
                readfile($filePath);
                exit;
            } else {
                throw new Exception('File not found on server.');
            }
        } else {
            throw new Exception('Invalid or expired download link.');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Download error: ' . $e->getMessage());
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Download Error</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading">Download Error</h4>
                    <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
                    <hr>
                    <p class="mb-0">If you believe this is an error, please contact our support team.</p>
                </div>
                <a href="shop.php" class="btn btn-primary">Return to Shop</a>
            </div>
        </body>
        </html>
        <?php
    }
} else {
    http_response_code(400);
    echo 'Invalid request. Download token is missing.';
}
?>