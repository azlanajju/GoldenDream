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
    $stmt = $conn->prepare("SELECT * FROM Schemes WHERE SchemeID = ? AND Status = 'Active'");
    $stmt->execute([$schemeId]);
    $scheme = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scheme) {
        header("Location: index.php");
        exit();
    }
    
    // Get all customers of this promoter
    $stmt = $conn->prepare("
        SELECT c.*, 
               CASE WHEN s.SubscriptionID IS NOT NULL THEN 1 ELSE 0 END as IsSubscribed,
               s.SubscriptionID,
               s.StartDate as SubscriptionStartDate,
               s.EndDate as SubscriptionEndDate,
               s.RenewalStatus
        FROM Customers c
        LEFT JOIN Subscriptions s ON c.CustomerID = s.CustomerID AND s.SchemeID = ?
        WHERE c.PromoterID = ? AND c.Status = 'Active'
        ORDER BY c.Name ASC
    ");
    $stmt->execute([$schemeId, $_SESSION['promoter_id']]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $message = "Error fetching data: " . $e->getMessage();
    $messageType = "error";
    $showNotification = true;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_subscription':
                    if (isset($_POST['customer_id']) && is_numeric($_POST['customer_id'])) {
                        // Check if customer already has an active subscription
                        $stmt = $conn->prepare("
                            SELECT COUNT(*) FROM Subscriptions 
                            WHERE CustomerID = ? AND SchemeID = ? AND RenewalStatus = 'Active'
                        ");
                        $stmt->execute([$_POST['customer_id'], $schemeId]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception("Customer already has an active subscription to this scheme.");
                        }
                        
                        // Add new subscription
                        $stmt = $conn->prepare("
                            INSERT INTO Subscriptions (CustomerID, SchemeID, StartDate, EndDate, RenewalStatus)
                            VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'Active')
                        ");
                        $stmt->execute([$_POST['customer_id'], $schemeId]);
                        
                        $message = "Customer successfully added to the scheme.";
                        $messageType = "success";
                    }
                    break;
                    
                case 'remove_subscription':
                    if (isset($_POST['subscription_id']) && is_numeric($_POST['subscription_id'])) {
                        // Update subscription status to Cancelled
                        $stmt = $conn->prepare("
                            UPDATE Subscriptions 
                            SET RenewalStatus = 'Cancelled', EndDate = CURDATE()
                            WHERE SubscriptionID = ? AND CustomerID IN (
                                SELECT CustomerID FROM Customers WHERE PromoterID = ?
                            )
                        ");
                        $stmt->execute([$_POST['subscription_id'], $_SESSION['promoter_id']]);
                        
                        $message = "Customer successfully removed from the scheme.";
                        $messageType = "success";
                    }
                    break;
            }
            
            // Refresh the page to show updated data
            header("Location: subscriptions.php?id=" . $schemeId . "&message=" . urlencode($message) . "&type=" . $messageType);
            exit();
            
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = "error";
            $showNotification = true;
        }
    }
}

// Check for message in URL (after redirect)
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'];
    $showNotification = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscriptions - <?php echo htmlspecialchars($scheme['SchemeName']); ?> | Golden Dreams</title>
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
        }

        .btn-secondary:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--error-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .customers-table {
            width: 100%;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .customers-table th,
        .customers-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .customers-table th {
            background: var(--bg-light);
            font-weight: 600;
            color: var(--text-primary);
        }

        .customers-table tr:last-child td {
            border-bottom: none;
        }

        .customer-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .customer-avatar {
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

        .customer-details {
            display: flex;
            flex-direction: column;
        }

        .customer-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .customer-id {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-cancelled {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .status-expired {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .modal-header {
            padding: 20px;
            background: var(--bg-light);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            background: var(--bg-light);
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .scheme-header {
                padding: 20px;
            }

            .scheme-name {
                font-size: 24px;
            }

            .customers-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
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
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="section-info">
                        <h2>Manage Subscriptions</h2>
                        <p>Add or remove customers from this scheme</p>
                    </div>
                </div>
                <div>
                    <a href="view.php?id=<?php echo $schemeId; ?>" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Scheme
                    </a>
                    <button class="btn-primary" id="addCustomerBtn">
                        <i class="fas fa-user-plus"></i>
                        Add Customer
                    </button>
                </div>
            </div>

            <div class="scheme-header">
                <h1 class="scheme-name"><?php echo htmlspecialchars($scheme['SchemeName']); ?></h1>
                <span class="scheme-status">Active</span>
                <p class="scheme-description">
                    <?php echo htmlspecialchars($scheme['Description'] ?: 'No description available.'); ?>
                </p>
            </div>

            <?php if (empty($customers)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Customers Available</h3>
                    <p>You don't have any customers yet. Add customers first, then you can subscribe them to this scheme.</p>
                    <a href="../customers/add.php" class="btn-primary" style="margin-top: 20px; display: inline-flex;">
                        <i class="fas fa-user-plus"></i>
                        Add Customer
                    </a>
                </div>
            <?php else: ?>
                <table class="customers-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Subscription Status</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <div class="customer-info">
                                        <div class="customer-avatar">
                                            <?php echo strtoupper(substr($customer['Name'], 0, 1)); ?>
                                        </div>
                                        <div class="customer-details">
                                            <div class="customer-name"><?php echo htmlspecialchars($customer['Name']); ?></div>
                                            <div class="customer-id"><?php echo htmlspecialchars($customer['CustomerUniqueID']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($customer['Contact']); ?></td>
                                <td>
                                    <?php if ($customer['IsSubscribed']): ?>
                                        <span class="status-badge status-<?php echo strtolower($customer['RenewalStatus']); ?>">
                                            <?php echo $customer['RenewalStatus']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge" style="background: var(--bg-light); color: var(--text-secondary);">
                                            Not Subscribed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $customer['SubscriptionStartDate'] ? date('d M Y', strtotime($customer['SubscriptionStartDate'])) : '-'; ?>
                                </td>
                                <td>
                                    <?php echo $customer['SubscriptionEndDate'] ? date('d M Y', strtotime($customer['SubscriptionEndDate'])) : '-'; ?>
                                </td>
                                <td>
                                    <?php if ($customer['IsSubscribed'] && $customer['RenewalStatus'] === 'Active'): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="remove_subscription">
                                            <input type="hidden" name="subscription_id" value="<?php echo $customer['SubscriptionID']; ?>">
                                            <button type="submit" class="btn-danger" onclick="return confirm('Are you sure you want to remove this customer from the scheme?');">
                                                <i class="fas fa-times"></i>
                                                Remove
                                            </button>
                                        </form>
                                    <?php elseif (!$customer['IsSubscribed']): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="add_subscription">
                                            <input type="hidden" name="customer_id" value="<?php echo $customer['CustomerID']; ?>">
                                            <button type="submit" class="btn-primary">
                                                <i class="fas fa-plus"></i>
                                                Add to Scheme
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Customer Modal -->
    <div class="modal" id="addCustomerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Customer</h3>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>To add a new customer to this scheme, first create the customer account, then subscribe them to the scheme.</p>
                <div style="margin-top: 20px;">
                    <a href="../customers/add.php?scheme=<?php echo $schemeId; ?>" class="btn-primary">
                        <i class="fas fa-user-plus"></i>
                        Add New Customer
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" id="cancelModal">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('addCustomerModal');
            const addCustomerBtn = document.getElementById('addCustomerBtn');
            const closeModal = document.getElementById('closeModal');
            const cancelModal = document.getElementById('cancelModal');
            
            addCustomerBtn.addEventListener('click', () => {
                modal.style.display = 'flex';
            });
            
            closeModal.addEventListener('click', () => {
                modal.style.display = 'none';
            });
            
            cancelModal.addEventListener('click', () => {
                modal.style.display = 'none';
            });
            
            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
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