<?php
session_start();
// Check if user is logged in, redirect if not
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "customers";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Handle Delete Customer
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $customerId = $_GET['delete'];

    try {
        // Begin transaction
        $conn->beginTransaction();

        // First, log the activity
        $action = "Deleted customer account";
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        // Now delete the customer
        $stmt = $conn->prepare("DELETE FROM Customers WHERE CustomerID = ?");
        $stmt->execute([$customerId]);

        // Commit the transaction
        $conn->commit();

        $_SESSION['success_message'] = "Customer deleted successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to delete customer: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Change Customer Status
if (isset($_GET['status']) && !empty($_GET['status']) && isset($_GET['id']) && !empty($_GET['id'])) {
    $customerId = $_GET['id'];
    $newStatus = $_GET['status'];

    // Validate status value
    if (!in_array($newStatus, ['Active', 'Inactive', 'Suspended'])) {
        $_SESSION['error_message'] = "Invalid status value.";
        header("Location: index.php");
        exit();
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // First, log the activity
        $action = "Changed customer status to " . $newStatus;
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        // Now update the customer status
        $stmt = $conn->prepare("UPDATE Customers SET Status = ? WHERE CustomerID = ?");
        $stmt->execute([$newStatus, $customerId]);

        // Commit the transaction
        $conn->commit();

        $_SESSION['success_message'] = "Customer status updated successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to update customer status: " . $e->getMessage();
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
$promoterId = isset($_GET['promoter_id']) ? $_GET['promoter_id'] : '';
$referredBy = isset($_GET['referred_by']) ? $_GET['referred_by'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(c.Name LIKE ? OR c.Email LIKE ? OR c.Contact LIKE ? OR c.CustomerUniqueID LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "c.Status = ?";
    $params[] = $status;
}

if (!empty($promoterId)) {
    $conditions[] = "c.PromoterID = ?";
    $params[] = $promoterId;
}

if (!empty($referredBy)) {
    $conditions[] = "c.ReferredBy = ?";
    $params[] = $referredBy;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total number of customers (for pagination)
$countQuery = "SELECT COUNT(*) as total FROM Customers c" . $whereClause;
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get customers with pagination
$query = "SELECT c.CustomerID, c.CustomerUniqueID, c.Name, c.Contact, c.Email, 
          c.Status, c.CreatedAt, c.ReferredBy, p.Name as PromoterName, p.PromoterUniqueID 
          FROM Customers c 
          LEFT JOIN Promoters p ON c.PromoterID = p.PromoterID"
    . $whereClause .
    " ORDER BY c.CreatedAt DESC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

// Bind search and filter parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}

$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get count of payments per customer
$customerPaymentCounts = [];
if (!empty($customers)) {
    $customerIds = array_column($customers, 'CustomerID');
    $placeholders = implode(',', array_fill(0, count($customerIds), '?'));

    $countQuery = "SELECT CustomerID, COUNT(*) as payment_count 
                   FROM Payments 
                   WHERE CustomerID IN ($placeholders) 
                   GROUP BY CustomerID";

    $stmt = $conn->prepare($countQuery);
    foreach ($customerIds as $key => $id) {
        $stmt->bindValue($key + 1, $id);
    }
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customerPaymentCounts[$row['CustomerID']] = $row['payment_count'];
    }
}

// Get sum of payments per customer
$customerPaymentSums = [];
if (!empty($customers)) {
    $customerIds = array_column($customers, 'CustomerID');
    $placeholders = implode(',', array_fill(0, count($customerIds), '?'));

    $sumQuery = "SELECT CustomerID, SUM(Amount) as payment_sum 
                 FROM Payments 
                 WHERE CustomerID IN ($placeholders) AND Status = 'Verified'
                 GROUP BY CustomerID";

    $stmt = $conn->prepare($sumQuery);
    foreach ($customerIds as $key => $id) {
        $stmt->bindValue($key + 1, $id);
    }
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customerPaymentSums[$row['CustomerID']] = $row['payment_sum'];
    }
}

// Get all promoters for filter dropdown
$promoterQuery = "SELECT PromoterID, Name, PromoterUniqueID FROM Promoters WHERE Status = 'Active' ORDER BY Name";
$stmt = $conn->prepare($promoterQuery);
$stmt->execute();
$promoters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Page-specific styles */
        .customer-actions {
            display: flex;
            gap: 8px;
        }

        .customer-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            color: white;
            transition: all 0.3s ease;
        }

        .customer-actions .view-btn {
            background: #3498db;
        }

        .customer-actions .view-btn:hover {
            background: #2980b9;
        }

        .customer-actions .edit-btn {
            background: #3a7bd5;
        }

        .customer-actions .edit-btn:hover {
            background: #2c60a9;
        }

        .customer-actions .delete-btn {
            background: #e74c3c;
        }

        .customer-actions .delete-btn:hover {
            background: #c0392b;
        }

        .customer-actions .status-btn {
            background: #2ecc71;
        }

        .customer-actions .status-btn:hover {
            background: #27ae60;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
            display: inline-block;
            min-width: 80px;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .status-suspended {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .add-customer-btn {
            background: linear-gradient(135deg, #3a7bd5, #00d2ff);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .add-customer-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .filter-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            align-items: center;
        }

        .filter-group label {
            margin-right: 8px;
            font-weight: 500;
            font-size: 14px;
            color: #555;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            min-width: 150px;
        }

        .filter-select:focus {
            border-color: #3a7bd5;
            outline: none;
        }

        .payment-count {
            background: #f1c40f;
            color: #2c3e50;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .payment-sum {
            background: #3498db;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .customer-search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .customer-search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .customer-search-box button {
            background: #3a7bd5;
            color: white;
            border: none;
            padding: 0 15px;
            border-radius: 6px;
            cursor: pointer;
        }

        .customer-search-box button:hover {
            background: #2c60a9;
        }

        .promoter-info {
            color: #7f8c8d;
            font-size: 12px;
            display: block;
            margin-top: 3px;
        }

        /* Responsive tables */
        @media (max-width: 992px) {
            .responsive-table {
                overflow-x: auto;
            }

            .customer-table {
                min-width: 800px;
            }
        }

        /* Filter button */
        .filter-btn {
            padding: 8px 15px;
            background: #f5f7fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: #e0e0e0;
        }

        .reset-btn {
            padding: 8px 15px;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            color: #e74c3c;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .reset-btn:hover {
            background: #fee;
        }

        .status-dropdown {
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border-radius: 6px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            display: none;
            z-index: 100;
            width: 150px;
        }

        .status-dropdown a {
            display: block;
            padding: 8px 15px;
            color: #333;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 13px;
            font-weight: 500;
            text-align: left;
            width: 100%;
        }

        .status-dropdown a:hover {
            background: #f5f7fa;
        }

        .status-dropdown a.active-status {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-dropdown a.inactive-status {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .status-dropdown a.suspended-status {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .status-container {
            position: relative;
        }

        .status-container:hover .status-dropdown {
            display: block;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Customer Management</h1>
            <a href="add.php" class="add-customer-btn">
                <i class="fas fa-user-plus"></i> Add New Customer
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
                <h2>Manage Customers</h2>
                <form action="" method="GET" class="customer-search-box">
                    <input type="text" name="search" placeholder="Search by name, email, contact or ID..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <div class="card-body">
                <form action="" method="GET" class="filter-container">
                    <div class="filter-group">
                        <label for="status_filter">Status:</label>
                        <select name="status_filter" id="status_filter" class="filter-select">
                            <option value="">All Status</option>
                            <option value="Active" <?php if ($status === 'Active') echo 'selected'; ?>>Active</option>
                            <option value="Inactive" <?php if ($status === 'Inactive') echo 'selected'; ?>>Inactive</option>
                            <option value="Suspended" <?php if ($status === 'Suspended') echo 'selected'; ?>>Suspended</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="promoter_id">Promoter:</label>
                        <select name="promoter_id" id="promoter_id" class="filter-select">
                            <option value="">All Promoters</option>
                            <option value="NULL" <?php if ($promoterId === 'NULL') echo 'selected'; ?>>No Promoter</option>
                            <?php foreach ($promoters as $promoter): ?>
                                <option value="<?php echo $promoter['PromoterID']; ?>" <?php if ($promoterId == $promoter['PromoterID']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($promoter['Name']); ?> (<?php echo $promoter['PromoterUniqueID']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="referred_by">Referred By:</label>
                        <input type="text" name="referred_by" id="referred_by" class="filter-select" value="<?php echo htmlspecialchars($referredBy); ?>" placeholder="Referral Code">
                    </div>

                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>

                    <?php if (!empty($search) || !empty($status) || !empty($promoterId) || !empty($referredBy)): ?>
                        <a href="index.php" class="reset-btn">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>

                <?php if (count($customers) > 0): ?>
                    <div class="responsive-table">
                        <table class="customer-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Payments</th>
                                    <th>Amount</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td><?php echo $customer['CustomerUniqueID']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($customer['Name']); ?>
                                            <?php if (!empty($customer['PromoterName'])): ?>
                                                <span class="promoter-info">
                                                    <i class="fas fa-user-tie"></i> Promoter: <?php echo htmlspecialchars($customer['PromoterName']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($customer['ReferredBy'])): ?>
                                                <span class="promoter-info">
                                                    <i class="fas fa-users"></i> Referred by: <?php echo htmlspecialchars($customer['ReferredBy']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($customer['Contact']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['Email'] ?? 'N/A'); ?></td>
                                        <td class="status-container">
                                            <span class="status-badge status-<?php echo strtolower($customer['Status']); ?>">
                                                <?php echo $customer['Status']; ?>
                                            </span>

                                            <div class="status-dropdown">
                                                <a href="index.php?id=<?php echo $customer['CustomerID']; ?>&status=Active"
                                                    class="active-status"
                                                    onclick="return confirm('Change status to Active?');">
                                                    <i class="fas fa-check-circle"></i> Set Active
                                                </a>
                                                <a href="index.php?id=<?php echo $customer['CustomerID']; ?>&status=Inactive"
                                                    class="inactive-status"
                                                    onclick="return confirm('Change status to Inactive?');">
                                                    <i class="fas fa-ban"></i> Set Inactive
                                                </a>
                                                <a href="index.php?id=<?php echo $customer['CustomerID']; ?>&status=Suspended"
                                                    class="suspended-status"
                                                    onclick="return confirm('Change status to Suspended?');">
                                                    <i class="fas fa-exclamation-circle"></i> Set Suspended
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="payment-count">
                                                <?php echo isset($customerPaymentCounts[$customer['CustomerID']]) ? $customerPaymentCounts[$customer['CustomerID']] : 0; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="payment-sum">
                                                ₹<?php echo isset($customerPaymentSums[$customer['CustomerID']]) ? number_format($customerPaymentSums[$customer['CustomerID']], 0) : 0; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($customer['CreatedAt'])); ?></td>
                                        <td>
                                            <div class="customer-actions">
                                                <a href="view.php?id=<?php echo $customer['CustomerID']; ?>" class="view-btn" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <a href="edit.php?id=<?php echo $customer['CustomerID']; ?>" class="edit-btn" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <a href="index.php?delete=<?php echo $customer['CustomerID']; ?>"
                                                    class="delete-btn"
                                                    title="Delete"
                                                    onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone and will also remove all associated payments and subscriptions.');">
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
                                                    echo !empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '';
                                                    echo !empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : ''; ?>"><i class="fas fa-angle-double-left"></i></a></li>
                                <li><a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                            echo !empty($status) ? '&status_filter=' . urlencode($status) : '';
                                                                            echo !empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '';
                                                                            echo !empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : ''; ?>"><i class="fas fa-angle-left"></i></a></li>
                            <?php endif; ?>

                            <?php
                            // Show limited page numbers with current page in the middle
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            // Always show first page button
                            if ($startPage > 1) {
                                echo '<li><a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($status) ? '&status_filter=' . urlencode($status) : '') . (!empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '') . (!empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : '') . '">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li><span>...</span></li>';
                                }
                            }

                            // Display page links
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                echo '<li class="' . ($page == $i ? 'active' : '') . '"><a href="?page=' . $i .
                                    (!empty($search) ? '&search=' . urlencode($search) : '') .
                                    (!empty($status) ? '&status_filter=' . urlencode($status) : '') .
                                    (!empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '') .
                                    (!empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : '') . '">' . $i . '</a></li>';
                            }

                            // Always show last page button
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li><span>...</span></li>';
                                }
                                echo '<li><a href="?page=' . $totalPages .
                                    (!empty($search) ? '&search=' . urlencode($search) : '') .
                                    (!empty($status) ? '&status_filter=' . urlencode($status) : '') .
                                    (!empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '') .
                                    (!empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : '') . '">' . $totalPages . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $totalPages): ?>
                                <li><a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                            echo !empty($status) ? '&status_filter=' . urlencode($status) : '';
                                                                            echo !empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '';
                                                                            echo !empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : ''; ?>"><i class="fas fa-angle-right"></i></a></li>
                                <li><a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                                echo !empty($status) ? '&status_filter=' . urlencode($status) : '';
                                                                                echo !empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '';
                                                                                echo !empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : ''; ?>"><i class="fas fa-angle-double-right"></i></a></li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-records">
                        <i class="fas fa-users-slash"></i>
                        <p>No customers found</p>
                        <?php if (!empty($search) || !empty($status) || !empty($promoterId) || !empty($referredBy)): ?>
                            <a href="index.php" class="reset-btn">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Confirm delete action
        function confirmDelete(customerID, customerName) {
            return confirm(`Are you sure you want to delete customer "${customerName}"? This action cannot be undone.`);
        }

        // Handle status dropdown toggle
        document.addEventListener('DOMContentLoaded', function() {
            const statusContainers = document.querySelectorAll('.status-container');

            statusContainers.forEach(container => {
                const badge = container.querySelector('.status-badge');
                const dropdown = container.querySelector('.status-dropdown');

                // Show dropdown on hover
                container.addEventListener('mouseenter', () => {
                    dropdown.style.display = 'block';
                });

                container.addEventListener('mouseleave', () => {
                    dropdown.style.display = 'none';
                });
            });

            // Initialize tooltips if you're using Bootstrap
            if (typeof bootstrap !== 'undefined') {
                const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltips.forEach(tooltip => {
                    new bootstrap.Tooltip(tooltip);
                });
            }
        });

        // Handle search form submission
        document.querySelector('.customer-search-box').addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="search"]');
            if (searchInput.value.trim() === '') {
                e.preventDefault();
                searchInput.focus();
            }
        });

        // Add loading indicator for actions
        document.querySelectorAll('.customer-actions a').forEach(link => {
            link.addEventListener('click', function() {
                if (this.classList.contains('delete-btn')) {
                    if (confirm('Are you sure you want to delete this customer?')) {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                        this.style.pointerEvents = 'none';
                    } else {
                        return false;
                    }
                }
            });
        });
    </script>
</body>

</html>