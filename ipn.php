<?php
include 'config.php';
include 'gateway-config.php';

// Read POST data
$raw_post_data = file_get_contents('php://input');
$raw_post_array = explode('&', $raw_post_data);
$myPost = array();
foreach ($raw_post_array as $keyval) {
    $keyval = explode ('=', $keyval);
    if (count($keyval) == 2)
        $myPost[$keyval[0]] = urldecode($keyval[1]);
}

// Read the post from PayPal system and add 'cmd'
$req = 'cmd=_notify-validate';
if (function_exists('get_magic_quotes_gpc')) {
    $get_magic_quotes_exists = true;
}
foreach ($myPost as $key => $value) {
    if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
        $value = urlencode(stripslashes($value));
    } else {
        $value = urlencode($value);
    }
    $req .= "&$key=$value";
}

// Post IPN data back to PayPal to validate
$ch = curl_init(PAYPAL_URL);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
$res = curl_exec($ch);

if ( ! $res) {
    $errno = curl_errno($ch);
    $errstr = curl_error($ch);
    curl_close($ch);
    exit;
}

$info = curl_getinfo($ch);
curl_close($ch);

if ($info['http_code'] != 200) exit;

// Verify the response
if (strcmp ($res, "VERIFIED") == 0) {
    // Check that txn_id has not been previously processed
    // Check that receiver_email is your Primary PayPal email
    // Check that payment_amount/payment_currency are correct
    // Process payment

    $listing_id = $_POST['custom'];
    $txn_id = $_POST['txn_id'];
    $payment_status = $_POST['payment_status'];
    $amount = $_POST['mc_gross'];

    if ($payment_status == 'Completed') {
        // Update listing status
        $sql = "UPDATE listings SET status = 'completed' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $listing_id);
        $stmt->execute();

        // Transfer coins (in a real app, you'd have more complex logic here)
        // This is a simplified example
        $sql = "SELECT user_id, amount FROM listings WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $listing_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $listing = $result->fetch_assoc();

        $seller_id = $listing['user_id'];
        $coin_amount = $listing['amount'];

        // Deduct coins from seller
        $sql = "UPDATE users SET balance = balance - ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $coin_amount, $seller_id);
        $stmt->execute();

        // Add coins to buyer (assuming buyer_id is stored in the session)
        $buyer_id = 1; // In a real app, get this from the session
        $sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $coin_amount, $buyer_id);
        $stmt->execute();

        // Log the transaction
        $sql = "INSERT INTO transactions (listing_id, buyer_id, amount, price, txn_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiids", $listing_id, $buyer_id, $coin_amount, $amount, $txn_id);
        $stmt->execute();
    }
} else if (strcmp ($res, "INVALID") == 0) {
    // Log for manual investigation
}
?>