<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}
$menuPath = "../";
$currentPage = "dashboard";
// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Helper functions
function getStats($conn) {
    $promoterId = $_SESSION['promoter_id'];
    try {
        // Get total customers
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM Customers WHERE PromoterID = ?");
        $stmt->execute([$promoterId]);
        $customers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get total earnings
        $stmt = $conn->prepare("SELECT COALESCE(SUM(Amount), 0) as total FROM Payments WHERE PromoterID = ? AND Status = 'Verified'");
        $stmt->execute([$promoterId]);
        $earnings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get active schemes count
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT s.SchemeID) as total 
                              FROM Schemes s 
                              INNER JOIN Subscriptions sub ON s.SchemeID = sub.SchemeID 
                              INNER JOIN Customers c ON sub.CustomerID = c.CustomerID 
                              WHERE c.PromoterID = ? AND s.Status = 'Active'");
        $stmt->execute([$promoterId]);
        $schemes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get pending payments count
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM Payments WHERE PromoterID = ? AND Status = 'Pending'");
        $stmt->execute([$promoterId]);
        $pendingPayments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'customers' => $customers,
            'earnings' => $earnings,
            'schemes' => $schemes,
            'pendingPayments' => $pendingPayments
        ];
    } catch (PDOException $e) {
        return [
            'customers' => 0,
            'earnings' => 0,
            'schemes' => 0,
            'pendingPayments' => 0
        ];
    }
}

function getRecentPayments($conn, $limit = 5) {
    $promoterId = $_SESSION['promoter_id'];
    try {
        $stmt = $conn->prepare("
            SELECT p.*, c.Name as CustomerName, s.SchemeName 
            FROM Payments p 
            LEFT JOIN Customers c ON p.CustomerID = c.CustomerID 
            LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID 
            WHERE p.PromoterID = ? 
            ORDER BY p.SubmittedAt DESC 
            LIMIT ?
        ");
        $stmt->execute([$promoterId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getRecentActivity($conn, $limit = 7) {
    $promoterId = $_SESSION['promoter_id'];
    try {
        $stmt = $conn->prepare("
            SELECT * FROM ActivityLogs 
            WHERE UserID = ? AND UserType = 'Promoter'
            ORDER BY CreatedAt DESC 
            LIMIT ?
        ");
        $stmt->execute([$promoterId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Get dashboard data
$stats = getStats($conn);
$recentPayments = getRecentPayments($conn);
$recentActivity = getRecentActivity($conn);

$currentPage = 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promoter Dashboard | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

  

    <script src="../assets/js/dashboard.js"></script>
</body>
</html> 