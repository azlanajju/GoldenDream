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
    $conditions[] = "(Name LIKE ? OR Email LIKE ? OR Contact LIKE ? OR PromoterUniqueID LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "Status = ?";
    $params[] = $status;
}

if (!empty($parentId)) {
    $conditions[] = "ParentPromoterID = ?";
    $params[] = $parentId;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total number of promoters (for pagination)
$countQuery = "SELECT COUNT(*) as total FROM Promoters" . $whereClause;
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

// Bind search and filter parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}

$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
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
        /* Page-specific styles */
        .promoter-actions {
            display: flex;
            gap: 8px;
        }

        .promoter-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            color: white;
            transition: all 0.3s ease;
        }

        .promoter-actions .view-btn {
            background: #3498db;
        }

        .promoter-actions .view-btn:hover {
            background: #2980b9;
        }

        .promoter-actions .edit-btn {
            background: #3a7bd5;
        }

        .promoter-actions .edit-btn:hover {
            background: #2c60a9;
        }

        .promoter-actions .delete-btn {
            background: #e74c3c;
        }

        .promoter-actions .delete-btn:hover {
            background: #c0392b;
        }

        .promoter-actions .activate-btn {
            background: #2ecc71;
        }

        .promoter-actions .activate-btn:hover {
            background: #27ae60;
        }

        .promoter-actions .deactivate-btn {
            background: #f39c12;
        }

        .promoter-actions .deactivate-btn:hover {
            background: #d35400;
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

        .add-promoter-btn {
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

        .add-promoter-btn:hover {
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

        .customer-count {
            background: #f1c40f;
            color: #2c3e50;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .promoter-search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .promoter-search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .promoter-search-box button {
            background: #3a7bd5;
            color: white;
            border: none;
            padding: 0 15px;
            border-radius: 6px;
            cursor: pointer;
        }

        .promoter-search-box button:hover {
            background: #2c60a9;
        }

        .payment-code-counter {
            font-weight: 600;
            color: #3a7bd5;
        }

        .parent-promoter {
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

            .promoter-table {
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
                            <i class="fas fa-times"></i> Reset
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
                                    <th>Payment Codes</th>
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
                                        <td class="payment-code-counter"><?php echo $promoter['PaymentCodeCounter']; ?></td>
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