<?php
// Database connection
include '../includes/conn.php'; 
include $_SERVER['DOCUMENT_ROOT'] . '/clinic/admin/includes/sidebar.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get date range from GET parameters or use default (current month)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'weekly';

// Function to get overview statistics
function getOverviewStats($conn, $start_date, $end_date) {
    $stats = [];
    
    // Total patients
    $sql = "SELECT COUNT(DISTINCT student_id) as total_patients FROM clinic_records WHERE date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_patients'] = $result->fetch_assoc()['total_patients'] ?? 0;
    
    // Total visits
    $sql = "SELECT COUNT(*) as total_visits FROM clinic_records WHERE date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_visits'] = $result->fetch_assoc()['total_visits'] ?? 0;
    
    // Total number of classes (unique grade_section)
    $sql = "SELECT COUNT(DISTINCT grade_section) as total_classes FROM clinic_records WHERE grade_section IS NOT NULL AND grade_section != '' AND date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_classes'] = $result->fetch_assoc()['total_classes'] ?? 0;
    
    // Average daily visits
    $sql = "SELECT COALESCE(AVG(daily_count), 0) as avg_daily FROM (SELECT date, COUNT(*) as daily_count FROM clinic_records WHERE date BETWEEN ? AND ? GROUP BY date) as daily_stats";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['avg_daily_visits'] = round($result->fetch_assoc()['avg_daily'] ?? 0, 1);
    
    // Average users per week
    $sql = "SELECT YEARWEEK(date) as week_num, COUNT(DISTINCT student_id) as weekly_users FROM clinic_records WHERE date BETWEEN ? AND ? GROUP BY YEARWEEK(date)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $weekly_users = [];
    while ($row = $result->fetch_assoc()) {
        $weekly_users[] = $row['weekly_users'];
    }
    
    if (count($weekly_users) > 0) {
        $stats['avg_users_per_week'] = round(array_sum($weekly_users) / count($weekly_users), 1);
    } else {
        $stats['avg_users_per_week'] = 0;
    }
    
    // Treated cases
    $sql = "SELECT COUNT(*) as treated_cases FROM clinic_records WHERE (treatment IS NOT NULL AND treatment != '') AND date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['treated_cases'] = $result->fetch_assoc()['treated_cases'] ?? 0;
    
    // Pending cases
    $sql = "SELECT COUNT(*) as pending_cases FROM clinic_records WHERE (treatment IS NULL OR treatment = '') AND date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['pending_cases'] = $result->fetch_assoc()['pending_cases'] ?? 0;
    
    // Most common complaint
    $sql = "SELECT complaint, COUNT(*) as count FROM clinic_records WHERE complaint IS NOT NULL AND complaint != '' AND date BETWEEN ? AND ? GROUP BY complaint ORDER BY count DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $most_common = $result->fetch_assoc();
    $stats['most_common_complaint'] = $most_common['complaint'] ?? 'N/A';
    $stats['most_common_count'] = $most_common['count'] ?? 0;
    
    // Treatment completion rate
    $stats['completion_rate'] = $stats['total_visits'] > 0 ? round(($stats['treated_cases'] / $stats['total_visits']) * 100, 1) : 0;
    
    // Average visits per patient
    $stats['avg_visits_per_patient'] = $stats['total_patients'] > 0 ? round($stats['total_visits'] / $stats['total_patients'], 2) : 0;
    
    return $stats;
}

// Function to get daily visits data for chart with date range fill
function getDailyVisitsData($conn, $start_date, $end_date) {
    // Create array of all dates in range
    $all_dates = [];
    $current = strtotime($start_date);
    $end = strtotime($end_date);
    
    while ($current <= $end) {
        $date_str = date('Y-m-d', $current);
        $all_dates[$date_str] = [
            'label' => date('M d', $current),
            'visits' => 0,
            'date' => $date_str
        ];
        $current = strtotime('+1 day', $current);
    }
    
    // Get actual visits data
    $sql = "SELECT DATE(date) as visit_date, COUNT(*) as visits FROM clinic_records WHERE date BETWEEN ? AND ? GROUP BY DATE(date) ORDER BY visit_date";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Update with actual data
    while ($row = $result->fetch_assoc()) {
        $date_str = $row['visit_date'];
        if (isset($all_dates[$date_str])) {
            $all_dates[$date_str]['visits'] = (int)$row['visits'];
        }
    }
    
    // Convert to separate arrays for chart
    $data = [
        'labels' => [],
        'visits' => [],
        'dates' => []
    ];
    
    foreach ($all_dates as $date_data) {
        $data['labels'][] = $date_data['label'];
        $data['visits'][] = $date_data['visits'];
        $data['dates'][] = $date_data['date'];
    }
    
    return $data;
}

// Function to get weekly statistics
function getWeeklyStats($conn, $start_date, $end_date) {
    $sql = "SELECT YEARWEEK(date) as week_num, 
                   MIN(date) as week_start,
                   MAX(date) as week_end,
                   COUNT(*) as total_visits,
                   COUNT(DISTINCT student_id) as unique_patients,
                   COUNT(DISTINCT grade_section) as active_classes
            FROM clinic_records 
            WHERE date BETWEEN ? AND ? 
            GROUP BY YEARWEEK(date)
            ORDER BY week_num";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $weeks = [];
    $labels = [];
    $visits = [];
    $patients = [];
    
    while ($row = $result->fetch_assoc()) {
        $week_label = date('M d', strtotime($row['week_start'])) . ' - ' . date('M d', strtotime($row['week_end']));
        
        $weeks[] = [
            'week_num' => $row['week_num'],
            'label' => $week_label,
            'total_visits' => $row['total_visits'],
            'unique_patients' => $row['unique_patients'],
            'active_classes' => $row['active_classes']
        ];
        
        $labels[] = 'Week ' . substr($row['week_num'], 4);
        $visits[] = $row['total_visits'];
        $patients[] = $row['unique_patients'];
    }
    
    return [
        'weeks' => $weeks,
        'chart_labels' => $labels,
        'chart_visits' => $visits,
        'chart_patients' => $patients
    ];
}

// Function to get complaint distribution for chart with better grouping
function getComplaintDistribution($conn, $start_date, $end_date) {
    $sql = "SELECT 
                CASE 
                    WHEN LOWER(complaint) LIKE '%head%' OR LOWER(complaint) LIKE '%ulo%' THEN 'Headache/Head Pain'
                    WHEN LOWER(complaint) LIKE '%stomach%' OR LOWER(complaint) LIKE '%tiyan%' THEN 'Stomach Pain'
                    WHEN LOWER(complaint) LIKE '%eye%' OR LOWER(complaint) LIKE '%mata%' THEN 'Eye Pain/Problem'
                    WHEN LOWER(complaint) LIKE '%fever%' OR LOWER(complaint) LIKE '%lagnat%' THEN 'Fever'
                    WHEN LOWER(complaint) LIKE '%cough%' OR LOWER(complaint) LIKE '%ubo%' THEN 'Cough'
                    WHEN complaint IS NULL OR complaint = '' THEN 'Not Specified'
                    ELSE complaint 
                END as complaint_group,
                COUNT(*) as count 
            FROM clinic_records 
            WHERE date BETWEEN ? AND ? 
            GROUP BY complaint_group 
            ORDER BY count DESC 
            LIMIT 8";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [
        'labels' => [],
        'data' => [],
        'colors' => []
    ];
    
    // Color palette for charts
    $colors = [
        '#4361ee', '#3a0ca3', '#7209b7', '#f72585', 
        '#4cc9f0', '#4895ef', '#560bad', '#b5179e'
    ];
    
    $i = 0;
    while ($row = $result->fetch_assoc()) {
        $data['labels'][] = $row['complaint_group'];
        $data['data'][] = $row['count'];
        $data['colors'][] = $colors[$i % count($colors)];
        $i++;
    }
    
    return $data;
}

// Function to get top classes by visits with cleaner names
function getTopClasses($conn, $start_date, $end_date) {
    $sql = "SELECT 
                CASE 
                    WHEN grade_section LIKE '%Grade 12%' THEN 'Grade 12'
                    WHEN grade_section LIKE '%Grade 11%' THEN 'Grade 11'
                    WHEN grade_section LIKE '%Grade 10%' THEN 'Grade 10'
                    WHEN grade_section LIKE '%Grade 9%' THEN 'Grade 9'
                    WHEN grade_section LIKE '%Grade 8%' THEN 'Grade 8'
                    WHEN grade_section LIKE '%Grade 7%' THEN 'Grade 7'
                    WHEN grade_section LIKE '%Kindergarten%' THEN 'Kindergarten'
                    ELSE grade_section 
                END as class_group,
                COUNT(*) as visits 
            FROM clinic_records 
            WHERE grade_section IS NOT NULL AND grade_section != '' 
                  AND date BETWEEN ? AND ?
            GROUP BY class_group 
            ORDER BY visits DESC 
            LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [
        'labels' => [],
        'data' => []
    ];
    
    while ($row = $result->fetch_assoc()) {
        $data['labels'][] = $row['class_group'];
        $data['data'][] = $row['visits'];
    }
    
    // If no data, create placeholder
    if (empty($data['labels'])) {
        $data['labels'] = ['No data'];
        $data['data'] = [0];
    }
    
    return $data;
}

// Function to get recent visits for print
function getRecentVisits($conn, $start_date, $end_date, $limit = 10) {
    $sql = "SELECT 
                name, 
                grade_section, 
                complaint, 
                treatment, 
                DATE(date) as visit_date,
                TIME(date) as visit_time
            FROM clinic_records 
            WHERE date BETWEEN ? AND ?
            ORDER BY date DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $start_date, $end_date, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Get all statistics
$overview_stats = getOverviewStats($conn, $start_date, $end_date);
$daily_data = getDailyVisitsData($conn, $start_date, $end_date);
$weekly_stats = getWeeklyStats($conn, $start_date, $end_date);
$complaint_data = getComplaintDistribution($conn, $start_date, $end_date);
$top_classes = getTopClasses($conn, $start_date, $end_date);
$recent_visits = getRecentVisits($conn, $start_date, $end_date, 10);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinic Statistics Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            min-height: 100vh;
        }

        /* Main content area with sidebar offset */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            padding: 25px 30px;
            border-radius: 12px 12px 0 0;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .header p {
            opacity: 0.9;
            font-size: 15px;
        }

        /* Filters */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .filter-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-item {
            flex: 1;
            min-width: 200px;
        }

        .filter-item label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }

        .filter-item input,
        .filter-item select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .filter-item input:focus,
        .filter-item select:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .btn-apply {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            height: 40px;
        }

        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-btn.print {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .action-btn.print:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .action-btn.export {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .action-btn.export:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        /* Date Range Display */
        .date-range {
            background: white;
            padding: 15px 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #4361ee;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: #4361ee;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, #4361ee, #3a0ca3);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 22px;
            color: white;
        }

        .stat-icon.patients { background: linear-gradient(135deg, #4361ee, #3a0ca3); }
        .stat-icon.visits { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-icon.classes { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .stat-icon.avg-daily { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .stat-icon.avg-weekly { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-icon.completion { background: linear-gradient(135deg, #ec4899, #db2777); }

        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-trend {
            font-size: 12px;
            color: #10b981;
            font-weight: 600;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-trend.down {
            color: #ef4444;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
        }

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-title i {
            color: #4361ee;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        /* Chart Data Summary */
        .chart-summary {
            margin-top: 15px;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chart-summary-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #495057;
        }

        .chart-summary-item i {
            color: #4361ee;
        }

        .chart-summary-value {
            font-weight: 600;
            color: #2c3e50;
        }

        /* Real Data Badge */
        .real-data-badge {
            background: #10b981;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        /* Tables Section */
        .tables-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .tables-section {
                grid-template-columns: 1fr;
            }
        }

        .table-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .table-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-title i {
            color: #4361ee;
        }

        .table-wrapper {
            overflow-x: auto;
            max-height: 400px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 400px;
        }

        thead {
            background: linear-gradient(135deg, #f8f9fa, #eef2f7);
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #4361ee;
            border-bottom: 2px solid #dee2e6;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #eef0f3;
            color: #34495e;
        }

        tr:hover {
            background-color: #f8fbff;
        }

        .count-badge {
            background: #e0e7ff;
            color: #4361ee;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
        }

        .percentage-bar {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .percentage-bar .bar {
            flex-grow: 1;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .percentage-bar .fill {
            height: 100%;
            background: linear-gradient(90deg, #4361ee, #3a0ca3);
            border-radius: 4px;
        }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-data i {
            font-size: 56px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-data h3 {
            font-size: 20px;
            margin-bottom: 12px;
            color: #495057;
        }

        .no-data p {
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-group {
                flex-direction: column;
            }
            
            .filter-item {
                min-width: 100%;
            }
            
            .btn-apply {
                width: 100%;
                justify-content: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
            
            .charts-section,
            .tables-section {
                grid-template-columns: 1fr;
            }
        }

        /* Chart Toggle */
        .chart-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .toggle-btn {
            padding: 8px 16px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            color: #495057;
            transition: all 0.3s;
        }

        .toggle-btn:hover {
            background: #e9ecef;
        }

        .toggle-btn.active {
            background: #4361ee;
            border-color: #4361ee;
            color: white;
        }

        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .print-section, .print-section * {
                visibility: visible;
            }
            
            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
            }
            
            .no-print {
                display: none !important;
            }
            
            .chart-container {
                page-break-inside: avoid;
            }
            
            table {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-chart-line"></i> Clinic Statistics Dashboard <span class="real-data-badge">REAL DATA</span></h1>
                <p>Comprehensive analysis of clinic operations with detailed charts and metrics</p>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="action-btn print" onclick="printReport()">
                    <i class="fas fa-print"></i> Print Report
                </button>
              
            </div>

            <!-- Date Range Display -->
            <div class="date-range">
                <i class="fas fa-calendar-alt"></i>
                Report Period: <?php echo date('F d, Y', strtotime($start_date)); ?> to <?php echo date('F d, Y', strtotime($end_date)); ?>
                <span style="margin-left: auto; font-size: 13px; color: #6c757d;">
                    <i class="fas fa-database"></i> <?php echo $overview_stats['total_visits']; ?> total records analyzed
                </span>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" action="">
                    <div class="filter-group">
                        <div class="filter-item">
                            <label><i class="fas fa-calendar-start"></i> Start Date</label>
                            <input type="date" name="start_date" value="<?php echo $start_date; ?>" required>
                        </div>
                        
                        <div class="filter-item">
                            <label><i class="fas fa-calendar-end"></i> End Date</label>
                            <input type="date" name="end_date" value="<?php echo $end_date; ?>" required>
                        </div>
                        
                        <div class="filter-item">
                            <label><i class="fas fa-chart-bar"></i> Report Type</label>
                            <select name="report_type">
                                <option value="weekly" <?php echo $report_type == 'weekly' ? 'selected' : ''; ?>>Weekly Analysis</option>
                                <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly Analysis</option>
                                <option value="comparison" <?php echo $report_type == 'comparison' ? 'selected' : ''; ?>>Period Comparison</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn-apply">
                            <i class="fas fa-filter"></i> Generate Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Key Statistics -->
            <div class="stats-grid">
                <!-- Total Patients -->
                <div class="stat-card">
                    <div class="stat-icon patients">
                        <i class="fas fa-user-injured"></i>
                    </div>
                    <div class="stat-number"><?php echo $overview_stats['total_patients']; ?></div>
                    <div class="stat-label">Total Unique Patients</div>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i> <?php echo $overview_stats['avg_visits_per_patient']; ?> avg visits per patient
                    </div>
                </div>

                <!-- Total Visits -->
                <div class="stat-card">
                    <div class="stat-icon visits">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $overview_stats['total_visits']; ?></div>
                    <div class="stat-label">Total Clinic Visits</div>
                    <div class="stat-trend <?php echo $overview_stats['pending_cases'] > 0 ? 'down' : ''; ?>">
                        <i class="fas fa-<?php echo $overview_stats['pending_cases'] > 0 ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                        <?php echo $overview_stats['pending_cases']; ?> pending cases
                    </div>
                </div>

                <!-- Total Classes -->
                <div class="stat-card">
                    <div class="stat-icon classes">
                        <i class="fas fa-school"></i>
                    </div>
                    <div class="stat-number"><?php echo $overview_stats['total_classes']; ?></div>
                    <div class="stat-label">Active Classes/Groups</div>
                    <div class="stat-trend">
                        <i class="fas fa-users"></i> Accessed clinic services
                    </div>
                </div>

                <!-- Average Daily Visits -->
                <div class="stat-card">
                    <div class="stat-icon avg-daily">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-number"><?php echo $overview_stats['avg_daily_visits']; ?></div>
                    <div class="stat-label">Average Daily Visits</div>
                    <div class="stat-trend">
                        <i class="fas fa-chart-bar"></i> Based on <?php echo count($daily_data['dates']); ?> days
                    </div>
                </div>

                <!-- Average Users Per Week -->
                <div class="stat-card">
                    <div class="stat-icon avg-weekly">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-number"><?php echo $overview_stats['avg_users_per_week']; ?></div>
                    <div class="stat-label">Average Users Per Week</div>
                    <div class="stat-trend">
                        <i class="fas fa-user-friends"></i> Weekly unique patients
                    </div>
                </div>

                <!-- Treatment Completion -->
                <div class="stat-card">
                    <div class="stat-icon completion">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-number"><?php echo $overview_stats['completion_rate']; ?>%</div>
                    <div class="stat-label">Treatment Completion Rate</div>
                    <div class="stat-trend">
                        <i class="fas fa-check-circle"></i> <?php echo $overview_stats['treated_cases']; ?> treated
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <!-- Daily/Weekly Visits Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="fas fa-chart-line"></i> Clinic Visits Trend
                            <span class="real-data-badge"><?php echo array_sum($daily_data['visits']); ?> visits</span>
                        </div>
                        <div class="chart-toggle">
                            <button class="toggle-btn active" onclick="showDailyChart()">Daily</button>
                            <button class="toggle-btn" onclick="showWeeklyChart()">Weekly</button>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="visitsChart"></canvas>
                    </div>
                    <!-- Visits Chart Summary -->
                    <div class="chart-summary">
                        <div class="chart-summary-item">
                            <i class="fas fa-calendar-day"></i>
                            <span>Period: <span class="chart-summary-value"><?php echo count($daily_data['dates']); ?> days</span></span>
                        </div>
                        <div class="chart-summary-item">
                            <i class="fas fa-users"></i>
                            <span>Total: <span class="chart-summary-value"><?php echo array_sum($daily_data['visits']); ?> visits</span></span>
                        </div>
                        <div class="chart-summary-item">
                            <i class="fas fa-chart-bar"></i>
                            <span>Avg/Day: <span class="chart-summary-value"><?php echo round(array_sum($daily_data['visits']) / max(1, count($daily_data['dates'])), 1); ?></span></span>
                        </div>
                    </div>
                </div>

                <!-- Complaint Distribution Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="fas fa-stethoscope"></i> Complaint Distribution
                            <span class="real-data-badge"><?php echo array_sum($complaint_data['data']); ?> cases</span>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="complaintChart"></canvas>
                    </div>
                    <!-- Complaint Chart Summary -->
                    <div class="chart-summary">
                        <div class="chart-summary-item">
                            <i class="fas fa-stethoscope"></i>
                            <span>Most Common: <span class="chart-summary-value"><?php echo htmlspecialchars($overview_stats['most_common_complaint']); ?></span></span>
                        </div>
                        <div class="chart-summary-item">
                            <i class="fas fa-list-ol"></i>
                            <span>Cases: <span class="chart-summary-value"><?php echo $overview_stats['most_common_count']; ?></span></span>
                        </div>
                        <div class="chart-summary-item">
                            <i class="fas fa-percentage"></i>
                            <span>Percentage: <span class="chart-summary-value"><?php echo $overview_stats['total_visits'] > 0 ? round(($overview_stats['most_common_count'] / $overview_stats['total_visits']) * 100, 1) : 0; ?>%</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Classes Chart -->
            <div class="chart-container" style="margin-bottom: 30px;">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-school"></i> Top 5 Classes by Visits
                        <span class="real-data-badge"><?php echo $overview_stats['total_classes']; ?> active classes</span>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="classesChart"></canvas>
                </div>
                <!-- Classes Chart Summary -->
                <div class="chart-summary">
                    <div class="chart-summary-item">
                        <i class="fas fa-chart-pie"></i>
                        <span>Top Class: <span class="chart-summary-value"><?php echo !empty($top_classes['labels'][0]) ? htmlspecialchars($top_classes['labels'][0]) : 'N/A'; ?></span></span>
                    </div>
                    <div class="chart-summary-item">
                        <i class="fas fa-user-graduate"></i>
                        <span>Visits: <span class="chart-summary-value"><?php echo !empty($top_classes['data'][0]) ? $top_classes['data'][0] : 0; ?></span></span>
                    </div>
                    <div class="chart-summary-item">
                        <i class="fas fa-percentage"></i>
                        <span>of Total: <span class="chart-summary-value"><?php echo array_sum($top_classes['data']) > 0 ? round(($top_classes['data'][0] / array_sum($top_classes['data'])) * 100, 1) : 0; ?>%</span></span>
                    </div>
                </div>
            </div>

            <!-- Recent Visits Table -->
            <div class="table-container" style="margin-bottom: 30px;">
                <div class="table-title">
                    <i class="fas fa-history"></i> Recent Clinic Visits (Last 10)
                </div>
                <div class="table-wrapper">
                    <?php if ($recent_visits->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student Name</th>
                                    <th>Class/Section</th>
                                    <th>Complaint</th>
                                    <th>Treatment</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $i = 1;
                                while ($row = $recent_visits->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['grade_section']); ?></td>
                                        <td><span class="count-badge"><?php echo htmlspecialchars($row['complaint'] ?: 'Not specified'); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['treatment'] ?: 'Pending'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['visit_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($row['visit_time'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-history"></i>
                            <h3>No Recent Visits</h3>
                            <p>No clinic visits found in the selected period</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Complaint Details Table -->
            <div class="table-container">
                <div class="table-title">
                    <i class="fas fa-stethoscope"></i> Complaint Analysis Details
                </div>
                <div class="table-wrapper">
                    <?php if (!empty($complaint_data['labels'])): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Complaint Type</th>
                                    <th>Cases</th>
                                    <th>Percentage</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_complaints = array_sum($complaint_data['data']);
                                foreach ($complaint_data['labels'] as $index => $complaint):
                                    $count = $complaint_data['data'][$index];
                                    $percentage = $total_complaints > 0 ? round(($count / $total_complaints) * 100, 1) : 0;
                                    $trend = $percentage > 20 ? 'High' : ($percentage > 10 ? 'Medium' : 'Low');
                                    $trend_color = $percentage > 20 ? '#ef4444' : ($percentage > 10 ? '#f59e0b' : '#10b981');
                                ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($complaint); ?></td>
                                        <td><span class="count-badge"><?php echo $count; ?></span></td>
                                        <td>
                                            <div class="percentage-bar">
                                                <div class="bar">
                                                    <div class="fill" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <span style="font-size: 12px; font-weight: 600;"><?php echo $percentage; ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="color: <?php echo $trend_color; ?>; font-weight: 600;">
                                                <?php echo $trend; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-stethoscope"></i>
                            <h3>No complaint data available</h3>
                            <p>No complaints recorded in the selected period</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Report Section (Hidden until printing) -->
    <div id="printReport" style="display: none;">
        <div class="print-section">
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 3px solid #4361ee; padding-bottom: 20px;">
                <h1 style="color: #4361ee; font-size: 28px; margin-bottom: 10px;">CLINIC STATISTICS REPORT</h1>
                <p style="color: #6c757d; font-size: 16px;">
                    Report Period: <?php echo date('F d, Y', strtotime($start_date)); ?> to <?php echo date('F d, Y', strtotime($end_date)); ?>
                </p>
                <p style="color: #6c757d; font-size: 14px;">Generated on: <span id="printDate"></span></p>
            </div>
            
            <!-- Key Statistics in Print -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px;">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #4361ee; margin-bottom: 5px;"><?php echo $overview_stats['total_patients']; ?></div>
                    <div style="font-size: 12px; color: #6c757d; text-transform: uppercase;">Total Patients</div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #4361ee; margin-bottom: 5px;"><?php echo $overview_stats['total_visits']; ?></div>
                    <div style="font-size: 12px; color: #6c757d; text-transform: uppercase;">Total Visits</div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #4361ee; margin-bottom: 5px;"><?php echo $overview_stats['total_classes']; ?></div>
                    <div style="font-size: 12px; color: #6c757d; text-transform: uppercase;">Active Classes</div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #4361ee; margin-bottom: 5px;"><?php echo $overview_stats['avg_daily_visits']; ?></div>
                    <div style="font-size: 12px; color: #6c757d; text-transform: uppercase;">Avg Daily Visits</div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #4361ee; margin-bottom: 5px;"><?php echo $overview_stats['avg_users_per_week']; ?></div>
                    <div style="font-size: 12px; color: #6c757d; text-transform: uppercase;">Avg Users/Week</div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #4361ee; margin-bottom: 5px;"><?php echo $overview_stats['completion_rate']; ?>%</div>
                    <div style="font-size: 12px; color: #6c757d; text-transform: uppercase;">Completion Rate</div>
                </div>
            </div>
            
            <!-- Summary Section -->
            <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3 style="color: #4361ee; margin-bottom: 15px; font-size: 18px;">Report Summary</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <p style="margin: 8px 0; color: #495057;">
                            <strong>Most Common Complaint:</strong> 
                            <span style="color: #4361ee;"><?php echo htmlspecialchars($overview_stats['most_common_complaint']); ?></span>
                            (<?php echo $overview_stats['most_common_count']; ?> cases)
                        </p>
                        <p style="margin: 8px 0; color: #495057;">
                            <strong>Treated Cases:</strong> <?php echo $overview_stats['treated_cases']; ?> of <?php echo $overview_stats['total_visits']; ?> total
                        </p>
                        <p style="margin: 8px 0; color: #495057;">
                            <strong>Pending Cases:</strong> <?php echo $overview_stats['pending_cases']; ?>
                        </p>
                    </div>
                    <div>
                        <p style="margin: 8px 0; color: #495057;">
                            <strong>Period Covered:</strong> <?php echo count($daily_data['dates']); ?> days
                        </p>
                        <p style="margin: 8px 0; color: #495057;">
                            <strong>Total Visits:</strong> <?php echo array_sum($daily_data['visits']); ?>
                        </p>
                        <p style="margin: 8px 0; color: #495057;">
                            <strong>Average per Day:</strong> <?php echo round(array_sum($daily_data['visits']) / max(1, count($daily_data['dates'])), 1); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Complaints Table in Print -->
            <?php if (!empty($complaint_data['labels'])): ?>
            <div style="margin-bottom: 30px;">
                <h3 style="color: #4361ee; margin-bottom: 15px; font-size: 18px; border-bottom: 2px solid #4361ee; padding-bottom: 5px;">Complaint Distribution</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left; font-size: 12px;">Complaint Type</th>
                            <th style="padding: 10px; border: 1px solid #dee2e6; text-align: center; font-size: 12px;">Cases</th>
                            <th style="padding: 10px; border: 1px solid #dee2e6; text-align: center; font-size: 12px;">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_complaints = array_sum($complaint_data['data']);
                        foreach ($complaint_data['labels'] as $index => $complaint):
                            $count = $complaint_data['data'][$index];
                            $percentage = $total_complaints > 0 ? round(($count / $total_complaints) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #dee2e6; font-size: 12px;"><?php echo htmlspecialchars($complaint); ?></td>
                                <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center; font-size: 12px; font-weight: bold;"><?php echo $count; ?></td>
                                <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center; font-size: 12px;"><?php echo $percentage; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Recent Visits in Print -->
            <?php if ($recent_visits->num_rows > 0): 
                $recent_visits->data_seek(0); // Reset pointer
            ?>
            <div style="margin-bottom: 30px;">
                <h3 style="color: #4361ee; margin-bottom: 15px; font-size: 18px; border-bottom: 2px solid #4361ee; padding-bottom: 5px;">Recent Clinic Visits</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Student Name</th>
                            <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Class/Section</th>
                            <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Complaint</th>
                            <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Treatment</th>
                            <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        while ($row = $recent_visits->fetch_assoc()): 
                            if ($i > 5) break; // Show only 5 in print
                        ?>
                            <tr>
                                <td style="padding: 8px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td style="padding: 8px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($row['grade_section']); ?></td>
                                <td style="padding: 8px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($row['complaint'] ?: 'Not specified'); ?></td>
                                <td style="padding: 8px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($row['treatment'] ?: 'Pending'); ?></td>
                                <td style="padding: 8px; border: 1px solid #dee2e6;"><?php echo date('M d, Y', strtotime($row['visit_date'])); ?></td>
                            </tr>
                        <?php 
                            $i++;
                        endwhile; 
                        ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 12px;">
                <p>Clinic Management System - Statistics Report</p>
                <p>Page 1 of 1 | Generated by: <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'System'; ?></p>
            </div>
        </div>
    </div>

    <script>
        // Chart instances
        let visitsChart;
        let complaintChartInstance;
        let classesChartInstance;
        let currentChartType = 'daily';

        // Initialize Daily Visits Chart
        function initDailyChart() {
            const ctx = document.getElementById('visitsChart').getContext('2d');
            if (visitsChart) visitsChart.destroy();
            
            // Create gradient for line chart
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(67, 97, 238, 0.2)');
            gradient.addColorStop(1, 'rgba(67, 97, 238, 0.05)');
            
            visitsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($daily_data['labels']); ?>,
                    datasets: [{
                        label: 'Daily Visits',
                        data: <?php echo json_encode($daily_data['visits']); ?>,
                        borderColor: '#4361ee',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#4361ee',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            padding: 12,
                            cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    const date = <?php echo json_encode($daily_data['dates']); ?>[context.dataIndex];
                                    return `${date}: ${context.raw} visits`;
                                },
                                title: function() {
                                    return '';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            title: {
                                display: true,
                                text: 'Number of Visits',
                                color: '#6c757d',
                                font: {
                                    size: 13,
                                    weight: '600'
                                }
                            },
                            ticks: {
                                color: '#6c757d',
                                stepSize: 1,
                                callback: function(value) {
                                    if (Number.isInteger(value)) {
                                        return value;
                                    }
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            title: {
                                display: true,
                                text: 'Date',
                                color: '#6c757d',
                                font: {
                                    size: 13,
                                    weight: '600'
                                }
                            },
                            ticks: {
                                color: '#6c757d',
                                maxRotation: 45
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    animations: {
                        tension: {
                            duration: 1000,
                            easing: 'linear'
                        }
                    }
                }
            });
        }

        // Initialize Weekly Visits Chart
        function initWeeklyChart() {
            const ctx = document.getElementById('visitsChart').getContext('2d');
            if (visitsChart) visitsChart.destroy();
            
            // Create gradients for bars
            const gradient1 = ctx.createLinearGradient(0, 0, 0, 300);
            gradient1.addColorStop(0, 'rgba(67, 97, 238, 0.9)');
            gradient1.addColorStop(1, 'rgba(67, 97, 238, 0.6)');
            
            const gradient2 = ctx.createLinearGradient(0, 0, 0, 300);
            gradient2.addColorStop(0, 'rgba(16, 185, 129, 0.9)');
            gradient2.addColorStop(1, 'rgba(16, 185, 129, 0.6)');
            
            visitsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($weekly_stats['chart_labels']); ?>,
                    datasets: [
                        {
                            label: 'Total Visits',
                            data: <?php echo json_encode($weekly_stats['chart_visits']); ?>,
                            backgroundColor: gradient1,
                            borderColor: '#4361ee',
                            borderWidth: 1,
                            borderRadius: 4
                        },
                        {
                            label: 'Unique Patients',
                            data: <?php echo json_encode($weekly_stats['chart_patients']); ?>,
                            backgroundColor: gradient2,
                            borderColor: '#10b981',
                            borderWidth: 1,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            padding: 12,
                            cornerRadius: 6
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            title: {
                                display: true,
                                text: 'Count',
                                color: '#6c757d',
                                font: {
                                    size: 13,
                                    weight: '600'
                                }
                            },
                            ticks: {
                                color: '#6c757d',
                                stepSize: 1
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            title: {
                                display: true,
                                text: 'Week',
                                color: '#6c757d',
                                font: {
                                    size: 13,
                                    weight: '600'
                                }
                            },
                            ticks: {
                                color: '#6c757d'
                            }
                        }
                    }
                }
            });
        }

        // Initialize Complaint Chart
        function initComplaintChart() {
            const ctx = document.getElementById('complaintChart').getContext('2d');
            if (complaintChartInstance) complaintChartInstance.destroy();
            
            complaintChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($complaint_data['labels']); ?>,
                    datasets: [{
                        data: <?php echo json_encode($complaint_data['data']); ?>,
                        backgroundColor: <?php echo json_encode($complaint_data['colors']); ?>,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: {
                                    size: 12
                                },
                                color: '#495057'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            padding: 12,
                            cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} cases (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '65%',
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 1000
                    }
                }
            });
        }

        // Initialize Classes Chart
        function initClassesChart() {
            const ctx = document.getElementById('classesChart').getContext('2d');
            if (classesChartInstance) classesChartInstance.destroy();
            
            // Create gradients for each bar
            const colors = [
                'rgba(67, 97, 238, 0.9)',
                'rgba(58, 12, 163, 0.9)',
                'rgba(114, 9, 183, 0.9)',
                'rgba(247, 37, 133, 0.9)',
                'rgba(76, 201, 240, 0.9)'
            ];
            
            const gradients = colors.map((color, index) => {
                const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, color);
                gradient.addColorStop(1, color.replace('0.9', '0.6'));
                return gradient;
            });
            
            classesChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($top_classes['labels']); ?>,
                    datasets: [{
                        label: 'Number of Visits',
                        data: <?php echo json_encode($top_classes['data']); ?>,
                        backgroundColor: gradients,
                        borderColor: colors.map(color => color.replace('0.9', '1')),
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false
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
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            padding: 12,
                            cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    const total = <?php echo array_sum($top_classes['data']); ?>;
                                    const percentage = total > 0 ? Math.round((context.raw / total) * 100) : 0;
                                    return `${context.label}: ${context.raw} visits (${percentage}%)`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            title: {
                                display: true,
                                text: 'Number of Visits',
                                color: '#6c757d',
                                font: {
                                    size: 13,
                                    weight: '600'
                                }
                            },
                            ticks: {
                                color: '#6c757d',
                                stepSize: 1,
                                callback: function(value) {
                                    if (Number.isInteger(value)) {
                                        return value;
                                    }
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            title: {
                                display: true,
                                text: 'Class / Section',
                                color: '#6c757d',
                                font: {
                                    size: 13,
                                    weight: '600'
                                }
                            },
                            ticks: {
                                color: '#6c757d',
                                maxRotation: 45
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        // Chart toggle functions
        function showDailyChart() {
            currentChartType = 'daily';
            document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            initDailyChart();
        }

        function showWeeklyChart() {
            currentChartType = 'weekly';
            document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            initWeeklyChart();
        }

        // Print Report Function
        function printReport() {
            // Update the print date
            const now = new Date();
            const printDate = now.toLocaleDateString() + ' ' + now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            document.getElementById('printDate').textContent = printDate;
            
            // Get print content
            const printContent = document.getElementById('printReport').innerHTML;
            const originalContent = document.body.innerHTML;
            
            // Open print window
            const printWindow = window.open('', '', 'width=1200,height=800');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Clinic Statistics Report</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            padding: 30px;
                            color: #333;
                            background: white;
                        }
                        @media print {
                            body { padding: 20px; }
                            .no-print { display: none !important; }
                            .print-section { display: block !important; }
                        }
                        @page {
                            size: auto;
                            margin: 15mm;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 15px 0;
                            font-size: 12px;
                        }
                        th {
                            background: #f8f9fa;
                            color: #4361ee;
                            padding: 10px;
                            border: 1px solid #dee2e6;
                            text-align: left;
                            font-weight: 600;
                        }
                        td {
                            padding: 8px 10px;
                            border: 1px solid #dee2e6;
                        }
                        .stat-grid {
                            display: grid;
                            grid-template-columns: repeat(3, 1fr);
                            gap: 15px;
                            margin: 20px 0;
                        }
                        .stat-item {
                            background: #f8f9fa;
                            padding: 15px;
                            border-radius: 8px;
                            text-align: center;
                        }
                        .stat-value {
                            font-size: 24px;
                            font-weight: bold;
                            color: #4361ee;
                            margin-bottom: 5px;
                        }
                        .stat-label {
                            font-size: 12px;
                            color: #6c757d;
                            text-transform: uppercase;
                        }
                        .report-header {
                            text-align: center;
                            margin-bottom: 30px;
                            border-bottom: 3px solid #4361ee;
                            padding-bottom: 20px;
                        }
                        .section-title {
                            color: #4361ee;
                            font-size: 18px;
                            margin: 25px 0 15px 0;
                            font-weight: bold;
                            border-bottom: 2px solid #e9ecef;
                            padding-bottom: 5px;
                        }
                        .summary-box {
                            background: #f8f9fa;
                            padding: 20px;
                            border-radius: 8px;
                            margin: 20px 0;
                        }
                        .footer {
                            margin-top: 40px;
                            padding-top: 20px;
                            border-top: 1px solid #dee2e6;
                            text-align: center;
                            color: #6c757d;
                            font-size: 12px;
                        }
                    </style>
                </head>
                <body>
                    ${printContent}
                    <script>
                        window.onload = function() {
                            window.print();
                            setTimeout(() => {
                                window.close();
                            }, 500);
                        }
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        // Export to PDF Function (simplified version)
        function exportToPDF() {
            alert('PDF export functionality would typically require a server-side library like TCPDF or mPDF. This is a placeholder for the export feature.');
            // In a real implementation, you would:
            // 1. Submit a form to a PHP script that generates PDF
            // 2. Use libraries like TCPDF, mPDF, or DomPDF
            // 3. Return the PDF file for download
        }

        // Initialize all charts on page load
        document.addEventListener('DOMContentLoaded', function() {
            initDailyChart();
            initComplaintChart();
            initClassesChart();
            
            // Animate stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Animate chart containers
            const chartContainers = document.querySelectorAll('.chart-container');
            chartContainers.forEach((container, index) => {
                container.style.opacity = '0';
                container.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    container.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    container.style.opacity = '1';
                    container.style.transform = 'translateY(0)';
                }, 300 + (index * 100));
            });
        });

        // Update real-time data badge
        function updateDataTimestamp() {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            const dateString = now.toLocaleDateString();
            
            document.querySelectorAll('.real-data-badge').forEach(badge => {
                const originalText = badge.textContent.includes('REAL DATA') ? 'REAL DATA' : 
                                   badge.textContent.includes('visits') ? 'visits' :
                                   badge.textContent.includes('cases') ? 'cases' :
                                   badge.textContent.includes('active classes') ? 'active classes' : badge.textContent;
                badge.innerHTML = `${originalText} • ${timeString}`;
                badge.title = `Last updated: ${dateString} ${timeString}`;
            });
            
            // Update every minute
            setTimeout(updateDataTimestamp, 60000);
        }

        // Start timestamp update
        setTimeout(updateDataTimestamp, 1000);
    </script>
</body>
</html>

<?php
if(isset($conn)) $conn->close();
?>