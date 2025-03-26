<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "withdrawals";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Handle Withdrawal Request Processing
if (isset($_POST['action']) && isset($_POST['withdrawal_id'])) {
    $withdrawalId = $_POST['withdrawal_id'];
    $action = $_POST['action'];
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        $conn->beginTransaction();

        $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';

        // Get withdrawal details first
        $stmt = $conn->prepare("
            SELECT w.*, 
                   CASE 
                       WHEN w.UserType = 'Customer' THEN c.Name 
                       WHEN w.UserType = 'Promoter' THEN p.Name 
                   END as UserName,
                   CASE 
                       WHEN w.UserType = 'Customer' THEN c.Email 
                       WHEN w.UserType = 'Promoter' THEN p.Email 
                   END as UserEmail
            FROM Withdrawals w
            LEFT JOIN Customers c ON w.UserType = 'Customer' AND w.UserID = c.CustomerID
            LEFT JOIN Promoters p ON w.UserType = 'Promoter' AND w.UserID = p.PromoterID
            WHERE w.WithdrawalID = ?
        ");
        $stmt->execute([$withdrawalId]);
        $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update withdrawal status
        $stmt = $conn->prepare("
            UPDATE Withdrawals 
            SET Status = ?, 
                ProcessedAt = CURRENT_TIMESTAMP, 
                AdminID = ?,
                Remarks = ?
            WHERE WithdrawalID = ?
        ");
        $stmt->execute([$newStatus, $_SESSION['admin_id'], $remarks, $withdrawalId]);

        // Create notification for user
        $notificationMessage = "Your withdrawal request for ₹" . number_format($withdrawal['Amount'], 2) .
            " has been " . strtolower($newStatus);
        if (!empty($remarks)) {
            $notificationMessage .= ". Remarks: " . $remarks;
        }

        $stmt = $conn->prepare("
            INSERT INTO Notifications (UserID, UserType, Message) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$withdrawal['UserID'], $withdrawal['UserType'], $notificationMessage]);

        // Log the activity
        $stmt = $conn->prepare("
            INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
            VALUES (?, 'Admin', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "$newStatus withdrawal request #$withdrawalId for " . $withdrawal['UserName'],
            $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        $_SESSION['success_message'] = "Withdrawal request has been $newStatus successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to process withdrawal: " . $e->getMessage();
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
$userType = isset($_GET['user_type']) ? $_GET['user_type'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(
        CASE 
            WHEN w.UserType = 'Customer' THEN c.Name 
            WHEN w.UserType = 'Promoter' THEN p.Name 
        END LIKE ? OR
        CASE 
            WHEN w.UserType = 'Customer' THEN c.CustomerUniqueID
            WHEN w.UserType = 'Promoter' THEN p.PromoterUniqueID
        END LIKE ?
    )";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "w.Status = ?";
    $params[] = $status;
}

if (!empty($userType)) {
    $conditions[] = "w.UserType = ?";
    $params[] = $userType;
}

if (!empty($startDate)) {
    $conditions[] = "DATE(w.RequestedAt) >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $conditions[] = "DATE(w.RequestedAt) <= ?";
    $params[] = $endDate;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total withdrawals count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM Withdrawals w
    LEFT JOIN Customers c ON w.UserType = 'Customer' AND w.UserID = c.CustomerID
    LEFT JOIN Promoters p ON w.UserType = 'Promoter' AND w.UserID = p.PromoterID"
    . $whereClause;
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get withdrawals with related data
$query = "
    SELECT w.*,
           CASE 
               WHEN w.UserType = 'Customer' THEN c.Name 
               WHEN w.UserType = 'Promoter' THEN p.Name 
           END as UserName,
           CASE 
               WHEN w.UserType = 'Customer' THEN c.CustomerUniqueID
               WHEN w.UserType = 'Promoter' THEN p.PromoterUniqueID
           END as UserUniqueID,
           CASE 
               WHEN w.UserType = 'Customer' THEN c.Contact
               WHEN w.UserType = 'Promoter' THEN p.Contact
           END as UserContact,
           CASE 
               WHEN w.UserType = 'Customer' THEN c.BankAccountName
               WHEN w.UserType = 'Promoter' THEN p.BankAccountName
           END as BankAccountName,
           CASE 
               WHEN w.UserType = 'Customer' THEN c.BankAccountNumber
               WHEN w.UserType = 'Promoter' THEN p.BankAccountNumber
           END as BankAccountNumber,
           CASE 
               WHEN w.UserType = 'Customer' THEN c.IFSCCode
               WHEN w.UserType = 'Promoter' THEN p.IFSCCode
           END as IFSCCode,
           CASE 
               WHEN w.UserType = 'Customer' THEN c.BankName
               WHEN w.UserType = 'Promoter' THEN p.BankName
           END as BankName,
           a.Name as ProcessedByName
    FROM Withdrawals w
    LEFT JOIN Customers c ON w.UserType = 'Customer' AND w.UserID = c.CustomerID
    LEFT JOIN Promoters p ON w.UserType = 'Promoter' AND w.UserID = p.PromoterID
    LEFT JOIN Admins a ON w.AdminID = a.AdminID"
    . $whereClause .
    " ORDER BY w.RequestedAt DESC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}

$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .withdrawal-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .withdrawal-card:hover {
            transform: translateY(-2px);
        }

        .withdrawal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .user-id {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .withdrawal-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: 6px;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .approve-btn {
            background: #2ecc71;
        }

        .approve-btn:hover {
            background: #27ae60;
        }

        .reject-btn {
            background: #e74c3c;
        }

        .reject-btn:hover {
            background: #c0392b;
        }

        .withdrawal-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 14px;
            color: #2c3e50;
            font-weight: 500;
        }

        .bank-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .bank-details-title {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .status-pending {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .status-approved {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-rejected {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .filter-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-label {
            font-size: 14px;
            color: #34495e;
            font-weight: 500;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            min-width: 150px;
        }

        .search-box {
            flex: 1;
            min-width: 200px;
        }

        .search-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .remarks-input {
            width: 100%;
            margin-top: 10px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            display: none;
        }

        .contact-badge {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
            margin-top: 5px;
        }

        .user-type-badge {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
            margin-left: 8px;
        }

        .processed-info {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 10px;
            font-style: italic;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Withdrawal Management</h1>
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
            <div class="card-body">
                <div class="filter-container">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Search by name or ID..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Status:</label>
                        <select class="filter-select" name="status_filter">
                            <option value="">All Status</option>
                            <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo $status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">User Type:</label>
                        <select class="filter-select" name="user_type">
                            <option value="">All Types</option>
                            <option value="Customer" <?php echo $userType === 'Customer' ? 'selected' : ''; ?>>Customer</option>
                            <option value="Promoter" <?php echo $userType === 'Promoter' ? 'selected' : ''; ?>>Promoter</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">From:</label>
                        <input type="date" class="filter-select" name="start_date" value="<?php echo $startDate; ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">To:</label>
                        <input type="date" class="filter-select" name="end_date" value="<?php echo $endDate; ?>">
                    </div>

                    <button type="button" class="action-btn approve-btn" onclick="applyFilters()">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>

                <?php if (count($withdrawals) > 0): ?>
                    <?php foreach ($withdrawals as $withdrawal): ?>
                        <div class="withdrawal-card">
                            <div class="withdrawal-header">
                                <div class="user-info">
                                    <div class="user-name">
                                        <?php echo htmlspecialchars($withdrawal['UserName']); ?>
                                        <span class="user-type-badge">
                                            <?php echo $withdrawal['UserType']; ?>
                                        </span>
                                    </div>
                                    <div class="user-id">
                                        <?php echo $withdrawal['UserUniqueID']; ?>
                                        <span class="status-badge status-<?php echo strtolower($withdrawal['Status']); ?>">
                                            <?php echo $withdrawal['Status']; ?>
                                        </span>
                                    </div>
                                    <div class="contact-badge">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($withdrawal['UserContact']); ?>
                                    </div>
                                </div>

                                <div class="withdrawal-actions">
                                    <?php if ($withdrawal['Status'] === 'Pending'): ?>
                                        <button class="action-btn approve-btn" onclick="showRemarks(this, 'approve', <?php echo $withdrawal['WithdrawalID']; ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="action-btn reject-btn" onclick="showRemarks(this, 'reject', <?php echo $withdrawal['WithdrawalID']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="withdrawal-details">
                                <div class="detail-item">
                                    <span class="detail-label">Amount</span>
                                    <span class="detail-value">₹<?php echo number_format($withdrawal['Amount'], 2); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Requested At</span>
                                    <span class="detail-value"><?php echo date('M d, Y h:i A', strtotime($withdrawal['RequestedAt'])); ?></span>
                                </div>

                                <?php if ($withdrawal['ProcessedAt']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Processed At</span>
                                        <span class="detail-value"><?php echo date('M d, Y h:i A', strtotime($withdrawal['ProcessedAt'])); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($withdrawal['Remarks'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Remarks</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($withdrawal['Remarks']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="bank-details">
                                <div class="bank-details-title">Bank Details</div>
                                <div class="withdrawal-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Account Name</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($withdrawal['BankAccountName']); ?></span>
                                    </div>

                                    <div class="detail-item">
                                        <span class="detail-label">Account Number</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($withdrawal['BankAccountNumber']); ?></span>
                                    </div>

                                    <div class="detail-item">
                                        <span class="detail-label">IFSC Code</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($withdrawal['IFSCCode']); ?></span>
                                    </div>

                                    <div class="detail-item">
                                        <span class="detail-label">Bank Name</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($withdrawal['BankName']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <?php if ($withdrawal['ProcessedByName']): ?>
                                <div class="processed-info">
                                    Processed by: <?php echo htmlspecialchars($withdrawal['ProcessedByName']); ?>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST" class="remarks-form" style="display: none;">
                                <input type="hidden" name="withdrawal_id" value="<?php echo $withdrawal['WithdrawalID']; ?>">
                                <input type="hidden" name="action" value="">
                                <textarea name="remarks" class="remarks-input" placeholder="Enter remarks (optional)"></textarea>
                            </form>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?><?php echo !empty($userType) ? '&user_type=' . urlencode($userType) : ''; ?><?php echo !empty($startDate) ? '&start_date=' . urlencode($startDate) : ''; ?><?php echo !empty($endDate) ? '&end_date=' . urlencode($endDate) : ''; ?>">&laquo;</a>
                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?><?php echo !empty($userType) ? '&user_type=' . urlencode($userType) : ''; ?><?php echo !empty($startDate) ? '&start_date=' . urlencode($startDate) : ''; ?><?php echo !empty($endDate) ? '&end_date=' . urlencode($endDate) : ''; ?>">&lsaquo;</a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?><?php echo !empty($userType) ? '&user_type=' . urlencode($userType) : ''; ?><?php echo !empty($startDate) ? '&start_date=' . urlencode($startDate) : ''; ?><?php echo !empty($endDate) ? '&end_date=' . urlencode($endDate) : ''; ?>"
                                    class="<?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?><?php echo !empty($userType) ? '&user_type=' . urlencode($userType) : ''; ?><?php echo !empty($startDate) ? '&start_date=' . urlencode($startDate) : ''; ?><?php echo !empty($endDate) ? '&end_date=' . urlencode($endDate) : ''; ?>">&rsaquo;</a>
                                <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?><?php echo !empty($userType) ? '&user_type=' . urlencode($userType) : ''; ?><?php echo !empty($startDate) ? '&start_date=' . urlencode($startDate) : ''; ?><?php echo !empty($endDate) ? '&end_date=' . urlencode($endDate) : ''; ?>">&raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-records">
                        <i class="fas fa-money-bill-wave"></i>
                        <p>No withdrawal requests found</p>
                        <?php if (!empty($search) || !empty($status) || !empty($userType) || !empty($startDate) || !empty($endDate)): ?>
                            <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Handle search and filters
        function applyFilters() {
            const search = document.querySelector('.search-input').value.trim();
            const status = document.querySelector('select[name="status_filter"]').value;
            const userType = document.querySelector('select[name="user_type"]').value;
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (status) params.append('status_filter', status);
            if (userType) params.append('user_type', userType);
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);

            window.location.href = 'index.php' + (params.toString() ? '?' + params.toString() : '');
        }

        // Handle Enter key press in search box
        document.querySelector('.search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });

        // Handle remarks form
        function showRemarks(button, action, withdrawalId) {
            const card = button.closest('.withdrawal-card');
            const form = card.querySelector('.remarks-form');
            const remarksInput = form.querySelector('.remarks-input');

            // Hide any other visible remarks inputs
            document.querySelectorAll('.remarks-input').forEach(input => {
                if (input !== remarksInput) {
                    input.style.display = 'none';
                }
            });

            if (remarksInput.style.display === 'block') {
                remarksInput.style.display = 'none';
                return;
            }

            form.querySelector('input[name="action"]').value = action;
            remarksInput.style.display = 'block';
            remarksInput.focus();

            remarksInput.onkeypress = function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (confirm(`Are you sure you want to ${action} this withdrawal request?`)) {
                        form.submit();
                    }
                }
            };
        }
    </script>
</body>

</html>