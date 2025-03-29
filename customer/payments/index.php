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

// Get all payments with scheme details
$stmt = $db->prepare("
    SELECT 
        p.*,
        s.SchemeName,
        s.MonthlyPayment,
        sub.StartDate,
        sub.EndDate,
        sub.RenewalStatus,
        CASE 
            WHEN p.Status = 'Verified' THEN 'success'
            WHEN p.Status = 'Pending' THEN 'warning'
            ELSE 'danger'
        END as status_color
    FROM Payments p
    JOIN Schemes s ON p.SchemeID = s.SchemeID
    LEFT JOIN Subscriptions sub ON p.CustomerID = sub.CustomerID 
        AND p.SchemeID = sub.SchemeID
    WHERE p.CustomerID = ?
    ORDER BY p.SubmittedAt DESC
");
$stmt->execute([$userData['customer_id']]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN Status = 'Verified' THEN 1 ELSE 0 END) as verified_payments,
        SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as pending_payments,
        SUM(CASE WHEN Status = 'Rejected' THEN 1 ELSE 0 END) as rejected_payments,
        SUM(CASE WHEN Status = 'Verified' THEN Amount ELSE 0 END) as total_paid
    FROM Payments
    WHERE CustomerID = ?
");
$stmt->execute([$userData['customer_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
        }

        .payments-container {
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

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .stat-item {
            text-align: center;
            padding: 15px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #4a90e2;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .payment-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .payment-card:hover {
            transform: translateY(-5px);
        }

        .scheme-name {
            color: #4a90e2;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .payment-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }

        .payment-date {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .status-verified {
            background: #28a745;
            color: white;
        }

        .status-pending {
            background: #ffc107;
            color: #000;
        }

        .status-rejected {
            background: #dc3545;
            color: white;
        }

        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 15px 0;
        }

        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }

        .detail-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .detail-value {
            font-weight: bold;
            color: #2c3e50;
        }

        .payment-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-view {
            background: #4a90e2;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-view:hover {
            background: #357abd;
            color: white;
        }

        .btn-payment {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-payment:hover {
            background: #218838;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .empty-state i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="payments-container">
            <div class="container">
                <div class="payment-header text-center">
                    <h2><i class="fas fa-money-bill-wave"></i> Payment History</h2>
                    <p class="mb-0">Track all your payments and their status</p>
                </div>

                <?php if (empty($payments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>No Payments Found</h3>
                        <p>You haven't made any payments yet.</p>
                        <a href="schemes.php" class="btn btn-primary">
                            <i class="fas fa-gem"></i> Explore Schemes
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['total_payments']; ?></div>
                                    <div class="stat-label">Total Payments</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['verified_payments']; ?></div>
                                    <div class="stat-label">Verified Payments</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['pending_payments']; ?></div>
                                    <div class="stat-label">Pending Payments</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value">₹<?php echo number_format($stats['total_paid'], 2); ?></div>
                                    <div class="stat-label">Total Amount Paid</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-card">
                            <div class="scheme-name">
                                <?php echo htmlspecialchars($payment['SchemeName']); ?>
                            </div>

                            <div class="payment-details">
                                <div class="detail-item">
                                    <div class="detail-label">Amount</div>
                                    <div class="payment-amount">
                                        ₹<?php echo number_format($payment['Amount'], 2); ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Status</div>
                                    <div class="detail-value">
                                        <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                            <?php echo $payment['Status']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Submitted Date</div>
                                    <div class="detail-value">
                                        <?php echo date('M d, Y', strtotime($payment['SubmittedAt'])); ?>
                                    </div>
                                </div>
                                <?php if ($payment['VerifiedAt']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Verified Date</div>
                                        <div class="detail-value">
                                            <?php echo date('M d, Y', strtotime($payment['VerifiedAt'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($payment['ScreenshotURL']): ?>
                                <div class="payment-actions">
                                    <a href="<?php echo htmlspecialchars($payment['ScreenshotURL']); ?>"
                                        target="_blank"
                                        class="btn btn-view">
                                        <i class="fas fa-image"></i> View Screenshot
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>