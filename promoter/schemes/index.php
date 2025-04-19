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

// Get all active schemes
try {
    $stmt = $conn->prepare("
        SELECT s.*, 
               COUNT(i.InstallmentID) as TotalInstallments,
               MIN(i.DrawDate) as FirstDrawDate,
               MAX(i.DrawDate) as LastDrawDate
        FROM Schemes s
        LEFT JOIN Installments i ON s.SchemeID = i.SchemeID
        WHERE s.Status = 'Active'
        GROUP BY s.SchemeID
        ORDER BY s.CreatedAt DESC
    ");
    $stmt->execute();
    $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching schemes: " . $e->getMessage();
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
    <title>Available Schemes | Golden Dreams</title>
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

        .schemes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .scheme-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
        }

        .scheme-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .scheme-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            position: relative;
            overflow: hidden;
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
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }

        .scheme-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
        }

        .scheme-body {
            padding: 20px;
        }

        .scheme-description {
            color: var(--text-secondary);
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }

        .scheme-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .scheme-footer {
            padding: 15px 20px;
            background: var(--bg-light);
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .scheme-actions {
            display: flex;
            gap: 10px;
            width: 100%;
            justify-content: flex-end;
        }

        .btn-primary {
            flex: 0 1 auto;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            text-decoration: none;
            min-width: 120px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .btn-primary i {
            font-size: 12px;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(13, 106, 80, 0.2);
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            font-weight: 500;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .btn-outline i {
            margin-right: 3px;
            font-size: 12px;
            transition: transform 0.2s ease;
        }

        .btn-outline:hover {
            background: var(--primary-light);
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(13, 106, 80, 0.15);
        }

        .btn-outline:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .btn-outline:hover i {
            transform: scale(1.1);
        }

        .payment-code-info {
            background: var(--primary-light);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 30px;
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

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
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

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .schemes-grid {
                grid-template-columns: 1fr;
            }

            .payment-code-info {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .scheme-actions {
                flex-direction: column;
            }
            
            .btn-primary {
                width: 100%;
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
                        <h2>Available Schemes</h2>
                        <p>Browse and explore all available schemes</p>
                    </div>
                </div>
            </div>

            <div class="payment-code-info">
                <div class="payment-code-info-text">
                    <i class="fas fa-ticket-alt"></i>
                    <div>
                        <div>Your Payment Codes</div>
                        <div class="payment-code-count"><?php echo number_format($paymentCodeCount); ?></div>
                    </div>
                </div>
                <a href="../profile/payment-codes.php" class="btn-primary">
                    <i class="fas fa-plus"></i>
                    Request More Codes
                </a>
            </div>

            <?php if (empty($schemes)): ?>
                <div class="empty-state">
                    <i class="fas fa-gift"></i>
                    <h3>No Schemes Available</h3>
                    <p>There are currently no active schemes available. Please check back later.</p>
                </div>
            <?php else: ?>
                <div class="schemes-grid">
                    <?php foreach ($schemes as $scheme): ?>
                        <div class="scheme-card">
                            <div class="scheme-header">
                                <h3 class="scheme-name"><?php echo htmlspecialchars($scheme['SchemeName']); ?></h3>
                                <span class="scheme-status">Active</span>
                            </div>
                            <div class="scheme-body">
                                <p class="scheme-description">
                                    <?php echo htmlspecialchars($scheme['Description'] ?: 'No description available.'); ?>
                                </p>
                                <div class="scheme-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Monthly Payment</span>
                                        <span class="detail-value">₹<?php echo number_format($scheme['MonthlyPayment'], 2); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Total Payments</span>
                                        <span class="detail-value"><?php echo $scheme['TotalPayments']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Installments</span>
                                        <span class="detail-value"><?php echo $scheme['TotalInstallments']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Duration</span>
                                        <span class="detail-value">
                                            <?php 
                                                if ($scheme['FirstDrawDate'] && $scheme['LastDrawDate']) {
                                                    $startDate = new DateTime($scheme['FirstDrawDate']);
                                                    $endDate = new DateTime($scheme['LastDrawDate']);
                                                    $interval = $startDate->diff($endDate);
                                                    echo $interval->m . ' months';
                                                } else {
                                                    echo 'N/A';
                                                }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="scheme-footer">
                                <div class="scheme-actions">
                                    <a href="view.php?id=<?php echo $scheme['SchemeID']; ?>" class="btn-primary">
                                        <i class="fas fa-eye"></i>
                                        View Details
                                    </a>
                                    <a href="subscriptions.php?id=<?php echo $scheme['SchemeID']; ?>" class="btn-primary btn-outline">
                                        <i class="fas fa-user-plus"></i>
                                        Add Existing Customer
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Ensure proper topbar integration
        document.addEventListener('DOMContentLoaded', function() {
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
