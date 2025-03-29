<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path="../";
$current_page="winners";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all winners with prize details
$stmt = $db->prepare("
    SELECT 
        w.*,
        a.Name as AdminName,
        CASE 
            WHEN w.Status = 'Claimed' THEN 'success'
            WHEN w.Status = 'Pending' THEN 'warning'
            ELSE 'danger'
        END as status_color
    FROM Winners w
    LEFT JOIN Admins a ON w.AdminID = a.AdminID
    WHERE w.UserID = ? AND w.UserType = 'Customer'
    ORDER BY w.WinningDate DESC
");
$stmt->execute([$userData['customer_id']]);
$winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get winner statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_prizes,
        SUM(CASE WHEN Status = 'Claimed' THEN 1 ELSE 0 END) as claimed_prizes,
        SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as pending_prizes,
        SUM(CASE WHEN Status = 'Expired' THEN 1 ELSE 0 END) as expired_prizes
    FROM Winners
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
    <title>My Prizes - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
        }

        .winners-container {
            padding: 20px;
        }

        .winners-header {
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

        .prize-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .prize-card:hover {
            transform: translateY(-5px);
        }

        .prize-type {
            color: #4a90e2;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .prize-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #4a90e2;
        }

        .prize-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 15px 0;
        }

        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }

        .detail-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .detail-value {
            font-weight: bold;
            color: #2c3e50;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .status-claimed {
            background: #28a745;
            color: white;
        }

        .status-pending {
            background: #ffc107;
            color: #000;
        }

        .status-expired {
            background: #dc3545;
            color: white;
        }

        .btn-claim {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-claim:hover {
            background: #218838;
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

        .prize-remarks {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            font-style: italic;
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="winners-container">
            <div class="container">
                <div class="winners-header text-center">
                    <h2><i class="fas fa-trophy"></i> My Prizes</h2>
                    <p class="mb-0">View all your prizes and rewards</p>
                </div>

                <?php if (empty($winners)): ?>
                    <div class="empty-state">
                        <i class="fas fa-gift"></i>
                        <h3>No Prizes Found</h3>
                        <p>You haven't won any prizes yet.</p>
                        <a href="schemes.php" class="btn btn-primary">
                            <i class="fas fa-gem"></i> Explore Schemes
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['total_prizes']; ?></div>
                                    <div class="stat-label">Total Prizes</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['claimed_prizes']; ?></div>
                                    <div class="stat-label">Claimed Prizes</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['pending_prizes']; ?></div>
                                    <div class="stat-label">Pending Prizes</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['expired_prizes']; ?></div>
                                    <div class="stat-label">Expired Prizes</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($winners as $winner): ?>
                        <div class="prize-card">
                            <div class="text-center">
                                <?php
                                $icon = match ($winner['PrizeType']) {
                                    'Surprise Prize' => 'fas fa-gift',
                                    'Bumper Prize' => 'fas fa-star',
                                    'Gift Hamper' => 'fas fa-box',
                                    'Education Scholarship' => 'fas fa-graduation-cap',
                                    default => 'fas fa-trophy'
                                };
                                ?>
                                <div class="prize-icon">
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>
                                <div class="prize-type">
                                    <?php echo htmlspecialchars($winner['PrizeType']); ?>
                                </div>
                            </div>

                            <div class="prize-details">
                                <div class="detail-item">
                                    <div class="detail-label">Winning Date</div>
                                    <div class="detail-value">
                                        <?php echo date('M d, Y', strtotime($winner['WinningDate'])); ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Status</div>
                                    <div class="detail-value">
                                        <span class="status-badge status-<?php echo strtolower($winner['Status']); ?>">
                                            <?php echo $winner['Status']; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if ($winner['AdminName']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Processed By</div>
                                        <div class="detail-value">
                                            <?php echo htmlspecialchars($winner['AdminName']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($winner['Remarks']): ?>
                                <div class="prize-remarks">
                                    <i class="fas fa-comment"></i> <?php echo htmlspecialchars($winner['Remarks']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($winner['Status'] === 'Pending'): ?>
                                <div class="text-center mt-3">
                                    <a href="claim_prize.php?winner_id=<?php echo $winner['WinnerID']; ?>"
                                        class="btn btn-claim">
                                        <i class="fas fa-check-circle"></i> Claim Prize
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>