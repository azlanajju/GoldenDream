<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path="../";
$current_page="subscriptions";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all subscriptions with scheme details and payment information
$stmt = $db->prepare("
    SELECT 
        s.*,
        sch.SchemeName,
        sch.MonthlyPayment,
        sch.Description,
        sch.TotalPayments,
        (SELECT COUNT(*) 
         FROM Payments p 
         WHERE p.CustomerID = s.CustomerID 
         AND p.SchemeID = s.SchemeID 
         AND p.Status = 'Verified') as paid_installments,
        (SELECT COUNT(*) 
         FROM Payments p 
         WHERE p.CustomerID = s.CustomerID 
         AND p.SchemeID = s.SchemeID) as total_installments,
        (SELECT COUNT(*) 
         FROM Payments p 
         WHERE p.CustomerID = s.CustomerID 
         AND p.SchemeID = s.SchemeID 
         AND p.Status = 'Pending') as pending_installments
    FROM Subscriptions s
    JOIN Schemes sch ON s.SchemeID = sch.SchemeID
    WHERE s.CustomerID = ?
    ORDER BY 
        CASE 
            WHEN s.RenewalStatus = 'Active' THEN 1
            ELSE 2
        END,
        s.StartDate DESC
");
$stmt->execute([$userData['customer_id']]);
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get next payment due date for active subscriptions
$stmt = $db->prepare("
    SELECT 
        p.CustomerID,
        p.SchemeID,
        MIN(p.SubmittedAt) as next_payment_date
    FROM Payments p
    WHERE p.CustomerID = ? 
    AND p.Status = 'Pending'
    GROUP BY p.CustomerID, p.SchemeID
");
$stmt->execute([$userData['customer_id']]);
$nextPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of next payment dates
$nextPaymentMap = [];
foreach ($nextPayments as $payment) {
    $key = $payment['CustomerID'] . '_' . $payment['SchemeID'];
    $nextPaymentMap[$key] = $payment['next_payment_date'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subscriptions - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
        }

        .subscriptions-container {
            padding: 20px;
        }

        .subscription-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .subscription-card:hover {
            transform: translateY(-5px);
        }

        .subscription-header {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .scheme-name {
            color: #4a90e2;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .subscription-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
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

        .progress {
            height: 10px;
            border-radius: 5px;
            margin: 15px 0;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .status-active {
            background: #28a745;
            color: white;
        }

        .status-expired {
            background: #dc3545;
            color: white;
        }

        .status-cancelled {
            background: #6c757d;
            color: white;
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

        .payment-due {
            color: #dc3545;
            font-weight: 600;
        }

        .payment-info {
            background: #e3fcef;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        .payment-info i {
            color: #28a745;
            margin-right: 10px;
        }

        .subscription-tabs {
            margin-bottom: 20px;
        }

        .subscription-tabs .nav-link {
            color: #6c757d;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            margin-right: 10px;
        }

        .subscription-tabs .nav-link.active {
            background: #4a90e2;
            color: white;
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="subscriptions-container">
            <div class="container">
                <div class="subscription-header text-center">
                    <h2><i class="fas fa-list"></i> My Subscriptions</h2>
                    <p class="mb-0">Track all your active and past subscriptions</p>
                </div>

                <?php if (empty($subscriptions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>No Subscriptions Found</h3>
                        <p>You haven't subscribed to any schemes yet.</p>
                        <a href="schemes.php" class="btn btn-primary">
                            <i class="fas fa-gem"></i> Explore Schemes
                        </a>
                    </div>
                <?php else: ?>
                    <ul class="nav subscription-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#active">
                                <i class="fas fa-check-circle"></i> Active
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#completed">
                                <i class="fas fa-history"></i> Completed
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="active">
                            <?php foreach ($subscriptions as $subscription): ?>
                                <?php if ($subscription['RenewalStatus'] === 'Active'): ?>
                                    <div class="subscription-card">
                                        <div class="scheme-name">
                                            <?php echo htmlspecialchars($subscription['SchemeName']); ?>
                                        </div>

                                        <div class="subscription-details">
                                            <div class="detail-item">
                                                <div class="detail-label">Monthly Payment</div>
                                                <div class="detail-value">
                                                    ₹<?php echo number_format($subscription['MonthlyPayment'], 2); ?>
                                                </div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Start Date</div>
                                                <div class="detail-value">
                                                    <?php echo date('M d, Y', strtotime($subscription['StartDate'])); ?>
                                                </div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">End Date</div>
                                                <div class="detail-value">
                                                    <?php echo date('M d, Y', strtotime($subscription['EndDate'])); ?>
                                                </div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Status</div>
                                                <div class="detail-value">
                                                    <span class="status-badge status-active">
                                                        Active
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="progress">
                                            <?php
                                            $percentage = ($subscription['total_installments'] > 0)
                                                ? ($subscription['paid_installments'] / $subscription['total_installments']) * 100
                                                : 0;
                                            ?>
                                            <div class="progress-bar" role="progressbar"
                                                style="width: <?php echo $percentage; ?>%"
                                                aria-valuenow="<?php echo $percentage; ?>"
                                                aria-valuemin="0"
                                                aria-valuemax="100">
                                                <?php echo round($percentage); ?>%
                                            </div>
                                        </div>

                                        <?php
                                        $nextPaymentKey = $subscription['CustomerID'] . '_' . $subscription['SchemeID'];
                                        if (isset($nextPaymentMap[$nextPaymentKey])):
                                        ?>
                                            <div class="payment-info">
                                                <i class="fas fa-info-circle"></i>
                                                Next payment due on:
                                                <span class="payment-due">
                                                    <?php echo date('M d, Y', strtotime($nextPaymentMap[$nextPaymentKey])); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <div>
                                                <small class="text-muted">
                                                    <?php echo $subscription['paid_installments']; ?> of <?php echo $subscription['total_installments']; ?> installments paid
                                                </small>
                                            </div>
                                            <div>
                                                <?php if ($subscription['pending_installments'] > 0): ?>
                                                    <a href="make_payment.php?scheme_id=<?php echo $subscription['SchemeID']; ?>"
                                                        class="btn btn-payment me-2">
                                                        <i class="fas fa-money-bill-wave"></i> Make Payment
                                                    </a>
                                                <?php endif; ?>
                                                <a href="view_benefits.php?scheme_id=<?php echo $subscription['SchemeID']; ?>"
                                                    class="btn btn-view">
                                                    <i class="fas fa-eye"></i> View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <div class="tab-pane fade" id="completed">
                            <?php foreach ($subscriptions as $subscription): ?>
                                <?php if ($subscription['RenewalStatus'] !== 'Active'): ?>
                                    <div class="subscription-card">
                                        <div class="scheme-name">
                                            <?php echo htmlspecialchars($subscription['SchemeName']); ?>
                                        </div>

                                        <div class="subscription-details">
                                            <div class="detail-item">
                                                <div class="detail-label">Monthly Payment</div>
                                                <div class="detail-value">
                                                    ₹<?php echo number_format($subscription['MonthlyPayment'], 2); ?>
                                                </div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Start Date</div>
                                                <div class="detail-value">
                                                    <?php echo date('M d, Y', strtotime($subscription['StartDate'])); ?>
                                                </div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">End Date</div>
                                                <div class="detail-value">
                                                    <?php echo date('M d, Y', strtotime($subscription['EndDate'])); ?>
                                                </div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Status</div>
                                                <div class="detail-value">
                                                    <span class="status-badge status-<?php echo strtolower($subscription['RenewalStatus']); ?>">
                                                        <?php echo $subscription['RenewalStatus']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="progress">
                                            <?php
                                            $percentage = ($subscription['total_installments'] > 0)
                                                ? ($subscription['paid_installments'] / $subscription['total_installments']) * 100
                                                : 0;
                                            ?>
                                            <div class="progress-bar" role="progressbar"
                                                style="width: <?php echo $percentage; ?>%"
                                                aria-valuenow="<?php echo $percentage; ?>"
                                                aria-valuemin="0"
                                                aria-valuemax="100">
                                                <?php echo round($percentage); ?>%
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <div>
                                                <small class="text-muted">
                                                    <?php echo $subscription['paid_installments']; ?> of <?php echo $subscription['total_installments']; ?> installments paid
                                                </small>
                                            </div>
                                            <div>
                                                <a href="view_benefits.php?scheme_id=<?php echo $subscription['SchemeID']; ?>"
                                                    class="btn btn-view">
                                                    <i class="fas fa-eye"></i> View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>