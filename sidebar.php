<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Management</title>
    <link rel="stylesheet" href="/clinic/assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Simple Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo" id="logoToggle">
                <i class="fas fa-clinic-medical"></i>
            </div>
            <div class="logo-text">ClinicPro</div>
            <!-- Hidden button but keeping it for structure -->
            <button class="toggle-btn" id="toggleBtn">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>

        <div class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">Main Menu</div>
                <a href="admin.php" class="nav-item active">
                    <div class="nav-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="nav-text">Dashboard</div>
                </a>

                <a href="patientreports.php" class="nav-item">
                    <div class="nav-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="nav-text">Patients</div>
                    <div class="badge">5</div>
                </a>

                <a href="#" class="nav-item">
                    <div class="nav-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="nav-text">Records</div>
                    <div class="badge">15</div>
                </a>

                
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Management</div>
         

                <a href="#" class="nav-item">
                    <div class="nav-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="nav-text">Reports</div>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Account</div>

                <a href="#" class="nav-item">
                    <div class="nav-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <div class="nav-text">Logout</div>
                </a>
            </div>
        </div>

    </div>

    

    <script src="/clinic/assets/js/sidebar.js"></script>
</body>
</html>


