<?php
include 'config.php';

$sql = "SELECT * FROM listings WHERE amount > 0 AND status = 'active' ORDER BY created_at DESC";
$result = $conn->query($sql);

$listings = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $listings[] = $row;
    }
}

echo json_encode($listings);
$conn->close();
?>