<?php
session_start();
// Check if user is logged in, redirect if not
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "promoters";

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No promoter ID provided.";
    header("Location: index.php");
    exit();
}

$promoterId = $_GET['id'];

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get promoter details
try {
    $query = "SELECT p.*, 
              parent.Name as ParentName, 
              parent.PromoterUniqueID as ParentUniqueID 
              FROM Promoters p 
              LEFT JOIN Promoters parent ON p.ParentPromoterID = parent.PromoterID 
              WHERE p.PromoterID = ?";

    $stmt = $conn->prepare($query);
    $stmt->execute([$promoterId]);

    $promoter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promoter) {
        $_SESSION['error_message'] = "Promoter not found.";
        header("Location: index.php");
        exit();
    }

    // Get customer count
    $stmt = $conn->prepare("SELECT COUNT(*) as customer_count FROM Customers WHERE PromoterID = ?");
    $stmt->execute([$promoterId]);
    $customerCount = $stmt->fetch(PDO::FETCH_ASSOC)['customer_count'];

    // Get recent customers (5)
    $stmt = $conn->prepare("SELECT CustomerID, CustomerUniqueID, Name, Contact, Email, Status, CreatedAt 
                           FROM Customers 
                           WHERE PromoterID = ? 
                           ORDER BY CreatedAt DESC LIMIT 5");
    $stmt->execute([$promoterId]);
    $recentCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent payments (5)
    $stmt = $conn->prepare("SELECT p.PaymentID, p.Amount, p.Status, p.SubmittedAt, 
                            c.Name as CustomerName, c.CustomerUniqueID, 
                            s.SchemeName 
                            FROM Payments p 
                            LEFT JOIN Customers c ON p.CustomerID = c.CustomerID 
                            LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID 
                            WHERE p.PromoterID = ? 
                            ORDER BY p.SubmittedAt DESC LIMIT 5");
    $stmt->execute([$promoterId]);
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get payment code transactions (5)
    $stmt = $conn->prepare("SELECT t.TransactionID, t.PaymentCodeChange, t.TransactionType, 
                           t.Remarks, t.CreatedAt, a.Name as AdminName 
                           FROM PaymentCodeTransactions t 
                           LEFT JOIN Admins a ON t.AdminID = a.AdminID 
                           WHERE t.PromoterID = ? 
                           ORDER BY t.CreatedAt DESC LIMIT 5");
    $stmt->execute([$promoterId]);
    $codeTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get activity logs (10)
    $stmt = $conn->prepare("SELECT LogID, Action, IPAddress, CreatedAt 
                           FROM ActivityLogs 
                           WHERE UserID = ? AND UserType = 'Promoter' 
                           ORDER BY CreatedAt DESC LIMIT 10");
    $stmt->execute([$promoterId]);
    $activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving promoter details: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Promoter - <?php echo htmlspecialchars($promoter['Name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Promoter View Page Styles */
        :root {
            --pr_primary: #3a7bd5;
            --pr_primary-hover: #2c60a9;
            --pr_secondary: #00d2ff;
            --pr_success: #2ecc71;
            --pr_success-hover: #27ae60;
            --pr_warning: #f39c12;
            --pr_warning-hover: #d35400;
            --pr_danger: #e74c3c;
            --pr_danger-hover: #c0392b;
            --pr_info: #3498db;
            --pr_info-hover: #2980b9;
            --pr_text-dark: #2c3e50;
            --pr_text-medium: #34495e;
            --pr_text-light: #7f8c8d;
            --pr_bg-light: #f8f9fa;
            --pr_border-color: #e0e0e0;
            --pr_shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.05);
            --pr_shadow-md: 0 4px 10px rgba(0, 0, 0, 0.08);
            --pr_transition: 0.25s;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--pr_text-medium);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            transition: color var(--pr_transition);
        }

        .back-link:hover {
            color: var(--pr_primary);
        }

        .promoter-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
        }

        .promoter-avatar {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--pr_primary), var(--pr_secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            font-weight: 600;
            box-shadow: var(--pr_shadow-md);
            border: 4px solid white;
        }

        .promoter-info {
            flex: 1;
        }

        .promoter-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--pr_text-dark);
            margin-bottom: 5px;
        }

        .promoter-id {
            font-size: 14px;
            color: var(--pr_text-light);
            margin-bottom: 10px;
        }

        .promoter-contact {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: var(--pr_text-medium);
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .contact-item i {
            color: var(--pr_primary);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--pr_success);
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--pr_danger);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all var(--pr_transition);
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--pr_primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--pr_primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(58, 123, 213, 0.2);
        }

        .btn-warning {
            background: var(--pr_warning);
            color: white;
        }

        .btn-warning:hover {
            background: var(--pr_warning-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(243, 156, 18, 0.2);
        }

        .btn-danger {
            background: var(--pr_danger);
            color: white;
        }

        .btn-danger:hover {
            background: var(--pr_danger-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.2);
        }

        .btn-outline {
            background: transparent;
            color: var(--pr_text-medium);
            border: 1px solid var(--pr_border-color);
        }

        .btn-outline:hover {
            border-color: var(--pr_primary);
            color: var(--pr_primary);
            background: rgba(58, 123, 213, 0.05);
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .grid-item-8 {
            grid-column: span 8;
        }

        .grid-item-4 {
            grid-column: span 4;
        }

        .grid-item-6 {
            grid-column: span 6;
        }

        .grid-item-12 {
            grid-column: span 12;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--pr_shadow-sm);
            overflow: hidden;
            height: 100%;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--pr_border-color);
            background-color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--pr_text-dark);
        }

        .card-header-action {
            color: var(--pr_primary);
            font-size: 13px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .card-header-action:hover {
            text-decoration: underline;
        }

        .card-body {
            padding: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 12px;
            color: var(--pr_text-light);
        }

        .info-value {
            font-size: 14px;
            color: var(--pr_text-dark);
            font-weight: 500;
        }

        .table-clean {
            width: 100%;
            border-collapse: collapse;
        }

        .table-clean th,
        .table-clean td {
            padding: 10px 12px;
            text-align: left;
            font-size: 13px;
            border-bottom: 1px solid var(--pr_border-color);
        }

        .table-clean th {
            font-weight: 600;
            color: var(--pr_text-medium);
            background-color: var(--pr_bg-light);
        }

        .table-clean tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--pr_success);
        }

        .badge-warning {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--pr_warning);
        }

        .badge-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--pr_danger);
        }

        .badge-info {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--pr_info);
        }

        .transaction-type-addition {
            color: var(--pr_success);
        }

        .transaction-type-deduction {
            color: var(--pr_danger);
        }

        .transaction-type-correction {
            color: var(--pr_warning);
        }

        .customer-count-badge {
            background: linear-gradient(135deg, #f1c40f, #f39c12);
            color: white;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            box-shadow: var(--pr_shadow-sm);
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .count-value {
            font-size: 36px;
            font-weight: 700;
        }

        .count-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .code-count-badge {
            background: linear-gradient(135deg, var(--pr_primary), var(--pr_secondary));
            color: white;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            box-shadow: var(--pr_shadow-sm);
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-top: 20px;
        }

        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid var(--pr_border-color);
            font-size: 13px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-time {
            font-size: 11px;
            color: var(--pr_text-light);
            margin-top: 4px;
        }

        .address-info {
            background-color: var(--pr_bg-light);
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
            color: var(--pr_text-medium);
            line-height: 1.5;
        }

        .bank-info {
            background-color: var(--pr_bg-light);
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .bank-info-item {
            margin-bottom: 10px;
        }

        .bank-info-label {
            font-size: 12px;
            color: var(--pr_text-light);
            margin-bottom: 3px;
        }

        .bank-info-value {
            font-size: 14px;
            color: var(--pr_text-dark);
            font-weight: 500;
        }

        /* Responsive styles */
        @media (max-width: 992px) {
            .grid-container {
                grid-template-columns: 1fr;
            }

            .grid-item-8,
            .grid-item-4,
            .grid-item-6,
            .grid-item-12 {
                grid-column: span 1;
            }

            .promoter-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .promoter-contact {
                flex-direction: column;
                gap: 10px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Promoters List
        </a>

        <div class="promoter-header">
            <div class="promoter-avatar">
                <?php
                $initials = '';
                $nameParts = explode(' ', $promoter['Name']);
                if (count($nameParts) >= 2) {
                    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                } else {
                    $initials = strtoupper(substr($promoter['Name'], 0, 2));
                }
                echo $initials;
                ?>
            </div>
            <div class="promoter-info">
                <div class="promoter-name">
                    <?php echo htmlspecialchars($promoter['Name']); ?>
                    <span class="status-badge status-<?php echo strtolower($promoter['Status']); ?>">
                        <?php echo $promoter['Status']; ?>
                    </span>
                </div>
                <div class="promoter-id">ID: <?php echo $promoter['PromoterUniqueID']; ?></div>
                <div class="promoter-contact">
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <?php echo htmlspecialchars($promoter['Contact']); ?>
                    </div>
                    <?php if (!empty($promoter['Email'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($promoter['Email']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="contact-item">
                        <i class="fas fa-calendar"></i>
                        Joined: <?php echo date('M d, Y', strtotime($promoter['CreatedAt'])); ?>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="edit.php?id=<?php echo $promoter['PromoterID']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Promoter
                    </a>

                    <?php if ($promoter['Status'] == 'Active'): ?>
                        <a href="index.php?status=deactivate&id=<?php echo $promoter['PromoterID']; ?>"
                            class="btn btn-warning"
                            onclick="return confirm('Are you sure you want to deactivate this promoter?');">
                            <i class="fas fa-ban"></i> Deactivate
                        </a>
                    <?php else: ?>
                        <a href="index.php?status=activate&id=<?php echo $promoter['PromoterID']; ?>"
                            class="btn btn-primary"
                            onclick="return confirm('Are you sure you want to activate this promoter?');">
                            <i class="fas fa-check"></i> Activate
                        </a>
                    <?php endif; ?>

                    <a href="index.php?delete=<?php echo $promoter['PromoterID']; ?>"
                        class="btn btn-danger"
                        onclick="return confirm('Are you sure you want to delete this promoter? This action cannot be undone.');">
                        <i class="fas fa-trash"></i> Delete
                    </a>

                    <a href="#" class="btn btn-outline" onclick="window.print();">
                        <i class="fas fa-print"></i> Print
                    </a>
                </div>
            </div>
        </div>

        <div class="grid-container">
            <!-- Left Column -->
            <div class="grid-item-8">
                <!-- Basic Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>Basic Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Full Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($promoter['Name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Unique ID</span>
                                <span class="info-value"><?php echo $promoter['PromoterUniqueID']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Contact Number</span>
                                <span class="info-value"><?php echo htmlspecialchars($promoter['Contact']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email Address</span>
                                <span class="info-value"><?php echo !empty($promoter['Email']) ? htmlspecialchars($promoter['Email']) : 'Not provided'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value">
                                    <span class="badge badge-<?php echo $promoter['Status'] === 'Active' ? 'success' : 'danger'; ?>">
                                        <?php echo $promoter['Status']; ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Registration Date</span>
                                <span class="info-value"><?php echo date('F d, Y', strtotime($promoter['CreatedAt'])); ?></span>
                            </div>
                            <?php if (!empty($promoter['ParentName'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Parent Promoter</span>
                                    <span class="info-value">
                                        <?php echo htmlspecialchars($promoter['ParentName']); ?>
                                        (<?php echo $promoter['ParentUniqueID']; ?>)
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($promoter['Address'])): ?>
                            <div class="address-info">
                                <strong>Address:</strong> <?php echo htmlspecialchars($promoter['Address']); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Bank Details -->
                        <?php if (!empty($promoter['BankAccountNumber'])): ?>
                            <div class="bank-info">
                                <div class="bank-info-item">
                                    <div class="bank-info-label">Bank Name</div>
                                    <div class="bank-info-value"><?php echo htmlspecialchars($promoter['BankName']); ?></div>
                                </div>
                                <div class="bank-info-item">
                                    <div class="bank-info-label">Account Holder Name</div>
                                    <div class="bank-info-value"><?php echo htmlspecialchars($promoter['BankAccountName']); ?></div>
                                </div>
                                <div class="bank-info-item">
                                    <div class="bank-info-label">Account Number</div>
                                    <div class="bank-info-value"><?php echo htmlspecialchars($promoter['BankAccountNumber']); ?></div>
                                </div>
                                <div class="bank-info-item">
                                    <div class="bank-info-label">IFSC Code</div>
                                    <div class="bank-info-value"><?php echo htmlspecialchars($promoter['IFSCCode']); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Customers -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3>Recent Customers</h3>
                        <a href="../customers/index.php?promoter_id=<?php echo $promoter['PromoterID']; ?>" class="card-header-action">
                            View All <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recentCustomers) > 0): ?>
                            <table class="table-clean">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentCustomers as $customer): ?>
                                        <tr>
                                            <td><?php echo $customer['CustomerUniqueID']; ?></td>
                                            <td><?php echo htmlspecialchars($customer['Name']); ?></td>
                                            <td><?php echo htmlspecialchars($customer['Contact']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $customer['Status'] === 'Active' ? 'success' : 'danger'; ?>">
                                                    <?php echo $customer['Status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($customer['CreatedAt'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No customers found for this promoter.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3>Recent Payments</h3>
                        <a href="../payments/index.php?promoter_id=<?php echo $promoter['PromoterID']; ?>" class="card-header-action">
                            View All <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recentPayments) > 0): ?>
                            <table class="table-clean">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Scheme</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentPayments as $payment): ?>
                                        <tr>
                                            <td>#<?php echo $payment['PaymentID']; ?></td>
                                            <td><?php echo htmlspecialchars($payment['CustomerName']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['SchemeName']); ?></td>
                                            <td>₹<?php echo number_format($payment['Amount'], 2); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo
                                                                            $payment['Status'] === 'Verified' ? 'success' : ($payment['Status'] === 'Pending' ? 'warning' : 'danger'); ?>">
                                                    <?php echo $payment['Status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($payment['SubmittedAt'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No payments found for this promoter.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="grid-item-4">
                <!-- Stats -->
                <div class="customer-count-badge">
                    <span class="count-value"><?php echo $customerCount; ?></span>
                    <span class="count-label">Total Customers</span>
                </div>

                <div class="code-count-badge">
                    <span class="count-value"><?php echo $promoter['PaymentCodeCounter']; ?></span>
                    <span class="count-label">Payment Codes Generated</span>
                </div>

                <!-- Payment Code Transactions -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3>Code Transactions</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($codeTransactions) > 0): ?>
                            <?php foreach ($codeTransactions as $transaction): ?>
                                <div class="activity-item">
                                    <div>
                                        <span class="transaction-type-<?php echo strtolower($transaction['TransactionType']); ?>">
                                            <?php
                                            if ($transaction['TransactionType'] == 'Addition') {
                                                echo '<i class="fas fa-plus-circle"></i> +';
                                            } elseif ($transaction['TransactionType'] == 'Deduction') {
                                                echo '<i class="fas fa-minus-circle"></i> -';
                                            } else {
                                                echo '<i class="fas fa-sync-alt"></i> ';
                                            }
                                            echo abs($transaction['PaymentCodeChange']);
                                            ?> codes
                                        </span>
                                        by <?php echo htmlspecialchars($transaction['AdminName']); ?>
                                    </div>
                                    <?php if (!empty($transaction['Remarks'])): ?>
                                        <div><?php echo htmlspecialchars($transaction['Remarks']); ?></div>
                                    <?php endif; ?>
                                    <div class="activity-time">
                                        <?php echo date('M d, Y h:i A', strtotime($transaction['CreatedAt'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No payment code transactions found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Activity Log -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3>Activity Log</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($activityLogs) > 0): ?>
                            <?php foreach ($activityLogs as $log): ?>
                                <div class="activity-item">
                                    <div><?php echo htmlspecialchars($log['Action']); ?></div>
                                    <div class="activity-time">
                                        <i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($log['CreatedAt'])); ?>
                                        <i class="fas fa-globe"></i> <?php echo htmlspecialchars($log['IPAddress']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No activity logs found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Add Payment Codes Form -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3>Manage Payment Codes</h3>
                    </div>
                    <div class="card-body">
                        <form action="manage-codes.php" method="post">
                            <input type="hidden" name="promoter_id" value="<?php echo $promoter['PromoterID']; ?>">

                            <div style="margin-bottom: 15px;">
                                <label for="transaction_type" style="display: block; margin-bottom: 5px; font-size: 13px; color: var(--pr_text-medium);">
                                    Transaction Type
                                </label>
                                <select name="transaction_type" id="transaction_type" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--pr_border-color); font-size: 14px;">
                                    <option value="Addition">Add Codes</option>
                                    <option value="Deduction">Remove Codes</option>
                                </select>
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label for="code_count" style="display: block; margin-bottom: 5px; font-size: 13px; color: var(--pr_text-medium);">
                                    Number of Codes
                                </label>
                                <input type="number" name="code_count" id="code_count" min="1" max="1000" value="1"
                                    style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--pr_border-color); font-size: 14px;">
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label for="remarks" style="display: block; margin-bottom: 5px; font-size: 13px; color: var(--pr_text-medium);">
                                    Remarks
                                </label>
                                <textarea name="remarks" id="remarks" rows="3"
                                    style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--pr_border-color); font-size: 14px; resize: vertical;"
                                    placeholder="Enter reason for this transaction"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-save"></i> Submit
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer & Payment Stats (Full Width) -->
        <div class="grid-container">
            <div class="grid-item-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Monthly Performance</h3>
                    </div>
                    <div class="card-body" style="min-height: 300px;">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Monthly performance chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('performanceChart').getContext('2d');

            // Fetch data or use dummy data
            // This would normally be populated from the database
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const customersData = [5, 8, 12, 15, 20, 25, 28, 30, 28, 25, 20, 18];
            const paymentsData = [10000, 15000, 25000, 30000, 35000, 40000, 45000, 50000, 45000, 40000, 35000, 30000];

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                            label: 'Customers Acquired',
                            data: customersData,
                            backgroundColor: 'rgba(58, 123, 213, 0.7)',
                            borderColor: 'rgba(58, 123, 213, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Payment Volume (₹)',
                            data: paymentsData,
                            backgroundColor: 'rgba(243, 156, 18, 0.7)',
                            borderColor: 'rgba(243, 156, 18, 1)',
                            borderWidth: 1,
                            type: 'line',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Customers'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false,
                            },
                            title: {
                                display: true,
                                text: 'Payment Volume (₹)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.datasetIndex === 1) {
                                        label += '₹' + context.raw.toLocaleString();
                                    } else {
                                        label += context.raw;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>

    <!-- Add customer confirmation modal -->
    <div id="addCustomerModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 500px;">
            <span class="close" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; color: var(--pr_text-dark);">Add New Customer</h2>
            <p>Adding a new customer under <strong><?php echo htmlspecialchars($promoter['Name']); ?></strong></p>

            <div style="margin-top: 20px;">
                <a href="../customers/add.php?promoter_id=<?php echo $promoter['PromoterID']; ?>" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-plus"></i> Proceed to Add Customer
                </a>
            </div>
        </div>
    </div>

    <script>
        // Handle modal display
        document.addEventListener('DOMContentLoaded', function() {
            const addCustomerBtn = document.getElementById('addCustomerBtn');
            const addCustomerModal = document.getElementById('addCustomerModal');
            const closeBtn = addCustomerModal.querySelector('.close');

            if (addCustomerBtn) {
                addCustomerBtn.addEventListener('click', function() {
                    addCustomerModal.style.display = 'block';
                });
            }

            closeBtn.addEventListener('click', function() {
                addCustomerModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target == addCustomerModal) {
                    addCustomerModal.style.display = 'none';
                }
            });
        });

        // Print function customization
        window.onbeforeprint = function() {
            document.querySelector('.action-buttons').style.display = 'none';
            document.querySelectorAll('.card-header-action').forEach(function(el) {
                el.style.display = 'none';
            });
        };

        window.onafterprint = function() {
            document.querySelector('.action-buttons').style.display = 'flex';
            document.querySelectorAll('.card-header-action').forEach(function(el) {
                el.style.display = 'flex';
            });
        };
    </script>
</body>

</html>