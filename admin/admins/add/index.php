<?php
session_start();
// Check if user is logged in, redirect if not
// if (!isset($_SESSION['admin_id'])) {
//     header("Location: ../../login.php");
//     exit();
// }

// Check if the logged-in admin has permission to add admins
// Superadmin role check
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] !== 'SuperAdmin') {
    // Redirect to dashboard with error message
    $_SESSION['error_message'] = "You don't have permission to add new admins.";
    header("Location: ../../dashboard/index.php");
    exit();
}

$menuPath = "../../";
$currentPage = "admins";

// Database connection
require_once("../../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Define variables and set to empty values
$name = $email = $role = $password = $confirmPassword = '';
$nameErr = $emailErr = $roleErr = $passwordErr = $confirmPasswordErr = '';

// Form submission handling
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $formValid = true;

    // Validate name
    if (empty($_POST["name"])) {
        $nameErr = "Name is required";
        $formValid = false;
    } else {
        $name = trim($_POST["name"]);
        // Check if name only contains letters and whitespace
        if (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
            $nameErr = "Only letters and white space allowed";
            $formValid = false;
        }
    }

    // Validate email
    if (empty($_POST["email"])) {
        $emailErr = "Email is required";
        $formValid = false;
    } else {
        $email = trim($_POST["email"]);
        // Check if email is valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailErr = "Invalid email format";
            $formValid = false;
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM Admins WHERE Email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $emailErr = "Email already in use";
                $formValid = false;
            }
        }
    }

    // Validate role
    if (empty($_POST["role"])) {
        $roleErr = "Role is required";
        $formValid = false;
    } else {
        $role = $_POST["role"];
        // Check if role is valid
        if (!in_array($role, ['SuperAdmin', 'Verifier'])) {
            $roleErr = "Invalid role selected";
            $formValid = false;
        }
    }

    // Validate password
    if (empty($_POST["password"])) {
        $passwordErr = "Password is required";
        $formValid = false;
    } else {
        $password = $_POST["password"];
        // Check if password is strong enough
        if (strlen($password) < 8) {
            $passwordErr = "Password must be at least 8 characters long";
            $formValid = false;
        }
    }

    // Validate confirm password
    if (empty($_POST["confirm_password"])) {
        $confirmPasswordErr = "Please confirm your password";
        $formValid = false;
    } else {
        $confirmPassword = $_POST["confirm_password"];
        // Check if passwords match
        if ($password !== $confirmPassword) {
            $confirmPasswordErr = "Passwords do not match";
            $formValid = false;
        }
    }

    // If form is valid, add admin to database
    if ($formValid) {
        try {
            // Begin transaction
            $conn->beginTransaction();

            // Hash the password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert the new admin
            $stmt = $conn->prepare("INSERT INTO Admins (Name, Email, PasswordHash, Role, Status) VALUES (?, ?, ?, ?, 'Active')");
            $stmt->execute([$name, $email, $passwordHash, $role]);

            // Log the activity
            $action = "Added new admin: " . $name;
            $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
            $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

            // Commit the transaction
            $conn->commit();

            $_SESSION['success_message'] = "Admin added successfully.";
            header("Location: ../index.php");
            exit();
        } catch (PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $_SESSION['error_message'] = "Failed to add admin: " . $e->getMessage();
        }
    }
}

// Include header and sidebar
include("../../components/sidebar.php");
include("../../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #3a7bd5;
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
            outline: none;
        }

        .error-text {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3a7bd5, #00d2ff);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2c60a9, #00bae0);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f5f7fa;
            color: #2c3e50;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }

        .password-strength {
            height: 5px;
            margin-top: 8px;
            border-radius: 3px;
            transition: all 0.3s ease;
            background: #e0e0e0;
        }

        .strength-weak {
            background: #e74c3c;
            width: 33.3%;
        }

        .strength-medium {
            background: #f39c12;
            width: 66.6%;
        }

        .strength-strong {
            background: #2ecc71;
            width: 100%;
        }

        .strength-text {
            font-size: 12px;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Add New Admin</h1>
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Admins
            </a>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header">
                <h2>Admin Information</h2>
            </div>

            <div class="card-body">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>">
                        <?php if (!empty($nameErr)): ?>
                            <div class="error-text"><?php echo $nameErr; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>">
                        <?php if (!empty($emailErr)): ?>
                            <div class="error-text"><?php echo $emailErr; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" class="form-control">
                            <option value="">Select a role</option>
                            <option value="SuperAdmin" <?php if ($role === 'SuperAdmin') echo 'selected'; ?>>Super Admin</option>
                            <option value="Verifier" <?php if ($role === 'Verifier') echo 'selected'; ?>>Verifier</option>
                        </select>
                        <?php if (!empty($roleErr)): ?>
                            <div class="error-text"><?php echo $roleErr; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-field">
                            <input type="password" id="password" name="password" class="form-control">
                            <i class="password-toggle fas fa-eye" onclick="togglePassword('password')"></i>
                        </div>
                        <div class="password-strength" id="password-strength"></div>
                        <div class="strength-text" id="strength-text"></div>
                        <?php if (!empty($passwordErr)): ?>
                            <div class="error-text"><?php echo $passwordErr; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="password-field">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                            <i class="password-toggle fas fa-eye" onclick="togglePassword('confirm_password')"></i>
                        </div>
                        <?php if (!empty($confirmPasswordErr)): ?>
                            <div class="error-text"><?php echo $confirmPasswordErr; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Admin
                        </button>
                        <a href="../index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const icon = passwordField.nextElementSibling;

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Password strength meter
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('password-strength');
        const strengthText = document.getElementById('strength-text');

        passwordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            let strength = 0;

            // Calculate password strength
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
            if (password.match(/\d/)) strength += 1;
            if (password.match(/[^a-zA-Z\d]/)) strength += 1;

            // Update strength meter
            strengthBar.className = 'password-strength';

            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.textContent = '';
            } else if (strength < 2) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#e74c3c';
            } else if (strength < 4) {
                strengthBar.classList.add('strength-medium');
                strengthText.textContent = 'Medium strength password';
                strengthText.style.color = '#f39c12';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = 'Strong password';
                strengthText.style.color = '#2ecc71';
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>

</html>