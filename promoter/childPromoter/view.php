<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "childPromoter";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get promoter ID from session
$promoterId = $_SESSION['promoter_id'];

// Get child promoter ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$childPromoterId = $_GET['id'];

// Verify that the child promoter belongs to the current promoter
$verifyQuery = "SELECT PromoterID FROM Promoters WHERE PromoterID = :childPromoterId AND ParentPromoterID = :promoterId";
$verifyStmt = $conn->prepare($verifyQuery);
$verifyStmt->bindParam(':childPromoterId', $childPromoterId);
$verifyStmt->bindParam(':promoterId', $promoterId);
$verifyStmt->execute();

if ($verifyStmt->rowCount() === 0) {
    header("Location: index.php");
    exit();
}

// Get child promoter details
$query = "
    SELECT 
        p.PromoterID,
        p.PromoterUniqueID,
        p.Name,
        p.Contact,
        p.Email,
        p.Address,
        p.ProfileImageURL,
        p.BankAccountName,
        p.BankAccountNumber,
        p.IFSCCode,
        p.BankName,
        p.PaymentCodeCounter,
        p.TeamName,
        p.Status,
        p.Commission,
        p.CreatedAt,
        p.ParentPromoterID,
        parent.Name AS ParentName,
        parent.PromoterUniqueID AS ParentPromoterUniqueID,
        parent.Contact AS ParentContact,
        parent.Email AS ParentEmail,
        COUNT(DISTINCT c.CustomerID) AS CustomerCount,
        COUNT(DISTINCT pay.PaymentID) AS PaymentCount,
        SUM(CASE WHEN pay.Status = 'Verified' THEN pay.Amount ELSE 0 END) AS TotalVerifiedAmount
    FROM 
        Promoters p
    LEFT JOIN 
        Promoters parent ON p.ParentPromoterID = parent.PromoterID
    LEFT JOIN 
        Customers c ON p.PromoterID = c.PromoterID
    LEFT JOIN 
        Payments pay ON p.PromoterID = pay.PromoterID
    WHERE 
        p.PromoterID = :childPromoterId
    GROUP BY 
        p.PromoterID
";

$stmt = $conn->prepare($query);
$stmt->bindParam(':childPromoterId', $childPromoterId);
$stmt->execute();
$promoter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$promoter) {
    header("Location: index.php");
    exit();
}

// Get recent customers
$customersQuery = "
    SELECT 
        c.CustomerID,
        c.CustomerUniqueID,
        c.Name,
        c.Contact,
        c.Email,
        c.Status,
        c.CreatedAt,
        COUNT(DISTINCT pay.PaymentID) AS PaymentCount,
        SUM(CASE WHEN pay.Status = 'Verified' THEN pay.Amount ELSE 0 END) AS TotalVerifiedAmount
    FROM 
        Customers c
    LEFT JOIN 
        Payments pay ON c.CustomerID = pay.CustomerID
    WHERE 
        c.PromoterID = :childPromoterId
    GROUP BY 
        c.CustomerID
    ORDER BY 
        c.CreatedAt DESC
    LIMIT 5
";

$customersStmt = $conn->prepare($customersQuery);
$customersStmt->bindParam(':childPromoterId', $childPromoterId);
$customersStmt->execute();
$recentCustomers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent payments
$paymentsQuery = "
    SELECT 
        pay.PaymentID,
        pay.Amount,
        pay.Status,
        pay.SubmittedAt,
        pay.VerifiedAt,
        c.Name AS CustomerName,
        c.CustomerUniqueID,
        sch.SchemeName,
        i.InstallmentName
    FROM 
        Payments pay
    JOIN 
        Customers c ON pay.CustomerID = c.CustomerID
    JOIN 
        Schemes sch ON pay.SchemeID = sch.SchemeID
    JOIN 
        Installments i ON pay.InstallmentID = i.InstallmentID
    WHERE 
        c.PromoterID = :childPromoterId
    ORDER BY 
        pay.SubmittedAt DESC
    LIMIT 5
";

$paymentsStmt = $conn->prepare($paymentsQuery);
$paymentsStmt->bindParam(':childPromoterId', $childPromoterId);
$paymentsStmt->execute();
$recentPayments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get child promoters of the current promoter being viewed
$childPromotersQuery = "
    SELECT 
        p.PromoterID,
        p.PromoterUniqueID,
        p.Name,
        p.Contact,
        p.Email,
        p.ProfileImageURL,
        p.Status,
        p.TeamName,
        p.CreatedAt,
        COUNT(DISTINCT c.CustomerID) AS CustomerCount,
        COUNT(DISTINCT pay.PaymentID) AS PaymentCount,
        SUM(CASE WHEN pay.Status = 'Verified' THEN pay.Amount ELSE 0 END) AS TotalVerifiedAmount
    FROM 
        Promoters p
    LEFT JOIN 
        Customers c ON p.PromoterID = c.PromoterID
    LEFT JOIN 
        Payments pay ON p.PromoterID = pay.PromoterID
    WHERE 
        p.ParentPromoterID = :childPromoterId
    GROUP BY 
        p.PromoterID
    ORDER BY 
        p.CreatedAt DESC
    LIMIT 5
";

$childPromotersStmt = $conn->prepare($childPromotersQuery);
$childPromotersStmt->bindParam(':childPromoterId', $childPromoterId);
$childPromotersStmt->execute();
$childPromoters = $childPromotersStmt->fetchAll(PDO::FETCH_ASSOC);

// Count total child promoters for pagination
$countChildPromotersQuery = "
    SELECT COUNT(*) as total
    FROM Promoters
    WHERE ParentPromoterID = :childPromoterId
";

$countChildPromotersStmt = $conn->prepare($countChildPromotersQuery);
$countChildPromotersStmt->bindParam(':childPromoterId', $childPromoterId);
$countChildPromotersStmt->execute();
$totalChildPromoters = $countChildPromotersStmt->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Promoter | Golden Dreams</title>
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

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(13, 106, 80, 0.2);
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(13, 106, 80, 0.15);
        }

        .btn-back {
            background: white;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-back:hover {
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .promoter-profile {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .profile-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .profile-header {
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 20px;
            overflow: hidden;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: var(--primary-color);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .profile-id {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-inactive {
            background: rgba(231, 76, 60, 0.1);
            color: #c0392b;
        }

        .profile-body {
            padding: 30px;
        }

        .info-section {
            margin-bottom: 25px;
        }

        .info-section:last-child {
            margin-bottom: 0;
        }

        .info-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-title i {
            color: var(--primary-color);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .info-label {
            color: var(--text-secondary);
        }

        .info-value {
            font-weight: 500;
            color: var(--text-primary);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            text-align: center;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 15px;
        }

        .stat-icon.customers {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .stat-icon.payments {
            background: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
        }

        .stat-icon.amount {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .stat-info h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .recent-section {
            margin-bottom: 30px;
        }

        .recent-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recent-title i {
            color: var(--primary-color);
        }

        .recent-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .recent-table th {
            background: var(--bg-light);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
        }

        .recent-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-primary);
        }

        .recent-table tr:last-child td {
            border-bottom: none;
        }

        .recent-table tr:hover {
            background: var(--bg-light);
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            background: var(--primary-light);
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .view-more {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .empty-state {
            padding: 50px 20px;
            text-align: center;
            background: white;
            border-radius: 12px;
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
            margin: 0 auto 20px;
        }

        @media (max-width: 992px) {
            .promoter-profile {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .recent-table {
                display: block;
                overflow-x: auto;
            }
        }

        .child-promoters-grid {
            display: flex;
            flex-direction: row;
            overflow-x: auto;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            scrollbar-width: thin;
            scrollbar-color: var(--primary-color) var(--bg-light);
        }

        .child-promoters-grid::-webkit-scrollbar {
            height: 8px;
        }

        .child-promoters-grid::-webkit-scrollbar-track {
            background: var(--bg-light);
            border-radius: 10px;
        }

        .child-promoters-grid::-webkit-scrollbar-thumb {
            background-color: var(--primary-color);
            border-radius: 10px;
        }

        .child-promoter-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            min-width: 300px;
            flex: 0 0 auto;
        }

        .child-promoter-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .child-promoter-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .child-promoter-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--primary-color);
        }

        .child-promoter-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .child-promoter-info {
            flex: 1;
        }

        .child-promoter-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .child-promoter-id {
            font-size: 12px;
            color: var(--text-secondary);
            display: block;
            margin-bottom: 5px;
        }

        .child-promoter-body {
            padding: 15px 20px;
        }

        .child-promoter-contact {
            margin-bottom: 15px;
        }

        .child-promoter-contact div {
            font-size: 13px;
            margin-bottom: 5px;
            color: var(--text-secondary);
        }

        .child-promoter-contact i {
            width: 20px;
            color: var(--primary-color);
        }

        .child-promoter-stats {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
        }

        .child-promoter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            background: var(--primary-light);
            color: var(--primary-color);
            margin-left: 10px;
        }

        @media (max-width: 768px) {
            .child-promoters-grid {
                flex-direction: row;
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
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="section-info">
                        <h2>Promoter Details</h2>
                        <p>View detailed information about this promoter</p>
                    </div>
                </div>
                <div>
                    <a href="index.php" class="btn btn-back">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>

            <div class="promoter-profile">
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php if ($promoter['ProfileImageURL']): ?>
                                <img src="../../<?php echo htmlspecialchars($promoter['ProfileImageURL']); ?>" alt="<?php echo htmlspecialchars($promoter['Name']); ?>">
                            <?php else: ?>
                                <img src="../../uploads/profile/image.png" alt="Default Profile">
                            <?php endif; ?>
                        </div>
                        <h3 class="profile-name"><?php echo htmlspecialchars($promoter['Name']); ?></h3>
                        <div class="profile-id"><?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?></div>
                        <span class="status-badge status-<?php echo strtolower($promoter['Status']); ?>">
                            <?php echo $promoter['Status']; ?>
                        </span>
                    </div>
                    <div class="profile-body">
                        <div class="info-section">
                            <div class="info-title">
                                <i class="fas fa-info-circle"></i> Personal Information
                            </div>
                            <div class="info-row">
                                <span class="info-label">Contact:</span>
                                <span class="info-value"><?php echo htmlspecialchars($promoter['Contact']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($promoter['Email']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Address:</span>
                                <span class="info-value"><?php echo htmlspecialchars($promoter['Address']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Team:</span>
                                <span class="info-value"><?php echo htmlspecialchars($promoter['TeamName'] ?? 'Not Assigned'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Commission:</span>
                                <span class="info-value"><?php echo htmlspecialchars($promoter['Commission'] ?? 'Not Set'); ?></span>
                            </div>
                        </div>
                        
                        <!-- Parent Promoter Information -->
                        <div class="info-section">
                            <div class="info-title">
                                <i class="fas fa-user-friends"></i> Parent Promoter
                            </div>
                            <?php if ($promoter['ParentPromoterID']): ?>
                                <div class="info-row">
                                    <span class="info-label">Name:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($promoter['ParentName']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">ID:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($promoter['ParentPromoterUniqueID']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Contact:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($promoter['ParentContact']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($promoter['ParentEmail']); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="info-row">
                                    <span class="info-value">No parent promoter assigned</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="info-section">
                            <div class="info-title">
                                <i class="fas fa-university"></i> Bank Details
                            </div>
                            <div class="info-row">
                                <span class="info-label">Account Holder:</span>
                                <span class="info-value"><?php echo htmlspecialchars($promoter['BankAccountName']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Account Number:</span>
                                <span class="info-value"><?php echo htmlspecialchars($promoter['BankAccountNumber']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">IFSC Code:</span>
                                <span class="info-value"><?php echo htmlspecialchars($promoter['IFSCCode']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Bank Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($promoter['BankName']); ?></span>
                            </div>
                        </div>
                        <div class="info-section">
                            <div class="info-title">
                                <i class="fas fa-cog"></i> Account Details
                            </div>
                            <div class="info-row">
                                <span class="info-label">Payment Codes:</span>
                                <span class="info-value"><?php echo $promoter['PaymentCodeCounter']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Joined:</span>
                                <span class="info-value"><?php echo date('d M Y', strtotime($promoter['CreatedAt'])); ?></span>
                            </div>
                        </div>
                        <div class="info-section">
                            <div class="info-title">
                                <i class="fas fa-cogs"></i> Actions
                            </div>
                            <div style="display: flex; gap: 10px; margin-top: 15px;">
                                <a href="edit.php?id=<?php echo $promoter['PromoterID']; ?>" class="btn btn-outline" style="flex: 1;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="delete.php?id=<?php echo $promoter['PromoterID']; ?>" class="btn btn-outline" style="flex: 1;" onclick="return confirm('Are you sure you want to delete this promoter? This action cannot be undone.');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats and Recent Activity -->
                <div>
                    <!-- Statistics -->
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-icon customers">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $promoter['CustomerCount']; ?></h3>
                                <p>Total Customers</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon payments">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $promoter['PaymentCount']; ?></h3>
                                <p>Total Payments</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon amount">
                                <i class="fas fa-rupee-sign"></i>
                            </div>
                            <div class="stat-info">
                                <h3>₹<?php echo number_format($promoter['TotalVerifiedAmount'], 2); ?></h3>
                                <p>Total Verified Amount</p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Customers -->
                    <div class="recent-section">
                        <div class="recent-title">
                            <i class="fas fa-user-friends"></i> Recent Customers
                        </div>
                        <?php if (empty($recentCustomers)): ?>
                            <div class="empty-state">
                                <i class="fas fa-user-friends"></i>
                                <h3>No Customers Found</h3>
                                <p>This promoter hasn't added any customers yet.</p>
                            </div>
                        <?php else: ?>
                            <table class="recent-table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Payments</th>
                                        <th>Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentCustomers as $customer): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo htmlspecialchars($customer['Name']); ?></div>
                                                <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($customer['CustomerUniqueID']); ?></div>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($customer['Contact']); ?></div>
                                                <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($customer['Email']); ?></div>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($customer['Status']); ?>">
                                                    <?php echo $customer['Status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $customer['PaymentCount']; ?></td>
                                            <td>₹<?php echo number_format($customer['TotalVerifiedAmount'], 2); ?></td>
                                            <td>
                                                <div class="promoter-actions">
                                                    <a href="../customers/view.php?id=<?php echo $customer['CustomerID']; ?>" class="action-btn" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="../customers/edit.php?id=<?php echo $customer['CustomerID']; ?>" class="action-btn" title="Edit Customer">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="../customers/delete.php?id=<?php echo $customer['CustomerID']; ?>" class="action-btn" title="Delete Customer" onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="view-more">
                                <a href="../customers/index.php?promoter=<?php echo $promoter['PromoterID']; ?>" class="btn btn-outline">
                                    <i class="fas fa-list"></i> View All Customers
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Payments -->
                    <div class="recent-section">
                        <div class="recent-title">
                            <i class="fas fa-money-bill-wave"></i> Recent Payments
                        </div>
                        <?php if (empty($recentPayments)): ?>
                            <div class="empty-state">
                                <i class="fas fa-money-bill-wave"></i>
                                <h3>No Payments Found</h3>
                                <p>This promoter's customers haven't made any payments yet.</p>
                            </div>
                        <?php else: ?>
                            <table class="recent-table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Scheme</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentPayments as $payment): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo htmlspecialchars($payment['CustomerName']); ?></div>
                                                <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($payment['CustomerUniqueID']); ?></div>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($payment['SchemeName']); ?></div>
                                                <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($payment['InstallmentName']); ?></div>
                                            </td>
                                            <td>₹<?php echo number_format($payment['Amount'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                                    <?php echo $payment['Status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($payment['SubmittedAt'])); ?></td>
                                            <td>
                                                <div class="promoter-actions">
                                                    <a href="../payments/view.php?id=<?php echo $payment['PaymentID']; ?>" class="action-btn" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="../payments/edit.php?id=<?php echo $payment['PaymentID']; ?>" class="action-btn" title="Edit Payment">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="../payments/delete.php?id=<?php echo $payment['PaymentID']; ?>" class="action-btn" title="Delete Payment" onclick="return confirm('Are you sure you want to delete this payment? This action cannot be undone.');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="view-more">
                                <a href="../payments/index.php?promoter=<?php echo $promoter['PromoterID']; ?>" class="btn btn-outline">
                                    <i class="fas fa-list"></i> View All Payments
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Child Promoters Section -->
                    <div class="recent-section">
                        <div class="recent-title">
                            <i class="fas fa-users"></i> Child Promoters
                            <span class="badge"><?php echo $totalChildPromoters; ?></span>
                        </div>
                        <?php if (empty($childPromoters)): ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h3>No Child Promoters Found</h3>
                                <p>This promoter doesn't have any child promoters yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="child-promoters-grid">
                                <?php foreach ($childPromoters as $childPromoter): ?>
                                    <div class="child-promoter-card">
                                        <div class="child-promoter-header">
                                            <div class="child-promoter-avatar">
                                                <?php if ($childPromoter['ProfileImageURL']): ?>
                                                    <img src="../../<?php echo htmlspecialchars($childPromoter['ProfileImageURL']); ?>" alt="<?php echo htmlspecialchars($childPromoter['Name']); ?>">
                                                <?php else: ?>
                                                    <img src="../../uploads/profile/image.png" alt="Default Profile">
                                                <?php endif; ?>
                                            </div>
                                            <div class="child-promoter-info">
                                                <h4 class="child-promoter-name"><?php echo htmlspecialchars($childPromoter['Name']); ?></h4>
                                                <span class="child-promoter-id"><?php echo htmlspecialchars($childPromoter['PromoterUniqueID']); ?></span>
                                                <span class="status-badge status-<?php echo strtolower($childPromoter['Status']); ?>">
                                                    <?php echo $childPromoter['Status']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="child-promoter-body">
                                            <div class="child-promoter-contact">
                                                <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($childPromoter['Contact']); ?></div>
                                                <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($childPromoter['Email']); ?></div>
                                                <div><i class="fas fa-users"></i> <?php echo htmlspecialchars($childPromoter['TeamName'] ?? 'Not Assigned'); ?></div>
                                            </div>
                                            <div class="child-promoter-stats">
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $childPromoter['CustomerCount']; ?></div>
                                                    <div class="stat-label">Customers</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $childPromoter['PaymentCount']; ?></div>
                                                    <div class="stat-label">Payments</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value">₹<?php echo number_format($childPromoter['TotalVerifiedAmount'], 2); ?></div>
                                                    <div class="stat-label">Amount</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="child-promoter-actions">
                                            <a href="view.php?id=<?php echo $childPromoter['PromoterID']; ?>" class="action-btn" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $childPromoter['PromoterID']; ?>" class="action-btn" title="Edit Promoter">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo $childPromoter['PromoterID']; ?>" class="action-btn" title="Delete Promoter" onclick="return confirm('Are you sure you want to delete this promoter? This action cannot be undone.');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($totalChildPromoters > 5): ?>
                                <div class="view-more">
                                    <a href="index.php?parent=<?php echo $promoter['PromoterID']; ?>" class="btn btn-outline">
                                        <i class="fas fa-list"></i> View All Child Promoters
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
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
        });
    </script>
</body>
</html> 