<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "schemes";
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
           sub.StartDate as subscription_start_date,
           sub.EndDate,
           sub.RenewalStatus,
           s.StartDate as scheme_start_date
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

        .schemes-container {
            padding: 24px;
            margin-top: 70px;
        }

        .scheme-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 40px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .scheme-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
        }

        .scheme-header h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
        }

        .scheme-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
            position: relative;
        }

        .scheme-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .scheme-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .scheme-card::before {
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

        .scheme-card:hover::before {
            opacity: 1;
        }

        .scheme-price {
            font-size: 32px;
            font-weight: 600;
            color: var(--accent-green);
            margin-bottom: 16px;
            display: flex;
            align-items: baseline;
            gap: 8px;
        }

        .scheme-duration {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: normal;
        }

        .scheme-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .scheme-features li {
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .scheme-features li:last-child {
            border-bottom: none;
        }

        .scheme-features i {
            color: var(--accent-green);
            font-size: 16px;
        }

        .total-amount {
            color: var(--accent-green);
            font-weight: 600;
        }

        .subscription-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(47, 155, 127, 0.1);
            color: var(--accent-green);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-subscribe {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-subscribe:hover {
            background: #248c6f;
            transform: translateY(-2px);
        }

        .btn-subscribed {
            background: rgba(47, 155, 127, 0.1);
            color: var(--accent-green);
            cursor: not-allowed;
        }

        .btn-subscribed:hover {
            background: rgba(47, 155, 127, 0.15);
            transform: none;
        }

        .btn-outline-primary {
            border: 1px solid var(--accent-green);
            color: var(--accent-green);
            background: transparent;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: rgba(47, 155, 127, 0.1);
            color: var(--accent-green);
        }

        /* Active Subscriptions Table Styling */
        .active-subscriptions {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }

        .active-subscriptions h5 {
            color: var(--text-primary);
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .active-subscriptions h5 i {
            color: var(--accent-green);
        }

        .table {
            color: var(--text-primary);
        }

        .table thead th {
            background: rgba(47, 155, 127, 0.1);
            color: var(--text-primary);
            font-weight: 500;
            border-bottom: none;
            padding: 12px 16px;
        }

        .table tbody td {
            color: var(--text-secondary);
            border-color: var(--border-color);
            padding: 12px 16px;
            vertical-align: middle;
            color: #1A1D21;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
        }

        .bg-success {
            background: rgba(47, 155, 127, 0.1) !important;
            color: var(--accent-green);
        }

        @media (max-width: 768px) {
            .schemes-container {
                margin-left: 70px;
                padding: 16px;
            }

            .scheme-header {
                padding: 30px 20px;
            }

            .scheme-card {
                padding: 20px;
            }

            .scheme-price {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="schemes-container">
            <div class="container">
                <div class="scheme-header">
                    <h2><i class="fas fa-gem me-2"></i> Available Schemes</h2>
                    <p>Choose the perfect scheme for your financial goals</p>
                </div>

                <?php if (!empty($activeSubscriptions)): ?>
                    <div class="active-subscriptions">
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
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i> Active
                                                </span>
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
                        <div class="col-md-6 col-lg-4">
                            <div class="scheme-card">
                                <?php if ($scheme['is_subscribed']): ?>
                                    <span class="subscription-badge">
                                        <i class="fas fa-check-circle"></i> Subscribed
                                    </span>
                                <?php endif; ?>

                                <h4 class="mb-3"><?php echo htmlspecialchars($scheme['SchemeName']); ?></h4>
                                <div class="scheme-price">
                                    ₹<?php echo number_format($scheme['MonthlyPayment'], 2); ?>
                                    <span class="scheme-duration">/month</span>
                                </div>

                                <ul class="scheme-features">
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
                                    <li>
                                        <i class="fas fa-clock"></i>
                                        <?php
                                        $startDate = new DateTime($scheme['scheme_start_date']);
                                        $currentDate = new DateTime();
                                        $datePrefix = $startDate > $currentDate ? 'Starting from' : 'Started on';
                                        echo $datePrefix . ': ' . $startDate->format('M d, Y');
                                        ?>
                                    </li>
                                </ul>

                                <a href="view_benefits.php?scheme_id=<?php echo $scheme['SchemeID']; ?>"
                                    class="btn btn-outline-primary w-100 mb-3">
                                    <i class="fas fa-gift me-2"></i> View Benefits
                                </a>

                                <?php if ($scheme['is_subscribed']): ?>
                                    <button class="btn btn-subscribe btn-subscribed" disabled>
                                        <i class="fas fa-check-circle me-2"></i> Already Subscribed
                                    </button>
                                <?php else: ?>
                                    <a href="subscribe.php?scheme_id=<?php echo $scheme['SchemeID']; ?>"
                                        class="btn btn-subscribe">
                                        <i class="fas fa-play-circle me-2"></i> Subscribe Now
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