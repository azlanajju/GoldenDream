<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "payments";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all payments with scheme details and unpaid installments
$stmt = $db->prepare("
    SELECT 
        p.*,
        s.SchemeName,
        s.MonthlyPayment,
        sub.StartDate,
        sub.EndDate,
        sub.RenewalStatus,
        i.InstallmentNumber,
        i.DrawDate,
        CASE 
            WHEN p.Status = 'Verified' THEN 'success'
            WHEN p.Status = 'Pending' THEN 'warning'
            ELSE 'danger'
        END as status_color
    FROM Payments p
    JOIN Schemes s ON p.SchemeID = s.SchemeID
    LEFT JOIN Subscriptions sub ON p.CustomerID = sub.CustomerID 
        AND p.SchemeID = sub.SchemeID
    LEFT JOIN Installments i ON p.InstallmentID = i.InstallmentID
    WHERE p.CustomerID = ?
    ORDER BY p.SubmittedAt DESC
");
$stmt->execute([$userData['customer_id']]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active subscriptions with unpaid installments
$stmt = $db->prepare("
    SELECT 
        s.SchemeID,
        s.SchemeName,
        s.MonthlyPayment,
        i.InstallmentID,
        i.InstallmentNumber,
        i.Amount,
        i.DrawDate,
        sub.StartDate,
        sub.EndDate
    FROM Subscriptions sub
    JOIN Schemes s ON sub.SchemeID = s.SchemeID
    JOIN Installments i ON s.SchemeID = i.SchemeID
    LEFT JOIN Payments p ON i.InstallmentID = p.InstallmentID 
        AND p.CustomerID = sub.CustomerID
    WHERE sub.CustomerID = ? 
    AND sub.RenewalStatus = 'Active'
    AND (p.PaymentID IS NULL OR p.Status = 'Rejected')
    ORDER BY i.DrawDate ASC
");
$stmt->execute([$userData['customer_id']]);
$unpaid_installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        :root {
            --dark-bg: #1A1D21;
            --card-bg: #222529;
            --accent-green: #2F9B7F;
            --text-primary: rgba(255, 255, 255, 0.9);
            --text-secondary: rgba(255, 255, 255, 0.7);
            --border-color: rgba(255, 255, 255, 0.05);
        }

        body {
            background: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }

        .payments-container {
            padding: 24px;
            margin-top: 70px;
        }

        .payment-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 40px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        /* .payment-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
        } */

        .payment-header h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
        }

        .payment-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
            position: relative;
        }

        .stats-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }

        .stat-item {
            text-align: center;
            padding: 16px;
            background: rgba(47, 155, 127, 0.1);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--accent-green);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .payment-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .payment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .payment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-green), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .payment-card:hover::before {
            opacity: 1;
        }

        .scheme-name {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .scheme-name i {
            color: var(--accent-green);
        }

        .payment-amount {
            font-size: 24px;
            font-weight: 600;
            color: var(--accent-green);
        }

        .payment-date {
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-verified {
            background: rgba(47, 155, 127, 0.1);
            color: var(--accent-green);
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 20px 0;
        }

        .detail-item {
            background: rgba(47, 155, 127, 0.1);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .detail-label {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 16px;
        }

        .payment-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn-view,
        .btn-payment {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .btn-view {
            background: rgba(47, 155, 127, 0.1);
            color: var(--accent-green);
            border: 1px solid var(--accent-green);
        }

        .btn-view:hover {
            background: var(--accent-green);
            color: white;
        }

        .btn-payment {
            background: var(--accent-green);
            color: white;
            border: none;
        }

        .btn-payment:hover {
            background: #248c6f;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .payment-code {
            background: rgba(47, 155, 127, 0.1);
            padding: 8px 12px;
            border-radius: 6px;
            font-family: monospace;
            color: var(--accent-green);
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        @media (max-width: 768px) {
            .payments-container {
                margin-left: 70px;
                padding: 16px;
            }

            .payment-header {
                padding: 30px 20px;
            }

            .payment-card {
                padding: 20px;
            }

            .payment-details {
                grid-template-columns: 1fr;
            }
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
        }

        .modal-title {
            color: var(--text-primary);
        }

        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .screenshot-link {
            color: var(--accent-green);
            cursor: pointer;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .screenshot-link:hover {
            color: #248c6f;
        }

        .screenshot-modal img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
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
                    <?php if (!empty($unpaid_installments)): ?>
                        <div class="mt-4">
                            <a href="make_payment.php" class="btn btn-payment">
                                <i class="fas fa-plus-circle"></i> Make New Payment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($payments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>No Payments Found</h3>
                        <p>You haven't made any payments yet.</p>
                        <?php if (!empty($unpaid_installments)): ?>
                            <a href="make_payment.php" class="btn btn-payment">
                                <i class="fas fa-plus-circle"></i> Make New Payment
                            </a>
                        <?php else: ?>
                            <a href="../schemes" class="btn btn-view">
                                <i class="fas fa-gem"></i> Explore Schemes
                            </a>
                        <?php endif; ?>
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
                                    <a href="#" class="screenshot-link" data-bs-toggle="modal" data-bs-target="#screenshotModal"
                                        data-screenshot="<?php echo htmlspecialchars($payment['ScreenshotURL']); ?>">
                                        <i class="fas fa-image"></i> View Screenshot
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="payment-actions">
                                    <span class="text-secondary">No screenshot</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Screenshot Modal -->
    <div class="modal fade screenshot-modal" id="screenshotModal" tabindex="-1" aria-labelledby="screenshotModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="screenshotModalLabel">Payment Screenshot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" alt="Payment Screenshot" id="screenshotImage">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-back" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const screenshotLinks = document.querySelectorAll('.screenshot-link');
            const screenshotModal = document.getElementById('screenshotModal');
            const screenshotImage = document.getElementById('screenshotImage');

            screenshotLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const screenshotUrl = this.getAttribute('data-screenshot');
                    screenshotImage.src = `../../${screenshotUrl}`;
                });
            });
        });
    </script>
</body>

</html>