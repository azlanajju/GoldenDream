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
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);

        if (empty($name) || empty($phone)) {
            throw new Exception('Name and phone number are required');
        }

        // Validate phone number format
        if (!preg_match('/^[0-9]{10}$/', $phone)) {
            throw new Exception('Invalid phone number format');
        }

        // Validate email if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Check if phone number is already taken by another user
        $stmt = $db->prepare("SELECT CustomerID FROM Customers WHERE Contact = ? AND CustomerID != ?");
        $stmt->execute([$phone, $userData['customer_id']]);
        if ($stmt->rowCount() > 0) {
            throw new Exception('Phone number already registered');
        }

        // Handle profile image upload
        $profileImageUrl = $customer['ProfileImageURL'];
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($_FILES['profile_image']['type'], $allowedTypes)) {
                throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed');
            }

            if ($_FILES['profile_image']['size'] > $maxSize) {
                throw new Exception('File size too large. Maximum size is 5MB');
            }

            $uploadDir = 'uploads/profile_images/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileExtension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $fileName = $userData['customer_id'] . '_' . time() . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
                // Delete old profile image if exists
                if ($profileImageUrl && file_exists($profileImageUrl)) {
                    unlink($profileImageUrl);
                }
                $profileImageUrl = $targetPath;
            } else {
                throw new Exception('Failed to upload profile image');
            }
        }

        // Update customer information
        $stmt = $db->prepare("
            UPDATE Customers 
            SET Name = ?, Email = ?, Contact = ?, Address = ?, ProfileImageURL = ?
            WHERE CustomerID = ?
        ");

        $stmt->execute([$name, $email, $phone, $address, $profileImageUrl, $userData['customer_id']]);

        $success_message = 'Profile updated successfully';

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
    <title>Edit Profile - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
        }

        .edit-profile-container {
            padding: 20px;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .profile-image-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #4a90e2;
            margin-bottom: 10px;
        }

        .image-upload {
            position: relative;
            display: inline-block;
        }

        .image-upload input[type="file"] {
            display: none;
        }

        .image-upload label {
            background: #4a90e2;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .image-upload label:hover {
            background: #357abd;
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
    </style>
</head>

<body>
    <?php include 'c_includes/sidebar.php'; ?>
    <?php include 'c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="edit-profile-container">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="profile-card">
                            <h4 class="mb-4"><i class="fas fa-user-edit"></i> Edit Profile</h4>

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

                            <form method="POST" enctype="multipart/form-data">
                                <div class="profile-image-container">
                                    <img src="<?php echo $customer['ProfileImageURL'] ?: 'assets/images/default-avatar.png'; ?>"
                                        alt="Profile" class="profile-image" id="profilePreview">
                                    <div class="image-upload">
                                        <input type="file" name="profile_image" id="profileImage" accept="image/*">
                                        <label for="profileImage">
                                            <i class="fas fa-camera"></i> Change Photo
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                        value="<?php echo htmlspecialchars($customer['Name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo htmlspecialchars($customer['Email']); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                        value="<?php echo htmlspecialchars($customer['Contact']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($customer['Address']); ?></textarea>
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
        // Preview profile image before upload
        document.getElementById('profileImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>

</html>