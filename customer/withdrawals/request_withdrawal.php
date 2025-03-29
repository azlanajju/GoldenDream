<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path="../";
$current_page="withdrawals";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total balance from Balances table
$stmt = $db->prepare("
    SELECT SUM(BalanceAmount) as total_balance 
    FROM Balances 
    WHERE UserID = ? AND UserType = 'Customer'
");
$stmt->execute([$userData['customer_id']]);
$balance_result = $stmt->fetch(PDO::FETCH_ASSOC);
$available_balance = $balance_result['total_balance'] ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $remarks = trim($_POST['remarks'] ?? '');

    // Validate amount
    if ($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } elseif ($amount > $available_balance) {
        $error = "Amount cannot exceed your available balance of ₹" . number_format($available_balance, 2);
    } else {
        try {
            $db->beginTransaction();

            // Insert withdrawal request
            $stmt = $db->prepare("
                INSERT INTO Withdrawals (UserID, UserType, Amount, Status, Remarks)
                VALUES (?, 'Customer', ?, 'Pending', ?)
            ");
            $stmt->execute([$userData['customer_id'], $amount, $remarks]);

            // Create notification for admin
            $stmt = $db->prepare("
                INSERT INTO Notifications (UserID, UserType, Message)
                SELECT AdminID, 'Admin', CONCAT('New withdrawal request of ₹', ?, ' from customer ', ?)
                FROM Admins
                WHERE Role = 'SuperAdmin'
            ");
            $stmt->execute([$amount, $customer['Name']]);

            $db->commit();
            $success = "Withdrawal request submitted successfully!";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error submitting withdrawal request. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Withdrawal - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
        }

        .withdrawal-container {
            padding: 20px;
        }

        .withdrawal-header {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .balance-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .balance-amount {
            font-size: 2.5rem;
            font-weight: bold;
            color: #4a90e2;
            margin: 15px 0;
        }

        .withdrawal-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .form-label {
            font-weight: 500;
        }

        .alert {
            border-radius: 10px;
        }
    </style>
</head>

<body>
    <?php include '../c_includes/topbar.php'; ?>
    <?php include '../c_includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="withdrawal-container">
            <div class="container">
                <div class="withdrawal-header text-center">
                    <h2><i class="fas fa-money-bill-wave"></i> Request Withdrawal</h2>
                    <p class="mb-0">Submit your withdrawal request</p>
                </div>

                <!-- Balance Card -->
                <div class="balance-card">
                    <h4>Available Balance</h4>
                    <div class="balance-amount">₹<?php echo number_format($available_balance, 2); ?></div>
                </div>

                <?php if ($available_balance <= 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> You don't have any available balance for withdrawal.
                    </div>
                <?php else: ?>
                    <!-- Withdrawal Form -->
                    <div class="withdrawal-form">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Amount (₹)</label>
                                <input type="number"
                                    class="form-control"
                                    name="amount"
                                    step="0.01"
                                    min="0"
                                    max="<?php echo $available_balance; ?>"
                                    required>
                                <div class="form-text">
                                    Maximum amount: ₹<?php echo number_format($available_balance, 2); ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Remarks (Optional)</label>
                                <textarea class="form-control"
                                    name="remarks"
                                    rows="3"
                                    placeholder="Add any additional information here..."></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                                <a href="withdrawals.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>