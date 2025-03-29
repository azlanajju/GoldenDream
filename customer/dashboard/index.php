<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path="../";
$current_page="dashboard";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total balance
$stmt = $db->prepare("
    SELECT SUM(BalanceAmount) as total_balance 
    FROM Balances 
    WHERE UserID = ? AND UserType = 'Customer'
");
$stmt->execute([$userData['customer_id']]);
$balance_result = $stmt->fetch(PDO::FETCH_ASSOC);
$available_balance = $balance_result['total_balance'] ?? 0;

// Get current month winners
$stmt = $db->prepare("
    SELECT w.*, s.SchemeName 
    FROM Winners w
    JOIN Subscriptions sub ON w.UserID = sub.CustomerID
    JOIN Schemes s ON sub.SchemeID = s.SchemeID
    WHERE w.UserID = ? 
    AND w.UserType = 'Customer'
    AND MONTH(w.WinningDate) = MONTH(CURRENT_DATE())
    AND YEAR(w.WinningDate) = YEAR(CURRENT_DATE())
    ORDER BY w.WinningDate DESC
");
$stmt->execute([$userData['customer_id']]);
$current_month_winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get next payment due
$stmt = $db->prepare("
    SELECT s.SchemeName, s.MonthlyPayment, sub.StartDate, sub.EndDate
    FROM Subscriptions sub
    JOIN Schemes s ON sub.SchemeID = s.SchemeID
    WHERE sub.CustomerID = ? 
    AND sub.RenewalStatus = 'Active'
    ORDER BY sub.EndDate ASC
    LIMIT 1
");
$stmt->execute([$userData['customer_id']]);
$next_payment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total active subscriptions
$stmt = $db->prepare("
    SELECT COUNT(*) as total_subscriptions
    FROM Subscriptions
    WHERE CustomerID = ? AND RenewalStatus = 'Active'
");
$stmt->execute([$userData['customer_id']]);
$subscription_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_subscriptions'];

// Get total prizes won
$stmt = $db->prepare("
    SELECT COUNT(*) as total_prizes
    FROM Winners
    WHERE UserID = ? AND UserType = 'Customer'
");
$stmt->execute([$userData['customer_id']]);
$total_prizes = $stmt->fetch(PDO::FETCH_ASSOC)['total_prizes'];

// Get pending withdrawals
$stmt = $db->prepare("
    SELECT COUNT(*) as pending_withdrawals
    FROM Withdrawals
    WHERE UserID = ? AND UserType = 'Customer' AND Status = 'Pending'
");
$stmt->execute([$userData['customer_id']]);
$pending_withdrawals = $stmt->fetch(PDO::FETCH_ASSOC)['pending_withdrawals'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-gold: #FFD700;
            --dark-bg: #000000;
            --card-bg: rgba(20, 20, 20, 0.95);
            --hover-gold: rgba(255, 215, 0, 0.1);
            --border-gold: rgba(255, 215, 0, 0.3);
        }

        body {
            background: var(--dark-bg);
            color: #fff;
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }

        .dashboard-container {
            padding: 2rem;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }

        .dashboard-header {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-gold);
            position: relative;
            overflow: hidden;
        }

        .dashboard-header h2 {
            color: var(--primary-gold);
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .dashboard-header h2 i {
            font-size: 1.8rem;
        }

        .dashboard-header p {
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
            font-size: 1.1rem;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid var(--border-gold);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--primary-gold), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stats-card:hover::before {
            opacity: 1;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.15);
        }

        .stats-icon {
            font-size: 2rem;
            color: var(--primary-gold);
            margin-bottom: 1rem;
            display: inline-block;
        }

        .stats-card h5 {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .stats-card h3 {
            color: var(--primary-gold);
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }

        .info-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .info-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid var(--border-gold);
            height: 100%;
        }

        .info-card h4 {
            color: var(--primary-gold);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-card h4 i {
            font-size: 1.2rem;
        }

        .payment-due-date {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-gold);
            margin: 1rem 0;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--primary-gold);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--primary-gold);
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.7);
            margin: 0;
        }

        .badge {
            background: var(--primary-gold) !important;
            color: var(--dark-bg);
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (max-width: 1200px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                margin-left: 70px;
                padding: 1rem;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .info-cards {
                grid-template-columns: 1fr;
            }

            .dashboard-header {
                padding: 1.5rem;
            }

            .dashboard-header h2 {
                font-size: 1.5rem;
            }

            .stats-card h3 {
                font-size: 1.5rem;
            }
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-header {
            animation: slideIn 0.6s ease-out;
        }

        .stats-card {
            animation: slideIn 0.6s ease-out;
        }

        .stats-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .stats-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .stats-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .stats-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        .info-card {
            animation: slideIn 0.6s ease-out;
            animation-delay: 0.5s;
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h2><i class="fas fa-chart-line"></i> Dashboard</h2>
                <p>Welcome back, <?php echo htmlspecialchars($customer['Name']); ?>!</p>
            </div>

            <div class="stats-row">
                <div class="stats-card">
                    <i class="fas fa-wallet stats-icon"></i>
                    <h5>Available Balance</h5>
                    <h3>₹<?php echo number_format($available_balance, 2); ?></h3>
                </div>
                <div class="stats-card">
                    <i class="fas fa-calendar-check stats-icon"></i>
                    <h5>Active Subscriptions</h5>
                    <h3><?php echo $subscription_count; ?></h3>
                </div>
                <div class="stats-card">
                    <i class="fas fa-trophy stats-icon"></i>
                    <h5>Total Prizes Won</h5>
                    <h3><?php echo $total_prizes; ?></h3>
                </div>
                <div class="stats-card">
                    <i class="fas fa-clock stats-icon"></i>
                    <h5>Pending Withdrawals</h5>
                    <h3><?php echo $pending_withdrawals; ?></h3>
                </div>
            </div>

            <div class="info-cards">
                <div class="info-card">
                    <h4><i class="fas fa-calendar-alt"></i> Next Payment Due</h4>
                    <?php if ($next_payment): ?>
                        <div>
                            <h5><?php echo htmlspecialchars($next_payment['SchemeName']); ?></h5>
                            <div class="payment-due-date">
                                <?php echo date('d M Y', strtotime($next_payment['EndDate'])); ?>
                            </div>
                            <div>
                                <strong>Amount:</strong> ₹<?php echo number_format($next_payment['MonthlyPayment'], 2); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Active Subscriptions</h3>
                            <p>You don't have any active subscriptions at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <h4><i class="fas fa-trophy"></i> Current Month Winners</h4>
                    <?php if (!empty($current_month_winners)): ?>
                        <?php foreach ($current_month_winners as $winner): ?>
                            <div class="winner-item">
                                <h5><?php echo htmlspecialchars($winner['SchemeName']); ?></h5>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge"><?php echo $winner['PrizeType']; ?></span>
                                    <small class="text-muted">
                                        <?php echo date('d M Y', strtotime($winner['WinningDate'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-trophy"></i>
                            <h3>No Winners This Month</h3>
                            <p>Keep participating to win prizes!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>