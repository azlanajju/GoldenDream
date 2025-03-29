<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path="../";
$current_page="notifications";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle mark as read action
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $stmt = $db->prepare("UPDATE Notifications SET IsRead = TRUE WHERE NotificationID = ? AND UserID = ? AND UserType = 'Customer'");
    $stmt->execute([$notification_id, $userData['customer_id']]);
    header("Location: notifications.php");
    exit;
}

// Handle mark all as read action
if (isset($_POST['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE Notifications SET IsRead = TRUE WHERE UserID = ? AND UserType = 'Customer'");
    $stmt->execute([$userData['customer_id']]);
    header("Location: notifications.php");
    exit;
}

// Get notifications
$stmt = $db->prepare("
    SELECT * FROM Notifications 
    WHERE UserID = ? AND UserType = 'Customer'
    ORDER BY CreatedAt DESC
");
$stmt->execute([$userData['customer_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notification statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_notifications,
        SUM(CASE WHEN IsRead = FALSE THEN 1 ELSE 0 END) as unread_notifications
    FROM Notifications 
    WHERE UserID = ? AND UserType = 'Customer'
");
$stmt->execute([$userData['customer_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
        }

        .notifications-container {
            padding: 20px;
        }

        .notifications-header {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .stat-item {
            text-align: center;
            padding: 15px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #4a90e2;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .notification-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
            border-left: 4px solid #4a90e2;
        }

        .notification-card.unread {
            border-left-color: #28a745;
            background: #f8fff9;
        }

        .notification-card:hover {
            transform: translateY(-5px);
        }

        .notification-time {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .btn-mark-read {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-mark-read:hover {
            background: #218838;
            color: white;
        }

        .btn-mark-all-read {
            background: #4a90e2;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-mark-all-read:hover {
            background: #357abd;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .empty-state i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 20px;
        }

        .notification-icon {
            font-size: 1.5rem;
            margin-right: 10px;
            color: #4a90e2;
        }

        .notification-message {
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="notifications-container">
            <div class="container">
                <div class="notifications-header text-center">
                    <h2><i class="fas fa-bell"></i> Notifications</h2>
                    <p class="mb-0">Stay updated with your account activities</p>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Notifications</h3>
                        <p>You don't have any notifications yet.</p>
                    </div>
                <?php else: ?>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['total_notifications']; ?></div>
                                    <div class="stat-label">Total Notifications</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['unread_notifications']; ?></div>
                                    <div class="stat-label">Unread Notifications</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($stats['unread_notifications'] > 0): ?>
                        <div class="text-end mb-4">
                            <form method="POST" action="" style="display: inline;">
                                <button type="submit" name="mark_all_read" class="btn btn-mark-all-read">
                                    <i class="fas fa-check-double"></i> Mark All as Read
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-card <?php echo $notification['IsRead'] ? '' : 'unread'; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="notification-message">
                                        <i class="fas fa-info-circle notification-icon"></i>
                                        <?php echo htmlspecialchars($notification['Message']); ?>
                                    </div>
                                    <div class="notification-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($notification['CreatedAt'])); ?>
                                    </div>
                                </div>
                                <?php if (!$notification['IsRead']): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['NotificationID']; ?>">
                                        <button type="submit" name="mark_read" class="btn btn-mark-read">
                                            <i class="fas fa-check"></i> Mark as Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>