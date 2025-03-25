<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .topbar {
        position: fixed;
        top: 0;
        right: 0;
        left: var(--sidebar-width);
        height: 70px;
        background: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        z-index: 900;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 30px;
        transition: left var(--transition-speed) ease;
    }

    .content-wrapper {
        padding-top: 90px !important;
        /* Adjusted to account for topbar */
    }

    .sidebar.collapsed+.topbar {
        left: var(--sidebar-collapsed-width);
    }

    .search-container {
        position: relative;
        width: 40%;
    }

    .search-container input {
        width: 100%;
        padding: 10px 20px;
        padding-left: 45px;
        border-radius: 25px;
        border: 1px solid #e0e0e0;
        font-size: 14px;
        background-color: #f5f5f5;
        transition: all 0.3s ease;
    }

    .search-container input:focus {
        background-color: white;
        box-shadow: 0 0 10px rgba(58, 123, 213, 0.1);
        border-color: var(--primary-color);
        outline: none;
    }

    .search-container i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #999;
        font-size: 16px;
    }

    .topbar-actions {
        display: flex;
        align-items: center;
    }

    .action-icon {
        position: relative;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #555;
        margin-left: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
        background-color: #f5f5f5;
    }

    .action-icon:hover {
        background-color: #e0e0e0;
        color: var(--primary-color);
    }

    .action-icon .badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background-color: #f1c40f;
        color: #2c3e50;
        font-size: 10px;
        width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: bold;
    }

    .user-profile {
        display: flex;
        align-items: center;
        margin-left: 25px;
        cursor: pointer;
        position: relative;
    }

    .user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        overflow: hidden;
        margin-right: 12px;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 18px;
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .user-info {
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-size: 15px;
        font-weight: 600;
        color: #333;
    }

    .user-role {
        font-size: 12px;
        color: #777;
    }

    .dropdown-icon {
        margin-left: 10px;
        color: #999;
        transition: transform 0.3s ease;
    }

    .user-profile:hover .dropdown-icon {
        transform: rotate(180deg);
    }

    .user-dropdown {
        position: absolute;
        top: 60px;
        right: 0;
        width: 200px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        padding: 10px 0;
        opacity: 0;
        visibility: hidden;
        transform: translateY(10px);
        transition: all 0.3s ease;
        z-index: 1000;
    }

    .user-profile:hover .user-dropdown {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-item {
        padding: 12px 20px;
        display: flex;
        align-items: center;
        color: #333;
        font-size: 14px;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .dropdown-item i {
        margin-right: 10px;
        font-size: 16px;
        width: 20px;
        text-align: center;
    }

    .dropdown-item:hover {
        background-color: #f5f5f5;
        color: var(--primary-color);
    }

    .dropdown-divider {
        height: 1px;
        background-color: #e0e0e0;
        margin: 8px 0;
    }

    .logout-item {
        color: #e74c3c;
    }

    .logout-item:hover {
        background-color: rgba(231, 76, 60, 0.1);
        color: #e74c3c;
    }

    /* Responsive styles */
    @media (max-width: 991px) {
        .search-container {
            width: 30%;
        }
    }

    @media (max-width: 768px) {

        .user-info,
        .search-container {
            display: none;
        }

        .topbar {
            padding: 0 15px;
        }
    }

    @media (max-width: 480px) {
        .action-icon {
            width: 35px;
            height: 35px;
            margin-left: 10px;
        }
    }
</style>

<div class="topbar" id="topbar">
    <div class="search-container">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search...">
    </div>

    <div class="topbar-actions">
        <div class="action-icon">
            <i class="fas fa-bell"></i>
            <span class="badge">3</span>
        </div>

        <div class="action-icon">
            <i class="fas fa-envelope"></i>
            <span class="badge">5</span>
        </div>

        <div class="user-profile">
            <?php
            // Get admin info from session (adjust according to your authentication system)
            $adminName = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin User';
            $adminRole = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'Administrator';
            $adminImage = isset($_SESSION['admin_image']) ? $_SESSION['admin_image'] : '';

            // Get admin initials for avatar if no image is available
            $initials = '';
            $nameParts = explode(' ', $adminName);
            if (count($nameParts) >= 2) {
                $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
            } else {
                $initials = strtoupper(substr($adminName, 0, 2));
            }
            ?>

            <div class="user-avatar">
                <?php if (!empty($adminImage) && file_exists($adminImage)): ?>
                    <img src="<?php echo $adminImage; ?>" alt="<?php echo $adminName; ?>">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>

            <div class="user-info">
                <span class="user-name"><?php echo $adminName; ?></span>
                <span class="user-role"><?php echo $adminRole; ?></span>
            </div>

            <i class="fas fa-chevron-down dropdown-icon"></i>

            <div class="user-dropdown">
                <a href="<?php echo $menuPath; ?>profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a href="<?php echo $menuPath; ?>settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo $menuPath; ?>logout.php" class="dropdown-item logout-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Adjust content and topbar when sidebar is toggled
        const sidebar = document.getElementById('sidebar');
        const topbar = document.getElementById('topbar');
        const content = document.getElementById('content');

        // Ensure topbar adjusts when sidebar state changes
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class') {
                    if (sidebar.classList.contains('collapsed')) {
                        topbar.style.left = 'var(--sidebar-collapsed-width)';
                    } else {
                        topbar.style.left = 'var(--sidebar-width)';
                    }
                }
            });
        });

        observer.observe(sidebar, {
            attributes: true
        });

        // Initialize topbar position based on sidebar state
        if (sidebar.classList.contains('collapsed')) {
            topbar.style.left = 'var(--sidebar-collapsed-width)';
        } else {
            topbar.style.left = 'var(--sidebar-width)';
        }
    });
</script>