<?php
session_start();
// Check if user is logged in
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

// Function to get promoter's children (both promoters and customers)
function getChildren($conn, $parentPromoterID)
{
    // Get child promoters
    $promoterStmt = $conn->prepare("
        SELECT 
            'promoter' as type,
            PromoterUniqueID,
            Name,
            Status,
            CreatedAt,
            PaymentCodeCounter,
            (SELECT COUNT(*) FROM Customers WHERE PromoterID = p.PromoterUniqueID) as customer_count
        FROM Promoters p
        WHERE ParentPromoterID = :parentId
    ");
    $promoterStmt->bindParam(':parentId', $parentPromoterID);
    $promoterStmt->execute();
    $promoters = $promoterStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get direct customers
    $customerStmt = $conn->prepare("
        SELECT 
            'customer' as type,
            CustomerUniqueID,
            Name,
            Status,
            CreatedAt,
            Contact
        FROM Customers 
        WHERE PromoterID = :promoterId
    ");
    $customerStmt->bindParam(':promoterId', $parentPromoterID);
    $customerStmt->execute();
    $customers = $customerStmt->fetchAll(PDO::FETCH_ASSOC);

    return array_merge($promoters, $customers);
}

// Get root level promoters (those without parent)
try {
    $rootStmt = $conn->prepare("
        SELECT 
            PromoterUniqueID,
            Name,
            Status,
            CreatedAt,
            PaymentCodeCounter,
            (SELECT COUNT(*) FROM Customers WHERE PromoterID = p.PromoterUniqueID) as customer_count
        FROM Promoters p
        WHERE ParentPromoterID IS NULL OR ParentPromoterID = ''
        ORDER BY CreatedAt DESC
    ");
    $rootStmt->execute();
    $rootPromoters = $rootStmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug output
    if (empty($rootPromoters)) {
        echo "<!-- Debug: No root promoters found -->";
        // Try to get all promoters to see if there's any data
        $allStmt = $conn->query("SELECT COUNT(*) as total FROM Promoters");
        $total = $allStmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<!-- Debug: Total promoters in database: " . $total . " -->";
    }
} catch (PDOException $e) {
    echo "<!-- Debug: Database error: " . $e->getMessage() . " -->";
    $rootPromoters = [];
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
    <title>Promoter Hierarchy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .hierarchy-container {
            padding: 20px;
            overflow-x: auto;
            min-height: calc(100vh - 200px);
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
        }

        .org-tree {
            display: flex;
            justify-content: center;
            padding: 20px;
        }

        .tree {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .tree ul {
            padding-top: 20px;
            position: relative;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .tree li {
            float: left;
            text-align: center;
            list-style-type: none;
            position: relative;
            padding: 20px 5px 0 5px;
        }

        .tree li::before,
        .tree li::after {
            content: '';
            position: absolute;
            top: 0;
            right: 50%;
            border-top: 2px solid #ddd;
            width: 50%;
            height: 20px;
        }

        .tree li::after {
            right: auto;
            left: 50%;
            border-left: 2px solid #ddd;
        }

        .tree li:only-child::after,
        .tree li:only-child::before {
            display: none;
        }

        .tree li:first-child::before,
        .tree li:last-child::after {
            border: 0 none;
        }

        .tree li:last-child::before {
            border-right: 2px solid #ddd;
            border-radius: 0 5px 0 0;
        }

        .tree li:first-child::after {
            border-radius: 5px 0 0 0;
        }

        .tree ul ul::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            border-left: 2px solid #ddd;
            width: 0;
            height: 20px;
        }

        .tree-node {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            position: relative;
            min-width: 200px;
        }

        .promoter-node {
            background: linear-gradient(135deg, rgba(58, 123, 213, 0.1), rgba(0, 210, 255, 0.1));
            border: 2px solid var(--ad_primary-color);
        }

        .customer-node {
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), rgba(0, 210, 255, 0.1));
            border: 2px solid var(--ad_success-color);
        }

        .node-header {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
            color: var(--text-dark);
        }

        .node-id {
            font-size: 12px;
            color: var(--text-light);
            margin-bottom: 5px;
        }

        .node-stats {
            display: flex;
            justify-content: center;
            gap: 10px;
            font-size: 12px;
            margin-top: 5px;
        }

        .node-stat {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .status-active {
            background-color: var(--ad_success-color);
        }

        .status-inactive {
            background-color: var(--danger-color);
        }

        .expand-collapse {
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border: 1px solid #ddd;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1;
            transition: all 0.3s ease;
        }

        .expand-collapse:hover {
            background: var(--ad_primary-color);
            color: white;
            border-color: var(--ad_primary-color);
        }

        .tree li.collapsed ul {
            display: none;
        }

        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box button {
            padding: 10px 20px;
            background: var(--ad_primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .loading {
            text-align: center;
            padding: 20px;
            font-size: 14px;
            color: var(--text-light);
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Promoter Hierarchy</h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Promoters
            </a>
        </div>

        <div class="hierarchy-container">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search by name or ID...">
                <button onclick="searchHierarchy()">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>

            <div class="org-tree">
                <div class="tree">
                    <?php if (!empty($rootPromoters)): ?>
                        <ul>
                            <?php foreach ($rootPromoters as $promoter): ?>
                                <li>
                                    <?php displayNode($conn, $promoter); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="no-data" style="text-align: center; padding: 20px;">
                            <p>No promoters found in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleChildren(element) {
            const li = element.closest('li');
            li.classList.toggle('collapsed');
            const icon = element.querySelector('i');
            icon.classList.toggle('fa-plus');
            icon.classList.toggle('fa-minus');
        }

        function searchHierarchy() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const nodes = document.querySelectorAll('.tree-node');

            nodes.forEach(node => {
                const text = node.textContent.toLowerCase();
                const li = node.closest('li');

                if (text.includes(searchTerm)) {
                    node.style.backgroundColor = 'rgba(58, 123, 213, 0.2)';
                    // Expand all parent nodes
                    let parent = li.parentElement;
                    while (parent) {
                        if (parent.tagName === 'LI') {
                            parent.classList.remove('collapsed');
                        }
                        parent = parent.parentElement;
                    }
                } else {
                    node.style.backgroundColor = '';
                }
            });
        }

        // Add event listener for search input
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                searchHierarchy();
            }
        });
    </script>
</body>

</html>

<?php
function displayNode($conn, $node)
{
    $isPromoter = !isset($node['type']) || $node['type'] === 'promoter';
    $nodeClass = $isPromoter ? 'promoter-node' : 'customer-node';
    $icon = $isPromoter ? 'user-tie' : 'user';
?>
    <div class="tree-node <?php echo $nodeClass; ?>">
        <div class="node-header">
            <span class="status-indicator status-<?php echo strtolower($node['Status']); ?>"></span>
            <i class="fas fa-<?php echo $icon; ?>"></i>
            <?php echo htmlspecialchars($node['Name']); ?>
        </div>
        <div class="node-id">ID: <?php echo htmlspecialchars($isPromoter ? $node['PromoterUniqueID'] : $node['CustomerUniqueID']); ?></div>
        <?php if ($isPromoter): ?>
            <div class="node-stats">
                <span class="node-stat">
                    <i class="fas fa-users"></i>
                    <?php echo isset($node['customer_count']) ? $node['customer_count'] : '0'; ?> customers
                </span>
                <span class="node-stat">
                    <i class="fas fa-ticket"></i>
                    <?php echo isset($node['PaymentCodeCounter']) ? $node['PaymentCodeCounter'] : '0'; ?> codes
                </span>
            </div>
        <?php else: ?>
            <div class="node-stats">
                <span class="node-stat">
                    <i class="fas fa-phone"></i>
                    <?php echo isset($node['Contact']) ? $node['Contact'] : 'N/A'; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

<?php
    if ($isPromoter) {
        $children = getChildren($conn, $node['PromoterUniqueID']);
        if (!empty($children)) {
            echo '<div class="expand-collapse" onclick="toggleChildren(this.parentElement)">
                    <i class="fas fa-minus"></i>
                  </div>';
            echo '<ul>';
            foreach ($children as $child) {
                echo '<li>';
                displayNode($conn, $child);
                echo '</li>';
            }
            echo '</ul>';
        }
    }
}
?>