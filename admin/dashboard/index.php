<?php
session_start();
// Check if user is logged in, redirect if not
// if (!isset($_SESSION['admin_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

$menuPath = "../";
$currentPage = "dashboard";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get stats data
function getStats($conn) {
    $stats = [];
    
    // Total Customers
    $query = "SELECT COUNT(*) as total FROM Customers WHERE Status = 'Active'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['customers'] = $result['total'] ?? 0;
    
    // Get previous month customer count for comparison
    $query = "SELECT COUNT(*) as prev_month FROM Customers WHERE Status = 'Active' AND CreatedAt <= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $prevMonthCustomers = $result['prev_month'] ?? 0;
    
    // Calculate percentage change
    if ($prevMonthCustomers > 0) {
        $stats['customers_growth'] = round((($stats['customers'] - $prevMonthCustomers) / $prevMonthCustomers) * 100, 1);
    } else {
        $stats['customers_growth'] = 100; // If there were no customers before
    }
    
    // Total Revenue (from Payments table)
    $query = "SELECT SUM(Amount) as total FROM Payments WHERE Status = 'Verified'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['revenue'] = $result['total'] ?? 0;
    
    // Previous month revenue
    $query = "SELECT SUM(Amount) as prev_month FROM Payments WHERE Status = 'Verified' AND SubmittedAt <= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $prevMonthRevenue = $result['prev_month'] ?? 0;
    
    // Calculate percentage change
    if ($prevMonthRevenue > 0) {
        $stats['revenue_growth'] = round((($stats['revenue'] - $prevMonthRevenue) / $prevMonthRevenue) * 100, 1);
    } else {
        $stats['revenue_growth'] = 100; // If there was no revenue before
    }
    
    // Active Schemes
    $query = "SELECT COUNT(*) as total FROM Schemes WHERE Status = 'Active'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['schemes'] = $result['total'] ?? 0;
    
    // New schemes this month
    $query = "SELECT COUNT(*) as new_schemes FROM Schemes WHERE Status = 'Active' AND CreatedAt >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['new_schemes'] = $result['new_schemes'] ?? 0;
    
    // Total Payments
    $query = "SELECT COUNT(*) as total FROM Payments WHERE SubmittedAt >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['payments'] = $result['total'] ?? 0;
    
    // Previous month payments
    $query = "SELECT COUNT(*) as prev_month FROM Payments WHERE SubmittedAt BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $prevMonthPayments = $result['prev_month'] ?? 0;
    
    // Calculate percentage change
    if ($prevMonthPayments > 0) {
        $stats['payments_growth'] = round((($stats['payments'] - $prevMonthPayments) / $prevMonthPayments) * 100, 1);
    } else {
        $stats['payments_growth'] = 100;
    }
    
    return $stats;
}

// Get recent payments
function getRecentPayments($conn, $limit = 5) {
    $query = "SELECT p.PaymentID, p.Amount, p.Status, p.SubmittedAt, p.VerifiedAt, 
              c.Name as CustomerName, s.SchemeName 
              FROM Payments p 
              JOIN Customers c ON p.CustomerID = c.CustomerID 
              JOIN Schemes s ON p.SchemeID = s.SchemeID 
              ORDER BY p.SubmittedAt DESC 
              LIMIT :limit";
              
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get recent activity
function getRecentActivity($conn, $limit = 7) {
    $query = "SELECT a.Action, a.CreatedAt, a.UserType, a.UserID 
              FROM ActivityLogs a 
              ORDER BY a.CreatedAt DESC 
              LIMIT :limit";
              
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $activities = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get user name
        if ($row['UserType'] == 'Admin') {
            $userQuery = "SELECT Name FROM Admins WHERE AdminID = :userId";
        } else {
            $userQuery = "SELECT Name FROM Promoters WHERE PromoterID = :userId";
        }
        
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bindParam(':userId', $row['UserID'], PDO::PARAM_INT);
        $userStmt->execute();
        $userResult = $userStmt->fetch(PDO::FETCH_ASSOC);
        $userName = $userResult['Name'] ?? 'Unknown User';
        
        $row['UserName'] = $userName;
        $activities[] = $row;
    }
    
    return $activities;
}

// Get revenue data for chart
function getRevenueChartData($conn, $period = 'week') {
    $data = ['labels' => [], 'values' => []];
    
    if ($period == 'day') {
        $query = "SELECT 
                  HOUR(SubmittedAt) as hour,
                  SUM(Amount) as amount
                  FROM Payments 
                  WHERE Status = 'Verified' 
                  AND DATE(SubmittedAt) = CURDATE()
                  GROUP BY HOUR(SubmittedAt)
                  ORDER BY HOUR(SubmittedAt)";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        // Initialize with all hours
        for ($i = 0; $i < 24; $i++) {
            $hourFormatted = ($i < 10) ? "0$i:00" : "$i:00";
            $data['labels'][] = $hourFormatted;
            $data['values'][] = 0;
        }
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hour = (int)$row['hour'];
            $data['values'][$hour] = (float)$row['amount'];
        }
        
    } elseif ($period == 'week') {
        $query = "SELECT 
                  DAYNAME(SubmittedAt) as day,
                  DAYOFWEEK(SubmittedAt) as day_num,
                  SUM(Amount) as amount
                  FROM Payments 
                  WHERE Status = 'Verified' 
                  AND SubmittedAt >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  GROUP BY DAYOFWEEK(SubmittedAt), DAYNAME(SubmittedAt)
                  ORDER BY DAYOFWEEK(SubmittedAt)";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        // Initialize with all days
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach ($dayNames as $day) {
            $data['labels'][] = substr($day, 0, 3);
            $data['values'][] = 0;
        }
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dayIndex = $row['day_num'] - 1; // DAYOFWEEK starts with 1 for Sunday
            $data['values'][$dayIndex] = (float)$row['amount'];
        }
        
    } elseif ($period == 'month') {
        $query = "SELECT 
                  MONTH(SubmittedAt) as month,
                  MONTHNAME(SubmittedAt) as month_name,
                  SUM(Amount) as amount
                  FROM Payments 
                  WHERE Status = 'Verified' 
                  AND SubmittedAt >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                  GROUP BY MONTH(SubmittedAt), MONTHNAME(SubmittedAt)
                  ORDER BY MONTH(SubmittedAt)";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        // Initialize with all months
        $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        foreach ($monthNames as $month) {
            $data['labels'][] = substr($month, 0, 3);
            $data['values'][] = 0;
        }
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $monthIndex = (int)$row['month'] - 1; // MONTH starts with 1 for January
            $data['values'][$monthIndex] = (float)$row['amount'];
        }
    }
    
    return $data;
}

// Get customer growth data for chart
function getCustomerGrowthData($conn, $period = 'week') {
    $data = [
        'labels' => [],
        'new' => [],
        'returning' => []
    ];
    
    if ($period == 'day') {
        // New customers per hour today
        $newQuery = "SELECT 
                    HOUR(CreatedAt) as hour,
                    COUNT(*) as count
                    FROM Customers 
                    WHERE DATE(CreatedAt) = CURDATE()
                    GROUP BY HOUR(CreatedAt)
                    ORDER BY HOUR(CreatedAt)";
        
        // Returning customers based on recent payments 
        $returningQuery = "SELECT 
                          HOUR(p.SubmittedAt) as hour,
                          COUNT(DISTINCT p.CustomerID) as count
                          FROM Payments p
                          JOIN Customers c ON p.CustomerID = c.CustomerID
                          WHERE DATE(p.SubmittedAt) = CURDATE()
                          AND c.CreatedAt < CURDATE() 
                          GROUP BY HOUR(p.SubmittedAt)
                          ORDER BY HOUR(p.SubmittedAt)";
        
        // Initialize with all hours
        for ($i = 0; $i < 24; $i++) {
            $hourFormatted = ($i < 10) ? "0$i:00" : "$i:00";
            $data['labels'][] = $hourFormatted;
            $data['new'][] = 0;
            $data['returning'][] = 0;
        }
        
        $newStmt = $conn->prepare($newQuery);
        $newStmt->execute();
        while ($row = $newStmt->fetch(PDO::FETCH_ASSOC)) {
            $hour = (int)$row['hour'];
            $data['new'][$hour] = (int)$row['count'];
        }
        
        $returningStmt = $conn->prepare($returningQuery);
        $returningStmt->execute();
        while ($row = $returningStmt->fetch(PDO::FETCH_ASSOC)) {
            $hour = (int)$row['hour'];
            $data['returning'][$hour] = (int)$row['count'];
        }
        
    } elseif ($period == 'week') {
        // New customers per day this week
        $newQuery = "SELECT 
                    DAYNAME(CreatedAt) as day,
                    DAYOFWEEK(CreatedAt) as day_num,
                    COUNT(*) as count
                    FROM Customers 
                    WHERE CreatedAt >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY DAYOFWEEK(CreatedAt), DAYNAME(CreatedAt)
                    ORDER BY DAYOFWEEK(CreatedAt)";
        
        // Returning customers based on recent payments
        $returningQuery = "SELECT 
                          DAYNAME(p.SubmittedAt) as day,
                          DAYOFWEEK(p.SubmittedAt) as day_num,
                          COUNT(DISTINCT p.CustomerID) as count
                          FROM Payments p
                          JOIN Customers c ON p.CustomerID = c.CustomerID
                          WHERE p.SubmittedAt >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                          AND c.CreatedAt < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                          GROUP BY DAYOFWEEK(p.SubmittedAt), DAYNAME(p.SubmittedAt)
                          ORDER BY DAYOFWEEK(p.SubmittedAt)";
        
        // Initialize with all days
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach ($dayNames as $day) {
            $data['labels'][] = substr($day, 0, 3);
            $data['new'][] = 0;
            $data['returning'][] = 0;
        }
        
        $newStmt = $conn->prepare($newQuery);
        $newStmt->execute();
        while ($row = $newStmt->fetch(PDO::FETCH_ASSOC)) {
            $dayIndex = $row['day_num'] - 1; // DAYOFWEEK starts with 1 for Sunday
            $data['new'][$dayIndex] = (int)$row['count'];
        }
        
        $returningStmt = $conn->prepare($returningQuery);
        $returningStmt->execute();
        while ($row = $returningStmt->fetch(PDO::FETCH_ASSOC)) {
            $dayIndex = $row['day_num'] - 1;
            $data['returning'][$dayIndex] = (int)$row['count'];
        }
        
    } elseif ($period == 'month') {
        // New customers per month this year
        $newQuery = "SELECT 
                    MONTH(CreatedAt) as month,
                    MONTHNAME(CreatedAt) as month_name,
                    COUNT(*) as count
                    FROM Customers 
                    WHERE CreatedAt >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    GROUP BY MONTH(CreatedAt), MONTHNAME(CreatedAt)
                    ORDER BY MONTH(CreatedAt)";
        
        // Returning customers based on payments
        $returningQuery = "SELECT 
                          MONTH(p.SubmittedAt) as month,
                          MONTHNAME(p.SubmittedAt) as month_name,
                          COUNT(DISTINCT p.CustomerID) as count
                          FROM Payments p
                          JOIN Customers c ON p.CustomerID = c.CustomerID
                          WHERE p.SubmittedAt >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                          AND c.CreatedAt < DATE_FORMAT(p.SubmittedAt, '%Y-%m-01')
                          GROUP BY MONTH(p.SubmittedAt), MONTHNAME(p.SubmittedAt)
                          ORDER BY MONTH(p.SubmittedAt)";
        
        // Initialize with all months
        $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        foreach ($monthNames as $month) {
            $data['labels'][] = substr($month, 0, 3);
            $data['new'][] = 0;
            $data['returning'][] = 0;
        }
        
        $newStmt = $conn->prepare($newQuery);
        $newStmt->execute();
        while ($row = $newStmt->fetch(PDO::FETCH_ASSOC)) {
            $monthIndex = (int)$row['month'] - 1; // MONTH starts with 1 for January
            $data['new'][$monthIndex] = (int)$row['count'];
        }
        
        $returningStmt = $conn->prepare($returningQuery);
        $returningStmt->execute();
        while ($row = $returningStmt->fetch(PDO::FETCH_ASSOC)) {
            $monthIndex = (int)$row['month'] - 1;
            $data['returning'][$monthIndex] = (int)$row['count'];
        }
    }
    
    return $data;
}

// Try to fetch stats, but gracefully handle errors if tables don't exist yet
try {
    $stats = getStats($conn);
    $recentPayments = getRecentPayments($conn);
    $recentActivity = getRecentActivity($conn);
    $revenueData = getRevenueChartData($conn);
    $customerData = getCustomerGrowthData($conn);
} catch (PDOException $e) {
    // If tables don't exist yet, use sample data
    $stats = [
        'customers' => 0,
        'customers_growth' => 0,
        'revenue' => 0,
        'revenue_growth' => 0,
        'schemes' => 0,
        'new_schemes' => 0,
        'payments' => 0,
        'payments_growth' => 0
    ];
    $recentPayments = [];
    $recentActivity = [];
    $revenueData = ['labels' => [], 'values' => []];
    $customerData = ['labels' => [], 'new' => [], 'returning' => []];
    
    // If in development, show the error
    if (ini_get('display_errors')) {
        echo "<div style='color:red; padding:10px; background:#ffeeee; border:1px solid #ff0000;'>";
        echo "Database Error: " . $e->getMessage();
        echo "<br>Note: This error is only shown because display_errors is enabled.";
        echo "</div>";
    }
}

// Format revenue for display
function formatAmount($amount) {
    if ($amount >= 100000) {
        return '₹' . number_format($amount / 100000, 1) . 'L';
    } elseif ($amount >= 1000) {
        return '₹' . number_format($amount / 1000, 1) . 'K';
    } else {
        return '₹' . number_format($amount, 0);
    }
}

// Get initials from name
function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    
    if (count($words) >= 2) {
        $initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    } else {
        $initials = strtoupper(substr($name, 0, 2));
    }
    
    return $initials;
}

// Format date for display
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Format activity message
function formatActivity($action, $userName) {
    $actionLower = strtolower($action);
    
    if (strpos($actionLower, 'customer') !== false && strpos($actionLower, 'add') !== false) {
        return "<strong>New customer</strong> added by {$userName}";
    } elseif (strpos($actionLower, 'customer') !== false && strpos($actionLower, 'edit') !== false) {
        return "<strong>Customer updated</strong> by {$userName}";
    } elseif (strpos($actionLower, 'payment') !== false && strpos($actionLower, 'verify') !== false) {
        return "<strong>Payment verified</strong> by {$userName}";
    } elseif (strpos($actionLower, 'payment') !== false && strpos($actionLower, 'reject') !== false) {
        return "<strong>Payment rejected</strong> by {$userName}";
    } elseif (strpos($actionLower, 'promoter') !== false && strpos($actionLower, 'add') !== false) {
        return "<strong>New promoter</strong> added by {$userName}";
    } elseif (strpos($actionLower, 'scheme') !== false && strpos($actionLower, 'add') !== false) {
        return "<strong>New scheme</strong> created by {$userName}";
    } elseif (strpos($actionLower, 'winner') !== false) {
        return "<strong>Winner announced</strong> by {$userName}";
    } else {
        return "<strong>{$action}</strong> by {$userName}";
    }
}

// Convert time to "X time ago" format
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    } elseif ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    } elseif ($diff->d > 0) {
        if ($diff->d == 1) {
            return 'Yesterday';
        }
        return $diff->d . ' days ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}

// Get the current month range for display
$currentMonthStart = date('F 1');
$currentMonthEnd = date('F t');
$dateRangeText = $currentMonthStart . ' - ' . $currentMonthEnd;

// Get Admin info (normally would come from session)
$adminName = $_SESSION['admin_name'] ?? 'Admin User';
$adminRole = $_SESSION['admin_role'] ?? 'Administrator';

// Create API endpoint for chart data
// Create necessary directories if they don't exist
if (!file_exists('../api')) {
    mkdir('../api', 0755, true);
}

// Define the API endpoint file
$apiEndpointFile = '../api/chart-data.php';
if (!file_exists($apiEndpointFile)) {
    $apiCode = '<?php
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

header("Content-Type: application/json");

$chart = $_GET["chart"] ?? "";
$period = $_GET["period"] ?? "week";

if ($chart == "revenue-chart") {
    // Get revenue data
    $data = getRevenueChartData($conn, $period);
    echo json_encode($data);
} elseif ($chart == "customers-chart") {
    // Get customer growth data
    $data = getCustomerGrowthData($conn, $period);
    echo json_encode($data);
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid chart type"]);
}

// Get revenue data for chart
function getRevenueChartData($conn, $period = "week") {
    // Same function as in dashboard.php
    $data = ["labels" => [], "values" => []];
    
    // Function implementation here...
    // Copy same implementation from dashboard.php
    
    return $data;
}

// Get customer growth data for chart
function getCustomerGrowthData($conn, $period = "week") {
    // Same function as in dashboard.php
    $data = [
        "labels" => [],
        "new" => [],
        "returning" => []
    ];
    
    // Function implementation here...
    // Copy same implementation from dashboard.php
    
    return $data;
}
';
    file_put_contents($apiEndpointFile, $apiCode);
}

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="content-wrapper">
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Dashboard Overview</h1>
            <div class="date-range">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo $dateRangeText; ?></span>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon customers-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-title">Total Customers</div>
                <div class="stat-value"><?php echo number_format($stats['customers']); ?></div>
                <div class="stat-change <?php echo $stats['customers_growth'] >= 0 ? 'positive-change' : 'negative-change'; ?>">
                    <i class="fas fa-arrow-<?php echo $stats['customers_growth'] >= 0 ? 'up' : 'down'; ?>"></i>
                    <span><?php echo abs($stats['customers_growth']); ?>% this month</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon revenue-icon">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-title">Total Revenue</div>
                <div class="stat-value"><?php echo formatAmount($stats['revenue']); ?></div>
                <div class="stat-change <?php echo $stats['revenue_growth'] >= 0 ? 'positive-change' : 'negative-change'; ?>">
                    <i class="fas fa-arrow-<?php echo $stats['revenue_growth'] >= 0 ? 'up' : 'down'; ?>"></i>
                    <span><?php echo abs($stats['revenue_growth']); ?>% this month</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon schemes-icon">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <div class="stat-title">Active Schemes</div>
                <div class="stat-value"><?php echo $stats['schemes']; ?></div>
                <div class="stat-change positive-change">
                    <i class="fas fa-arrow-up"></i>
                    <span><?php echo $stats['new_schemes']; ?> new this month</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon payments-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-title">New Payments</div>
                <div class="stat-value"><?php echo number_format($stats['payments']); ?></div>
                <div class="stat-change <?php echo $stats['payments_growth'] >= 0 ? 'positive-change' : 'negative-change'; ?>">
                    <i class="fas fa-arrow-<?php echo $stats['payments_growth'] >= 0 ? 'up' : 'down'; ?>"></i>
                    <span><?php echo abs($stats['payments_growth']); ?>% this month</span>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Revenue Overview</h3>
                    <div class="chart-actions">
                        <button class="chart-action" data-period="day">Day</button>
                        <button class="chart-action active" data-period="week">Week</button>
                        <button class="chart-action" data-period="month">Month</button>
                    </div>
                </div>
                <div class="chart-container" id="revenue-chart"></div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Customer Growth</h3>

            <div class="stat-card">
                <div class="stat-icon revenue-icon">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-title">Total Revenue</div>
                <div class="stat-value"><?php echo formatAmount($stats['revenue']); ?></div>
                <div class="stat-change <?php echo $stats['revenue_growth'] >= 0 ? 'positive-change' : 'negative-change'; ?>">
                    <i class="fas fa-arrow-<?php echo $stats['revenue_growth'] >= 0 ? 'up' : 'down'; ?>"></i>
                    <span><?php echo abs($stats['revenue_growth']); ?>% this month</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon schemes-icon">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <div class="stat-title">Active Schemes</div>
                <div class="stat-value"><?php echo $stats['schemes']; ?></div>
                <div class="stat-change positive-change">
                    <i class="fas fa-arrow-up"></i>
                    <span><?php echo $stats['new_schemes']; ?> new this month</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon payments-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-title">New Payments</div>
                <div class="stat-value"><?php echo number_format($stats['payments']); ?></div>
                <div class="stat-change <?php echo $stats['payments_growth'] >= 0 ? 'positive-change' : 'negative-change'; ?>">
                    <i class="fas fa-arrow-<?php echo $stats['payments_growth'] >= 0 ? 'up' : 'down'; ?>"></i>
                    <span><?php echo abs($stats['payments_growth']); ?>% this month</span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Revenue Overview</h3>
                    <div class="chart-actions">
                        <button class="chart-action" data-period="day">Day</button>
                        <button class="chart-action active" data-period="week">Week</button>
                        <button class="chart-action" data-period="month">Month</button>
                    </div>
                </div>
                <div class="chart-container" id="revenue-chart"></div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Customer Growth</h3>
                    <div class="chart-actions">
                        <button class="chart-action" data-period="day">Day</button>
                        <button class="chart-action active" data-period="week">Week</button>
                        <button class="chart-action" data-period="month">Month</button>
                    </div>
                </div>
                <div class="chart-container" id="customers-chart"></div>
            </div>
        </div>

        <!-- Recent Payments Table -->
        <div class="recent-payments">
            <div class="payments-header">
                <h3 class="payments-title">Recent Payments</h3>
                <a href="<?php echo $menuPath; ?>payments.php" class="chart-action">View All</a>
            </div>
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Scheme</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPayments as $payment): ?>
                        <tr>
                            <td>
                                <div class="customer-cell">
                                    <div class="customer-avatar"><?php echo getInitials($payment['CustomerName']); ?></div>
                                    <span><?php echo $payment['CustomerName']; ?></span>
                                </div>
                            </td>
                            <td><?php echo $payment['SchemeName']; ?></td>
                            <td><?php echo formatDate($payment['SubmittedAt']); ?></td>
                            <td>₹<?php echo number_format($payment['Amount']); ?></td>
                            <td>
                                <span class="payment-status status-<?php echo strtolower($payment['Status']); ?>">
                                    <?php echo $payment['Status']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo $menuPath; ?>payment-details.php?id=<?php echo $payment['PaymentID']; ?>" class="action-btn custom-tooltip" data-tooltip="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($recentPayments)): ?>
                        <tr>
                            <td colspan="6" class="no-data">No payment records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Quick Access and Activity Feed -->
        <div class="bottom-grid">
            <div class="quick-access">
                <h3 class="quick-access-title">Quick Actions</h3>
                <div class="actions-grid">
                    <a href="<?php echo $menuPath; ?>customers/add.php" class="action-card">
                        <div class="action-icon" style="background: linear-gradient(135deg, #3a7bd5, #00d2ff)">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="action-name">Add Customer</div>
                    </a>
                    <a href="<?php echo $menuPath; ?>promoters/add.php" class="action-card">
                        <div class="action-icon" style="background: linear-gradient(135deg, #11998e, #38ef7d)">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="action-name">Add Promoter</div>
                    </a>
                    <a href="<?php echo $menuPath; ?>schemes/add.php" class="action-card">
                        <div class="action-icon" style="background: linear-gradient(135deg, #f2994a, #f2c94c)">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="action-name">New Scheme</div>
                    </a>
                    <a href="<?php echo $menuPath; ?>payments/verify.php" class="action-card">
                        <div class="action-icon" style="background: linear-gradient(135deg, #6a11cb, #2575fc)">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="action-name">Verify Payments</div>
                    </a>
                    <a href="<?php echo $menuPath; ?>reports/index.php" class="action-card">
                        <div class="action-icon" style="background: linear-gradient(135deg, #1a2a6c, #b21f1f)">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-name">Reports</div>
                    </a>
                    <a href="<?php echo $menuPath; ?>winners/add.php" class="action-card">
                        <div class="action-icon" style="background: linear-gradient(135deg, #c31432, #240b36)">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="action-name">Add Winner</div>
                    </a>
                </div>
            </div>

            <div class="activity-feed">
                <h3 class="activity-title">Recent Activity</h3>
                <div class="activity-list">
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <?php
                            // Determine icon based on action
                            $iconClass = 'fas fa-history';
                            $iconBg = '#6c757d';

                            if (stripos($activity['Action'], 'customer') !== false && stripos($activity['Action'], 'add') !== false) {
                                $iconClass = 'fas fa-user-plus';
                                $iconBg = '#3a7bd5';
                            } elseif (stripos($activity['Action'], 'customer') !== false && stripos($activity['Action'], 'edit') !== false) {
                                $iconClass = 'fas fa-user-edit';
                                $iconBg = '#3a7bd5';
                            } elseif (stripos($activity['Action'], 'payment') !== false && stripos($activity['Action'], 'verify') !== false) {
                                $iconClass = 'fas fa-check-circle';
                                $iconBg = '#11998e';
                            } elseif (stripos($activity['Action'], 'payment') !== false && stripos($activity['Action'], 'reject') !== false) {
                                $iconClass = 'fas fa-times-circle';
                                $iconBg = '#f53b57';
                            } elseif (stripos($activity['Action'], 'promoter') !== false) {
                                $iconClass = 'fas fa-user-tie';
                                $iconBg = '#f2994a';
                            } elseif (stripos($activity['Action'], 'scheme') !== false) {
                                $iconClass = 'fas fa-project-diagram';
                                $iconBg = '#6a11cb';
                            } elseif (stripos($activity['Action'], 'winner') !== false) {
                                $iconClass = 'fas fa-trophy';
                                $iconBg = '#c31432';
                            } elseif (stripos($activity['Action'], 'login') !== false) {
                                $iconClass = 'fas fa-sign-in-alt';
                                $iconBg = '#1a2a6c';
                            }
                            ?>
                            <div class="activity-icon" style="background: <?php echo $iconBg; ?>">
                                <i class="<?php echo $iconClass; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-message">
                                    <?php echo formatActivity($activity['Action'], $activity['UserName']); ?>
                                </div>
                                <div class="activity-time"><?php echo timeAgo($activity['CreatedAt']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($recentActivity)): ?>
                        <div class="no-activity">No recent activity found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Initialize charts with data from PHP
        document.addEventListener('DOMContentLoaded', function() {
            // Revenue data from PHP
            const revenueLabels = <?php echo json_encode($revenueData['labels']); ?>;
            const revenueValues = <?php echo json_encode($revenueData['values']); ?>;

            // Customer data from PHP
            const customerLabels = <?php echo json_encode($customerData['labels']); ?>;
            const newCustomers = <?php echo json_encode($customerData['new']); ?>;
            const returningCustomers = <?php echo json_encode($customerData['returning']); ?>;

            // Initialize charts
            initRevenueChart(revenueLabels, revenueValues);
            initCustomersChart(customerLabels, newCustomers, returningCustomers);

            // Fetch new data when changing time period
            setupChartPeriodControls();
        });
    </script>
</body>

</html>