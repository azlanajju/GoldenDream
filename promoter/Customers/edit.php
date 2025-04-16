<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "customers";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$message = '';
$messageType = '';
$showNotification = false;

// Get customer ID from URL
$customerId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$customerId) {
    header("Location: index.php");
    exit();
}

// Get customer details
try {
    $stmt = $conn->prepare("
        SELECT * FROM Customers 
        WHERE CustomerID = ? AND PromoterID = ?
    ");
    $stmt->execute([$customerId, $_SESSION['promoter_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $message = "Error fetching customer data";
    $messageType = "error";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    
    // Bank details
    $bankAccountName = $_POST['bank_account_name'] ?? '';
    $bankAccountNumber = $_POST['bank_account_number'] ?? '';
    $ifscCode = $_POST['ifsc_code'] ?? '';
    $bankName = $_POST['bank_name'] ?? '';
    
    // Validate required fields
    if (empty($name) || empty($contact)) {
        $message = "Name and contact number are required";
        $messageType = "error";
        $showNotification = true;
    } else {
        try {
            // Check if contact number is already taken by another customer
            $stmt = $conn->prepare("
                SELECT CustomerID FROM Customers 
                WHERE Contact = ? AND CustomerID != ?
            ");
            $stmt->execute([$contact, $customerId]);
            if ($stmt->rowCount() > 0) {
                $message = "Contact number is already registered with another customer";
                $messageType = "error";
                $showNotification = true;
            } else {
                // Check if email is already taken by another customer
                if (!empty($email)) {
                    $stmt = $conn->prepare("
                        SELECT CustomerID FROM Customers 
                        WHERE Email = ? AND CustomerID != ?
                    ");
                    $stmt->execute([$email, $customerId]);
                    if ($stmt->rowCount() > 0) {
                        $message = "Email is already registered with another customer";
                        $messageType = "error";
                        $showNotification = true;
                    } else {
                        // Update customer details
                        $stmt = $conn->prepare("
                            UPDATE Customers 
                            SET Name = ?, Contact = ?, Email = ?, Address = ?, 
                                DateOfBirth = ?, Gender = ?, Status = ?,
                                BankAccountName = ?, BankAccountNumber = ?, 
                                IFSCCode = ?, BankName = ?,
                                UpdatedAt = CURRENT_TIMESTAMP
                            WHERE CustomerID = ? AND PromoterID = ?
                        ");
                        $stmt->execute([
                            $name, $contact, $email, $address, 
                            $dateOfBirth, $gender, $status,
                            $bankAccountName, $bankAccountNumber, 
                            $ifscCode, $bankName,
                            $customerId, $_SESSION['promoter_id']
                        ]);
                        
                        $message = "Customer details updated successfully";
                        $messageType = "success";
                        $showNotification = true;
                        
                        // Refresh customer data
                        $stmt = $conn->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
                        $stmt->execute([$customerId]);
                        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                } else {
                    // Update customer details without email check
                    $stmt = $conn->prepare("
                        UPDATE Customers 
                        SET Name = ?, Contact = ?, Email = ?, Address = ?, 
                            DateOfBirth = ?, Gender = ?, Status = ?,
                            BankAccountName = ?, BankAccountNumber = ?, 
                            IFSCCode = ?, BankName = ?,
                            UpdatedAt = CURRENT_TIMESTAMP
                        WHERE CustomerID = ? AND PromoterID = ?
                    ");
                    $stmt->execute([
                        $name, $contact, $email, $address, 
                        $dateOfBirth, $gender, $status,
                        $bankAccountName, $bankAccountNumber, 
                        $ifscCode, $bankName,
                        $customerId, $_SESSION['promoter_id']
                    ]);
                    
                    $message = "Customer details updated successfully";
                    $messageType = "success";
                    $showNotification = true;
                    
                    // Refresh customer data
                    $stmt = $conn->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
                    $stmt->execute([$customerId]);
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        } catch (PDOException $e) {
            $message = "Error updating customer details: " . $e->getMessage();
            $messageType = "error";
            $showNotification = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --warning-color: #f1c40f;
            --border-color: #e0e0e0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Poppins', sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--topbar-height) + 20px) !important;
        }

        .main-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            max-width: 800px;
            margin: 0 auto;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-icon i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .section-info h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .section-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 106, 80, 0.3);
        }

        .btn-secondary {
            background: var(--bg-light);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .form-text {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .form-section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section-title i {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .main-content {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions button,
            .form-actions a {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="main-content">
            <?php if ($showNotification && $message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div class="section-info">
                        <h2>Edit Customer</h2>
                        <p>Update customer information</p>
                    </div>
                </div>
                <a href="view.php?id=<?php echo $customer['CustomerID']; ?>" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Details
                </a>
            </div>

            <form method="POST" action="">
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-user"></i>
                        Personal Information
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name" class="form-label">Full Name <span style="color: var(--error-color);">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($customer['Name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="contact" class="form-label">Contact Number <span style="color: var(--error-color);">*</span></label>
                            <input type="text" id="contact" name="contact" class="form-control" value="<?php echo htmlspecialchars($customer['Contact']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($customer['Email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?php echo $customer['DateOfBirth'] ? date('Y-m-d', strtotime($customer['DateOfBirth'])) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender" class="form-label">Gender</label>
                            <select id="gender" name="gender" class="form-control">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo $customer['Gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $customer['Gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo $customer['Gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="Active" <?php echo $customer['Status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $customer['Status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Suspended" <?php echo $customer['Status'] === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address" class="form-label">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($customer['Address'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-university"></i>
                        Bank Details
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bank_account_name" class="form-label">Account Holder Name</label>
                            <input type="text" id="bank_account_name" name="bank_account_name" class="form-control" value="<?php echo htmlspecialchars($customer['BankAccountName'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="bank_account_number" class="form-label">Account Number</label>
                            <input type="text" id="bank_account_number" name="bank_account_number" class="form-control" value="<?php echo htmlspecialchars($customer['BankAccountNumber'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ifsc_code" class="form-label">IFSC Code</label>
                            <input type="text" id="ifsc_code" name="ifsc_code" class="form-control" value="<?php echo htmlspecialchars($customer['IFSCCode'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="bank_name" class="form-label">Bank Name</label>
                            <input type="text" id="bank_name" name="bank_name" class="form-control" value="<?php echo htmlspecialchars($customer['BankName'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="view.php?id=<?php echo $customer['CustomerID']; ?>" class="btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Ensure proper topbar integration
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content-wrapper');

            function adjustContent() {
                if (sidebar.classList.contains('collapsed')) {
                    content.style.marginLeft = 'var(--sidebar-collapsed-width)';
                } else {
                    content.style.marginLeft = 'var(--sidebar-width)';
                }
            }

            // Initial adjustment
            adjustContent();

            // Watch for sidebar changes
            const observer = new MutationObserver(adjustContent);
            observer.observe(sidebar, { attributes: true });
        });
    </script>
</body>
</html> 