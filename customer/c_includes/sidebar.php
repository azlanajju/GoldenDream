<div class="sidebar">
    <div class="sidebar-header">
        <div class="side-logo">
            <h3>Golden Dream</h3>
        </div>
    </div>

    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" href="<?php echo $c_path; ?>dashboard">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'profile' ? 'active' : ''; ?>" href="<?php echo $c_path; ?>profile">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'schemes' ? 'active' : ''; ?>" href="<?php echo $c_path; ?>schemes">
                <i class="fas fa-gift"></i>
                <span>Schemes</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'subscriptions' ? 'active' : ''; ?>" href="<?php echo $c_path; ?>subscriptions">
                <i class="fas fa-calendar-check"></i>
                <span>My Subscriptions</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'payments' ? 'active' : ''; ?>" href="<?php echo $c_path; ?>payments">
                <i class="fas fa-money-bill-wave"></i>
                <span>Payments</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'winners' ? 'active' : ''; ?>" href="<?php echo $c_path; ?>winners">
                <i class="fas fa-trophy"></i>
                <span>Winners</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'withdrawals' ? 'active' : ''; ?>" href="<?php echo $c_path; ?>withdrawals">
                <i class="fas fa-wallet"></i>
                <span>Withdrawals</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'notifications' ? 'active' : ''; ?>" href="<?php echo $c_path; ?>notifications">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
                <span class="badge bg-danger notification-badge">0</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'payment_qr' ? 'active' : ''; ?>" href="<?php echo $c_path; ?>payment_qr">
                <i class="fas fa-qrcode"></i>
                <span>Payment QR</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="<?php echo $c_path; ?>side-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>side-logout</span>
            </a>
        </li>
    </ul>
</div>

<style>
    .sidebar {
        width: 250px;
        height: 100vh;
        background: #1A1D21;
        /* Dark background like in the image */
        color: #fff;
        position: fixed;
        left: 0;
        top: 0;
        overflow-y: auto;
        transition: all 0.3s ease;
        z-index: 1000;
        border-right: 1px solid rgba(255, 255, 255, 0.05);
    }

    .sidebar-header {
        padding: 20px;
        text-align: center;
        background: #1A1D21;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .sidebar-header .side-logo h3 {
        color: #fff;
        margin: 0;
        font-size: 18px !important;
        font-weight: 500;
        letter-spacing: 0.5px;
    }

    .sidebar::-webkit-scrollbar {
        width: 0;
    }

    .sidebar .nav {
        padding: 10px 0;
    }

    .sidebar .nav-link {
        color: rgba(255, 255, 255, 0.7);
        padding: 12px 16px;
        display: flex;
        align-items: center;
        transition: all 0.2s ease;
        position: relative;
        border-radius: 6px;
        margin: 2px 8px;
        width: calc(100% - 16px);
    }

    .sidebar .nav-link:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.05);
        transform: translateX(0);
        /* Remove the transform effect */
    }

    .sidebar .nav-link.active {
        color: #fff;
        background: #2F9B7F;
        /* Green background for active state */
        border-left: none;
        /* Remove left border */
        box-shadow: none;
    }

    .sidebar .nav-link i {
        width: 20px;
        margin-right: 12px;
        font-size: 1.1rem;
        color: rgba(255, 255, 255, 0.7);
        /* Icons same color as text */
        filter: none;
        /* Remove glow effect */
    }

    .sidebar .nav-link.active i {
        color: #fff;
        /* White icon color for active state */
    }

    .notification-badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
        border-radius: 50%;
        background: #2F9B7F !important;
        /* Green background */
        color: #fff;
        box-shadow: none;
    }

    /* Responsive Sidebar */
    @media (max-width: 768px) {
        .sidebar {
            width: 70px;
            background: #1A1D21;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .sidebar .nav-link {
            padding: 12px 8px;
            margin: 2px 4px;
            justify-content: center;
        }

        .sidebar .nav-link span {
            display: none;
        }

        .sidebar .nav-link i {
            margin-right: 0;
            font-size: 1.3rem;
        }

        .sidebar-header .side-logo h3 {
            display: none;
        }

        .sidebar-header {
            padding: 15px 10px;
        }
    }

    /* Main Content Adjustment */
    .main-content {
        margin-left: 250px;
        padding: 20px;
        transition: all 0.3s ease;
        background: #1A1D21;
        /* Match sidebar background */
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 70px;
        }
    }
</style>

<script>
    // Add this to your existing JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle sidebar on mobile
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'btn btn-primary d-md-none position-fixed';
        toggleBtn.style.cssText = 'top: 10px; left: 10px; z-index: 1001;';
        toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        document.body.appendChild(toggleBtn);

        toggleBtn.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.querySelector('.sidebar');
                const toggleBtn = document.querySelector('.btn-primary');

                if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    });
</script>