<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "payments";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Handle Payment Verification
if (isset($_POST['action']) && isset($_POST['payment_id'])) {
    $paymentId = $_POST['payment_id'];
    $action = $_POST['action'];
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        $conn->beginTransaction();

        $newStatus = ($action === 'verify') ? 'Verified' : 'Rejected';

        // Update payment status
        $stmt = $conn->prepare("
            UPDATE Payments 
            SET Status = ?, AdminID = ?, VerifiedAt = CURRENT_TIMESTAMP 
            WHERE PaymentID = ?
        ");
        $stmt->execute([$newStatus, $_SESSION['admin_id'], $paymentId]);

        // Get payment details for notification
        $stmt = $conn->prepare("
            SELECT p.*, c.Name as CustomerName, s.SchemeName 
            FROM Payments p
            LEFT JOIN Customers c ON p.CustomerID = c.CustomerID
            LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID
            WHERE p.PaymentID = ?
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        // Create notification for customer
        $notificationMessage = "Your payment of ₹" . number_format($payment['Amount'], 2) .
            " for " . $payment['SchemeName'] . " has been " . strtolower($newStatus);
        if (!empty($remarks)) {
            $notificationMessage .= ". Remarks: " . $remarks;
        }

        $stmt = $conn->prepare("
            INSERT INTO Notifications (UserID, UserType, Message) 
            VALUES (?, 'Customer', ?)
        ");
        $stmt->execute([$payment['CustomerID'], $notificationMessage]);

        // Log the activity
        $stmt = $conn->prepare("
            INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
            VALUES (?, 'Admin', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "$newStatus payment #$paymentId for customer " . $payment['CustomerName'],
            $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        $_SESSION['success_message'] = "Payment has been $newStatus successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to process payment: " . $e->getMessage();
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
$schemeId = isset($_GET['scheme_id']) ? $_GET['scheme_id'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(c.Name LIKE ? OR c.CustomerUniqueID LIKE ? OR c.Contact LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "p.Status = ?";
    $params[] = $status;
}

if (!empty($schemeId)) {
    $conditions[] = "p.SchemeID = ?";
    $params[] = $schemeId;
}

if (!empty($startDate)) {
    $conditions[] = "DATE(p.SubmittedAt) >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $conditions[] = "DATE(p.SubmittedAt) <= ?";
    $params[] = $endDate;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total payments count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM Payments p
    LEFT JOIN Customers c ON p.CustomerID = c.CustomerID" . $whereClause;
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get all active schemes for filter
$stmt = $conn->query("SELECT SchemeID, SchemeName FROM Schemes WHERE Status = 'Active' ORDER BY SchemeName");
$schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payments with related data
$query = "
    SELECT p.*, 
           c.Name as CustomerName, c.CustomerUniqueID, c.Contact,
           s.SchemeName,
           pr.Name as PromoterName, pr.PromoterUniqueID,
           a.Name as VerifierName
    FROM Payments p
    LEFT JOIN Customers c ON p.CustomerID = c.CustomerID
    LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID
    LEFT JOIN Promoters pr ON p.PromoterID = pr.PromoterID
    LEFT JOIN Admins a ON p.AdminID = a.AdminID"
    . $whereClause .
    " ORDER BY p.SubmittedAt DESC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}

$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .payment-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .payment-card:hover {
            transform: translateY(-2px);
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .customer-info {
            flex: 1;
        }

        .customer-name {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .customer-id {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .payment-actions {
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

        .verify-btn {
            background: #2ecc71;
        }

        .verify-btn:hover {
            background: #27ae60;
        }

        .reject-btn {
            background: #e74c3c;
        }

        .reject-btn:hover {
            background: #c0392b;
        }

        .view-btn {
            background: #3498db;
        }

        .view-btn:hover {
            background: #2980b9;
        }

        .payment-details {
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

        .status-verified {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-rejected {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .payment-screenshot {
            max-width: 200px;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .payment-screenshot:hover {
            transform: scale(1.05);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            max-width: 90%;
            max-height: 90%;
        }

        .modal-image {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 8px;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 24px;
            cursor: pointer;
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

        .filter-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
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

        .promoter-info {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .verifier-info {
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
            <h1 class="page-title">Payment Management</h1>
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
                        <input type="text" class="search-input" placeholder="Search by customer name, ID or contact..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Status:</label>
                        <select class="filter-select" name="status_filter">
                            <option value="">All Status</option>
                            <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Verified" <?php echo $status === 'Verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="Rejected" <?php echo $status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Scheme:</label>
                        <select class="filter-select" name="scheme_id">
                            <option value="">All Schemes</option>
                            <?php foreach ($schemes as $scheme): ?>
                                <option value="<?php echo $scheme['SchemeID']; ?>"
                                    <?php echo $schemeId == $scheme['SchemeID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($scheme['SchemeName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Date Range:</label>
                        <input type="date" class="filter-input" name="start_date" value="<?php echo $startDate; ?>">
                        <span>to</span>
                        <input type="date" class="filter-input" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                </div>

                <?php if (count($payments) > 0): ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-card">
                            <div class="payment-header">
                                <div class="customer-info">
                                    <div class="customer-name"><?php echo htmlspecialchars($payment['CustomerName']); ?></div>
                                    <div class="customer-id"><?php echo $payment['CustomerUniqueID']; ?></div>
                                    <div class="contact-badge">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($payment['Contact']); ?>
                                    </div>
                                    <?php if ($payment['PromoterName']): ?>
                                        <div class="promoter-info">
                                            <i class="fas fa-user-tie"></i> Promoter: <?php echo htmlspecialchars($payment['PromoterName']); ?>
                                            (<?php echo $payment['PromoterUniqueID']; ?>)
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="payment-actions">
                                    <?php if ($payment['Status'] === 'Pending'): ?>
                                        <button class="action-btn verify-btn" onclick="showRemarks(this, 'verify', <?php echo $payment['PaymentID']; ?>)">
                                            <i class="fas fa-check"></i> Verify
                                        </button>
                                        <button class="action-btn reject-btn" onclick="showRemarks(this, 'reject', <?php echo $payment['PaymentID']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                    <a href="view.php?id=<?php echo $payment['PaymentID']; ?>" class="action-btn view-btn">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </div>

                            <div class="payment-details">
                                <div class="detail-item">
                                    <span class="detail-label">Scheme</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($payment['SchemeName']); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Amount</span>
                                    <span class="detail-value">₹<?php echo number_format($payment['Amount'], 2); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Submitted At</span>
                                    <span class="detail-value"><?php echo date('M d, Y H:i', strtotime($payment['SubmittedAt'])); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Status</span>
                                    <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                        <?php echo $payment['Status']; ?>
                                    </span>
                                </div>
                            </div>

                            <?php if ($payment['ScreenshotURL']): ?>
                                <img src="../../<?php echo htmlspecialchars($payment['ScreenshotURL']); ?>"
                                    class="payment-screenshot"
                                    onclick="showImage(this.src)"
                                    alt="Payment Screenshot">
                            <?php endif; ?>

                            <?php if ($payment['Status'] !== 'Pending' && $payment['VerifierName']): ?>
                                <div class="verifier-info">
                                    <?php echo $payment['Status']; ?> by <?php echo htmlspecialchars($payment['VerifierName']); ?>
                                    on <?php echo date('M d, Y H:i', strtotime($payment['VerifiedAt'])); ?>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST" class="remarks-form" style="display: none;">
                                <input type="hidden" name="payment_id" value="<?php echo $payment['PaymentID']; ?>">
                                <input type="hidden" name="action" value="">
                                <textarea name="remarks" class="remarks-input" placeholder="Enter remarks (optional)"></textarea>
                            </form>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&laquo;</a>
                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&lsaquo;</a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                                    class="<?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&rsaquo;</a>
                                <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-records">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <p>No payments found</p>
                        <?php if (!empty($search) || !empty($status) || !empty($schemeId) || !empty($startDate) || !empty($endDate)): ?>
                            <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal" id="imageModal" onclick="hideModal()">
        <span class="close-modal">&times;</span>
        <img class="modal-image" id="modalImage">
    </div>

    <script>
        // Handle search and filters
        const searchInput = document.querySelector('.search-input');
        const statusSelect = document.querySelector('select[name="status_filter"]');
        const schemeSelect = document.querySelector('select[name="scheme_id"]');
        const startDate = document.querySelector('input[name="start_date"]');
        const endDate = document.querySelector('input[name="end_date"]');

        let searchTimeout;

        function updateFilters() {
            const search = searchInput.value.trim();
            const status = statusSelect.value;
            const schemeId = schemeSelect.value;
            const start = startDate.value;
            const end = endDate.value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (status) params.append('status_filter', status);
            if (schemeId) params.append('scheme_id', schemeId);
            if (start) params.append('start_date', start);
            if (end) params.append('end_date', end);

            window.location.href = 'index.php' + (params.toString() ? '?' + params.toString() : '');
        }

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(updateFilters, 500);
        });

        statusSelect.addEventListener('change', updateFilters);
        schemeSelect.addEventListener('change', updateFilters);
        startDate.addEventListener('change', updateFilters);
        endDate.addEventListener('change', updateFilters);

        // Handle image modal
        function showImage(src) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            modal.style.display = 'flex';
            modalImage.src = src;
        }

        function hideModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // Handle remarks and form submission
        function showRemarks(button, action, paymentId) {
            const card = button.closest('.payment-card');
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
                    if (confirm(`Are you sure you want to ${action} this payment?`)) {
                        form.submit();
                    }
                }
            };
        }

        // Close modal when clicking outside
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal();
            }
        });
    </script>
</body>

</html>