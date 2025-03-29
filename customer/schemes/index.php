<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path="../";
$current_page="schemes";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get active schemes
$stmt = $db->prepare("
    SELECT s.*, 
           CASE WHEN sub.SubscriptionID IS NOT NULL THEN 1 ELSE 0 END as is_subscribed,
           sub.SubscriptionID,
           sub.StartDate,
           sub.EndDate,
           sub.RenewalStatus
    FROM Schemes s
    LEFT JOIN Subscriptions sub ON s.SchemeID = sub.SchemeID 
        AND sub.CustomerID = ? AND sub.RenewalStatus = 'Active'
    WHERE s.Status = 'Active'
    ORDER BY s.MonthlyPayment ASC
");
$stmt->execute([$userData['customer_id']]);
$schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get customer's active subscriptions
$stmt = $db->prepare("
    SELECT s.*, sch.SchemeName, sch.MonthlyPayment
    FROM Subscriptions s
    JOIN Schemes sch ON s.SchemeID = sch.SchemeID
    WHERE s.CustomerID = ? AND s.RenewalStatus = 'Active'
    ORDER BY s.StartDate DESC
");
$stmt->execute([$userData['customer_id']]);
$activeSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schemes - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
        }

        .schemes-container {
            padding: 20px;
        }

        .scheme-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .scheme-card:hover {
            transform: translateY(-5px);
        }

        .scheme-header {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .scheme-price {
            font-size: 2rem;
            font-weight: bold;
            color: #4a90e2;
        }

        .scheme-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .scheme-features li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .scheme-features li:last-child {
            border-bottom: none;
        }

        .scheme-features i {
            color: #4a90e2;
            margin-right: 10px;
        }

        .btn-subscribe {
            background: #4a90e2;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-subscribe:hover {
            background: #357abd;
            color: white;
        }

        .btn-subscribed {
            background: #28a745;
            color: white;
        }

        .btn-subscribed:hover {
            background: #218838;
            color: white;
        }

        .subscription-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .scheme-duration {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .total-amount {
            font-size: 1.2rem;
            color: #28a745;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="schemes-container">
            <div class="container">
                <div class="scheme-header text-center">
                    <h2><i class="fas fa-gem"></i> Available Schemes</h2>
                    <p class="mb-0">Choose the perfect scheme for your financial goals</p>
                </div>

                <?php if (!empty($activeSubscriptions)): ?>
                    <div class="alert alert-info mb-4">
                        <h5><i class="fas fa-info-circle"></i> Your Active Subscriptions</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Scheme</th>
                                        <th>Monthly Payment</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeSubscriptions as $subscription): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subscription['SchemeName']); ?></td>
                                            <td>₹<?php echo number_format($subscription['MonthlyPayment'], 2); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($subscription['StartDate'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($subscription['EndDate'])); ?></td>
                                            <td>
                                                <span class="badge bg-success">Active</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <?php foreach ($schemes as $scheme): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="scheme-card position-relative">
                                <?php if ($scheme['is_subscribed']): ?>
                                    <span class="subscription-badge">
                                        <i class="fas fa-check-circle"></i> Subscribed
                                    </span>
                                <?php endif; ?>

                                <h4 class="mb-3"><?php echo htmlspecialchars($scheme['SchemeName']); ?></h4>
                                <div class="scheme-price mb-3">
                                    ₹<?php echo number_format($scheme['MonthlyPayment'], 2); ?>
                                    <span class="scheme-duration">/month</span>
                                </div>

                                <div class="scheme-features">
                                    <li>
                                        <i class="fas fa-calendar-check"></i>
                                        Duration: <?php echo $scheme['TotalPayments']; ?> months
                                    </li>
                                    <li>
                                        <i class="fas fa-calculator"></i>
                                        Total Amount:
                                        <span class="total-amount">
                                            ₹<?php echo number_format($scheme['MonthlyPayment'] * $scheme['TotalPayments'], 2); ?>
                                        </span>
                                    </li>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <a href="view_benefits.php?scheme_id=<?php echo $scheme['SchemeID']; ?>"
                                        class="btn btn-outline-primary">
                                        <i class="fas fa-gift"></i> View Benefits
                                    </a>
                                </div>

                                <?php if ($scheme['is_subscribed']): ?>
                                    <button class="btn btn-subscribed" disabled>
                                        <i class="fas fa-check-circle"></i> Already Subscribed
                                    </button>
                                <?php else: ?>
                                    <a href="subscribe.php?scheme_id=<?php echo $scheme['SchemeID']; ?>"
                                        class="btn btn-subscribe">
                                        <i class="fas fa-play-circle"></i> Subscribe Now
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>