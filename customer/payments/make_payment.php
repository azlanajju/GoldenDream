<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path="../";
$current_page="payments";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get subscription ID from URL
$subscription_id = isset($_GET['subscription_id']) ? intval($_GET['subscription_id']) : 0;

// Get subscription details
$stmt = $db->prepare("
    SELECT s.*, sch.SchemeName, sch.MonthlyPayment
    FROM Subscriptions s
    JOIN Schemes sch ON s.SchemeID = sch.SchemeID
    WHERE s.SubscriptionID = ? AND s.CustomerID = ? AND s.RenewalStatus = 'Active'
");
$stmt->execute([$subscription_id, $userData['customer_id']]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription) {
    header("Location: subscriptions.php");
    exit;
}

// Get payment QR details
$stmt = $db->prepare("SELECT * FROM PaymentQR WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$payment_qr = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $payment_code = intval($_POST['payment_code']);

    // Validate amount
    if ($amount <= 0) {
        $error_message = "Please enter a valid amount.";
    } elseif ($amount != $subscription['MonthlyPayment']) {
        $error_message = "Amount must match the monthly payment of ₹" . number_format($subscription['MonthlyPayment'], 2);
    } else {
        // Handle file upload
        $screenshot = $_FILES['screenshot'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($screenshot['type'], $allowed_types)) {
            $error_message = "Invalid file type. Please upload JPG or PNG image.";
        } elseif ($screenshot['size'] > $max_size) {
            $error_message = "File size too large. Maximum size is 5MB.";
        } else {
            try {
                // Start transaction
                $db->beginTransaction();

                // Create upload directory if it doesn't exist
                $upload_dir = 'uploads/payments/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Generate unique filename
                $file_extension = pathinfo($screenshot['name'], PATHINFO_EXTENSION);
                $filename = 'payment_' . $userData['customer_id'] . '_' . time() . '.' . $file_extension;
                $filepath = $upload_dir . $filename;

                // Move uploaded file
                if (move_uploaded_file($screenshot['tmp_name'], $filepath)) {
                    // Insert payment record
                    $stmt = $db->prepare("
                        INSERT INTO Payments (CustomerID, SchemeID, Amount, PaymentCodeValue, ScreenshotURL, Status)
                        VALUES (?, ?, ?, ?, ?, 'Pending')
                    ");
                    $stmt->execute([
                        $userData['customer_id'],
                        $subscription['SchemeID'],
                        $amount,
                        $payment_code,
                        $filepath
                    ]);

                    // Create notification for admin
                    $stmt = $db->prepare("
                        INSERT INTO Notifications (UserID, UserType, Message)
                        SELECT AdminID, 'Admin', CONCAT('New payment of ₹', ?, ' from ', ?)
                        FROM Admins
                        WHERE Role = 'SuperAdmin'
                    ");
                    $stmt->execute([$amount, $customer['Name']]);

                    // Commit transaction
                    $db->commit();

                    $success_message = "Payment submitted successfully! Please wait for admin verification.";
                } else {
                    throw new Exception("Failed to upload file.");
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                $db->rollBack();
                $error_message = "An error occurred. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
        }

        .payment-container {
            padding: 20px;
        }

        .payment-header {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .payment-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .qr-section {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .qr-image {
            max-width: 200px;
            margin-bottom: 15px;
        }

        .bank-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .bank-detail-item {
            margin-bottom: 10px;
        }

        .bank-detail-label {
            font-weight: 500;
            color: #6c757d;
        }

        .bank-detail-value {
            color: #2c3e50;
        }

        .payment-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #dee2e6;
        }

        .form-control:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
        }

        .btn-submit {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: #218838;
            color: white;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #5a6268;
            color: white;
        }

        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .preview-image {
            max-width: 200px;
            margin-top: 10px;
            display: none;
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="payment-container">
            <div class="container">
                <div class="payment-header text-center">
                    <h2><i class="fas fa-money-bill-wave"></i> Make Payment</h2>
                    <p class="mb-0">Submit your monthly payment for <?php echo htmlspecialchars($subscription['SchemeName']); ?></p>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="payment-card">
                            <h4 class="mb-4">Payment Details</h4>
                            <div class="mb-4">
                                <div class="bank-detail-item">
                                    <div class="bank-detail-label">Scheme Name</div>
                                    <div class="bank-detail-value"><?php echo htmlspecialchars($subscription['SchemeName']); ?></div>
                                </div>
                                <div class="bank-detail-item">
                                    <div class="bank-detail-label">Monthly Payment</div>
                                    <div class="bank-detail-value">₹<?php echo number_format($subscription['MonthlyPayment'], 2); ?></div>
                                </div>
                                <div class="bank-detail-item">
                                    <div class="bank-detail-label">Payment Due Date</div>
                                    <div class="bank-detail-value"><?php echo date('M d, Y', strtotime($subscription['StartDate'])); ?></div>
                                </div>
                            </div>

                            <?php if ($payment_qr): ?>
                                <div class="qr-section">
                                    <h5 class="mb-3">Scan QR Code to Pay</h5>
                                    <img src="<?php echo htmlspecialchars($payment_qr['UPIQRImageURL']); ?>"
                                        alt="Payment QR Code"
                                        class="qr-image">
                                    <p class="text-muted">Scan this QR code using any UPI payment app</p>
                                </div>
                            <?php endif; ?>

                            <div class="bank-details">
                                <h5 class="mb-3">Bank Details</h5>
                                <div class="bank-detail-item">
                                    <div class="bank-detail-label">Account Name</div>
                                    <div class="bank-detail-value"><?php echo htmlspecialchars($customer['BankAccountName']); ?></div>
                                </div>
                                <div class="bank-detail-item">
                                    <div class="bank-detail-label">Account Number</div>
                                    <div class="bank-detail-value"><?php echo htmlspecialchars($customer['BankAccountNumber']); ?></div>
                                </div>
                                <div class="bank-detail-item">
                                    <div class="bank-detail-label">IFSC Code</div>
                                    <div class="bank-detail-value"><?php echo htmlspecialchars($customer['IFSCCode']); ?></div>
                                </div>
                                <div class="bank-detail-item">
                                    <div class="bank-detail-label">Bank Name</div>
                                    <div class="bank-detail-value"><?php echo htmlspecialchars($customer['BankName']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="payment-form">
                            <h4 class="mb-4">Submit Payment</h4>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="mb-4">
                                    <label for="amount" class="form-label">Payment Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number"
                                            class="form-control"
                                            id="amount"
                                            name="amount"
                                            value="<?php echo $subscription['MonthlyPayment']; ?>"
                                            readonly>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="payment_code" class="form-label">Payment Code</label>
                                    <input type="number"
                                        class="form-control"
                                        id="payment_code"
                                        name="payment_code"
                                        required>
                                    <div class="form-text">
                                        Enter the payment code shown in your payment app
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="screenshot" class="form-label">Payment Screenshot</label>
                                    <input type="file"
                                        class="form-control"
                                        id="screenshot"
                                        name="screenshot"
                                        accept="image/jpeg,image/png"
                                        required>
                                    <div class="form-text">
                                        Upload a screenshot of your payment confirmation
                                    </div>
                                    <img id="preview" class="preview-image">
                                </div>

                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-submit">
                                        <i class="fas fa-paper-plane"></i> Submit Payment
                                    </button>
                                    <a href="subscriptions.php" class="btn btn-cancel">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview uploaded image
        document.getElementById('screenshot').addEventListener('change', function(e) {
            const preview = document.getElementById('preview');
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>

</html>