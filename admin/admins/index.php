<?php
session_start();
// Check if user is logged in, redirect if not
// if (!isset($_SESSION['admin_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

// Check if the logged-in admin has permission to manage admins
// Superadmin role check
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] !== 'SuperAdmin') {
    // Redirect to dashboard with error message
    $_SESSION['error_message'] = "You don't have permission to access the admin management page.";
    header("Location: ../dashboard/index.php");
    exit();
}

$menuPath = "../";
$currentPage = "admins";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Handle Delete Admin
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $adminId = $_GET['delete'];

    // Prevent self-deletion
    if ($adminId == $_SESSION['admin_id']) {
        $_SESSION['error_message'] = "You cannot delete your own account.";
        header("Location: index.php");
        exit();
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // First, log the activity
        $action = "Deleted admin account";
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        // Now delete the admin
        $stmt = $conn->prepare("DELETE FROM Admins WHERE AdminID = ?");
        $stmt->execute([$adminId]);

        // Commit the transaction
        $conn->commit();

        $_SESSION['success_message'] = "Admin deleted successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to delete admin: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Change Admin Status
if (isset($_GET['status']) && !empty($_GET['status']) && isset($_GET['id']) && !empty($_GET['id'])) {
    $adminId = $_GET['id'];
    $newStatus = $_GET['status'] === 'activate' ? 'Active' : 'Inactive';

    // Prevent changing own status
    if ($adminId == $_SESSION['admin_id']) {
        $_SESSION['error_message'] = "You cannot change your own account status.";
        header("Location: index.php");
        exit();
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // First, log the activity
        $action = "Changed admin status to " . $newStatus;
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        // Now update the admin status
        $stmt = $conn->prepare("UPDATE Admins SET Status = ? WHERE AdminID = ?");
        $stmt->execute([$newStatus, $adminId]);

        // Commit the transaction
        $conn->commit();

        $_SESSION['success_message'] = "Admin status updated successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to update admin status: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Search query
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$searchParams = [];

if (!empty($search)) {
    $searchCondition = " WHERE (Name LIKE ? OR Email LIKE ? OR Role LIKE ?)";
    $searchParams = ["%$search%", "%$search%", "%$search%"];
}

// Get total number of admins (for pagination)
$countQuery = "SELECT COUNT(*) as total FROM Admins" . $searchCondition;
$stmt = $conn->prepare($countQuery);

if (!empty($searchParams)) {
    $stmt->execute($searchParams);
} else {
    $stmt->execute();
}

$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get admins with pagination
$query = "SELECT AdminID, Name, Email, Role, Status, CreatedAt FROM Admins" . $searchCondition .
    " ORDER BY CreatedAt DESC LIMIT :offset, :limit";
$stmt = $conn->prepare($query);

if (!empty($searchParams)) {
    foreach ($searchParams as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
}

$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Any additional page-specific styles */
        .admin-actions {
            display: flex;
            gap: 10px;
        }

        .admin-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            color: white;
            transition: all 0.3s ease;
        }

        .admin-actions .edit-btn {
            background: #3a7bd5;
        }

        .admin-actions .edit-btn:hover {
            background: #2c60a9;
        }

        .admin-actions .delete-btn {
            background: #e74c3c;
        }

        .admin-actions .delete-btn:hover {
            background: #c0392b;
        }

        .admin-actions .activate-btn {
            background: #2ecc71;
        }

        .admin-actions .activate-btn:hover {
            background: #27ae60;
        }

        .admin-actions .deactivate-btn {
            background: #f39c12;
        }

        .admin-actions .deactivate-btn:hover {
            background: #d35400;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .current-user-row {
            background-color: rgba(58, 123, 213, 0.05);
        }

        .add-admin-btn {
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

        .add-admin-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .admin-search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .admin-search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .admin-search-box button {
            background: #3a7bd5;
            color: white;
            border: none;
            padding: 0 15px;
            border-radius: 6px;
            cursor: pointer;
        }

        .admin-search-box button:hover {
            background: #2c60a9;
        }

        .admin-table th {
            position: relative;
            cursor: pointer;
        }

        .admin-table th::after {
            content: '↕';
            position: absolute;
            right: 8px;
            color: #ccc;
        }

        .admin-table th.asc::after {
            content: '↑';
            color: #3a7bd5;
        }

        .admin-table th.desc::after {
            content: '↓';
            color: #3a7bd5;
        }

        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 20px 0;
            justify-content: center;
        }

        .pagination li {
            margin: 0 5px;
        }

        .pagination a {
            display: block;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            color: #3a7bd5;
            background: #f5f7fa;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #e0e0e0;
        }

        .pagination .active a {
            background: #3a7bd5;
            color: white;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Admin Management</h1>
            <a href="./add/" class="add-admin-btn">
                <i class="fas fa-plus"></i> Add New Admin
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
                <h2>Manage Administrators</h2>
                <form action="" method="GET" class="admin-search-box">
                    <input type="text" name="search" placeholder="Search by name, email or role..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <div class="card-body">
                <?php if (count($admins) > 0): ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr <?php if ($admin['AdminID'] == $_SESSION['admin_id']) echo 'class="current-user-row"'; ?>>
                                    <td><?php echo htmlspecialchars($admin['Name']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['Email']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['Role']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($admin['Status']); ?>">
                                            <?php echo $admin['Status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($admin['CreatedAt'])); ?></td>
                                    <td>
                                        <div class="admin-actions">
                                            <a href="edit.php?id=<?php echo $admin['AdminID']; ?>" class="edit-btn" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <?php if ($admin['Status'] == 'Active'): ?>
                                                <a href="index.php?status=deactivate&id=<?php echo $admin['AdminID']; ?>"
                                                    class="deactivate-btn"
                                                    title="Deactivate"
                                                    onclick="return confirm('Are you sure you want to deactivate this admin?');">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="index.php?status=activate&id=<?php echo $admin['AdminID']; ?>"
                                                    class="activate-btn"
                                                    title="Activate"
                                                    onclick="return confirm('Are you sure you want to activate this admin?');">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($admin['AdminID'] != $_SESSION['admin_id']): ?>
                                                <a href="index.php?delete=<?php echo $admin['AdminID']; ?>"
                                                    class="delete-btn"
                                                    title="Delete"
                                                    onclick="return confirm('Are you sure you want to delete this admin? This action cannot be undone.');">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li><a href="?page=1<?php if (!empty($search)) echo '&search=' . urlencode($search); ?>"><i class="fas fa-angle-double-left"></i></a></li>
                                <li><a href="?page=<?php echo $page - 1; ?><?php if (!empty($search)) echo '&search=' . urlencode($search); ?>"><i class="fas fa-angle-left"></i></a></li>
                            <?php endif; ?>

                            <?php
                            // Show limited page numbers with current page in the middle
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            // Always show first page button
                            if ($startPage > 1) {
                                echo '<li><a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li><span>...</span></li>';
                                }
                            }

                            // Display page links
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                echo '<li class="' . ($page == $i ? 'active' : '') . '"><a href="?page=' . $i .
                                    (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $i . '</a></li>';
                            }

                            // Always show last page button
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li><span>...</span></li>';
                                }
                                echo '<li><a href="?page=' . $totalPages . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $totalPages . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $totalPages): ?>
                                <li><a href="?page=<?php echo $page + 1; ?><?php if (!empty($search)) echo '&search=' . urlencode($search); ?>"><i class="fas fa-angle-right"></i></a></li>
                                <li><a href="?page=<?php echo $totalPages; ?><?php if (!empty($search)) echo '&search=' . urlencode($search); ?>"><i class="fas fa-angle-double-right"></i></a></li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-data">
                        <?php if (!empty($search)): ?>
                            <p>No admins found matching your search criteria.</p>
                            <a href="index.php" class="btn btn-primary">Clear Search</a>
                        <?php else: ?>
                            <p>No admins found in the system.</p>
                            <a href="add/" class="btn btn-primary">Add Your First Admin</a>
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

                // Update header classes for sort direction indication
                table.querySelectorAll('th').forEach(header => {
                    header.classList.remove('asc', 'desc');
                });

                th.classList.toggle('asc', this.asc);
                th.classList.toggle('desc', !this.asc);
            })));

            // Flash messages fade out
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 3000);
        });
    </script>
</body>

</html>