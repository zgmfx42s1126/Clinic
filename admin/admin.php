<?php
include '../includes/conn.php'; // your DB connection
include $_SERVER['DOCUMENT_ROOT'] . '/clinic/admin/includes/sidebar.php';


// Check if admin is logged in (you should implement this in your system)
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';
$admin_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'Clinic Administrator';

// Fetch data for the graph
$graph_sql = "SELECT DATE(date) as visit_date, COUNT(*) as visit_count 
              FROM clinic_records 
              WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              GROUP BY DATE(date) 
              ORDER BY visit_date";
$graph_result = $conn->query($graph_sql);

// Prepare data for the graph
$graph_labels = [];
$graph_data = [];
$today = new DateTime();
for ($i = 6; $i >= 0; $i--) {
    $date = clone $today;
    $date->modify("-$i days");
    $date_str = $date->format('Y-m-d');
    $graph_labels[] = $date->format('M d');
    $graph_data[$date_str] = 0;
}

if ($graph_result && $graph_result->num_rows > 0) {
    while($row = $graph_result->fetch_assoc()) {
        $date_str = $row['visit_date'];
        $graph_data[$date_str] = (int)$row['visit_count'];
    }
}

// Get status distribution
$status_sql = "SELECT 
                CASE 
                    WHEN treatment IS NULL OR treatment = '' THEN 'Pending'
                    ELSE 'Treated'
                END as status_type,
                COUNT(*) as count
              FROM clinic_records 
              GROUP BY status_type";
$status_result = $conn->query($status_sql);

$status_labels = [];
$status_counts = [];
$status_colors = [];
if ($status_result && $status_result->num_rows > 0) {
    while($row = $status_result->fetch_assoc()) {
        $status_labels[] = $row['status_type'];
        $status_counts[] = $row['count'];
        $status_colors[] = $row['status_type'] == 'Treated' ? '#28a745' : '#ffc107';
    }
}

// Get today's stats for quick info
$today_stats_sql = "SELECT 
                    COUNT(*) as total_today,
                    SUM(CASE WHEN treatment IS NULL OR treatment = '' THEN 1 ELSE 0 END) as pending_today,
                    SUM(CASE WHEN treatment IS NOT NULL AND treatment != '' THEN 1 ELSE 0 END) as treated_today
                    FROM clinic_records 
                    WHERE DATE(date) = CURDATE()";
$today_stats_result = $conn->query($today_stats_sql);
$today_stats = $today_stats_result->fetch_assoc();

// Get most common complaints
$common_complaints_sql = "SELECT complaint, COUNT(*) as count 
                          FROM clinic_records 
                          WHERE complaint IS NOT NULL AND complaint != ''
                          GROUP BY complaint 
                          ORDER BY count DESC 
                          LIMIT 5";
$common_complaints_result = $conn->query($common_complaints_sql);

// Fetch main table data
$sql = "SELECT * FROM clinic_records ORDER BY date DESC, time DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Records - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
</head>
<body>
    <!-- Your sidebar is included via sidebar.php -->
    <div
        id="dashboard-data"
        data-graph-labels="<?php echo htmlspecialchars(json_encode($graph_labels), ENT_QUOTES); ?>"
        data-graph-values="<?php echo htmlspecialchars(json_encode(array_values($graph_data)), ENT_QUOTES); ?>"
        data-status-labels="<?php echo htmlspecialchars(json_encode($status_labels), ENT_QUOTES); ?>"
        data-status-counts="<?php echo htmlspecialchars(json_encode($status_counts), ENT_QUOTES); ?>"
        data-status-colors="<?php echo htmlspecialchars(json_encode($status_colors), ENT_QUOTES); ?>"
    ></div>
    
    <div class="main-content">
        <div class="container">
            <div class="header">
                <div class="header-content">
                    <h1><i class="fas fa-clinic-medical"></i> Clinic Records Management</h1>
                    <p>Welcome to the admin panel for managing all student clinic visits and treatments</p>
                    <div class="admin-info">
                        <div class="admin-avatar">
                            <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                        </div>
                        <div class="admin-details">
                            <h4><?php echo htmlspecialchars($admin_name); ?></h4>
                            <p><?php echo htmlspecialchars($admin_role); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="quick-stats">
                <div class="stat-card stat-today" onclick="showTodayRecords()">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $today_stats['total_today'] ?? 0; ?></h3>
                        <p>Today's Visits</p>
                    </div>
                </div>
                
                <div class="stat-card stat-pending" onclick="filterByStatus('pending')">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $today_stats['pending_today'] ?? 0; ?></h3>
                        <p>Pending Today</p>
                    </div>
                </div>
                
                <div class="stat-card stat-treated" onclick="filterByStatus('treated')">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $today_stats['treated_today'] ?? 0; ?></h3>
                        <p>Treated Today</p>
                    </div>
                </div>
            </div>
            
            <div class="charts-container">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-line"></i> Weekly Clinic Visits</h3>
                        <select id="time-period" onchange="updateChart()">
                            <option value="7">Last 7 days</option>
                            <option value="14">Last 14 days</option>
                            <option value="30">Last 30 days</option>
                        </select>
                    </div>
                    <div class="chart-canvas-container">
                        <canvas id="visitsChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-pie"></i> Treatment Status</h3>
                    </div>
                    <div class="chart-canvas-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="chart-legend" id="statusLegend">
                        <!-- Legend will be generated by JavaScript -->
                    </div>
                    <div class="common-complaints">
                        <h4><i class="fas fa-notes-medical"></i> Top Complaints</h4>
                        <?php if ($common_complaints_result && $common_complaints_result->num_rows > 0): ?>
                            <?php while($row = $common_complaints_result->fetch_assoc()): ?>
                            <div class="complaint-item">
                                <span class="complaint-name"><?php echo htmlspecialchars($row['complaint']); ?></span>
                                <span class="complaint-count"><?php echo $row['count']; ?></span>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #999; font-size: 14px;">No complaint data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
 
            
            <?php if ($result && $result->num_rows > 0): ?>
         
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add/Edit Record Modal -->
    <div class="modal" id="recordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Clinic Record</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form id="recordForm">
                <input type="hidden" id="recordId" name="id">
                
                <div class="form-group">
                    <label for="studentId">Student ID *</label>
                    <input type="text" id="studentId" name="studentid" required>
                </div>
                
                <div class="form-group">
                    <label for="name">Student Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="gradeSection">Grade & Section *</label>
                    <input type="text" id="gradeSection" name="grade_section" required>
                </div>
                
                <div class="form-group">
                    <label for="complaint">Complaint *</label>
                    <textarea id="complaint" name="complaint" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="treatment">Treatment</label>
                    <textarea id="treatment" name="treatment"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="time">Time</label>
                    <input type="time" id="time" name="time" value="<?php echo date('H:i'); ?>">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-submit">Save Record</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="assets/js/admin-dashboard.js"></script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>


