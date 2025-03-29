<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path="../";
$current_page="profile";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $bankName = trim($_POST['bank_name']);
        $accountName = trim($_POST['account_name']);
        $accountNumber = trim($_POST['account_number']);
        $ifscCode = trim($_POST['ifsc_code']);

        if (empty($bankName) || empty($accountName) || empty($accountNumber) || empty($ifscCode)) {
            throw new Exception('All fields are required');
        }

        // Validate account number (assuming 9-18 digits)
        if (!preg_match('/^[0-9]{9,18}$/', $accountNumber)) {
            throw new Exception('Invalid account number format');
        }

        // Validate IFSC code (11 characters, alphanumeric)
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', strtoupper($ifscCode))) {
            throw new Exception('Invalid IFSC code format');
        }

        // Update bank information
        $stmt = $db->prepare("
            UPDATE Customers 
            SET BankName = ?, BankAccountName = ?, BankAccountNumber = ?, IFSCCode = ?
            WHERE CustomerID = ?
        ");

        $stmt->execute([
            $bankName,
            $accountName,
            $accountNumber,
            strtoupper($ifscCode),
            $userData['customer_id']
        ]);

        $success_message = 'Bank details updated successfully';

        // Refresh customer data
        $stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
        $stmt->execute([$userData['customer_id']]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bank Details - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
        }

        .edit-bank-container {
            padding: 20px;
        }

        .bank-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }

        .btn-save {
            background: #4a90e2;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            background: #357abd;
            color: white;
        }

        .alert {
            border-radius: 10px;
        }

        .bank-icon {
            font-size: 2rem;
            color: #4a90e2;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <?php include 'c_includes/sidebar.php'; ?>
    <?php include 'c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="edit-bank-container">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="bank-card">
                            <div class="text-center">
                                <i class="fas fa-university bank-icon"></i>
                                <h4 class="mb-4">Edit Bank Details</h4>
                            </div>

                            <?php if ($success_message): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo $success_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($error_message): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo $error_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="mb-3">
                                    <label for="bank_name" class="form-label">Bank Name</label>
                                    <input type="text" class="form-control" id="bank_name" name="bank_name"
                                        value="<?php echo htmlspecialchars($customer['BankName']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="account_name" class="form-label">Account Holder Name</label>
                                    <input type="text" class="form-control" id="account_name" name="account_name"
                                        value="<?php echo htmlspecialchars($customer['BankAccountName']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="account_number" class="form-label">Account Number</label>
                                    <input type="text" class="form-control" id="account_number" name="account_number"
                                        value="<?php echo htmlspecialchars($customer['BankAccountNumber']); ?>" required>
                                    <div class="form-text">Enter 9-18 digit account number</div>
                                </div>

                                <div class="mb-3">
                                    <label for="ifsc_code" class="form-label">IFSC Code</label>
                                    <input type="text" class="form-control" id="ifsc_code" name="ifsc_code"
                                        value="<?php echo htmlspecialchars($customer['IFSCCode']); ?>" required>
                                    <div class="form-text">Enter 11 character IFSC code</div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="profile.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to Profile
                                    </a>
                                    <button type="submit" class="btn btn-save">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Format account number input
        document.getElementById('account_number').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Format IFSC code input
        document.getElementById('ifsc_code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    </script>
</body>

</html>