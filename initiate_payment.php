<?php
include 'config.php';
include 'gateway-config.php';

$listing_id = $_POST['listing_id'];

$sql = "SELECT * FROM listings WHERE id = ? AND status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$result = $stmt->get_result();
$listing = $result->fetch_assoc();

if ($listing) {
    $paypal_url = PAYPAL_URL;
    $paypal_email = $listing['paypal_email'];
    $return_url = PAYPAL_RETURN_URL;
    $cancel_url = PAYPAL_CANCEL_URL;
    $notify_url = PAYPAL_NOTIFY_URL;

    $item_name = $listing['amount'] . " CryptoCoins";
    $item_amount = $listing['price'];

    $querystring = "?business=" . urlencode($paypal_email) .
                   "&item_name=" . urlencode($item_name) .
                   "&amount=" . urlencode($item_amount) .
                   "&currency_code=" . urlencode(PAYPAL_CURRENCY) .
                   "&return=" . urlencode(stripslashes($return_url)) .
                   "&cancel_return=" . urlencode(stripslashes($cancel_url)) .
                   "&notify_url=" . urlencode($notify_url) .
                   "&custom=" . urlencode($listing_id);

    echo json_encode(['success' => true, 'paypal_url' => $paypal_url . $querystring]);
} else {
    echo json_encode(['success' => false, 'error' => 'Listing not found or inactive']);
}

$stmt->close();
$conn->close();
?>