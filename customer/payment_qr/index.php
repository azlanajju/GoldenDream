<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path="../";
$current_page="payment_qr";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers");
$stmt->execute();
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all payment QR details
$stmt = $db->prepare("
    SELECT pq.*, c.Name as CustomerName 
    FROM PaymentQR pq 
    JOIN Customers c ON pq.CustomerID = c.CustomerID
");
$stmt->execute();
$payment_qrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment QR - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
        }

        .qr-container {
            padding: 20px;
        }

        .qr-header {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .qr-card {
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
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }

        .bank-detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .bank-detail-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .bank-detail-value {
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .empty-state i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 20px;
        }

        .customer-name {
            color: #4a90e2;
            font-weight: 500;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="qr-container">
            <div class="container">
                <div class="qr-header text-center">
                    <h2><i class="fas fa-qrcode"></i> Payment QR Codes</h2>
                    <p class="mb-0">Available payment QR codes and bank details</p>
                </div>

                <?php if (!empty($payment_qrs)): ?>
                    <div class="row">
                        <?php foreach ($payment_qrs as $payment_qr): ?>
                            <div class="col-md-6 mb-4">
                                <div class="qr-card">
                                    <div class="customer-name">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($payment_qr['CustomerName']); ?>
                                    </div>
                                    <div class="qr-section">
                                        <img src="<?php echo htmlspecialchars($payment_qr['UPIQRImageURL']); ?>"
                                            alt="Payment QR Code"
                                            class="qr-image">
                                        <p class="text-muted">Scan this QR code using any UPI payment app</p>
                                    </div>

                                    <div class="bank-details">
                                        <div class="bank-detail-item">
                                            <div class="bank-detail-label">Account Name</div>
                                            <div class="bank-detail-value"><?php echo htmlspecialchars($payment_qr['BankAccountName']); ?></div>
                                        </div>
                                        <div class="bank-detail-item">
                                            <div class="bank-detail-label">Account Number</div>
                                            <div class="bank-detail-value"><?php echo htmlspecialchars($payment_qr['BankAccountNumber']); ?></div>
                                        </div>
                                        <div class="bank-detail-item">
                                            <div class="bank-detail-label">IFSC Code</div>
                                            <div class="bank-detail-value"><?php echo htmlspecialchars($payment_qr['IFSCCode']); ?></div>
                                        </div>
                                        <div class="bank-detail-item">
                                            <div class="bank-detail-label">Bank Name</div>
                                            <div class="bank-detail-value"><?php echo htmlspecialchars($payment_qr['BankName']); ?></div>
                                        </div>
                                        <div class="bank-detail-item">
                                            <div class="bank-detail-label">Bank Branch</div>
                                            <div class="bank-detail-value"><?php echo htmlspecialchars($payment_qr['BankBranch']); ?></div>
                                        </div>
                                        <div class="bank-detail-item">
                                            <div class="bank-detail-label">Bank Address</div>
                                            <div class="bank-detail-value"><?php echo nl2br(htmlspecialchars($payment_qr['BankAddress'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-qrcode"></i>
                        <h3>No Payment QR Codes Available</h3>
                        <p>No payment QR codes have been set up yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>