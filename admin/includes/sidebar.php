<?php
// No output before this PHP tag - NO EMPTY LINES OR SPACES!

// Start session if needed
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// You can add any PHP logic here if needed
// For example, check if user is logged in, etc.

// Then output the HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-blue: #2c5aa0;
            --primary-blue-dark: #1c3a6e;
            --primary-blue-light: #4a7fd4;
            --accent-blue: #3498db;
            --hover-blue: #3b6cb0;
            --sidebar-bg: #ffffff;
            --sidebar-text: #2c3e50;
            --sidebar-hover: #f0f7ff;
            --main-bg: #f8fafc;
            --white: #ffffff;
            --border-color: #e2e8f0;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            display: flex;
            min-height: 100vh;
            background: var(--main-bg);
        }

        /* Simple Sidebar */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            transition: var(--transition);
            box-shadow: var(--shadow);
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            overflow: hidden;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar-header {
            padding: 24px 20px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-blue-dark));
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            transition: var(--transition);
        }

        .sidebar-header:hover {
            background: linear-gradient(135deg, var(--primary-blue-light), var(--primary-blue));
        }

        /* Logo image */
        .logo {
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            overflow: hidden;
        }

        .logo img {
            width: 32px;
            height: 32px;
            object-fit: contain;
            display: block;
        }

        .logo:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 0.3);
        }

        .logo-text {
            font-size: 20px;
            font-weight: 700;
            white-space: nowrap;
            color: white;
            letter-spacing: 0.5px;
            transition: var(--transition);
        }

        .sidebar.collapsed .logo-text {
            opacity: 0;
            width: 0;
        }

        .toggle-btn {
            display: none;
        }

        /* Navigation */
        .nav-menu {
            padding: 20px 0;
            overflow-y: auto;
            height: calc(100vh - 100px);
        }

        .nav-section {
            padding: 0 0 10px 0;
        }

        .nav-section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            padding: 15px 24px 8px 24px;
            font-weight: 600;
            transition: var(--transition);
        }

        .sidebar.collapsed .nav-section-title {
            opacity: 0;
            height: 0;
            padding: 0;
            margin: 0;
        }

        .nav-item {
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
            margin: 2px 8px;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-blue);
            transform: scaleY(0);
            transition: transform 0.3s ease;
            border-radius: 0 4px 4px 0;
        }

        .nav-item:hover {
            background: var(--sidebar-hover);
            transform: translateX(5px);
            color: var(--primary-blue);
            border-left-color: var(--accent-blue);
        }

        .nav-item:hover::before {
            transform: scaleY(1);
        }

        .nav-item:hover .nav-icon {
            transform: scale(1.1);
            color: var(--accent-blue);
        }

        /* ✅ ACTIVE (blue) */
        .nav-item.active {
            background: linear-gradient(90deg, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.05));
            color: var(--primary-blue);
            border-left-color: var(--accent-blue);
            font-weight: 600;
            box-shadow: inset 4px 0 0 var(--accent-blue);
        }

        .nav-item.active .nav-icon {
            color: var(--accent-blue);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .nav-icon {
            width: 24px;
            text-align: center;
            font-size: 20px;
            color: #64748b;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .nav-text {
            font-size: 15px;
            font-weight: 500;
            white-space: nowrap;
            transition: var(--transition);
        }

        .sidebar.collapsed .nav-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.2);
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Simple Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo" id="logoToggle">
                <img src="/clinic/admin/assets/pictures/logohcm.png" alt="Clinic Logo">
            </div>

            <div class="logo-text">Clinic Records</div>

            <button class="toggle-btn" id="toggleBtn">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>

        <div class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">Main Menu</div>
                <a href="/clinic/admin/admin.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-tachometer-alt"></i></div>
                    <div class="nav-text">Dashboard</div>
                </a>

                <a href="/clinic/admin/pages/patientrecords.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-users"></i></div>
                    <div class="nav-text">Patients</div>
                </a>

                <a href="/clinic/admin/pages/recordslogs.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="nav-text">Records</div>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Management</div>

                <a href="/clinic/admin/pages/reportsComplaint.php" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-chart-column"></i></div>
                    <div class="nav-text">Reports Complaint</div>
                </a>

                <a href="/clinic/admin/pages/reportslogs.php" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="nav-text">Reports Logs</div>
                </a>

                <a href="/clinic/admin/pages/statistics.php" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-chart-area"></i></div>
                    <div class="nav-text">Statistics</div>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Settings</div>

                <a href="#" class="nav-item">
                    <div class="nav-icon"><i class="fas fa-cog"></i></div>
                    <div class="nav-text">Settings</div>
                </a>

               <a href="/clinic/admin/adminlogin.php" class="nav-item">
                    <div class="nav-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <div class="nav-text">Logout</div>
                </a>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const logoToggle = document.getElementById('logoToggle');
        const toggleBtn = document.getElementById('toggleBtn');

        // Desktop toggle - Logo click
        logoToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const icon = toggleBtn.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.className = 'fas fa-chevron-right';
            } else {
                icon.className = 'fas fa-chevron-left';
            }
        });

        // ✅ ACTIVE BLUE ON CURRENT PAGE (FIX)
        function setActiveFromURL() {
            const currentPath = window.location.pathname.replace(/\/+$/, '');
            const navItems = document.querySelectorAll('.nav-item');

            navItems.forEach(item => {
                const href = item.getAttribute('href') || '';
                if (!href || href === '#') return;

                const linkPath = new URL(href, window.location.origin).pathname.replace(/\/+$/, '');
                if (linkPath === currentPath) {
                    navItems.forEach(n => n.classList.remove('active'));
                    item.classList.add('active');
                }
            });
        }

        // Click active (instant) + allow navigation
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                const href = this.getAttribute('href');

                // Keep active effect instantly
                navItems.forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');

                // Only prevent default for "#" links
                if (href === '#') {
                    e.preventDefault();
                }
            });
        });

        // Run on load
        document.addEventListener('DOMContentLoaded', function() {
            setActiveFromURL();
        });
    </script>
</body>
</html>