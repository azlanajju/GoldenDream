<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "schemes";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Check if scheme ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No scheme specified.";
    header("Location: index.php");
    exit();
}

$schemeId = intval($_GET['id']);

// Handle form submission for updating scheme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_scheme'])) {
    $schemeName = trim($_POST['scheme_name']);
    $schemeType = trim($_POST['scheme_type']);
    $schemeAmount = floatval($_POST['scheme_amount']);
    $schemeDuration = intval($_POST['scheme_duration']);
    $installmentFrequency = trim($_POST['installment_frequency']);
    $installmentAmount = floatval($_POST['installment_amount']);
    $monthlyAmount = floatval($_POST['monthly_amount']);
    $minSubscribers = intval($_POST['min_subscribers']);
    $maxSubscribers = intval($_POST['max_subscribers']);
    $lateFee = floatval($_POST['late_fee']);
    $description = trim($_POST['description']);

    // Validate inputs
    $errors = [];

    if (empty($schemeName)) {
        $errors[] = "Scheme name is required.";
    }

    if (empty($schemeType)) {
        $errors[] = "Scheme type is required.";
    }

    if ($schemeAmount <= 0) {
        $errors[] = "Scheme amount must be greater than zero.";
    }

    if ($schemeDuration <= 0) {
        $errors[] = "Scheme duration must be greater than zero.";
    }

    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->beginTransaction();

            // Update scheme
            $stmt = $conn->prepare("
                UPDATE Schemes 
                SET SchemeName = ?, 
                    SchemeType = ?, 
                    SchemeAmount = ?, 
                    SchemeDuration = ?, 
                    InstallmentFrequency = ?, 
                    InstallmentAmount = ?, 
                    MonthlyAmount = ?, 
                    MinSubscribers = ?, 
                    MaxSubscribers = ?, 
                    LateFee = ?, 
                    Description = ?,
                    UpdatedAt = NOW()
                WHERE SchemeID = ?
            ");

            $stmt->execute([
                $schemeName,
                $schemeType,
                $schemeAmount,
                $schemeDuration,
                $installmentFrequency,
                $installmentAmount,
                $monthlyAmount,
                $minSubscribers,
                $maxSubscribers,
                $lateFee,
                $description,
                $schemeId
            ]);

            // Log the activity
            $stmt = $conn->prepare("
                INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
                VALUES (?, 'Admin', ?, ?)
            ");
            $stmt->execute([
                $_SESSION['admin_id'],
                "Updated scheme: $schemeName",
                $_SERVER['REMOTE_ADDR']
            ]);

            $conn->commit();
            $_SESSION['success_message'] = "Scheme updated successfully.";
            header("Location: view.php?id=$schemeId");
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get scheme details
try {
    $stmt = $conn->prepare("SELECT * FROM Schemes WHERE SchemeID = ?");
    $stmt->execute([$schemeId]);
    $scheme = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$scheme) {
        $_SESSION['error_message'] = "Scheme not found.";
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Scheme - <?php echo htmlspecialchars($scheme['SchemeName']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eaeaea;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        .text-muted {
            color: #7f8c8d;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #2573a7);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #ecf0f1;
            color: #2c3e50;
        }

        .btn-secondary:hover {
            background: #dde4e6;
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            border-left: 4px solid #e74c3c;
            color: #c0392b;
        }

        .alert-danger ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Edit Scheme</h1>
            <a href="view.php?id=<?php echo $schemeId; ?>" class="action-btn edit-btn">
                <i class="fas fa-arrow-left"></i> Back to View
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <h2 class="form-title">Edit Scheme Details</h2>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="scheme_name">Scheme Name *</label>
                        <input type="text" id="scheme_name" name="scheme_name" class="form-control"
                            value="<?php echo htmlspecialchars($scheme['SchemeName']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="scheme_type">Scheme Type *</label>
                        <select id="scheme_type" name="scheme_type" class="form-control" required>
                            <option value="">Select Scheme Type</option>
                            <option value="Gold" <?php echo $scheme['SchemeType'] === 'Gold' ? 'selected' : ''; ?>>Gold</option>
                            <option value="Silver" <?php echo $scheme['SchemeType'] === 'Silver' ? 'selected' : ''; ?>>Silver</option>
                            <option value="Diamond" <?php echo $scheme['SchemeType'] === 'Diamond' ? 'selected' : ''; ?>>Diamond</option>
                            <option value="Platinum" <?php echo $scheme['SchemeType'] === 'Platinum' ? 'selected' : ''; ?>>Platinum</option>
                            <option value="General" <?php echo $scheme['SchemeType'] === 'General' ? 'selected' : ''; ?>>General</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="scheme_amount">Scheme Amount (₹) *</label>
                        <input type="number" id="scheme_amount" name="scheme_amount" class="form-control"
                            value="<?php echo $scheme['SchemeAmount']; ?>" min="1" step="0.01" required>
                        <small class="text-muted">Total value of the scheme</small>
                    </div>

                    <div class="form-group">
                        <label for="scheme_duration">Scheme Duration (Months) *</label>
                        <input type="number" id="scheme_duration" name="scheme_duration" class="form-control"
                            value="<?php echo $scheme['SchemeDuration']; ?>" min="1" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="installment_frequency">Installment Frequency *</label>
                        <select id="installment_frequency" name="installment_frequency" class="form-control" required>
                            <option value="Monthly" <?php echo $scheme['InstallmentFrequency'] === 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="Quarterly" <?php echo $scheme['InstallmentFrequency'] === 'Quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                            <option value="Half-Yearly" <?php echo $scheme['InstallmentFrequency'] === 'Half-Yearly' ? 'selected' : ''; ?>>Half-Yearly</option>
                            <option value="Weekly" <?php echo $scheme['InstallmentFrequency'] === 'Weekly' ? 'selected' : ''; ?>>Weekly</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="installment_amount">Installment Amount (₹) *</label>
                        <input type="number" id="installment_amount" name="installment_amount" class="form-control"
                            value="<?php echo $scheme['InstallmentAmount']; ?>" min="1" step="0.01" required>
                        <small class="text-muted">Amount to be paid per installment</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="monthly_amount">Monthly Amount (₹) *</label>
                        <input type="number" id="monthly_amount" name="monthly_amount" class="form-control"
                            value="<?php echo $scheme['MonthlyAmount']; ?>" min="1" step="0.01" required>
                        <small class="text-muted">Monthly contribution amount</small>
                    </div>

                    <div class="form-group">
                        <label for="late_fee">Late Fee (₹)</label>
                        <input type="number" id="late_fee" name="late_fee" class="form-control"
                            value="<?php echo $scheme['LateFee']; ?>" min="0" step="0.01">
                        <small class="text-muted">Fee charged for late payments</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="min_subscribers">Minimum Subscribers</label>
                        <input type="number" id="min_subscribers" name="min_subscribers" class="form-control"
                            value="<?php echo $scheme['MinSubscribers']; ?>" min="0">
                    </div>

                    <div class="form-group">
                        <label for="max_subscribers">Maximum Subscribers</label>
                        <input type="number" id="max_subscribers" name="max_subscribers" class="form-control"
                            value="<?php echo $scheme['MaxSubscribers']; ?>" min="0">
                        <small class="text-muted">0 means unlimited</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($scheme['Description']); ?></textarea>
                    <small class="text-muted">Provide details about the scheme, benefits, terms, etc.</small>
                </div>

                <div class="btn-group">
                    <button type="submit" name="update_scheme" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Scheme
                    </button>
                    <a href="view.php?id=<?php echo $schemeId; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-calculate monthly amount based on scheme amount and duration
        document.addEventListener('DOMContentLoaded', function() {
            const schemeAmountInput = document.getElementById('scheme_amount');
            const schemeDurationInput = document.getElementById('scheme_duration');
            const monthlyAmountInput = document.getElementById('monthly_amount');
            const installmentFrequencySelect = document.getElementById('installment_frequency');
            const installmentAmountInput = document.getElementById('installment_amount');

            function calculateMonthlyAmount() {
                const schemeAmount = parseFloat(schemeAmountInput.value) || 0;
                const schemeDuration = parseInt(schemeDurationInput.value) || 1;

                if (schemeAmount > 0 && schemeDuration > 0) {
                    const monthlyAmount = schemeAmount / schemeDuration;
                    monthlyAmountInput.value = monthlyAmount.toFixed(2);

                    // Also update installment amount based on frequency
                    calculateInstallmentAmount();
                }
            }

            function calculateInstallmentAmount() {
                const monthlyAmount = parseFloat(monthlyAmountInput.value) || 0;
                const frequency = installmentFrequencySelect.value;

                let installmentAmount = monthlyAmount;

                if (frequency === 'Quarterly') {
                    installmentAmount = monthlyAmount * 3;
                } else if (frequency === 'Half-Yearly') {
                    installmentAmount = monthlyAmount * 6;
                } else if (frequency === 'Weekly') {
                    installmentAmount = monthlyAmount / 4; // Approximate weekly amount
                }

                installmentAmountInput.value = installmentAmount.toFixed(2);
            }

            schemeAmountInput.addEventListener('input', calculateMonthlyAmount);
            schemeDurationInput.addEventListener('input', calculateMonthlyAmount);
            installmentFrequencySelect.addEventListener('change', calculateInstallmentAmount);
            monthlyAmountInput.addEventListener('input', calculateInstallmentAmount);
        });
    </script>
</body>

</html>