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
function getStats($conn)
{
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
function getRecentPayments($conn, $limit = 5)
{
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
function getRecentActivity($conn, $limit = 7)
{
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

// Get more detailed revenue data for chart - make sure it returns actual data
function getRevenueChartData($conn, $period = 'week')
{
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
        // Get data for last 7 days instead of just days of current week
        $query = "SELECT 
                  DATE(SubmittedAt) as payment_date,
                  DAYNAME(SubmittedAt) as day_name,
                  SUM(Amount) as amount
                  FROM Payments 
                  WHERE Status = 'Verified' 
                  AND SubmittedAt >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  GROUP BY DATE(SubmittedAt), DAYNAME(SubmittedAt)
                  ORDER BY DATE(SubmittedAt)";

        $stmt = $conn->prepare($query);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If we have no data for the week, try getting some historical data
        if (empty($results)) {
            $query = "SELECT 
                     DATE(SubmittedAt) as payment_date,
                     DAYNAME(SubmittedAt) as day_name,
                     SUM(Amount) as amount
                     FROM Payments 
                     WHERE Status = 'Verified' 
                     GROUP BY DATE(SubmittedAt), DAYNAME(SubmittedAt)
                     ORDER BY DATE(SubmittedAt) DESC
                     LIMIT 7";

            $stmt = $conn->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Process the results
        foreach ($results as $row) {
            $data['labels'][] = date('D', strtotime($row['payment_date']));
            $data['values'][] = (float)$row['amount'];
        }

        // If still no data, use empty structure
        if (empty($data['labels'])) {
            $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $today = date('w'); // 0 = Sunday, 6 = Saturday

            for ($i = 6; $i >= 0; $i--) {
                $dayIndex = ($today - $i + 7) % 7;
                $data['labels'][] = $dayNames[$dayIndex];
                $data['values'][] = 0;
            }
        }
    } elseif ($period == 'month') {
        // Get actual monthly data for the past year
        $query = "SELECT 
                  DATE_FORMAT(SubmittedAt, '%Y-%m') as month_year,
                  DATE_FORMAT(SubmittedAt, '%b') as month_name,
                  SUM(Amount) as amount
                  FROM Payments 
                  WHERE Status = 'Verified' 
                  AND SubmittedAt >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                  GROUP BY DATE_FORMAT(SubmittedAt, '%Y-%m'), DATE_FORMAT(SubmittedAt, '%b')
                  ORDER BY DATE_FORMAT(SubmittedAt, '%Y-%m')";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process the actual monthly results
        foreach ($results as $row) {
            $data['labels'][] = $row['month_name'];
            $data['values'][] = (float)$row['amount'];
        }

        // If we have no data for the months, try getting some data
        if (empty($data['labels'])) {
            $query = "SELECT 
                     DATE_FORMAT(SubmittedAt, '%Y-%m') as month_year,
                     DATE_FORMAT(SubmittedAt, '%b') as month_name,
                     SUM(Amount) as amount
                     FROM Payments 
                     WHERE Status = 'Verified' 
                     GROUP BY DATE_FORMAT(SubmittedAt, '%Y-%m'), DATE_FORMAT(SubmittedAt, '%b')
                     ORDER BY DATE_FORMAT(SubmittedAt, '%Y-%m') DESC
                     LIMIT 12";

            $stmt = $conn->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $data['labels'][] = $row['month_name'];
                $data['values'][] = (float)$row['amount'];
            }
        }

        // If still no data, use empty structure with month names
        if (empty($data['labels'])) {
            $currentMonth = (int)date('m');
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            for ($i = 11; $i >= 0; $i--) {
                $monthIndex = ($currentMonth - 1 - $i + 12) % 12;
                $data['labels'][] = $monthNames[$monthIndex];
                $data['values'][] = 0;
            }
        }
    }

    return $data;
}

// Get more detailed customer growth data for chart - ensure real data
function getCustomerGrowthData($conn, $period = 'week')
{
    $data = [
        'labels' => [],
        'new' => [],
        'returning' => []
    ];

    if ($period == 'day') {
        // Get actual hourly data for today
        $newQuery = "SELECT 
                    HOUR(CreatedAt) as hour,
                    COUNT(*) as count
                    FROM Customers 
                    WHERE DATE(CreatedAt) = CURDATE()
                    GROUP BY HOUR(CreatedAt)
                    ORDER BY HOUR(CreatedAt)";

        $returningQuery = "SELECT 
                          HOUR(p.SubmittedAt) as hour,
                          COUNT(DISTINCT p.CustomerID) as count
                          FROM Payments p
                          JOIN Customers c ON p.CustomerID = c.CustomerID
                          WHERE DATE(p.SubmittedAt) = CURDATE()
                          AND c.CreatedAt < CURDATE() 
                          GROUP BY HOUR(p.SubmittedAt)
                          ORDER BY HOUR(p.SubmittedAt)";

        // Create arrays for each hour
        $hours = [];
        $newCustomers = [];
        $returningCustomers = [];

        for ($i = 0; $i < 24; $i++) {
            $hourFormatted = ($i < 10) ? "0$i:00" : "$i:00";
            $hours[$i] = $hourFormatted;
            $newCustomers[$i] = 0;
            $returningCustomers[$i] = 0;
        }

        // Get new customer data
        $newStmt = $conn->prepare($newQuery);
        $newStmt->execute();
        while ($row = $newStmt->fetch(PDO::FETCH_ASSOC)) {
            $hour = (int)$row['hour'];
            $newCustomers[$hour] = (int)$row['count'];
        }

        // Get returning customer data
        $returningStmt = $conn->prepare($returningQuery);
        $returningStmt->execute();
        while ($row = $returningStmt->fetch(PDO::FETCH_ASSOC)) {
            $hour = (int)$row['hour'];
            $returningCustomers[$hour] = (int)$row['count'];
        }

        // Populate the data array
        $data['labels'] = array_values($hours);
        $data['new'] = array_values($newCustomers);
        $data['returning'] = array_values($returningCustomers);
    } elseif ($period == 'week') {
        // Get actual daily data for last 7 days
        $newQuery = "SELECT 
                    DATE(CreatedAt) as created_date,
                    COUNT(*) as count
                    FROM Customers 
                    WHERE CreatedAt >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY DATE(CreatedAt)
                    ORDER BY DATE(CreatedAt)";

        $returningQuery = "SELECT 
                          DATE(p.SubmittedAt) as payment_date,
                          COUNT(DISTINCT p.CustomerID) as count
                          FROM Payments p
                          JOIN Customers c ON p.CustomerID = c.CustomerID
                          WHERE p.SubmittedAt >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                          AND c.CreatedAt < DATE(p.SubmittedAt)
                          GROUP BY DATE(p.SubmittedAt)
                          ORDER BY DATE(p.SubmittedAt)";

        // Prepare data structure for last 7 days
        $days = [];
        $newCustomers = [];
        $returningCustomers = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dayName = date('D', strtotime($date));
            $days[$date] = $dayName;
            $newCustomers[$date] = 0;
            $returningCustomers[$date] = 0;
        }

        // Get new customer data
        $newStmt = $conn->prepare($newQuery);
        $newStmt->execute();
        while ($row = $newStmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['created_date'];
            if (isset($newCustomers[$date])) {
                $newCustomers[$date] = (int)$row['count'];
            }
        }

        // Get returning customer data
        $returningStmt = $conn->prepare($returningQuery);
        $returningStmt->execute();
        while ($row = $returningStmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['payment_date'];
            if (isset($returningCustomers[$date])) {
                $returningCustomers[$date] = (int)$row['count'];
            }
        }

        // Populate the data array
        $data['labels'] = array_values($days);
        $data['new'] = array_values($newCustomers);
        $data['returning'] = array_values($returningCustomers);
    } elseif ($period == 'month') {
        // Get actual monthly data for past year
        $newQuery = "SELECT 
                    DATE_FORMAT(CreatedAt, '%Y-%m') as month_year,
                    DATE_FORMAT(CreatedAt, '%b') as month_name,
                    COUNT(*) as count
                    FROM Customers 
                    WHERE CreatedAt >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(CreatedAt, '%Y-%m'), DATE_FORMAT(CreatedAt, '%b')
                    ORDER BY DATE_FORMAT(CreatedAt, '%Y-%m')";

        $returningQuery = "SELECT 
                          DATE_FORMAT(p.SubmittedAt, '%Y-%m') as month_year,
                          DATE_FORMAT(p.SubmittedAt, '%b') as month_name,
                          COUNT(DISTINCT p.CustomerID) as count
                          FROM Payments p
                          JOIN Customers c ON p.CustomerID = c.CustomerID
                          WHERE p.SubmittedAt >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                          AND c.CreatedAt < DATE_FORMAT(p.SubmittedAt, '%Y-%m-01')
                          GROUP BY DATE_FORMAT(p.SubmittedAt, '%Y-%m'), DATE_FORMAT(p.SubmittedAt, '%b')
                          ORDER BY DATE_FORMAT(p.SubmittedAt, '%Y-%m')";

        // Process new customers by month
        $newStmt = $conn->prepare($newQuery);
        $newStmt->execute();
        $newResults = $newStmt->fetchAll(PDO::FETCH_ASSOC);

        // Process returning customers by month
        $returningStmt = $conn->prepare($returningQuery);
        $returningStmt->execute();
        $returningResults = $returningStmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare month labels and data - use past 12 months
        $currentMonth = date('Y-m');
        $months = [];
        $monthLabels = [];

        for ($i = 11; $i >= 0; $i--) {
            $monthDate = date('Y-m', strtotime("-$i months"));
            $monthLabel = date('M', strtotime("-$i months"));
            $months[$monthDate] = 0; // For new customers
            $monthLabels[$monthDate] = $monthLabel;
        }

        // Fill in actual new customer data
        foreach ($newResults as $row) {
            $monthYear = $row['month_year'];
            if (isset($months[$monthYear])) {
                $months[$monthYear] = (int)$row['count'];
            }
        }

        // Prepare array for returning customers
        $returningMonths = [];
        foreach (array_keys($months) as $month) {
            $returningMonths[$month] = 0;
        }

        // Fill in actual returning customer data
        foreach ($returningResults as $row) {
            $monthYear = $row['month_year'];
            if (isset($returningMonths[$monthYear])) {
                $returningMonths[$monthYear] = (int)$row['count'];
            }
        }

        // Set final data
        $data['labels'] = array_values($monthLabels);
        $data['new'] = array_values($months);
        $data['returning'] = array_values($returningMonths);
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
function formatAmount($amount)
{
    if ($amount >= 100000) {
        return '₹' . number_format($amount / 100000, 1) . 'L';
    } elseif ($amount >= 1000) {
        return '₹' . number_format($amount / 1000, 1) . 'K';
    } else {
        return '₹' . number_format($amount, 0);
    }
}

// Get initials from name
function getInitials($name)
{
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
function formatDate($date)
{
    return date('M d, Y', strtotime($date));
}

// Format activity message
function formatActivity($action, $userName)
{
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
function timeAgo($datetime)
{
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
    <style>
        /* Fix for layout collisions and improve responsiveness */
        .dashboard-container {
            padding: 20px;
            max-width: 100%;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
            /* Add more space after charts section */
        }

        /* Ensure proper spacing between each section */
        .recent-payments {
            margin-bottom: 30px;
            clear: both;
            /* Prevent any floating elements from affecting layout */
            overflow: hidden;
            /* Contain any overflowing content */
        }

        /* Make the payments table more responsive */
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
            display: block;
            max-width: 100%;
        }

        @media (min-width: 992px) {
            .payments-table {
                display: table;
            }
        }

        /* Make sure table cells don't shrink too much */
        .payments-table th,
        .payments-table td {
            min-width: 100px;
            padding: 12px 15px;
            text-align: left;
            white-space: nowrap;
        }

        /* Customer cell can be more flexible */
        .payments-table td:first-child {
            min-width: 150px;
        }

        /* Add horizontal scrolling for the table on small screens */
        @media (max-width: 768px) {
            .recent-payments {
                overflow-x: auto;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Fix bottom section layout */
        .bottom-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
            clear: both;
        }

        /* Ensure content wrapper follows a proper box model */
        .content-wrapper {
            box-sizing: border-box;
            padding: 15px;
        }

        /* Chart containers need a minimum height */
        .chart-container {
            min-height: 300px;
            width: 100%;
            position: relative;
        }

        /* Add a clearfix for any floating elements */
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>

<body class="">
    <div class="content-wrapper">
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

                <!-- <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Customer Growth</h3>
                        <div class="chart-actions">
                            <button class="chart-action" data-period="day">Day</button>
                            <button class="chart-action active" data-period="week">Week</button>
                            <button class="chart-action" data-period="month">Month</button>
                        </div>
                    </div>
                    <div class="chart-container" id="customers-chart"></div>
                </div> -->
            </div>

            <!-- Add a clearfix div after the charts section -->
            <div class="clearfix"></div>

            <!-- Recent Payments Table - with improved structure -->
            <div class="recent-payments">
                <div class="payments-header">
                    <h3 class="payments-title">Recent Payments</h3>
                    <a href="<?php echo $menuPath; ?>payments" class="chart-action">View All</a>
                </div>
                <div class="table-responsive">
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
            </div>
            <div class="clearfix"></div>

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
    </div>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Revenue data from PHP - real data from database
            const revenueLabels = <?php echo json_encode($revenueData['labels']); ?>;
            const revenueValues = <?php echo json_encode($revenueData['values']); ?>;

            // Customer data from PHP - real data from database
            const customerLabels = <?php echo json_encode($customerData['labels']); ?>;
            const newCustomers = <?php echo json_encode($customerData['new']); ?>;
            const returningCustomers = <?php echo json_encode($customerData['returning']); ?>;

            // Revenue Chart
            function initRevenueChart(labels, values) {
                // Check if we have real data
                const hasRealData = values.some(value => value > 0);

                // If no real data, show a message in the chart
                if (!hasRealData) {
                    const container = document.querySelector("#revenue-chart");
                    container.innerHTML = '<div style="height:300px;display:flex;align-items:center;justify-content:center;flex-direction:column;"><i class="fas fa-chart-line" style="font-size:48px;color:#d1d1d1;margin-bottom:15px;"></i><span style="color:#6c757d;font-size:14px;">No revenue data available yet</span></div>';
                    return null;
                }

                const options = {
                    series: [{
                        name: 'Revenue',
                        data: values
                    }],
                    chart: {
                        height: 300,
                        type: 'area',
                        fontFamily: 'Poppins, sans-serif',
                        toolbar: {
                            show: false
                        },
                        zoom: {
                            enabled: false
                        }
                    },
                    colors: ['#3a7bd5'],
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        curve: 'smooth',
                        width: 3
                    },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.7,
                            opacityTo: 0.3,
                            stops: [0, 90, 100]
                        }
                    },
                    markers: {
                        size: 4,
                        colors: ['#fff'],
                        strokeColors: '#3a7bd5',
                        strokeWidth: 2,
                        hover: {
                            size: 7,
                        }
                    },
                    xaxis: {
                        categories: labels,
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        },
                        labels: {
                            style: {
                                colors: '#6c757d',
                                fontSize: '12px'
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            formatter: function(val) {
                                return '₹' + val.toLocaleString('en-IN');
                            },
                            style: {
                                colors: '#6c757d',
                                fontSize: '12px'
                            }
                        }
                    },
                    tooltip: {
                        y: {
                            formatter: function(val) {
                                return '₹' + val.toLocaleString('en-IN');
                            }
                        }
                    },
                    grid: {
                        borderColor: '#f1f1f1',
                        xaxis: {
                            lines: {
                                show: true
                            }
                        },
                        yaxis: {
                            lines: {
                                show: true
                            }
                        }
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'right',
                        offsetY: -15,
                        fontFamily: 'Poppins, sans-serif',
                        fontSize: '13px'
                    }
                };

                const chart = new ApexCharts(document.querySelector("#revenue-chart"), options);
                chart.render();
                return chart;
            }

            // Customers Chart
            function initCustomersChart(labels, newData, returningData) {
                // Check if we have real data
                const hasRealData = newData.some(value => value > 0) || returningData.some(value => value > 0);

                // If no real data, show a message in the chart
                if (!hasRealData) {
                    const container = document.querySelector("#customers-chart");
                    container.innerHTML = '<div style="height:300px;display:flex;align-items:center;justify-content:center;flex-direction:column;"><i class="fas fa-users" style="font-size:48px;color:#d1d1d1;margin-bottom:15px;"></i><span style="color:#6c757d;font-size:14px;">No customer data available yet</span></div>';
                    return null;
                }

                const options = {
                    series: [{
                        name: 'New Customers',
                        data: newData
                    }, {
                        name: 'Returning Customers',
                        data: returningData
                    }],
                    chart: {
                        type: 'bar',
                        height: 300,
                        fontFamily: 'Poppins, sans-serif',
                        stacked: true,
                        toolbar: {
                            show: false
                        },
                        zoom: {
                            enabled: false
                        }
                    },
                    responsive: [{
                        breakpoint: 480,
                        options: {
                            legend: {
                                position: 'bottom',
                                offsetX: -10,
                                offsetY: 0
                            }
                        }
                    }],
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            borderRadius: 8,
                            columnWidth: '55%',
                            endingShape: 'rounded',
                            dataLabels: {
                                position: 'top',
                            },
                        },
                    },
                    colors: ['#3a7bd5', '#00d2ff'],
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: labels,
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        },
                        labels: {
                            style: {
                                colors: '#6c757d',
                                fontSize: '12px'
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: '#6c757d',
                                fontSize: '12px'
                            }
                        }
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'right',
                        offsetY: -15,
                        fontFamily: 'Poppins, sans-serif',
                        fontSize: '13px'
                    },
                    fill: {
                        opacity: 1,
                        type: 'gradient',
                        gradient: {
                            shade: 'light',
                            type: "vertical",
                            shadeIntensity: 0.25,
                            gradientToColors: undefined,
                            inverseColors: true,
                            opacityFrom: 1,
                            opacityTo: 0.85,
                            stops: [50, 100]
                        },
                    },
                    tooltip: {
                        y: {
                            formatter: function(val) {
                                return val.toLocaleString('en-IN') + " customers";
                            }
                        }
                    },
                    grid: {
                        borderColor: '#f1f1f1',
                        xaxis: {
                            lines: {
                                show: true
                            }
                        },
                        yaxis: {
                            lines: {
                                show: true
                            }
                        }
                    }
                };

                const chart = new ApexCharts(document.querySelector("#customers-chart"), options);
                chart.render();
                return chart;
            }

            // Initialize charts
            const revenueChart = initRevenueChart(revenueLabels, revenueValues);
            const customersChart = initCustomersChart(customerLabels, newCustomers, returningCustomers);

            // Fetch new data when changing time period
            const revenueButtons = document.querySelectorAll('.chart-card:first-child .chart-action');
            const customerButtons = document.querySelectorAll('.chart-card:last-child .chart-action');

            revenueButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const period = this.getAttribute('data-period');
                    revenueButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    // Show loading state
                    document.querySelector('#revenue-chart').innerHTML = '<div style="height:300px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-spinner fa-spin" style="font-size:24px;color:#3a7bd5;"></i></div>';

                    // Fetch new data using API endpoint
                    fetch(`../api/chart-data.php?chart=revenue-chart&period=${period}`)
                        .then(response => response.json())
                        .then(data => {
                            // Check if we have real data
                            const hasRealData = data.values.some(value => value > 0);

                            if (!hasRealData) {
                                document.querySelector('#revenue-chart').innerHTML = '<div style="height:300px;display:flex;align-items:center;justify-content:center;flex-direction:column;"><i class="fas fa-chart-line" style="font-size:48px;color:#d1d1d1;margin-bottom:15px;"></i><span style="color:#6c757d;font-size:14px;">No revenue data available for this period</span></div>';
                                return;
                            }

                            if (!revenueChart) {
                                // If chart was null (no initial data), initialize it now
                                initRevenueChart(data.labels, data.values);
                                return;
                            }

                            // Update existing chart with new data
                            revenueChart.updateOptions({
                                xaxis: {
                                    categories: data.labels
                                }
                            });
                            revenueChart.updateSeries([{
                                name: 'Revenue',
                                data: data.values
                            }]);
                        })
                        .catch(error => {
                            console.error('Error fetching revenue data:', error);
                            document.querySelector('#revenue-chart').innerHTML = '<div style="height:300px;display:flex;align-items:center;justify-content:center;flex-direction:column;"><i class="fas fa-exclamation-triangle" style="font-size:48px;color:#f8d7da;margin-bottom:15px;"></i><span style="color:#721c24;font-size:14px;">Error loading chart data</span></div>';
                        });
                });
            });

            customerButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const period = this.getAttribute('data-period');
                    customerButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    // Show loading state
                    document.querySelector('#customers-chart').innerHTML = '<div style="height:300px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-spinner fa-spin" style="font-size:24px;color:#3a7bd5;"></i></div>';

                    // Fetch new data
                    fetch(`../api/chart-data.php?chart=customers-chart&period=${period}`)
                        .then(response => response.json())
                        .then(data => {
                            // Check if we have real data
                            const hasRealData = data.new.some(value => value > 0) || data.returning.some(value => value > 0);

                            if (!hasRealData) {
                                document.querySelector('#customers-chart').innerHTML = '<div style="height:300px;display:flex;align-items:center;justify-content:center;flex-direction:column;"><i class="fas fa-users" style="font-size:48px;color:#d1d1d1;margin-bottom:15px;"></i><span style="color:#6c757d;font-size:14px;">No customer data available for this period</span></div>';
                                return;
                            }

                            if (!customersChart) {
                                // If chart was null (no initial data), initialize it now
                                initCustomersChart(data.labels, data.new, data.returning);
                                return;
                            }

                            // Update chart with new data
                            customersChart.updateOptions({
                                xaxis: {
                                    categories: data.labels
                                }
                            });
                            customersChart.updateSeries([{
                                name: 'New Customers',
                                data: data.new
                            }, {
                                name: 'Returning Customers',
                                data: data.returning
                            }]);
                        })
                        .catch(error => {
                            console.error('Error fetching customer data:', error);
                            document.querySelector('#customers-chart').innerHTML = '<div style="height:300px;display:flex;align-items:center;justify-content:center;flex-direction:column;"><i class="fas fa-exclamation-triangle" style="font-size:48px;color:#f8d7da;margin-bottom:15px;"></i><span style="color:#721c24;font-size:14px;">Error loading chart data</span></div>';
                        });
                });
            });
        });
    </script>
</body>

</html>