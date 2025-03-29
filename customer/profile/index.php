<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path="../";
$current_page="profile";
// Get user data and validate session
$userData = checkSession();

// Get additional customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
        }

        .profile-container {
            padding: 20px;
        }

        .profile-header {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
            margin-bottom: 15px;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .profile-section {
            margin-bottom: 30px;
        }

        .profile-section h4 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .info-label {
            color: #6c757d;
            font-weight: 500;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 600;
        }

        .btn-edit {
            background: #4a90e2;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-edit:hover {
            background: #357abd;
            color: white;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .status-active {
            background: #e3fcef;
            color: #00a854;
        }

        .status-inactive {
            background: #fff1f0;
            color: #f5222d;
        }

        .status-suspended {
            background: #fff7e6;
            color: #fa8c16;
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="profile-container">
            <div class="container">
                <div class="profile-header text-center">
                    <img src="<?php echo $customer['ProfileImageURL'] ?: 'assets/images/default-avatar.png'; ?>"
                        alt="Profile" class="profile-avatar">
                    <h2><?php echo htmlspecialchars($customer['Name']); ?></h2>
                    <p class="mb-0"><?php echo htmlspecialchars($customer['CustomerUniqueID']); ?></p>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <!-- Personal Information -->
                        <div class="profile-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4><i class="fas fa-user"></i> Personal Information</h4>
                                <a href="edit_profile.php" class="btn btn-edit">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </a>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['Name']); ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Phone Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['Contact']); ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['Email'] ?: 'Not provided'); ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Account Status</div>
                                    <div>
                                        <span class="status-badge status-<?php echo strtolower($customer['Status']); ?>">
                                            <?php echo htmlspecialchars($customer['Status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Information -->
                        <div class="profile-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4><i class="fas fa-university"></i> Bank Information</h4>
                                <a href="edit_bank.php" class="btn btn-edit">
                                    <i class="fas fa-edit"></i> Edit Bank Details
                                </a>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Bank Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['BankName'] ?: 'Not provided'); ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Account Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['BankAccountName'] ?: 'Not provided'); ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Account Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['BankAccountNumber'] ?: 'Not provided'); ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">IFSC Code</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['IFSCCode'] ?: 'Not provided'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Account Security -->
                        <div class="profile-card">
                            <h4><i class="fas fa-shield-alt"></i> Account Security</h4>
                            <div class="d-grid gap-2">
                                <a href="change_password.php" class="btn btn-outline-primary">
                                    <i class="fas fa-key"></i> Change Password
                                </a>
                                <a href="two_factor.php" class="btn btn-outline-primary">
                                    <i class="fas fa-lock"></i> Two-Factor Authentication
                                </a>
                            </div>
                        </div>

                        <!-- Account Activity -->
                        <div class="profile-card">
                            <h4><i class="fas fa-history"></i> Account Activity</h4>
                            <div class="mb-3">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo date('M d, Y', strtotime($customer['CreatedAt'])); ?></div>
                            </div>
                            <div class="mb-3">
                                <div class="info-label">Last Updated</div>
                                <div class="info-value"><?php echo date('M d, Y', strtotime($customer['UpdatedAt'])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>