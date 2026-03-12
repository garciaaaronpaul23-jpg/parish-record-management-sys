<?php
// Certificate fees configuration
$fees = [
    'Baptism' => 150,
    'Confirmation' => 150,
    'Marriage' => 200,
    'Funeral' => 100,
];

// Function to calculate fees based on certificate type
function calculateFees($type) {
    global $fees;
    return isset($fees[$type]) ? $fees[$type] : 0;
}

// AJAX endpoint to handle fee calculations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $fee = calculateFees($type);
    echo json_encode(['fee' => $fee]);
    exit;
}

// Function to store fees in the database
function storeFeeInDatabase($type, $amount) {
    // Assuming you have a PDO connection $pdo
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO certificate_fees (certificate_type, amount) VALUES (?, ?)");
    $stmt->execute([$type, $amount]);
}

// Integrate with frontend to trigger modal on successful request
if (isset($_POST['request_successful'])) {
    echo "<script>$('#feeModal').modal('show');</script>";
}
?>

<!-- Modal HTML for displaying fees and payment method selection -->
<div class="modal fade" id="feeModal" tabindex="-1" role="dialog" aria-labelledby="feeModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="feeModalLabel">Certificate Fees</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Please choose your payment method.</p>
        <h6>Fee Breakdown:</h6>
        <ul>
            <li>Baptism: $150</li>
            <li>Confirmation: $150</li>
            <li>Marriage: $200</li>
            <li>Funeral: $100</li>
        </ul>
        <select id="payment-method">
            <option value="credit_card">Credit Card</option>
            <option value="paypal">PayPal</option>
            <option value="bank_transfer">Bank Transfer</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">Proceed to Payment</button>
      </div>
    </div>
  </div>
</div>