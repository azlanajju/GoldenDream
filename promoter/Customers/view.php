<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "customers";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$message = '';
$messageType = '';
$showNotification = false;

// Get customer ID from URL
$customerId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$customerId) {
    header("Location: index.php");
    exit();
}

// Get customer details
try {
    $stmt = $conn->prepare("
        SELECT c.*, p.Name as PromoterName, p.PromoterUniqueID as PromoterID
        FROM Customers c
        LEFT JOIN Promoters p ON c.PromoterID = p.PromoterID
        WHERE c.CustomerID = ? AND c.PromoterID = ?
    ");
    $stmt->execute([$customerId, $_SESSION['promoter_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $message = "Error fetching customer data";
    $messageType = "error";
}

// Get customer's KYC details
try {
    $stmt = $conn->prepare("SELECT * FROM KYC WHERE UserID = ? AND UserType = 'Customer'");
    $stmt->execute([$customerId]);
    $kyc = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching KYC data";
    $messageType = "error";
}

// Get customer's transactions
try {
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            s.SchemeName,
            i.InstallmentName,
            p.SubmittedAt as TransactionDate,
            CASE 
                WHEN p.Status = 'Verified' THEN 'Credit'
                ELSE 'Pending'
            END as TransactionType,
            CONCAT(s.SchemeName, ' - ', i.InstallmentName) as Description
        FROM Payments p
        LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID
        LEFT JOIN Installments i ON p.InstallmentID = i.InstallmentID
        WHERE p.CustomerID = ? 
        ORDER BY p.SubmittedAt DESC 
        LIMIT 10
    ");
    $stmt->execute([$customerId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching transaction data";
    $messageType = "error";
    $transactions = []; // Initialize as empty array if query fails
}

// Get customer's bank details
try {
    $stmt = $conn->prepare("
        SELECT * FROM BankDetails 
        WHERE CustomerID = ? 
        ORDER BY CreatedAt DESC 
        LIMIT 1
    ");
    $stmt->execute([$customerId]);
    $bankDetails = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching bank details";
    $messageType = "error";
}

// Get customer's referral details
try {
    $stmt = $conn->prepare("
        SELECT c.CustomerID, c.Name, c.CustomerUniqueID, c.Contact, c.Status
        FROM Customers c
        WHERE c.ReferredBy = ?
        ORDER BY c.CreatedAt DESC
    ");
    $stmt->execute([$customer['CustomerUniqueID']]);
    $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching referral data";
    $messageType = "error";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Customer | Golden Dreams</title>
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

        .customer-header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .profile-image-container {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--primary-light);
            flex-shrink: 0;
        }

        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .customer-header-info {
            flex: 1;
        }

        .customer-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .customer-id {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .customer-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .meta-item i {
            color: var(--primary-color);
        }

        .customer-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-active {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-inactive {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .status-suspended {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .customer-actions {
            display: flex;
            gap: 10px;
        }

        .info-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: var(--card-shadow);
        }

        .info-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .info-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .info-card-icon i {
            font-size: 18px;
            color: var(--primary-color);
        }

        .info-card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .info-card-content {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .info-card-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
            margin-top: 10px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: white;
            padding: 10px;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
        }

        .tab {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab.active {
            background: var(--primary-color);
            color: white;
        }

        .tab:not(.active) {
            color: var(--text-secondary);
        }

        .tab:not(.active):hover {
            background: var(--bg-light);
        }

        .tab-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .tab-content:not(.active) {
            display: none;
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
            font-size: 14px;
            color: var(--text-secondary);
        }

        .info-value {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .kyc-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .kyc-pending {
            background: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
        }

        .kyc-verified {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .kyc-rejected {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .transactions-table th,
        .transactions-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .transactions-table th {
            font-weight: 600;
            color: var(--text-secondary);
            background: var(--bg-light);
        }

        .transactions-table tr:last-child td {
            border-bottom: none;
        }

        .transaction-amount {
            font-weight: 600;
        }

        .amount-credit {
            color: var(--success-color);
        }

        .amount-debit {
            color: var(--error-color);
        }

        .referral-card {
            background: var(--bg-light);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .referral-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .referral-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-weight: 600;
        }

        .referral-details h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .referral-details p {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 40px;
            margin-bottom: 15px;
            color: var(--border-color);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .customer-header {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }

            .customer-meta {
                justify-content: center;
            }

            .customer-actions {
                justify-content: center;
            }

            .info-cards {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .tabs {
                overflow-x: auto;
                padding-bottom: 5px;
            }

            .tab {
                white-space: nowrap;
            }

            .transactions-table {
                display: block;
                overflow-x: auto;
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
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="section-info">
                        <h2>Customer Details</h2>
                        <p>View and manage customer information</p>
                    </div>
                </div>
                <div class="section-actions">
                    <a href="edit.php?id=<?php echo $customer['CustomerID']; ?>" class="btn-primary">
                        <i class="fas fa-edit"></i>
                        Edit Customer
                    </a>
                    <a href="index.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to List
                    </a>
                </div>
            </div>

            <div class="customer-header">
                <div class="profile-image-container">
                    <img src="<?php 
                        if ($customer['ProfileImageURL'] && file_exists('../../uploads/profile/' . $customer['ProfileImageURL'])) {
                            echo '../../uploads/profile/' . htmlspecialchars($customer['ProfileImageURL']);
                        } else {
                            echo '../../uploads/profile/image.png';
                        }
                    ?>" alt="Profile Image" class="profile-image">
                </div>
                <div class="customer-header-info">
                    <h2 class="customer-name"><?php echo htmlspecialchars($customer['Name']); ?></h2>
                    <div class="customer-id">ID: <?php echo htmlspecialchars($customer['CustomerUniqueID']); ?></div>
                    <div class="customer-meta">
                        <div class="meta-item">
                            <i class="fas fa-phone"></i>
                            <?php echo htmlspecialchars($customer['Contact']); ?>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($customer['Email'] ?: 'N/A'); ?>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            Joined: <?php echo date('d M Y', strtotime($customer['CreatedAt'])); ?>
                        </div>
                        <?php if ($customer['ReferredBy']): ?>
                        <div class="meta-item">
                            <i class="fas fa-user-plus"></i>
                            Referred by: <?php echo htmlspecialchars($customer['ReferredBy']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="customer-status status-<?php echo strtolower($customer['Status']); ?>">
                        <?php echo htmlspecialchars($customer['Status']); ?>
                    </div>
                    <?php if ($kyc): ?>
                        <div class="kyc-status kyc-<?php echo strtolower($kyc['Status']); ?>">
                            KYC: <?php echo htmlspecialchars($kyc['Status']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-cards">
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="info-card-title">Bank Details</div>
                    </div>
                    <div class="info-card-content">
                        <?php if ($customer['BankAccountName'] && $customer['BankAccountNumber']): ?>
                            <div class="info-card-value">
                                <?php echo htmlspecialchars($customer['BankAccountName']); ?>
                            </div>
                            <div>Account: <?php echo htmlspecialchars($customer['BankAccountNumber']); ?></div>
                            <div>IFSC: <?php echo htmlspecialchars($customer['IFSCCode']); ?></div>
                            <div>Bank: <?php echo htmlspecialchars($customer['BankName']); ?></div>
                        <?php else: ?>
                            <div class="info-card-value">Not Provided</div>
                            <div>Bank details have not been added yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="info-card-title">Referrals</div>
                    </div>
                    <div class="info-card-content">
                        <div class="info-card-value">
                            <?php echo count($referrals); ?>
                        </div>
                        <div>Total customers referred by this customer.</div>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="info-card-title">Transactions</div>
                    </div>
                    <div class="info-card-content">
                        <div class="info-card-value">
                            <?php echo count($transactions); ?>
                        </div>
                        <div>Recent transactions for this customer.</div>
                    </div>
                </div>
            </div>

            <div class="tabs">
                <div class="tab active" data-tab="personal">Personal Info</div>
                <div class="tab" data-tab="kyc">KYC Details</div>
                <div class="tab" data-tab="transactions">Transactions</div>
                <div class="tab" data-tab="referrals">Referrals</div>
            </div>

            <div class="tab-content active" id="personal-tab">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['Name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Contact Number</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['Contact']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['Email'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date of Birth</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['DateOfBirth'] ? date('d M Y', strtotime($customer['DateOfBirth'])) : 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Gender</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['Gender'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['Address'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Promoter</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['PromoterName']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Promoter ID</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['PromoterID']); ?></span>
                    </div>
                </div>
            </div>

            <div class="tab-content" id="kyc-tab">
                <?php if ($kyc): ?>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Aadhar Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($kyc['AadharNumber']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">PAN Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($kyc['PANNumber']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">ID Proof Type</span>
                            <span class="info-value"><?php echo htmlspecialchars($kyc['IDProofType']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Address Proof Type</span>
                            <span class="info-value"><?php echo htmlspecialchars($kyc['AddressProofType']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Submission Date</span>
                            <span class="info-value"><?php echo date('d M Y', strtotime($kyc['SubmissionDate'])); ?></span>
                        </div>
                        <?php if ($kyc['VerificationDate']): ?>
                        <div class="info-item">
                            <span class="info-label">Verification Date</span>
                            <span class="info-value"><?php echo date('d M Y', strtotime($kyc['VerificationDate'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($kyc['Remarks']): ?>
                        <div class="info-item">
                            <span class="info-label">Remarks</span>
                            <span class="info-value"><?php echo htmlspecialchars($kyc['Remarks']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-file-alt"></i>
                        <h3>No KYC Information</h3>
                        <p>This customer has not submitted their KYC details yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-content" id="transactions-tab">
                <?php if (empty($transactions)): ?>
                    <div class="no-data">
                        <i class="fas fa-exchange-alt"></i>
                        <h3>No Transactions</h3>
                        <p>This customer has no payment history yet.</p>
                    </div>
                <?php else: ?>
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($transaction['TransactionDate'])); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['TransactionType']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['Description']); ?></td>
                                    <td class="transaction-amount <?php echo $transaction['TransactionType'] === 'Credit' ? 'amount-credit' : 'amount-debit'; ?>">
                                        ₹<?php echo number_format($transaction['Amount'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($transaction['Status']); ?>">
                                            <?php echo htmlspecialchars($transaction['Status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="tab-content" id="referrals-tab">
                <?php if (empty($referrals)): ?>
                    <div class="no-data">
                        <i class="fas fa-users"></i>
                        <h3>No Referrals</h3>
                        <p>This customer has not referred any other customers yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($referrals as $referral): ?>
                        <div class="referral-card">
                            <div class="referral-info">
                                <div class="referral-avatar">
                                    <?php echo substr($referral['Name'], 0, 1); ?>
                                </div>
                                <div class="referral-details">
                                    <h4><?php echo htmlspecialchars($referral['Name']); ?></h4>
                                    <p>ID: <?php echo htmlspecialchars($referral['CustomerUniqueID']); ?></p>
                                </div>
                            </div>
                            <div class="customer-status status-<?php echo strtolower($referral['Status']); ?>">
                                <?php echo htmlspecialchars($referral['Status']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
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

            // Tab functionality
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    tab.classList.add('active');
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                });
            });
        });
    </script>
</body>
</html> 