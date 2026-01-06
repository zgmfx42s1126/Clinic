<?php
// Database connection
include '../includes/conn.php'; 
include $_SERVER['DOCUMENT_ROOT'] . '/clinic/admin/includes/sidebar.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filter parameters - Updated defaults to first and last day of current month
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'Monthly Analysis';

// Automatically get user's grade section from session or database

$grade_section = '';

// Try to get grade section from session
if (isset($_SESSION['user_grade_section']) && !empty($_SESSION['user_grade_section'])) {
    $grade_section = $_SESSION['user_grade_section'];
} 
// Or from URL parameter (if you still want to allow override)
elseif (isset($_GET['grade_section']) && !empty($_GET['grade_section'])) {
    $grade_section = $_GET['grade_section'];
} 
// Or from user profile in database
else {
    // Assuming user ID is stored in session
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $user_sql = "SELECT grade_section FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        if ($user_row = $user_result->fetch_assoc()) {
            $grade_section = $user_row['grade_section'] ?? '';
        }
        $user_stmt->close();
    }
}

// Build the base SQL query
$sql = "SELECT * FROM clinic_records WHERE 1=1";
$params = array();
$types = "";

// Add date filter
if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

// Add grade/section filter (always applied automatically)
if (!empty($grade_section)) {
    $sql .= " AND grade_section = ?";
    $params[] = $grade_section;
    $types .= "s";
}

// Order by
$sql .= " ORDER BY date DESC, time DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get total records
$total_records = $result ? $result->num_rows : 0;

// Get all grade sections for filter dropdown
$all_grades_sql = "SELECT DISTINCT grade_section FROM clinic_records WHERE grade_section IS NOT NULL AND grade_section != '' ORDER BY grade_section ASC";
$all_grades_result = $conn->query($all_grades_sql);

// Get stats based on current filters
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN treatment IS NOT NULL AND treatment != '' THEN 1 ELSE 0 END) as treated,
                SUM(CASE WHEN treatment IS NULL OR treatment = '' THEN 1 ELSE 0 END) as pending
              FROM clinic_records WHERE 1=1";
              
if (!empty($start_date) && !empty($end_date)) {
    $stats_sql .= " AND date BETWEEN '$start_date' AND '$end_date'";
}
if (!empty($grade_section)) {
    $stats_sql .= " AND grade_section = '$grade_section'";
}

$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total' => 0, 'treated' => 0, 'pending' => 0];

$report_date = date('F d, Y');
$report_time = date('h:i A');

$web_path = '/clinic/assets/pictures/format.png';
$server_path = $_SERVER['DOCUMENT_ROOT'] . $web_path;
$image_exists = file_exists($server_path);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Reports - Clinic Management System</title>
    
    <link rel="preload" as="image" href="<?php echo $web_path; ?>">
    <link rel="stylesheet" href="../assets/css/patient.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Custom styles for filters */
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #4b5563;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            width: 100%;
            transition: border-color 0.3s;
            background: white;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 10px;
        }
        
        .filter-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            min-width: 150px;
        }
        
        .btn-apply {
            background: #4361ee;
            color: white;
        }
        
        .btn-apply:hover {
            background: #3a56d4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }
        
        .btn-reset {
            background: #6c757d;
            color: white;
        }
        
        .btn-reset:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        /* Update existing table controls */
        .table-controls {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-filter {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            min-width: 250px;
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            min-width: 150px;
            background: white;
        }
        
        .grade-section-select {
            min-width: 250px;
        }
        
        .table-info {
            font-weight: 600;
            color: #4361ee;
            background: #f0f4ff;
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #dbe4ff;
        }
        
        /* Keep existing styles */
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            background-image: url('<?php echo $web_path; ?>'); 
            background-size: cover; 
            background-position: center;
            background-repeat: no-repeat;
        }

        .print-template {
            display: none;
        }

        @media print {
            @page {
                size: A4;
                margin: 0;
            }

            body {
                background: none;
                margin: 0;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .main-content, .sidebar, .header, .table-controls, .no-print,
            .filter-section {
                display: none !important;
            }

            .print-template {
                display: block !important;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                visibility: visible;
            }

            .page {
                width: 100%;
                height: 100%;
                margin: 0;
                box-shadow: none;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                background-image: url('<?php echo $web_path; ?>') !important; 
            }
        }
        
        /* Update header styling */
        .header {
            background: linear-gradient(135deg, #4361ee 0%, #3a56d4 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(67, 97, 238, 0.2);
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        /* Table styling */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        th {
            background-color: #4361ee;
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            border: none;
            position: sticky;
            top: 0;
        }
        
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #eef0f3;
            vertical-align: middle;
        }
        
        tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-treated {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            color: #6c757d;
            text-align: center;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #d1d5db;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #4b5563;
        }
    </style>
</head>
<body>

    <div style="background-image: url('<?php echo $web_path; ?>'); width:0; height:0; overflow:hidden; visibility:hidden; position:absolute;"></div>

    <div class="main-content no-print">
        <div class="container">
            <div class="header">
                <h1><i class="fa fa-file-medical-alt"></i> Patient Records</h1>
                <p>View all patient clinic visit records</p>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="startDate">
                            <i class="fas fa-calendar-day"></i>
                            Start Date
                        </label>
                        <input type="date" id="startDate" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="endDate">
                            <i class="fas fa-calendar-day"></i>
                            End Date
                        </label>
                        <input type="date" id="endDate" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="reportType">
                            <i class="fas fa-chart-bar"></i>
                            Report Type
                        </label>
                        <select id="reportType" name="report_type">
                            <option value="Weekly Analysis" <?php echo $report_type == 'Weekly Analysis' ? 'selected' : ''; ?>>Weekly Analysis</option>
                            <option value="Monthly Analysis" <?php echo $report_type == 'Monthly Analysis' ? 'selected' : ''; ?>>Monthly Analysis</option>
                            <option value="Yearly Analysis" <?php echo $report_type == 'Yearly Analysis' ? 'selected' : ''; ?>>Yearly Analysis</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button class="filter-btn btn-reset" onclick="resetFilters()">
                        <i class="fas fa-redo"></i>
                        Reset
                    </button>
                    <button class="filter-btn btn-apply" onclick="applyFilters()">
                        <i class="fas fa-filter"></i>
                        Apply Filter
                    </button>
                </div>
            </div>
            
            <div class="table-controls">
                <div class="search-filter">
                    <input type="text" class="search-box" placeholder="Search patients..." onkeyup="searchTable()" id="searchInput">
                    
                    <select class="filter-select" onchange="filterTableByStatus()" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="treated">Treated</option>
                        <option value="pending">Pending</option>
                    </select>
                    
                    <!-- Grade & Section Filter - MOVED TO RIGHT SIDE -->
                    <select class="filter-select grade-section-select" onchange="applyFilters()" id="gradeSectionFilter">
                        <option value="">All Grades & Sections</option>
                        <?php if ($all_grades_result && $all_grades_result->num_rows > 0): ?>
                            <?php while ($grade_row = $all_grades_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($grade_row['grade_section']); ?>" 
                                    <?php echo $grade_row['grade_section'] == $grade_section ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade_row['grade_section']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="table-info">
                    <?php if (!empty($grade_section)): ?>
                        Showing <?php echo $total_records; ?> record(s) for <?php echo htmlspecialchars($grade_section); ?>
                    <?php else: ?>
                        Showing <?php echo $total_records; ?> record(s)
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="table-container">
                <?php if ($result && $result->num_rows > 0): ?>
                    <table id="patientsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Grade & Section</th>
                                <th>Complaint</th>
                                <th>Treatment</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['student_id'] ?? ''); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['name'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['grade_section'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['complaint'] ?? 'Not specified'); ?></td>
                                <td><?php echo !empty($row['treatment']) ? htmlspecialchars($row['treatment']) : '<span style="color:#999;">No treatment yet</span>'; ?></td>
                                <td>
                                    <?php if (!empty($row['treatment'])): ?>
                                        <span class="status-badge status-treated">Treated</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo !empty($row['date']) ? date('Y-m-d', strtotime($row['date'])) : ''; ?></td>
                                <td><?php echo !empty($row['time']) ? date('h:i A', strtotime($row['time'])) : ''; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No patient records found</h3>
                        <p>There are no records matching your filter criteria<?php echo !empty($grade_section) ? ' for ' . htmlspecialchars($grade_section) : ''; ?>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PRINT TEMPLATE -->
    <div class="print-template">
        <div class="page">
            <!-- UPDATED: Simple format like the second image -->
            <div style="position: relative; z-index: 10; font-family: monospace; margin-top: 20mm; line-height: 1.8;">
                <div style="font-size: 14px; margin-bottom: 5px;">
                    Report Period: <?php echo date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)); ?>
                </div>
                <div style="font-size: 14px; margin-bottom: 15px;">
                    <?php echo $total_records; ?> total records analyzed
                </div>
                
                <!-- Simple format like the second image -->
                <div style="font-family: monospace; font-size: 14px; margin-bottom: 5px;">
                    Start Date                                                                  End Date
                </div>
                <div style="font-family: monospace; font-size: 14px; margin-bottom: 10px;">
                    <?php 
                    $start_date_formatted = date('m/d/Y', strtotime($start_date));
                    $end_date_formatted = date('m/d/Y', strtotime($end_date));
                    
                    // Simple format with proper spacing
                    echo sprintf("%-70s%s", $start_date_formatted, $end_date_formatted);
                    ?>
                </div>
                
                <!-- Report Type line -->
                <div style="font-family: monospace; font-size: 14px; margin-top: 10px;">
                    Report Type: <?php echo $report_type; ?>
                </div>
                
                <!-- Grade Section line (if applicable) -->
                <?php if (!empty($grade_section)): ?>
                <div style="font-family: monospace; font-size: 14px; margin-top: 5px;">
                    Grade/Section: <?php echo htmlspecialchars($grade_section); ?>
                </div>
                <?php endif; ?>
            </div>
            <!-- END: Updated format -->
            
            <div class="document-header" style="text-align: center; margin-bottom: 30px; position: relative; z-index: 10;">
                <div class="school-subtitle" style="font-size: 16px;">Clinic Management System</div>
            </div>
            
            <div class="report-header" style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; position: relative; z-index: 10;">
                <div class="report-title" style="font-size: 20px; font-weight: bold; text-transform: uppercase;">Patient Clinic Visit Report</div>
                <div class="report-subtitle">Complete Patient Records</div>
            </div>
            
            <div class="report-info" style="display: flex; justify-content: space-between; margin-bottom: 20px; position: relative; z-index: 10;">
                <div class="info-section">
                    <strong>Report Generated:</strong> <?php echo $report_date . ' at ' . $report_time; ?>
                </div>
                <div class="info-section">
                    <strong>Date Range:</strong> <?php echo date('m/d/Y', strtotime($start_date)) . ' - ' . date('m/d/Y', strtotime($end_date)); ?>
                </div>
                <div class="info-section">
                    <strong>School Year:</strong> 2025-2026
                </div>
            </div>
            
            <?php if (!empty($grade_section)): ?>
            <div class="filter-info" style="margin-bottom: 15px; padding: 10px; background: rgba(255,255,255,0.9); border-left: 4px solid #4361ee; position: relative; z-index: 10;">
                <strong>Grade/Section:</strong> <?php echo htmlspecialchars($grade_section); ?>
            </div>
            <?php endif; ?>
            
            <div class="report-stats" style="display: flex; justify-content: space-around; margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; background: rgba(255,255,255,0.9); position: relative; z-index: 10;">
                <div class="stat-item" style="text-align: center;">
                    <div class="stat-number" style="font-size: 24px; font-weight: bold;"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
                <div class="stat-item" style="text-align: center;">
                    <div class="stat-number" style="font-size: 24px; font-weight: bold;"><?php echo $stats['treated']; ?></div>
                    <div class="stat-label">Treated Cases</div>
                </div>
                <div class="stat-item" style="text-align: center;">
                    <div class="stat-number" style="font-size: 24px; font-weight: bold;"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending Cases</div>
                </div>
            </div>
            
            <div class="print-table-container" style="position: relative; z-index: 10;">
                <table class="print-table" style="width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.95);">
                    <thead>
                        <tr style="background-color: #f2f2f2;">
                            <th style="border: 1px solid #000; padding: 8px; width: 10%;">ID</th>
                            <th style="border: 1px solid #000; padding: 8px; width: 10%;">Name</th>
                            <th style="border: 1px solid #000; padding: 8px; width: 25%;">Grade/Section</th>
                            <th style="border: 1px solid #000; padding: 8px; width: 10%;">Complaint</th>
                            <th style="border: 1px solid #000; padding: 8px;">Treatment</th>
                            <th style="border: 1px solid #000; padding: 8px; width: 5%;">Status</th>
                            <th style="border: 1px solid #000; padding: 8px;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if($result) $result->data_seek(0);
                        while($row = $result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td style="border: 1px solid #000; padding: 8px;"><?php echo htmlspecialchars($row['student_id'] ?? ''); ?></td>
                            <td style="border: 1px solid #000; padding: 8px;"><strong><?php echo htmlspecialchars($row['name'] ?? ''); ?></strong></td>
                            <td style="border: 1px solid #000; padding: 8px;"><?php echo htmlspecialchars($row['grade_section'] ?? ''); ?></td>
                            <td style="border: 1px solid #000; padding: 8px;"><?php echo htmlspecialchars($row['complaint'] ?? ''); ?></td>
                            <td style="border: 1px solid #000; padding: 8px; width: 10%;"><?php echo htmlspecialchars($row['treatment'] ?? ''); ?></td>
                            <td style="border: 1px solid #000; padding: 8px;">
                                <?php echo !empty($row['treatment']) ? 'TREATED' : 'PENDING'; ?>
                            </td>
                            <td style="border: 1px solid #000; padding: 8px;"><?php echo !empty($row['date']) ? date('Y-m-d', strtotime($row['date'])) : ''; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
</body>
</html>

<script>
    function applyFilters() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const reportType = document.getElementById('reportType').value;
        const gradeSection = document.getElementById('gradeSectionFilter').value;
        
        // Update dates based on report type
        const updatedDates = updateDatesBasedOnReportType(startDate, endDate, reportType);
        
        window.location.href = `?start_date=${updatedDates.startDate}&end_date=${updatedDates.endDate}&report_type=${reportType}&grade_section=${gradeSection}`;
    }
    
    function resetFilters() {
        const today = new Date();
        const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        // Format dates as YYYY-MM-DD
        const firstDayStr = firstDayOfMonth.toISOString().split('T')[0];
        const lastDayStr = lastDayOfMonth.toISOString().split('T')[0];
        
        document.getElementById('startDate').value = firstDayStr;
        document.getElementById('endDate').value = lastDayStr;
        document.getElementById('reportType').value = 'Monthly Analysis';
        document.getElementById('gradeSectionFilter').value = '';
        
        applyFilters();
    }
    
    function updateDatesBasedOnReportType(startDate, endDate, reportType) {
        const endDateObj = new Date(endDate);
        let startDateObj = new Date(endDateObj);
        
        switch(reportType) {
            case 'Weekly Analysis':
                startDateObj.setDate(startDateObj.getDate() - 7);
                break;
            case 'Monthly Analysis':
                // Set to first day of the month
                startDateObj = new Date(endDateObj.getFullYear(), endDateObj.getMonth(), 1);
                // Set end date to last day of the month
                endDateObj.setMonth(endDateObj.getMonth() + 1);
                endDateObj.setDate(0);
                break;
            case 'Yearly Analysis':
                startDateObj.setFullYear(startDateObj.getFullYear() - 1);
                break;
        }
        
        return {
            startDate: startDateObj.toISOString().split('T')[0],
            endDate: endDateObj.toISOString().split('T')[0]
        };
    }
    
    // Auto-update date range when report type changes
    document.getElementById('reportType').addEventListener('change', function() {
        const reportType = this.value;
        const endDateInput = document.getElementById('endDate');
        const startDateInput = document.getElementById('startDate');
        
        const dates = updateDatesBasedOnReportType(startDateInput.value, endDateInput.value, reportType);
        startDateInput.value = dates.startDate;
        endDateInput.value = dates.endDate;
    });
    
    // Search function
    function searchTable() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("patientsTable");
        tr = table.getElementsByTagName("tr");
        
        for (i = 0; i < tr.length; i++) {
            td = tr[i].getElementsByTagName("td");
            var found = false;
            for (var j = 0; j < td.length; j++) {
                var cell = td[j];
                if (cell) {
                    txtValue = cell.textContent || cell.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }
            tr[i].style.display = found ? "" : "none";
        }
    }
    
    // Filter table by status (client-side filtering)
    function filterTableByStatus() {
        var filter, table, tr, td, i;
        filter = document.getElementById("statusFilter").value.toUpperCase();
        table = document.getElementById("patientsTable");
        tr = table.getElementsByTagName("tr");
        
        for (i = 0; i < tr.length; i++) {
            td = tr[i].getElementsByTagName("td")[6]; // Status column
            if (td) {
                var statusText = td.textContent || td.innerText;
                var status = statusText.toUpperCase().includes("TREATED") ? "TREATED" : "PENDING";
                
                if (filter === "" || (filter === "TREATED" && status === "TREATED") || (filter === "PENDING" && status === "PENDING")) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }
</script>

<?php
if(isset($conn)) $conn->close();
?>