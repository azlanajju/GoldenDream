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

// Get withdrawal statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_withdrawals,
        SUM(CASE WHEN Status = 'Approved' THEN 1 ELSE 0 END) as approved_withdrawals,
        SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as pending_withdrawals,
        SUM(CASE WHEN Status = 'Rejected' THEN 1 ELSE 0 END) as rejected_withdrawals,
        SUM(CASE WHEN Status = 'Approved' THEN Amount ELSE 0 END) as total_withdrawn
    FROM Withdrawals 
    WHERE UserID = ? AND UserType = 'Customer'
");
$stmt->execute([$userData['customer_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get withdrawal history
$stmt = $db->prepare("
    SELECT w.*, a.Name as AdminName 
    FROM Withdrawals w 
    LEFT JOIN Admins a ON w.AdminID = a.AdminID 
    WHERE w.UserID = ? AND w.UserType = 'Customer' 
    ORDER BY w.RequestedAt DESC
");
$stmt->execute([$userData['customer_id']]);
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawals - Golden Dream</title>
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

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .withdrawal-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .withdrawal-card:hover {
            transform: translateY(-5px);
        }

        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
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
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="withdrawal-container">
            <div class="container">
                <div class="withdrawal-header text-center">
                    <h2><i class="fas fa-money-bill-wave"></i> Withdrawals</h2>
                    <p class="mb-0">Manage your withdrawal requests and track their status</p>
                    <?php if ($available_balance > 0): ?>
                        <a href="request_withdrawal.php" class="btn btn-light btn-lg mt-3">
                            <i class="fas fa-plus"></i> Request Withdrawal
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Balance Card -->
                <div class="balance-card">
                    <h4>Available Balance</h4>
                    <div class="balance-amount">₹<?php echo number_format($available_balance, 2); ?></div>
                    <?php if ($available_balance > 0): ?>
                        <a href="request_withdrawal.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus"></i> Request Withdrawal
                        </a>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No available balance for withdrawal
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <i class="fas fa-wallet stats-icon text-primary"></i>
                            <h5>Total Withdrawals</h5>
                            <h3><?php echo $stats['total_withdrawals']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <i class="fas fa-check-circle stats-icon text-success"></i>
                            <h5>Approved</h5>
                            <h3><?php echo $stats['approved_withdrawals']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <i class="fas fa-clock stats-icon text-warning"></i>
                            <h5>Pending</h5>
                            <h3><?php echo $stats['pending_withdrawals']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <i class="fas fa-times-circle stats-icon text-danger"></i>
                            <h5>Rejected</h5>
                            <h3><?php echo $stats['rejected_withdrawals']; ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Withdrawal History -->
                <h4 class="mb-4">Withdrawal History</h4>
                <?php if (!empty($withdrawals)): ?>
                    <?php foreach ($withdrawals as $withdrawal): ?>
                        <div class="withdrawal-card">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <h5>₹<?php echo number_format($withdrawal['Amount'], 2); ?></h5>
                                    <small class="text-muted">
                                        <?php echo date('d M Y', strtotime($withdrawal['RequestedAt'])); ?>
                                    </small>
                                </div>
                                <div class="col-md-3">
                                    <span class="status-badge 
                                        <?php echo $withdrawal['Status'] === 'Pending' ? 'status-pending' : ($withdrawal['Status'] === 'Approved' ? 'status-approved' : 'status-rejected'); ?>">
                                        <?php echo $withdrawal['Status']; ?>
                                    </span>
                                </div>
                                <div class="col-md-3">
                                    <?php if ($withdrawal['AdminName']): ?>
                                        <small class="text-muted">Processed by: <?php echo htmlspecialchars($withdrawal['AdminName']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <?php if ($withdrawal['Remarks']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($withdrawal['Remarks']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No Withdrawal History</h3>
                        <p>You haven't made any withdrawal requests yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>