<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "schemes";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$message = '';
$messageType = '';
$showNotification = false;

// Check if scheme ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$schemeId = $_GET['id'];

// Get scheme details
try {
    $stmt = $conn->prepare("
        SELECT s.*, 
               COUNT(DISTINCT sub.SubscriptionID) as TotalSubscribers
        FROM Schemes s
        LEFT JOIN Subscriptions sub ON s.SchemeID = sub.SchemeID AND sub.RenewalStatus = 'Active'
        WHERE s.SchemeID = ? AND s.Status = 'Active'
        GROUP BY s.SchemeID
    ");
    $stmt->execute([$schemeId]);
    $scheme = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scheme) {
        header("Location: index.php");
        exit();
    }
    
    // Get installments for this scheme
    $stmt = $conn->prepare("
        SELECT * FROM Installments 
        WHERE SchemeID = ? AND Status = 'Active'
        ORDER BY InstallmentNumber ASC
    ");
    $stmt->execute([$schemeId]);
    $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total amount for this scheme
    $stmt = $conn->prepare("
        SELECT SUM(Amount) as TotalAmount 
        FROM Installments 
        WHERE SchemeID = ? AND Status = 'Active'
    ");
    $stmt->execute([$schemeId]);
    $totalAmount = $stmt->fetch(PDO::FETCH_ASSOC)['TotalAmount'];
    
} catch (PDOException $e) {
    $message = "Error fetching scheme details: " . $e->getMessage();
    $messageType = "error";
    $showNotification = true;
}

// Get promoter's payment code count
try {
    $stmt = $conn->prepare("SELECT PaymentCodeCounter FROM Promoters WHERE PromoterID = ?");
    $stmt->execute([$_SESSION['promoter_id']]);
    $promoter = $stmt->fetch(PDO::FETCH_ASSOC);
    $paymentCodeCount = $promoter['PaymentCodeCounter'];
} catch (PDOException $e) {
    $paymentCodeCount = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($scheme['SchemeName']); ?> | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --warning-color: #f1c40f;
            --border-color: #e0e0e0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Poppins', sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--topbar-height) + 20px) !important;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-icon i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .section-info h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .section-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .scheme-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .scheme-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(30deg);
        }

        .scheme-name {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .scheme-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
            margin-bottom: 15px;
        }

        .scheme-description {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            opacity: 0.9;
        }

        .scheme-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.8;
        }

        .scheme-content {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 30px;
        }

        .scheme-main {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .scheme-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .card-header {
            padding: 15px 20px;
            background: var(--bg-light);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header i {
            color: var(--primary-color);
        }

        .card-body {
            padding: 20px;
        }

        .installment-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .installment-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .installment-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .installment-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .installment-details {
            flex-grow: 1;
        }

        .installment-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .installment-info {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .installment-amount {
            font-weight: 600;
            color: var(--text-primary);
        }

        .installment-date {
            color: var(--text-secondary);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 106, 80, 0.3);
        }

        .btn-secondary {
            background: var(--bg-light);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            width: 100%;
            justify-content: center;
        }

        .btn-secondary:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }

        .payment-code-info {
            background: var(--primary-light);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .payment-code-info-text {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-code-info-text i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .payment-code-count {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .benefits-list {
            list-style: none;
        }

        .benefits-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .benefits-list li:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .benefits-list li i {
            color: var(--success-color);
            margin-top: 3px;
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .tab {
            padding: 15px 20px;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
            padding: 0 20px 20px;
        }

        .tab-content.active {
            display: block;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }

        .empty-state i {
            font-size: 50px;
            color: var(--border-color);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .empty-state p {
            color: var(--text-secondary);
            max-width: 400px;
            margin: 0 auto;
        }

        @media (max-width: 992px) {
            .scheme-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .scheme-stats {
                grid-template-columns: 1fr;
            }

            .scheme-header {
                padding: 20px;
            }

            .scheme-name {
                font-size: 24px;
            }

            .tabs {
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
            }

            .tab {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="main-content">
            <?php if ($showNotification && $message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="section-info">
                        <h2>Scheme Details</h2>
                        <p>View detailed information about this scheme</p>
                    </div>
                </div>
                <a href="index.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Schemes
                </a>
            </div>

            <div class="scheme-header">
                <h1 class="scheme-name"><?php echo htmlspecialchars($scheme['SchemeName']); ?></h1>
                <span class="scheme-status">Active</span>
                <p class="scheme-description">
                    <?php echo htmlspecialchars($scheme['Description'] ?: 'No description available.'); ?>
                </p>
                <div class="scheme-stats">
                    <div class="stat-item">
                        <div class="stat-value">₹<?php echo number_format($scheme['MonthlyPayment'], 2); ?></div>
                        <div class="stat-label">Monthly Payment</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $scheme['TotalPayments']; ?></div>
                        <div class="stat-label">Total Payments</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo count($installments); ?></div>
                        <div class="stat-label">Installments</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($scheme['TotalSubscribers']); ?></div>
                        <div class="stat-label">Active Subscribers</div>
                    </div>
                </div>
            </div>

            <div class="scheme-content">
                <div class="scheme-main">
                    <div class="tabs">
                        <div class="tab active" data-tab="installments">Installments</div>
                        <div class="tab" data-tab="benefits">Benefits</div>
                        <div class="tab" data-tab="terms">Terms & Conditions</div>
                    </div>

                    <div class="tab-content active" id="installments">
                        <?php if (empty($installments)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-alt"></i>
                                <h3>No Installments Available</h3>
                                <p>There are no installments defined for this scheme yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="installment-list">
                                <?php foreach ($installments as $installment): ?>
                                    <div class="installment-item">
                                        <div class="installment-number"><?php echo $installment['InstallmentNumber']; ?></div>
                                        <div class="installment-details">
                                            <div class="installment-name"><?php echo htmlspecialchars($installment['InstallmentName']); ?></div>
                                            <div class="installment-info">
                                                <span class="installment-amount">₹<?php echo number_format($installment['Amount'], 2); ?></span>
                                                <span class="installment-date">Draw Date: <?php echo date('d M Y', strtotime($installment['DrawDate'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tab-content" id="benefits">
                        <?php 
                        $benefits = [];
                        foreach ($installments as $installment) {
                            if (!empty($installment['Benefits'])) {
                                $benefits[] = $installment['Benefits'];
                            }
                        }
                        ?>
                        
                        <?php if (empty($benefits)): ?>
                            <div class="empty-state">
                                <i class="fas fa-gift"></i>
                                <h3>No Benefits Defined</h3>
                                <p>There are no specific benefits defined for this scheme yet.</p>
                            </div>
                        <?php else: ?>
                            <ul class="benefits-list">
                                <?php foreach ($benefits as $benefit): ?>
                                    <li>
                                        <i class="fas fa-check-circle"></i>
                                        <div><?php echo nl2br(htmlspecialchars($benefit)); ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="tab-content" id="terms">
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>Terms & Conditions</h3>
                            <p>Standard terms and conditions apply to all schemes. Please contact support for specific details.</p>
                        </div>
                    </div>
                </div>

                <div class="scheme-sidebar">
                    <div class="sidebar-card">
                        <div class="card-header">
                            <i class="fas fa-calculator"></i>
                            Scheme Summary
                        </div>
                        <div class="card-body">
                            <div class="stat-item" style="background: var(--bg-light); margin-bottom: 15px;">
                                <div class="stat-value">₹<?php echo number_format($totalAmount, 2); ?></div>
                                <div class="stat-label">Total Amount</div>
                            </div>
                            <div class="stat-item" style="background: var(--bg-light);">
                                <div class="stat-value"><?php echo $scheme['StartDate'] ? date('d M Y', strtotime($scheme['StartDate'])) : 'N/A'; ?></div>
                                <div class="stat-label">Start Date</div>
                            </div>
                        </div>
                    </div>

                    <div class="sidebar-card">
                        <div class="card-header">
                            <i class="fas fa-ticket-alt"></i>
                            Your Payment Codes
                        </div>
                        <div class="card-body">
                            <div class="payment-code-info">
                                <div class="payment-code-info-text">
                                    <i class="fas fa-ticket-alt"></i>
                                    <div>
                                        <div>Available</div>
                                        <div class="payment-code-count"><?php echo number_format($paymentCodeCount); ?></div>
                                    </div>
                                </div>
                            </div>
                            <a href="../profile/payment-codes.php" class="btn-primary">
                                <i class="fas fa-plus"></i>
                                Request More Codes
                            </a>
                        </div>
                    </div>

                    <div class="sidebar-card">
                        <div class="card-header">
                            <i class="fas fa-user-plus"></i>
                            Add Existing Customer
                        </div>
                        <div class="card-body">
                            <p style="margin-bottom: 15px; font-size: 14px;">Add an existing customer to this scheme.</p>
                            <a href="subscriptions.php?id=<?php echo $schemeId; ?>" class="btn-primary">
                                <i class="fas fa-user-plus"></i>
                                Add Existing Customer
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    tab.classList.add('active');
                    const tabId = tab.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Ensure proper topbar integration
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content-wrapper');

            function adjustContent() {
                if (sidebar.classList.contains('collapsed')) {
                    content.style.marginLeft = 'var(--sidebar-collapsed-width)';
                } else {
                    content.style.marginLeft = 'var(--sidebar-width)';
                }
            }

            // Initial adjustment
            adjustContent();

            // Watch for sidebar changes
            const observer = new MutationObserver(adjustContent);
            observer.observe(sidebar, { attributes: true });
        });
    </script>
</body>
</html> 