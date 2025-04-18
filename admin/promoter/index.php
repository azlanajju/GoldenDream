<?php
session_start();
// Check if user is logged in, redirect if not
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "promoters";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Handle Delete Promoter
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $promoterId = $_GET['delete'];

    try {
        // Begin transaction
        $conn->beginTransaction();

        // First, log the activity
        $action = "Deleted promoter account";
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        // Now delete the promoter
        $stmt = $conn->prepare("DELETE FROM Promoters WHERE PromoterID = ?");
        $stmt->execute([$promoterId]);

        // Commit the transaction
        $conn->commit();

        $_SESSION['success_message'] = "Promoter deleted successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to delete promoter: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Change Promoter Status
if (isset($_GET['status']) && !empty($_GET['status']) && isset($_GET['id']) && !empty($_GET['id'])) {
    $promoterId = $_GET['id'];
    $newStatus = $_GET['status'] === 'activate' ? 'Active' : 'Inactive';

    try {
        // Begin transaction
        $conn->beginTransaction();

        // First, log the activity
        $action = "Changed promoter status to " . $newStatus;
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        // Now update the promoter status
        $stmt = $conn->prepare("UPDATE Promoters SET Status = ? WHERE PromoterID = ?");
        $stmt->execute([$newStatus, $promoterId]);

        // Commit the transaction
        $conn->commit();

        $_SESSION['success_message'] = "Promoter status updated successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to update promoter status: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Search and filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$parentId = isset($_GET['parent_id']) ? $_GET['parent_id'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(p.Name LIKE :search OR p.Email LIKE :search OR p.Contact LIKE :search OR p.PromoterUniqueID LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "p.Status = :status";
    $params[':status'] = $status;
}

if (!empty($parentId)) {
    $conditions[] = "p.ParentPromoterID = :parent_id";
    $params[':parent_id'] = $parentId;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total number of promoters (for pagination)
$countQuery = "SELECT COUNT(*) as total FROM Promoters p" . $whereClause;
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get promoters with pagination
$query = "SELECT p.PromoterID, p.PromoterUniqueID, p.Name, p.Contact, p.Email, 
          p.Status, p.CreatedAt, p.PaymentCodeCounter, parent.Name as ParentName 
          FROM Promoters p 
          LEFT JOIN Promoters parent ON p.ParentPromoterID = parent.PromoterID"
    . $whereClause .
    " ORDER BY p.CreatedAt DESC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

// Bind all parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$promoters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get count of customers per promoter
$promoterCustomerCounts = [];
if (!empty($promoters)) {
    $promoterIds = array_column($promoters, 'PromoterID');
    $placeholders = implode(',', array_fill(0, count($promoterIds), '?'));

    $countQuery = "SELECT PromoterID, COUNT(*) as customer_count 
                   FROM Customers 
                   WHERE PromoterID IN ($placeholders) 
                   GROUP BY PromoterID";

    $stmt = $conn->prepare($countQuery);
    foreach ($promoterIds as $key => $id) {
        $stmt->bindValue($key + 1, $id);
    }
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $promoterCustomerCounts[$row['PromoterID']] = $row['customer_count'];
    }
}

// Get all parent promoters for filter dropdown
$parentQuery = "SELECT PromoterID, Name, PromoterUniqueID FROM Promoters WHERE Status = 'Active' ORDER BY Name";
$stmt = $conn->prepare($parentQuery);
$stmt->execute();
$parentPromoters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promoter Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Promoter Management Styles with pr_ prefix variables */
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

        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--pr_shadow-sm);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--pr_border-color);
            background-color: white;
        }

        .card-header h2 {
            margin: 0 0 15px 0;
            font-size: 18px;
            color: var(--pr_text-dark);
            font-weight: 600;
        }

        .card-body {
            padding: 0;
        }

        /* Promoter Table Styles */
        .promoter-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .promoter-table th,
        .promoter-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid var(--pr_border-color);
            font-size: 14px;
            vertical-align: middle;
        }

        .promoter-table th {
            background-color: var(--pr_bg-light);
            color: var(--pr_text-medium);
            font-weight: 600;
            position: relative;
            cursor: pointer;
            transition: background-color var(--pr_transition);
            white-space: nowrap;
        }

        .promoter-table th:hover {
            background-color: #edf2f7;
        }

        .promoter-table th::after {
            content: '↕';
            position: absolute;
            right: 15px;
            color: #cbd5e0;
            font-size: 14px;
        }

        .promoter-table th.asc::after {
            content: '↑';
            color: var(--pr_primary);
        }

        .promoter-table th.desc::after {
            content: '↓';
            color: var(--pr_primary);
        }

        .promoter-table tbody tr {
            transition: background-color var(--pr_transition);
        }

        .promoter-table tbody tr:hover {
            background-color: rgba(58, 123, 213, 0.03);
        }

        .promoter-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Column widths for better alignment */
        .promoter-table th:nth-child(1),
        .promoter-table td:nth-child(1) {
            width: 10%;
        }

        .promoter-table th:nth-child(2),
        .promoter-table td:nth-child(2) {
            width: 15%;
        }

        .promoter-table th:nth-child(3),
        .promoter-table td:nth-child(3) {
            width: 12%;
        }

        .promoter-table th:nth-child(4),
        .promoter-table td:nth-child(4) {
            width: 14%;
        }

        .promoter-table th:nth-child(5),
        .promoter-table td:nth-child(5) {
            width: 8%;
        }

        .promoter-table th:nth-child(6),
        .promoter-table td:nth-child(6) {
            width: 8%;
        }

        .promoter-table th:nth-child(7),
        .promoter-table td:nth-child(7) {
            width: 8%;
        }

        .promoter-table th:nth-child(8),
        .promoter-table td:nth-child(8) {
            width: 10%;
        }

        .promoter-table th:nth-child(9),
        .promoter-table td:nth-child(9) {
            width: 15%;
        }

        /* Status Badge Styles */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
            min-width: 80px;
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

        /* Promoter Actions Styles */
        .promoter-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .promoter-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            color: white;
            transition: all var(--pr_transition);
        }

        .promoter-actions .view-btn {
            background: var(--pr_info);
        }

        .promoter-actions .view-btn:hover {
            background: var(--pr_info-hover);
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(41, 128, 185, 0.2);
        }

        .promoter-actions .edit-btn {
            background: var(--pr_primary);
        }

        .promoter-actions .edit-btn:hover {
            background: var(--pr_primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(44, 96, 169, 0.2);
        }

        .promoter-actions .delete-btn {
            background: var(--pr_danger);
        }

        .promoter-actions .delete-btn:hover {
            background: var(--pr_danger-hover);
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(192, 57, 43, 0.2);
        }

        .promoter-actions .activate-btn {
            background: var(--pr_success);
        }

        .promoter-actions .activate-btn:hover {
            background: var(--pr_success-hover);
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(39, 174, 96, 0.2);
        }

        .promoter-actions .deactivate-btn {
            background: var(--pr_warning);
        }

        .promoter-actions .deactivate-btn:hover {
            background: var(--pr_warning-hover);
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(211, 84, 0, 0.2);
        }

        /* Customer Count Badge */
        .customer-count {
            background: linear-gradient(135deg, #f1c40f, #f39c12);
            color: #fff;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            min-width: 30px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(243, 156, 18, 0.2);
        }

        /* Parent Promoter Style */
        .parent-promoter {
            color: var(--pr_text-light);
            font-size: 12px;
            display: block;
            margin-top: 5px;
            line-height: 1.4;
        }

        .parent-promoter i {
            color: var(--pr_primary);
            margin-right: 5px;
        }

        /* Payment Code Counter */
        .payment-code-counter {
            font-weight: 600;
            color: var(--pr_primary);
            position: relative;
            display: inline-block;
            padding: 4px 8px;
            background-color: rgba(58, 123, 213, 0.08);
            border-radius: 6px;
            text-align: center;
        }

        /* Search Box Styles */
        .promoter-search-box {
            margin-bottom: 0;
            display: flex;
            gap: 10px;
        }

        .promoter-search-box input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid var(--pr_border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all var(--pr_transition);
        }

        .promoter-search-box input:focus {
            border-color: var(--pr_primary);
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
            outline: none;
        }

        .promoter-search-box button {
            background: var(--pr_primary);
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all var(--pr_transition);
        }

        .promoter-search-box button:hover {
            background: var(--pr_primary-hover);
        }

        /* Add Promoter Button */
        .add-promoter-btn {
            background: linear-gradient(135deg, var(--pr_primary), var(--pr_secondary));
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all var(--pr_transition);
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .add-promoter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
        }

        /* Filter Container */
        .filter-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 20px;
            background-color: var(--pr_bg-light);
            border-radius: 8px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 500;
            font-size: 14px;
            color: var(--pr_text-medium);
        }

        .filter-select {
            padding: 10px 15px;
            border: 1px solid var(--pr_border-color);
            border-radius: 6px;
            font-size: 14px;
            min-width: 170px;
            background-color: #fff;
            transition: all var(--pr_transition);
        }

        .filter-select:focus {
            border-color: var(--pr_primary);
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
            outline: none;
        }

        /* Filter Buttons */
        .filter-btn {
            padding: 10px 15px;
            background: var(--pr_primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all var(--pr_transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-btn:hover {
            background: var(--pr_primary-hover);
            transform: translateY(-1px);
        }

        .reset-btn {
            padding: 10px 15px;
            background: #fff;
            border: 1px solid var(--pr_danger);
            border-radius: 6px;
            font-size: 14px;
            color: var(--pr_danger);
            cursor: pointer;
            transition: all var(--pr_transition);
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .reset-btn:hover {
            background: rgba(231, 76, 60, 0.05);
            color: var(--pr_danger-hover);
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 25px 0;
            justify-content: center;
            gap: 6px;
        }

        .pagination li {
            margin: 0;
        }

        .pagination a,
        .pagination span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--pr_text-medium);
            background: white;
            border: 1px solid var(--pr_border-color);
            transition: all var(--pr_transition);
            font-size: 14px;
        }

        .pagination a:hover {
            background: var(--pr_bg-light);
            border-color: var(--pr_primary);
            color: var(--pr_primary);
        }

        .pagination .active a {
            background: var(--pr_primary);
            color: white;
            border-color: var(--pr_primary);
            box-shadow: 0 2px 5px rgba(58, 123, 213, 0.3);
        }

        /* No Data State */
        .no-data {
            padding: 50px 20px;
            text-align: center;
            color: var(--pr_text-light);
        }

        .no-data p {
            margin-bottom: 20px;
            font-size: 16px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all var(--pr_transition);
            font-weight: 500;
        }

        .btn-primary {
            background: var(--pr_primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--pr_primary-hover);
            transform: translateY(-2px);
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: opacity 0.5s ease;
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            border-left: 4px solid var(--pr_success);
            color: #2d6a4f;
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            border-left: 4px solid var(--pr_danger);
            color: #ae1e2f;
        }

        /* Responsive table container */
        .responsive-table {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: var(--pr_shadow-sm);
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {

            .promoter-table th:nth-child(4),
            .promoter-table td:nth-child(4),
            .promoter-table th:nth-child(8),
            .promoter-table td:nth-child(8) {
                display: none;
            }

            .promoter-table th:nth-child(1),
            .promoter-table td:nth-child(1) {
                width: 12%;
            }

            .promoter-table th:nth-child(2),
            .promoter-table td:nth-child(2) {
                width: 20%;
            }

            .promoter-table th:nth-child(3),
            .promoter-table td:nth-child(3) {
                width: 15%;
            }

            .promoter-table th:nth-child(5),
            .promoter-table td:nth-child(5) {
                width: 10%;
            }

            .promoter-table th:nth-child(6),
            .promoter-table td:nth-child(6) {
                width: 10%;
            }

            .promoter-table th:nth-child(7),
            .promoter-table td:nth-child(7) {
                width: 10%;
            }

            .promoter-table th:nth-child(9),
            .promoter-table td:nth-child(9) {
                width: 18%;
            }
        }

        @media (max-width: 992px) {
            .filter-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .filter-group {
                width: 100%;
            }

            .filter-select {
                flex-grow: 1;
            }

            .filter-btn,
            .reset-btn {
                align-self: flex-start;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .add-promoter-btn {
                align-self: flex-start;
            }

            .promoter-search-box {
                flex-direction: column;
            }

            .promoter-search-box button {
                align-self: flex-end;
            }
        }

        @media (max-width: 576px) {
            .promoter-actions a {
                width: 30px;
                height: 30px;
            }

            .pagination a,
            .pagination span {
                min-width: 32px;
                height: 32px;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Promoter Management</h1>
            <a href="add.php" class="add-promoter-btn">
                <i class="fas fa-user-plus"></i> Add New Promoter
            </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header">
                <h2>Manage Promoters</h2>
                <form action="" method="GET" class="promoter-search-box">
                    <input type="text" name="search" placeholder="Search by name, email, contact or ID..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <div class="card-body">
                <!-- Replace the existing filter-container section with this improved version -->
                <form action="" method="GET" class="filter-container">
                    <div class="filter-group">
                        <label for="status_filter">Status:</label>
                        <select name="status_filter" id="status_filter" class="filter-select">
                            <option value="">All Status</option>
                            <option value="Active" <?php if ($status === 'Active') echo 'selected'; ?>>Active</option>
                            <option value="Inactive" <?php if ($status === 'Inactive') echo 'selected'; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="parent_id">Parent Promoter:</label>
                        <select name="parent_id" id="parent_id" class="filter-select">
                            <option value="">All Promoters</option>
                            <option value="NULL" <?php if ($parentId === 'NULL') echo 'selected'; ?>>No Parent</option>
                            <?php foreach ($parentPromoters as $parent): ?>
                                <option value="<?php echo $parent['PromoterID']; ?>" <?php if ($parentId == $parent['PromoterID']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($parent['Name']); ?> (<?php echo $parent['PromoterUniqueID']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>

                    <?php if (!empty($search) || !empty($status) || !empty($parentId)): ?>
                        <a href="index.php" class="reset-btn">
                            <i class="fas fa-times"></i> Reset Filters
                        </a>
                    <?php endif; ?>
                </form>

                <?php if (count($promoters) > 0): ?>
                    <div class="responsive-table">
                        <table class="promoter-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Codes</th>
                                    <th>Customers</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($promoters as $promoter): ?>
                                    <tr>
                                        <td><?php echo $promoter['PromoterUniqueID']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($promoter['Name']); ?>
                                            <?php if (!empty($promoter['ParentName'])): ?>
                                                <span class="parent-promoter">
                                                    <i class="fas fa-user-friends"></i> Under: <?php echo htmlspecialchars($promoter['ParentName']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($promoter['Contact']); ?></td>
                                        <td><?php echo htmlspecialchars($promoter['Email'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($promoter['Status']); ?>">
                                                <?php echo $promoter['Status']; ?>
                                            </span>
                                        </td>
                                        <td><span class="payment-code-counter"><?php echo $promoter['PaymentCodeCounter']; ?></span></td>
                                        <td>
                                            <span class="customer-count">
                                                <?php echo isset($promoterCustomerCounts[$promoter['PromoterID']]) ? $promoterCustomerCounts[$promoter['PromoterID']] : 0; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($promoter['CreatedAt'])); ?></td>
                                        <td>
                                            <div class="promoter-actions">
                                                <a href="view.php?id=<?php echo $promoter['PromoterID']; ?>" class="view-btn" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <a href="edit.php?id=<?php echo $promoter['PromoterID']; ?>" class="edit-btn" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <?php if ($promoter['Status'] == 'Active'): ?>
                                                    <a href="index.php?status=deactivate&id=<?php echo $promoter['PromoterID']; ?>"
                                                        class="deactivate-btn"
                                                        title="Deactivate"
                                                        onclick="return confirm('Are you sure you want to deactivate this promoter?');">
                                                        <i class="fas fa-ban"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="index.php?status=activate&id=<?php echo $promoter['PromoterID']; ?>"
                                                        class="activate-btn"
                                                        title="Activate"
                                                        onclick="return confirm('Are you sure you want to activate this promoter?');">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <a href="index.php?delete=<?php echo $promoter['PromoterID']; ?>"
                                                    class="delete-btn"
                                                    title="Delete"
                                                    onclick="return confirm('Are you sure you want to delete this promoter? This action cannot be undone and will also remove all associated customers.');">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li><a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                    echo !empty($status) ? '&status_filter=' . urlencode($status) : '';
                                                    echo !empty($parentId) ? '&parent_id=' . urlencode($parentId) : ''; ?>"><i class="fas fa-angle-double-left"></i></a></li>
                                <li><a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                            echo !empty($status) ? '&status_filter=' . urlencode($status) : '';
                                                                            echo !empty($parentId) ? '&parent_id=' . urlencode($parentId) : ''; ?>"><i class="fas fa-angle-left"></i></a></li>
                            <?php endif; ?>

                            <?php
                            // Show limited page numbers with current page in the middle
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            // Always show first page button
                            if ($startPage > 1) {
                                echo '<li><a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($status) ? '&status_filter=' . urlencode($status) : '') . (!empty($parentId) ? '&parent_id=' . urlencode($parentId) : '') . '">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li><span>...</span></li>';
                                }
                            }

                            // Display page links
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                echo '<li class="' . ($page == $i ? 'active' : '') . '"><a href="?page=' . $i .
                                    (!empty($search) ? '&search=' . urlencode($search) : '') .
                                    (!empty($status) ? '&status_filter=' . urlencode($status) : '') .
                                    (!empty($parentId) ? '&parent_id=' . urlencode($parentId) : '') . '">' . $i . '</a></li>';
                            }

                            // Always show last page button
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li><span>...</span></li>';
                                }
                                echo '<li><a href="?page=' . $totalPages .
                                    (!empty($search) ? '&search=' . urlencode($search) : '') .
                                    (!empty($status) ? '&status_filter=' . urlencode($status) : '') .
                                    (!empty($parentId) ? '&parent_id=' . urlencode($parentId) : '') . '">' . $totalPages . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $totalPages): ?>
                                <li><a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                            echo !empty($status) ? '&status_filter=' . urlencode($status) : '';
                                                                            echo !empty($parentId) ? '&parent_id=' . urlencode($parentId) : ''; ?>"><i class="fas fa-angle-right"></i></a></li>
                                <li><a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                                echo !empty($status) ? '&status_filter=' . urlencode($status) : '';
                                                                                echo !empty($parentId) ? '&parent_id=' . urlencode($parentId) : ''; ?>"><i class="fas fa-angle-double-right"></i></a></li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-data">
                        <?php if (!empty($search) || !empty($status) || !empty($parentId)): ?>
                            <p>No promoters found matching your criteria.</p>
                            <a href="index.php" class="btn btn-primary">Clear Filters</a>
                        <?php else: ?>
                            <p>No promoters found in the system.</p>
                            <a href="add.php" class="btn btn-primary">Add Your First Promoter</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Table sorting functionality
            const getCellValue = (tr, idx) => tr.children[idx].innerText || tr.children[idx].textContent;

            const comparer = (idx, asc) => (a, b) => ((v1, v2) =>
                v1 !== '' && v2 !== '' && !isNaN(v1) && !isNaN(v2) ? v1 - v2 : v1.toString().localeCompare(v2)
            )(getCellValue(asc ? a : b, idx), getCellValue(asc ? b : a, idx));

            document.querySelectorAll('th').forEach(th => th.addEventListener('click', (() => {
                const table = th.closest('table');
                const tbody = table.querySelector('tbody');
                Array.from(tbody.querySelectorAll('tr'))
                    .sort(comparer(Array.from(th.parentNode.children).indexOf(th), this.asc = !this.asc))
                    .forEach(tr => tbody.appendChild(tr));
            })));

            // Flash messages fade out
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 3000);
        });
    </script>
</body>

</html>