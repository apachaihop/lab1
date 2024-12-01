<?php
// Use cURL instead of file_get_contents for better control
$ch = curl_init();

// First login to get a valid session
$loginData = http_build_query([
    'username' => 'test7',
    'password' => '30102003San!'
]);

curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost/lab1/src/auth/login.php',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $loginData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => 'cookies.txt',
    CURLOPT_COOKIEFILE => 'cookies.txt',
    CURLOPT_FOLLOWLOCATION => true
]);

$loginResponse = curl_exec($ch);

// Try different XSS payloads
$xssPayloads = [
    // Basic XSS
    "<script>alert('XSS1')</script>",
    // Event handler XSS
    "<img src='x' onerror='alert(\"XSS2\")'>",
    // JavaScript protocol
    "<a href='javascript:alert(\"XSS3\")'>Click me</a>",
    // Encoded XSS
    "&#60;script&#62;alert('XSS4')&#60;/script&#62;",
    // DOM XSS
    "<div onmouseover='alert(\"XSS5\")'>Hover me</div>"
];

foreach ($xssPayloads as $index => $payload) {
    // First, submit the payload
    $data = http_build_query([
        'title' => $payload,
        'description' => "XSS Test " . ($index + 1),
        'status' => 'open'
    ]);

    curl_setopt_array($ch, [
        CURLOPT_URL => 'http://localhost/lab1/src/issues.php',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data
    ]);

    $submitResponse = curl_exec($ch);

    // Then, fetch the issues page to see if the payload was stored
    curl_setopt_array($ch, [
        CURLOPT_URL => 'http://localhost/lab1/src/issues.php',
        CURLOPT_POST => false,
        CURLOPT_POSTFIELDS => null
    ]);

    $viewResponse = curl_exec($ch);

    echo "\nTesting Payload " . ($index + 1) . ":\n";
    echo "Original Payload: " . htmlspecialchars($payload) . "\n";

    // Check if the raw payload exists (vulnerability)
    $rawExists = strpos($viewResponse, $payload) !== false;
    echo "Raw payload found (vulnerability): " . ($rawExists ? "YES - VULNERABLE!" : "No - Secure") . "\n";

    // Check if the escaped payload exists (secure)
    $escapedPayload = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
    $escapedExists = strpos($viewResponse, $escapedPayload) !== false;
    echo "Escaped payload found (secure): " . ($escapedExists ? "Yes" : "No") . "\n";

    // Extract the actual stored value if possible
    if (preg_match('/<td[^>]*>(.+?)<\/td>/', $viewResponse, $matches)) {
        echo "Stored value: " . htmlspecialchars($matches[1]) . "\n";
    }

    echo "----------------------------------------\n";
}

curl_close($ch);

// Also check the database directly if possible
try {
    $conn = new mysqli('localhost', 'root', '', 'lab6');
    $result = $conn->query("SELECT title FROM Issues ORDER BY issue_id DESC LIMIT 5");

    echo "\nDatabase Check:\n";
    while ($row = $result->fetch_assoc()) {
        echo "Stored title: " . htmlspecialchars($row['title']) . "\n";
    }
    $conn->close();
} catch (Exception $e) {
    echo "Database check failed: " . $e->getMessage() . "\n";
}
