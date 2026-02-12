<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Management</title>
    <link rel="stylesheet" href="/clinic/admin/assets/css/sidebar.css">
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
                <div class="nav-section-title">Account</div>

                <a href="/clinic/admin/adminlogin.php" class="nav-item">
                    <div class="nav-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <div class="nav-text">Logout</div>
                </a>

            </div>
        </div>
    </div>

    <script src="/clinic/admin/assets/js/sidebar.js"></script>
</body>
</html>

