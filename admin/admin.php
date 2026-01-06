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
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a0ca3;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
            --text-color: #333;
            --text-light: #6c757d;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: var(--text-color);
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 250px; /* Adjust based on your sidebar width */
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 25px 30px;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .header-content {
            position: relative;
            z-index: 1;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header h1 i {
            font-size: 32px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 15px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
            width: fit-content;
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            background: white;
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
        }
        
        .admin-details h4 {
            font-size: 16px;
            margin-bottom: 3px;
        }
        
        .admin-details p {
            font-size: 13px;
            opacity: 0.8;
            margin-bottom: 0;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px 30px;
            background: var(--light-bg);
            border-bottom: 1px solid var(--border-color);
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-today .stat-icon { background: var(--info-color); }
        .stat-pending .stat-icon { background: var(--warning-color); }
        .stat-treated .stat-icon { background: var(--success-color); }
        
        .stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 5px;
        }
        
        .stat-info p {
            font-size: 14px;
            color: var(--text-light);
            margin: 0;
        }
        
        .charts-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            padding: 25px 30px;
            background: white;
        }
        
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-header h3 {
            font-size: 18px;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-header select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            font-size: 14px;
            cursor: pointer;
        }
        
        .chart-canvas-container {
            height: 250px;
            position: relative;
        }
        
        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .legend-item:hover {
            background: var(--light-bg);
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .common-complaints {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .common-complaints h4 {
            font-size: 16px;
            margin-bottom: 15px;
            color: var(--text-color);
        }
        
        .complaint-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .complaint-item:last-child {
            border-bottom: none;
        }
        
        .complaint-name {
            font-size: 14px;
            color: var(--text-color);
        }
        
        .complaint-count {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .table-container {
            padding: 30px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        thead {
            background-color: #f1f5fd;
        }
        
        th {
            padding: 16px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-light);
            border-bottom: 2px solid var(--border-color);
            font-size: 15px;
            position: sticky;
            top: 0;
            background: #f1f5fd;
        }
        
        td {
            padding: 16px 15px;
            border-bottom: 1px solid #eef0f3;
            color: #555;
            font-size: 14.5px;
        }
        
        tr:hover {
            background-color: #f8fbff;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-treated {
            background-color: #d4edda;
            color: #155724;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-edit {
            background-color: #e7f1ff;
            color: #0066cc;
        }
        
        .btn-edit:hover {
            background-color: #d0e3ff;
        }
        
        .btn-delete {
            background-color: #ffeaea;
            color: var(--danger-color);
        }
        
        .btn-delete:hover {
            background-color: #ffd6d6;
        }
        
        .btn-copy {
            background-color: #f0f9ff;
            color: #0d6efd;
        }
        
        .btn-copy:hover {
            background-color: #e1f2ff;
        }
        
        .btn-add {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            margin-bottom: 20px;
        }
        
        .btn-add:hover {
            background-color: var(--primary-dark);
        }
        

        
        .bulk-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .bulk-actions select, .bulk-actions button {
            padding: 10px 16px;
            border-radius: 6px;
            border: 1px solid #ced4da;
            font-size: 14px;
        }
        
        .bulk-actions button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        
        .bulk-actions button:hover {
            background-color: var(--primary-dark);
        }
        
        .pagination {
            display: flex;
            gap: 8px;
        }
        
        .pagination button {
            padding: 10px 16px;
            border: 1px solid var(--border-color);
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-light);
        }
        
        .pagination button.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .pagination button:hover:not(.active) {
            background-color: var(--light-bg);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .table-container {
                padding: 15px;
            }
            
            .charts-container, .quick-stats {
                padding: 15px;
            }
            
   
            
            .bulk-actions {
                flex-wrap: wrap;
            }
            
            .chart-canvas-container {
                height: 200px;
            }
            
            .header {
                padding: 20px 15px;
            }
            
            .stat-card {
                padding: 15px;
            }
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            color: var(--primary-color);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }
        
        .close-modal:hover {
            color: var(--danger-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn-cancel {
            background-color: #e9ecef;
            color: var(--text-color);
        }
        
        .btn-cancel:hover {
            background-color: #dee2e6;
        }
        
        .btn-submit {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-submit:hover {
            background-color: var(--primary-dark);
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Your sidebar is included via sidebar.php -->
    
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
    
    <script>
        // Chart.js configurations
        let visitsChart = null;
        let statusChart = null;
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            initVisitsChart();
            initStatusChart();
            
            // Link the select all checkboxes
            const selectAllHeader = document.getElementById('select-all');
            const selectAllFooter = document.getElementById('check-all-footer');
            
            if(selectAllHeader && selectAllFooter) {
                selectAllHeader.addEventListener('change', function() {
                    selectAllFooter.checked = this.checked;
                    toggleAllCheckboxes();
                });
                
                selectAllFooter.addEventListener('change', function() {
                    selectAllHeader.checked = this.checked;
                    toggleAllCheckboxes();
                });
            }
            
            // Form submission
            document.getElementById('recordForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveRecord();
            });
        });
        
        function initVisitsChart() {
            const ctx = document.getElementById('visitsChart').getContext('2d');
            
            const labels = <?php echo json_encode($graph_labels); ?>;
            const dataValues = <?php echo json_encode(array_values($graph_data)); ?>;
            
            visitsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Clinic Visits',
                        data: dataValues,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#4361ee',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return `Visits: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
        
        function initStatusChart() {
            const ctx = document.getElementById('statusChart').getContext('2d');
            
            const labels = <?php echo json_encode($status_labels); ?>;
            const data = <?php echo json_encode($status_counts); ?>;
            const backgroundColors = <?php echo json_encode($status_colors); ?>;
            
            statusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        borderWidth: 1,
                        borderColor: '#fff',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            createLegend(labels, backgroundColors, data);
        }
        
        function createLegend(labels, colors, data) {
            const legendContainer = document.getElementById('statusLegend');
            legendContainer.innerHTML = '';
            
            labels.forEach((label, index) => {
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                legendItem.onclick = () => filterByStatus(label.toLowerCase());
                
                const colorBox = document.createElement('div');
                colorBox.className = 'legend-color';
                colorBox.style.backgroundColor = colors[index];
                
                const text = document.createElement('span');
                text.textContent = `${label}: ${data[index]}`;
                
                legendItem.appendChild(colorBox);
                legendItem.appendChild(text);
                legendContainer.appendChild(legendItem);
            });
        }
        
        function updateChart() {
            const days = document.getElementById('time-period').value;
            alert(`Would fetch data for last ${days} days in a real application`);
            // AJAX call to fetch new data would go here
        }
        
        // Modal Functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Clinic Record';
            document.getElementById('recordForm').reset();
            document.getElementById('recordId').value = '';
            document.getElementById('date').value = new Date().toISOString().split('T')[0];
            document.getElementById('time').value = new Date().toTimeString().slice(0, 5);
            document.getElementById('recordModal').style.display = 'flex';
        }
        
        function editRecord(id) {
            // In real application, fetch record data via AJAX
            alert(`Would fetch record ${id} for editing`);
            document.getElementById('modalTitle').textContent = 'Edit Clinic Record';
            document.getElementById('recordId').value = id;
            // Populate form with record data
            document.getElementById('recordModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('recordModal').style.display = 'none';
        }
        
        function saveRecord() {
            const form = document.getElementById('recordForm');
            const formData = new FormData(form);
            
            // In real application, submit via AJAX
            alert('Would save record via AJAX');
            closeModal();
            // location.reload(); // Reload to show new/updated record
        }
        
        // Filter Functions
        function showTodayRecords() {
            const today = new Date().toISOString().split('T')[0];
            filterTableByDate(today);
        }
        
        function filterByStatus(status) {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                if (status === 'all' || rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            alert(`Showing ${status} records`);
        }
        
        function filterTableByDate(date) {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const dateCell = row.querySelector('td:nth-child(9)');
                if (dateCell && dateCell.textContent === date) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            alert(`Showing records for ${date}`);
        }
        
        // Toggle all checkboxes
        function toggleAllCheckboxes() {
            const checkAll = document.getElementById('select-all') || document.getElementById('check-all-footer');
            const checkboxes = document.querySelectorAll('.row-select');
            const isChecked = checkAll.checked;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
        }
        
        // Copy record function
        function copyRecord(id) {
            if(confirm(`Duplicate record ID: ${id}?`)) {
                alert(`Copying record ID: ${id}`);
                // AJAX call would go here
            }
        }
        
        // Delete record function
        function deleteRecord(id) {
            if(confirm(`Are you sure you want to delete record ID: ${id}?`)) {
                alert(`Deleting record ID: ${id}`);
                // AJAX call would go here
            }
        }
        
        // Bulk action function
        function performBulkAction() {
            const action = document.getElementById('bulk-action').value;
            const selectedIds = [];
            const checkboxes = document.querySelectorAll('.row-select:checked');
            
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const id = row.querySelector('td:nth-child(2)').textContent;
                selectedIds.push(id);
            });
            
            if(selectedIds.length === 0) {
                alert('Please select at least one record.');
                return;
            }
            
            if(!action) {
                alert('Please select an action.');
                return;
            }
            
            if(confirm(`Perform ${action} on ${selectedIds.length} record(s)?`)) {
                alert(`Performing ${action} on records: ${selectedIds.join(', ')}`);
                // AJAX call would go here
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('recordModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>