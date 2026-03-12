<?php
// Original document generation functions
function generateCertificate($data) {
    // Code to generate certificate
}

function previewCertificate($data) {
    // Code to preview certificate
}

function downloadCertificate($data) {
    // Code to download certificate
}

// HTML/JavaScript to display the modal for fees
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Request</title>
    <script>
        function showFeesModal() {
            document.getElementById('feesModal').style.display = 'block';
        }
    </script>
</head>
<body>
    <button onclick="showFeesModal()">Request</button>
    <div id="feesModal" style="display:none;">
        <h2>Fees</h2>
        <p>Details about fees go here.</p>
        <button onclick="document.getElementById('feesModal').style.display='none';">Close</button>
    </div>
</body>
</html>
