<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path="../";
$current_page="schemes";
// Get user data and validate session
$userData = checkSession();

// Get scheme ID from URL
$schemeId = isset($_GET['scheme_id']) ? (int)$_GET['scheme_id'] : 0;

if (!$schemeId) {
    header('Location: schemes.php');
    exit;
}

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get scheme details
$stmt = $db->prepare("SELECT * FROM Schemes WHERE SchemeID = ? AND Status = 'Active'");
$stmt->execute([$schemeId]);
$scheme = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$scheme) {
    header('Location: schemes.php');
    exit;
}

// Get installments for this scheme
$stmt = $db->prepare("
    SELECT * FROM Installments 
    WHERE SchemeID = ? AND Status = 'Active'
    ORDER BY InstallmentNumber ASC
");
$stmt->execute([$schemeId]);
$installments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheme Benefits - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
        }

        .benefits-container {
            padding: 20px;
        }

        .benefits-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .scheme-header {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .installment-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .installment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .installment-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .installment-number {
            background: #4a90e2;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 10px;
            display: inline-block;
        }

        .draw-date {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .benefits-list {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }

        .benefits-list li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .benefits-list li:last-child {
            border-bottom: none;
        }

        .benefits-list i {
            color: #4a90e2;
            margin-right: 10px;
        }

        .btn-back {
            background: #4a90e2;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #357abd;
            color: white;
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="benefits-container">
            <div class="container">
                <div class="scheme-header text-center">
                    <h2><i class="fas fa-gift"></i> <?php echo htmlspecialchars($scheme['SchemeName']); ?> Benefits</h2>
                    <p class="mb-0">Explore the exciting benefits and installments of this scheme</p>
                </div>

                <?php if ($scheme['Benefits']): ?>
                    <div class="benefits-card mb-4">
                        <h4><i class="fas fa-star"></i> General Benefits</h4>
                        <p><?php echo htmlspecialchars($scheme['Benefits']); ?></p>
                    </div>
                <?php endif; ?>

                <h4 class="mb-4"><i class="fas fa-calendar-check"></i> Installments</h4>
                <div class="row">
                    <?php foreach ($installments as $installment): ?>
                        <div class="col-md-6 mb-4">
                            <div class="installment-card">
                                <?php if ($installment['ImageURL']): ?>
                                    <img src="<?php echo htmlspecialchars($installment['ImageURL']); ?>"
                                        alt="Installment <?php echo $installment['InstallmentNumber']; ?>"
                                        class="installment-image">
                                <?php endif; ?>

                                <div class="installment-number">
                                    Installment <?php echo $installment['InstallmentNumber']; ?>
                                </div>

                                <div class="draw-date mb-3">
                                    <i class="fas fa-calendar"></i>
                                    Draw Date: <?php echo date('M d, Y', strtotime($installment['DrawDate'])); ?>
                                </div>

                                <div class="amount mb-3">
                                    <strong>Amount:</strong> ₹<?php echo number_format($installment['Amount'], 2); ?>
                                </div>

                                <?php if ($installment['Benefits']): ?>
                                    <div class="benefits-list">
                                        <?php
                                        $benefits = explode("\n", $installment['Benefits']);
                                        foreach ($benefits as $benefit):
                                        ?>
                                            <li>
                                                <i class="fas fa-check-circle"></i>
                                                <?php echo htmlspecialchars($benefit); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-center mt-4">
                    <a href="schemes.php" class="btn btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Schemes
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>