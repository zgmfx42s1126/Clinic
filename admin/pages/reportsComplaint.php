<?php
// Database connection
include '../includes/conn.php'; 
include $_SERVER['DOCUMENT_ROOT'] . '/clinic/admin/includes/sidebar.php';

// Add image path check
$web_path = '/clinic/assets/pictures/format.png';
$server_path = $_SERVER['DOCUMENT_ROOT'] . $web_path;
$image_exists = file_exists($server_path);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if this is an export request
if (isset($_GET['export']) && $_GET['export'] == 'true') {
    exportComplaintReport();
    exit;
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d'); // Changed to today as default
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Changed to today as default
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : "today"; // Changed default to today
$grade_section = isset($_GET['grade_section']) ? $_GET['grade_section'] : '';

// Get selected month from GET parameter (for backward compatibility)
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Update dates based on report type (only if dates are default or empty)
if ((empty($_GET['start_date']) && empty($_GET['end_date'])) || 
    ($start_date == date('Y-m-d') && $end_date == date('Y-m-d'))) {
    
    $endDateObj = new DateTime($end_date);
    $startDateObj = new DateTime($end_date);
    
    switch($report_type) {
        case "today":
            $start_date = $end_date = date('Y-m-d');
            break;
        case 'weekly':
            $startDateObj->modify('-7 days');
            $start_date = $startDateObj->format('Y-m-d');
            break;
        case 'monthly':
            $startDateObj = new DateTime(date('Y-m-01')); // First day of current month
            $start_date = $startDateObj->format('Y-m-d');
            $endDateObj = new DateTime(date('Y-m-t')); // Last day of current month
            $end_date = $endDateObj->format('Y-m-d');
            break;
        case 'yearly':
            $startDateObj->modify('-1 year');
            $start_date = $startDateObj->format('Y-m-d');
            break;
    }
}

// Pagination parameters for detailed records
// FIX: Capture per_page parameter from URL
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($records_per_page, [10, 25, 50, 100])) {
    $records_per_page = 10; // Default to 10 if invalid value
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $records_per_page;

// =======================
// COMPLAINT REPORT QUERY (using date range)
// =======================
$complaint_sql = "
    SELECT 
        complaint,
        COUNT(*) AS total_cases
    FROM clinic_records
    WHERE complaint IS NOT NULL AND complaint != ''
    AND date BETWEEN ? AND ?
";

$complaint_params = array($start_date, $end_date);
$complaint_types = "ss";

if (!empty($grade_section)) {
    $complaint_sql .= " AND grade_section = ?";
    $complaint_params[] = $grade_section;
    $complaint_types .= "s";
}

$complaint_sql .= " GROUP BY complaint ORDER BY total_cases DESC";

$complaint_stmt = $conn->prepare($complaint_sql);
$complaint_stmt->bind_param($complaint_types, ...$complaint_params);
$complaint_stmt->execute();
$complaint_result = $complaint_stmt->get_result();

// =======================
// DETAILED RECORDS QUERY WITH PAGINATION
// =======================
// First get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM clinic_records
    WHERE date BETWEEN ? AND ?
";

$count_params = array($start_date, $end_date);
$count_types = "ss";

if (!empty($grade_section)) {
    $count_sql .= " AND grade_section = ?";
    $count_params[] = $grade_section;
    $count_types .= "s";
}

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($count_types, ...$count_params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_row = $count_result->fetch_assoc();
$total_records = $total_row['total'] ?? 0;
$count_stmt->close();

// Calculate total pages
$total_pages = ceil($total_records / $records_per_page);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $records_per_page;
}

// Now get paginated results
$detailed_sql = "
    SELECT 
        student_id,
        name,
        grade_section,
        complaint,
        treatment,
        date,
        time
    FROM clinic_records
    WHERE date BETWEEN ? AND ?
";

$detailed_params = array($start_date, $end_date);
$detailed_types = "ss";

if (!empty($grade_section)) {
    $detailed_sql .= " AND grade_section = ?";
    $detailed_params[] = $grade_section;
    $detailed_types .= "s";
}

$detailed_sql .= " ORDER BY date DESC, time DESC LIMIT ? OFFSET ?";
$detailed_params[] = $records_per_page;
$detailed_params[] = $offset;
$detailed_types .= "ii";

$detailed_stmt = $conn->prepare($detailed_sql);
$detailed_stmt->bind_param($detailed_types, ...$detailed_params);
$detailed_stmt->execute();
$detailed_result = $detailed_stmt->get_result();

// Calculate starting number for current page
$start_number = ($page - 1) * $records_per_page + 1;

// Get all grade sections for filter dropdown - based on date filter
$all_grades_sql = "SELECT DISTINCT grade_section FROM clinic_records WHERE grade_section IS NOT NULL AND grade_section != ''";
$all_grades_params = array();
$all_grades_types = "";
$all_grades_where = "";

if (!empty($start_date) && !empty($end_date)) {
    $all_grades_where .= " AND date BETWEEN ? AND ?";
    $all_grades_params[] = $start_date;
    $all_grades_params[] = $end_date;
    $all_grades_types .= "ss";
}

$all_grades_sql .= $all_grades_where . " ORDER BY grade_section ASC";

if (!empty($all_grades_params)) {
    $all_grades_stmt = $conn->prepare($all_grades_sql);
    $all_grades_stmt->bind_param($all_grades_types, ...$all_grades_params);
    $all_grades_stmt->execute();
    $all_grades_result = $all_grades_stmt->get_result();
} else {
    $all_grades_result = $conn->query($all_grades_sql);
}

// Helper function to clean and format data
function cleanData($data) {
    $data = htmlspecialchars($data);
    $data = trim($data);
    // Remove any strange characters but keep basic punctuation
    $data = preg_replace('/[^\x20-\x7E]/', '', $data);
    return $data;
}

// Helper function to format time properly
function formatTime($time) {
    if (empty($time)) return 'N/A';
    
    $time = trim($time);
    
    // Handle cases like "13:31 AM" which should be "01:31 PM"
    if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $time, $matches)) {
        $hour = intval($matches[1]);
        $minute = $matches[2];
        $ampm = strtoupper($matches[3]);
        
        // Fix 24-hour to 12-hour conversion
        if ($hour >= 12) {
            if ($hour > 12) $hour -= 12;
            $ampm = 'PM';
        } else {
            if ($hour == 0) $hour = 12;
            $ampm = 'AM';
        }
        
        return sprintf('%d:%s %s', $hour, $minute, $ampm);
    }
    
    // Handle cases like "PM 0" or just numbers
    if (preg_match('/\d{1,2}/', $time, $matches)) {
        $hour = intval($matches[0]);
        $ampm = ($hour >= 12) ? 'PM' : 'AM';
        if ($hour > 12) $hour -= 12;
        if ($hour == 0) $hour = 12;
        return sprintf('%d:00 %s', $hour, $ampm);
    }
    
    // Default: try to format as time
    try {
        return date('h:i A', strtotime($time));
    } catch (Exception $e) {
        return $time;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Complaint Report</title>
    <link rel="stylesheet" href="../assets/css/reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Preload the background image for better performance -->
    <?php if ($image_exists): ?>
    <link rel="preload" as="image" href="<?php echo $web_path; ?>">
    <?php endif; ?>
    
    <style>
        /* Image preloader (hidden) - ensures image is loaded */
        .image-preloader {
            background-image: url('<?php echo $web_path; ?>');
            width: 0;
            height: 0;
            overflow: hidden;
            visibility: hidden;
            position: absolute;
        }
        
        /* Background image for on-screen display */
        .report-background {
            position: relative;
        }

        .report-background::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            <?php if ($image_exists): ?>
            background-image: url('<?php echo $web_path; ?>');
            <?php endif; ?>
            background-size: contain;
            background-position: 20px 20px;
            background-repeat: no-repeat;
            opacity: 0.1;
            z-index: 0;
            pointer-events: none;
        }
        
        .report-content {
            position: relative;
            z-index: 1;
        }

        /* ================== UPDATED TABLE STYLES TO MATCH IMAGE ================== */
        /* Simple table styling matching the reference image */
        .simple-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            table-layout: fixed;
        }
        
        .simple-table th {
            background-color: #4361ee;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #ddd;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .simple-table td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
            word-wrap: break-word;
        }
        
        .simple-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .simple-table tbody tr:hover {
            background-color: #f0f4ff;
        }
        
        /* Numbering column */
        .simple-table th:first-child,
        .simple-table td:first-child {
            width: 50px;
            text-align: center;
        }
        
        /* Column widths similar to image - adjust as needed */
        .simple-table th:nth-child(1), .simple-table td:nth-child(1) { width: 5%; }  /* # */
        .simple-table th:nth-child(2), .simple-table td:nth-child(2) { width: 12%; } /* Student ID */
        .simple-table th:nth-child(3), .simple-table td:nth-child(3) { width: 22%; } /* Name */
        .simple-table th:nth-child(4), .simple-table td:nth-child(4) { width: 18%; } /* Grade & Section */
        .simple-table th:nth-child(5), .simple-table td:nth-child(5) { width: 15%; } /* Complaint */
        .simple-table th:nth-child(6), .simple-table td:nth-child(6) { width: 15%; } /* Treatment */
        .simple-table th:nth-child(7), .simple-table td:nth-child(7) { width: 8%; }  /* Date */
        .simple-table th:nth-child(8), .simple-table td:nth-child(8) { width: 5%; }  /* Time */
        
        /* For the complaint summary table */
        .complaint-summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .complaint-summary-table th {
            background-color: #4361ee;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #ddd;
        }
        
        .complaint-summary-table td {
            padding: 10px 8px;
            border: 1px solid #ddd;
        }
        
        .complaint-summary-table th:first-child,
        .complaint-summary-table td:first-child {
            width: 50px;
            text-align: center;
        }
        
        .complaint-summary-table th:nth-child(2), .complaint-summary-table td:nth-child(2) { width: 50%; }
        .complaint-summary-table th:nth-child(3), .complaint-summary-table td:nth-child(3) { width: 25%; }
        .complaint-summary-table th:nth-child(4), .complaint-summary-table td:nth-child(4) { width: 25%; }
        
        /* Compact view for better printing */
        .compact-view .simple-table,
        .compact-view .complaint-summary-table {
            font-size: 12px;
        }
        
        .compact-view .simple-table th,
        .compact-view .simple-table td,
        .compact-view .complaint-summary-table th,
        .compact-view .complaint-summary-table td {
            padding: 6px 4px;
        }
        
        /* ==================== FIXED PRINT STYLES ==================== */
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: none !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                width: 100% !important;
                height: auto !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            /* Hide the on-screen content when printing */
            .main-content,
            .report-background,
            .report-content {
                display: none !important;
            }
            
            /* Show the print template */
            .print-template {
                display: block !important;
                width: 100% !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }
            
            .print-page {
                width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 0 !important;
                position: relative;
                page-break-after: always;
                page-break-inside: avoid;
                box-sizing: border-box;
                
                /* Background image covering entire page */
                <?php if ($image_exists): ?>
                background-image: url('<?php echo $web_path; ?>') !important;
                <?php endif; ?>
                background-size: 210mm 297mm !important;
                background-position: center top !important;
                background-repeat: no-repeat !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                background-color: white !important;
            }
            
            /* Last page shouldn't have page break */
            .print-page:last-child {
                page-break-after: auto !important;
            }
            
            .print-content {
                position: relative;
                width: 100% !important;
                height: 100% !important;
                padding: 15mm 20mm 20mm 20mm !important;
                box-sizing: border-box;
                background: transparent !important;
                z-index: 2;
            }
            
            /* Print template styles */
            .print-template .simple-table,
            .print-template .complaint-summary-table {
                font-size: 10pt !important;
                border: 1px solid #000 !important;
                background: rgba(255, 255, 255, 0.95) !important;
            }
            
            .print-template .simple-table th,
            .print-template .simple-table td,
            .print-template .complaint-summary-table th,
            .print-template .complaint-summary-table td {
                border: 1px solid #000 !important;
                padding: 6px 4px !important;
                background: rgba(255, 255, 255, 0.95) !important;
            }
            
            .print-template .simple-table th,
            .print-template .complaint-summary-table th {
                background-color: #f0f0f0 !important;
                color: #000 !important;
                font-weight: bold !important;
            }
            
            /* Make sure print content is visible */
            .document-header,
            .report-header,
            .report-info,
            .report-stats,
            .print-table-container,
            .print-table-container h3,
            .report-footer {
                background: rgba(255, 255, 255, 0.95) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            /* Remove on-screen background when printing */
            .report-background::before {
                display: none !important;
            }
        }

        /* Hide print template on screen */
        .print-template {
            display: none;
        }

        /* Your existing styles below - updated for compatibility */
        .table-wrapper {
            flex: 1;
            min-height: 400px;
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 0 0 8px 8px;
            background: white;
            position: relative;
            z-index: 1;
        }
        
        .table-container {
            margin-bottom: 30px;
            min-height: 500px;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
        }
        
        .section-title {
            background: #f8fafc;
            padding: 18px 20px;
            border-radius: 8px 8px 0 0;
            border-bottom: 2px solid #4361ee;
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
            position: relative;
            z-index: 2;
        }
        
        .table-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        
        .action-btn.print {
            background: #3b82f6;
            color: white;
        }
        
        .action-btn.print:hover {
            background: #2563eb;
        }
        
        /* Percentage bar styling */
        .percentage-bar {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .bar-container {
            flex: 1;
            height: 20px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #4361ee, #3a0ca3);
            border-radius: 10px;
        }
        
        .case-count {
            background: #4361ee;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        /* Your existing styles continue... */
        .date-range-filter {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }
        
        .date-group {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 200px;
        }
        
        .date-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #4b5563;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .date-group input,
        .date-group select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            width: 100%;
            transition: border-color 0.3s;
        }
        
        .date-group input:focus,
        .date-group select:focus {
            outline: none;
            border-color: #4361ee;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 24px;
        }
        
        .filter-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-apply {
            background: #4361ee;
            color: white;
        }
        
        .btn-apply:hover {
            background: #3a56d4;
        }
        
        .btn-reset {
            background: #6c757d;
            color: white;
        }
        
        .btn-reset:hover {
            background: #5a6268;
        }
        
        .table-controls {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            position: relative;
            z-index: 2;
        }
        
        .search-section {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box {
            padding: 8px 12px;
            border: 2px solid #ced4da;
            border-radius: 6px;
            min-width: 250px;
            font-size: 14px;
        }
        
        .grade-section-select {
            padding: 8px 12px;
            border: 2px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            min-width: 200px;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        
        .grade-section-select:hover {
            border-color: #4361ee;
        }
        
        .grade-section-select:focus {
            outline: none;
            border-color: #4361ee;
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .stats-cards {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }
        
        .stat-card {
            flex: 1;
            background: linear-gradient(135deg, #ffffffff 0%, #ffffffff 100%);
            color: white;
            padding: 25px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.2);
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .stat-card .number {
            font-size: 42px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #4361ee;
        }
        
        .stat-card .label {
            font-size: 16px;
            opacity: 0.9;
            color: #666;
        }
        
        .controls {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .btn-export {
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }
        
        .btn-export:hover {
            background: #0da271;
        }
        
        .main-content {
            padding: 25px;
        }
        
        .container {
            max-width: 1800px;
            margin: 0 auto;
        }
        
        .no-data {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 350px;
            color: #6c757d;
            position: relative;
            z-index: 1;
        }
        
        .no-data i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #d1d5db;
        }
        
        .no-data h3 {
            margin-bottom: 10px;
            color: #4b5563;
        }
        
        .header {
            background: linear-gradient(135deg, #4361ee 0%, #3a56d4 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(67, 97, 238, 0.2);
            position: relative;
            z-index: 2;
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
        
        .month-display {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            position: relative;
            z-index: 2;
        }
        
        .month-display h2 {
            color: #4361ee;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 22px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 25px;
            gap: 10px;
            position: relative;
            z-index: 2;
        }
        
        .pagination-btn {
            padding: 8px 16px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .pagination-btn:hover:not(.disabled) {
            background: #4361ee;
            color: white;
            border-color: #4361ee;
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-numbers {
            display: flex;
            gap: 5px;
        }
        
        .page-number {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            min-width: 40px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .page-number:hover {
            background: #f0f4ff;
            border-color: #4361ee;
        }
        
        .page-number.active {
            background: #4361ee;
            color: white;
            border-color: #4361ee;
        }
        
        .records-per-page {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            margin-left: auto;
        }
        
        .records-per-page select {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        
        .pagination-info {
            text-align: center;
            margin-top: 10px;
            color: #666;
            font-size: 14px;
        }
        
        .table-info {
            font-size: 14px;
            color: #4361ee;
            background: #f0f4ff;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #dbe4ff;
            font-weight: 600;
        }
        
        .btn-print-whole {
            background: #f59e0b;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }
        
        .btn-print-whole:hover {
            background: #d97706;
        }
    </style>
</head>
<body>
    <!-- Hidden image preloader -->
    <div class="image-preloader"></div>
    
    <!-- Main Content Area with sidebar offset -->
    <div class="main-content report-background no-print">
        <div class="container report-content">
            <!-- Header -->
            <div class="header">
                <h1>
                    <i class="fa-solid fa-chart-column"></i>
                    Complaint Analysis Report
                </h1>
                <p>Analysis of patient complaints</p>
                <div class="table-actions">
                    <button class="action-btn print" onclick="printWholePage()">
                        <i class="fas fa-print"></i> Print Whole Page
                    </button>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="date-range-filter">
                <div class="date-group">
                    <label for="startDate">
                        <i class="fas fa-calendar-day"></i>
                        Start Date
                    </label>
                    <input type="date" id="startDate" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="date-group">
                    <label for="endDate">
                        <i class="fas fa-calendar-day"></i>
                        End Date
                    </label>
                    <input type="date" id="endDate" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                
                <div class="date-group">
                    <label for="reportType">
                        <i class="fas fa-chart-bar"></i>
                        Report Type
                    </label>
                    <select id="reportType" name="report_type">
                        <option value="today" <?php echo $report_type == 'today' ? 'selected' : ''; ?>>Today's Analysis</option>
                        <option value="weekly" <?php echo $report_type == 'weekly' ? 'selected' : ''; ?>>Weekly Analysis</option>
                        <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly Analysis</option>
                        <option value="yearly" <?php echo $report_type == 'yearly' ? 'selected' : ''; ?>>Yearly Analysis</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button class="filter-btn btn-apply" onclick="applyDateFilter()">
                        <i class="fas fa-filter"></i>
                        Apply Filter
                    </button>
                    <button class="filter-btn btn-reset" onclick="resetDateFilter()">
                        <i class="fas fa-redo"></i>
                        Reset
                    </button>
                </div>
            </div>

            <!-- Month Display -->
            <div class="month-display">
             
                
                <!-- Stats Cards -->
                <div class="stats-cards">
                    <?php 
                    $total_cases = 0;
                    $complaint_types = 0;
                    if ($complaint_result && $complaint_result->num_rows > 0) {
                        $complaint_types = $complaint_result->num_rows;
                        $complaint_result->data_seek(0);
                        while ($row = $complaint_result->fetch_assoc()) {
                            $total_cases += $row['total_cases'];
                        }
                        $complaint_result->data_seek(0);
                    }
                    ?>
                    <div class="stat-card">
                        <div class="number"><?php echo $complaint_types; ?></div>
                        <div class="label">Complaint Types</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $total_cases; ?></div>
                        <div class="label">Total Cases</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $total_records; ?></div>
                        <div class="label">Total Records</div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="content">
                <!-- Complaint Table - UPDATED TO SIMPLE TABLE -->
                <div class="table-container">
                    <div class="section-title">
                        <div><i class="fas fa-table"></i> Complaint Details</div>
                        <div class="table-actions">
                            <button class="action-btn print" onclick="printComplaintTable()">
                                <i class="fas fa-print"></i> Print Table
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-wrapper">
                        <?php if ($complaint_result && $complaint_result->num_rows > 0): ?>
                            <table class="complaint-summary-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Complaint</th>
                                        <th>Total Cases</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $count = 1;
                                    $total_cases = 0;
                                    $complaint_result->data_seek(0);
                                    while ($row = $complaint_result->fetch_assoc()) $total_cases += $row['total_cases'];
                                    $complaint_result->data_seek(0);
                                    while ($row = $complaint_result->fetch_assoc()):
                                        $percentage = $total_cases > 0 ? round(($row['total_cases'] / $total_cases) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo $count++; ?></td>
                                            <td><?php echo cleanData($row['complaint']); ?></td>
                                            <td style="text-align: center;"><span class="case-count"><?php echo $row['total_cases']; ?></span></td>
                                            <td>
                                                <div class="percentage-bar">
                                                    <div class="bar-container">
                                                        <div class="bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                                                    </div>
                                                    <span><?php echo $percentage; ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-clipboard-list"></i>
                                <h3>No complaint data for selected filters</h3>
                                <p>Try selecting a different date range or section.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Detailed Records Table - UPDATED TO SIMPLE TABLE -->
                <div class="table-container">
                    <div class="section-title">
                        <div><i class="fas fa-list"></i> Detailed Clinic Records</div>
                        <div class="table-actions">
                            <button class="action-btn print" onclick="printDetailedTable()">
                                <i class="fas fa-print"></i> Print Table
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-controls">
                        <div class="search-section">
                            <input type="text" id="searchInput" class="search-box" placeholder="Search records...">
                            
                            <!-- Grade & Section Filter -->
                            <select id="gradeSectionFilter" class="grade-section-select" onchange="applyFilters()">
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
                                Showing <?php echo min($records_per_page, $total_records - $offset); ?> of <?php echo $total_records; ?> records for <?php echo htmlspecialchars($grade_section); ?>
                                (<?php echo $records_per_page; ?> per page)
                            <?php else: ?>
                                Showing <?php echo min($records_per_page, $total_records - $offset); ?> of <?php echo $total_records; ?> records
                                (<?php echo $records_per_page; ?> per page)
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="table-wrapper">
                        <?php if ($detailed_result && $detailed_result->num_rows > 0): ?>
                            <table class="simple-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Grade & Section</th>
                                        <th>Complaint</th>
                                        <th>Treatment</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $current_number = $start_number;
                                    while ($row = $detailed_result->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><?php echo $current_number++; ?></td>
                                            <td><?php echo cleanData($row['student_id']); ?></td>
                                            <td><?php echo cleanData($row['name']); ?></td>
                                            <td><?php echo cleanData($row['grade_section']); ?></td>
                                            <td><?php echo cleanData($row['complaint']); ?></td>
                                            <td><?php echo cleanData($row['treatment']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($row['date'])); ?></td>
                                            <td><?php echo formatTime($row['time']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <!-- Previous Button -->
                                <button class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
                                        onclick="changePage(<?php echo $page - 1; ?>)" 
                                        <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-chevron-left"></i> Previous
                                </button>
                                
                                <!-- Page Numbers -->
                                <div class="page-numbers">
                                    <?php
                                    // Show first page
                                    if ($page > 3): ?>
                                        <button class="page-number" onclick="changePage(1)">1</button>
                                        <?php if ($page > 4): ?>
                                            <span class="page-number" style="border: none; background: transparent;">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Show pages around current page
                                    for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <button class="page-number <?php echo $i == $page ? 'active' : ''; ?>" 
                                                onclick="changePage(<?php echo $i; ?>)">
                                            <?php echo $i; ?>
                                        </button>
                                    <?php endfor; ?>
                                    
                                    <?php
                                    // Show last page
                                    if ($page < $total_pages - 2): ?>
                                        <?php if ($page < $total_pages - 3): ?>
                                            <span class="page-number" style="border: none; background: transparent;">...</span>
                                        <?php endif; ?>
                                        <button class="page-number" onclick="changePage(<?php echo $total_pages; ?>)">
                                            <?php echo $total_pages; ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Next Button -->
                                <button class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
                                        onclick="changePage(<?php echo $page + 1; ?>)" 
                                        <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                                    Next <i class="fas fa-chevron-right"></i>
                                </button>
                                
                                <!-- Records per page selector -->
                                <div class="records-per-page">
                                    <span>Show:</span>
                                    <select onchange="changeRecordsPerPage(this.value)" id="recordsPerPageSelect">
                                        <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
                                        <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                                    </select>
                                    <span>per page</span>
                                </div>
                            </div>
                            
                            <div class="pagination-info">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?> • 
                                Records <?php echo min($offset + 1, $total_records); ?>-<?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?>
                            </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-clipboard-list"></i>
                                <h3>No detailed records found for selected filters</h3>
                                <p>Try selecting a different date range or section.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PRINT TEMPLATE (Hidden by default, shown when printing) -->
    <div class="print-template" style="display: none;">
        <!-- PAGE 1 -->
        <div class="print-page">
            <div class="print-content">
                <!-- If your format.png already has the logo, you can remove this header block -->
                <div class="document-header" style="text-align: center; margin-bottom: 30px; padding: 15px; background: rgba(255,255,255,0.95); border-radius: 8px; border: 2px solid #4361ee;">
                    <div class="school-name" style="font-size: 24px; font-weight: bold;">HOLY CROSS OF MINTAL</div>
                    <div class="school-subtitle" style="font-size: 16px;">Clinic Management System</div>
                    <div class="school-accreditation" style="font-size: 12px; font-weight: bold;">LEVEL II PASSCOI ACCREDITED</div>
                </div>
                
                <div class="report-header" style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; background: rgba(255,255,255,0.95); padding: 15px; border-radius: 8px; border: 1px solid #666;">
                    <div class="report-title" style="font-size: 20px; font-weight: bold; text-transform: uppercase;">Complaint Analysis Report</div>
                    <div class="report-subtitle">Analysis of patient complaints</div>
                </div>
                
                <div class="report-info" style="display: flex; justify-content: space-between; margin-bottom: 20px; background: rgba(255,255,255,0.95); padding: 15px; border-radius: 8px; border: 1px solid #666;">
                    <div class="info-section">
                        <strong>Date Range:</strong> <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?>
                    </div>
                    <div class="info-section">
                        <strong>Generated:</strong> <?php echo date('F d, Y h:i A'); ?>
                    </div>
                </div>
                
                <?php if (!empty($grade_section)): ?>
                <div class="filter-info" style="margin-bottom: 20px; background: rgba(255,255,255,0.95); padding: 10px 15px; border-radius: 8px; border: 1px solid #666; text-align: center;">
                    <strong>Filtered by:</strong> <?php echo htmlspecialchars($grade_section); ?>
                </div>
                <?php endif; ?>
                
                <!-- Stats for print -->
                <div class="report-stats" style="display: flex; justify-content: space-around; margin-bottom: 30px; border: 2px solid #4361ee; padding: 15px; background: rgba(255,255,255,0.95); border-radius: 8px;">
                    <?php 
                    $total_cases = 0;
                    $complaint_types = 0;
                    if ($complaint_result && $complaint_result->num_rows > 0) {
                        $complaint_types = $complaint_result->num_rows;
                        $complaint_result->data_seek(0);
                        while ($row = $complaint_result->fetch_assoc()) {
                            $total_cases += $row['total_cases'];
                        }
                        $complaint_result->data_seek(0);
                    }
                    ?>
                    <div class="stat-item" style="text-align: center;">
                        <div class="stat-number" style="font-size: 24px; font-weight: bold; color: #4361ee;"><?php echo $complaint_types; ?></div>
                        <div class="stat-label" style="font-weight: bold;">Complaint Types</div>
                    </div>
                    <div class="stat-item" style="text-align: center;">
                        <div class="stat-number" style="font-size: 24px; font-weight: bold; color: #4361ee;"><?php echo $total_cases; ?></div>
                        <div class="stat-label" style="font-weight: bold;">Total Cases</div>
                    </div>
                    <div class="stat-item" style="text-align: center;">
                        <div class="stat-number" style="font-size: 24px; font-weight: bold; color: #4361ee;"><?php echo $total_records; ?></div>
                        <div class="stat-label" style="font-weight: bold;">Total Records</div>
                    </div>
                </div>
                
                <!-- Complaint Summary Table -->
                <div class="print-table-container" style="margin-bottom: 40px;">
                    <h3 style="text-align: center; margin: 0 0 15px 0; background: rgba(67, 97, 238, 0.9); color: white; padding: 10px 15px; border-radius: 6px; font-size: 18px;">Complaint Summary</h3>
                    
                    <?php if ($complaint_result && $complaint_result->num_rows > 0): ?>
                        <table class="complaint-summary-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="border: 2px solid #000; padding: 10px; text-align: center; width: 10%;">#</th>
                                    <th style="border: 2px solid #000; padding: 10px; text-align: center; width: 50%;">Complaint</th>
                                    <th style="border: 2px solid #000; padding: 10px; text-align: center; width: 20%;">Total Cases</th>
                                    <th style="border: 2px solid #000; padding: 10px; text-align: center; width: 20%;">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $count = 1;
                                $total_cases = 0;
                                $complaint_result->data_seek(0);
                                while ($row = $complaint_result->fetch_assoc()) $total_cases += $row['total_cases'];
                                $complaint_result->data_seek(0);
                                while ($row = $complaint_result->fetch_assoc()):
                                    $percentage = $total_cases > 0 ? round(($row['total_cases'] / $total_cases) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td style="border: 1px solid #666; padding: 8px; text-align: center; font-weight: bold;"><?php echo $count++; ?></td>
                                        <td style="border: 1px solid #666; padding: 8px; text-align: left;"><?php echo cleanData($row['complaint']); ?></td>
                                        <td style="border: 1px solid #666; padding: 8px; text-align: center; font-weight: bold;"><?php echo $row['total_cases']; ?></td>
                                        <td style="border: 1px solid #666; padding: 8px; text-align: center; font-weight: bold;"><?php echo $percentage; ?>%</td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; font-weight: bold; padding: 20px; border: 1px solid #666; background: rgba(255,255,255,0.95);">No complaint data for selected filters.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- PAGE 2 (Detailed Records - Can be multiple pages) -->
        <?php if ($detailed_result && $detailed_result->num_rows > 0): ?>
        <div class="print-page">
            <div class="print-content">
                <div class="document-header" style="text-align: center; margin-bottom: 30px; padding: 15px; background: rgba(255,255,255,0.95); border-radius: 8px; border: 2px solid #4361ee;">
                    <div class="school-name" style="font-size: 24px; font-weight: bold;">HOLY CROSS OF MINTAL</div>
                    <div class="school-subtitle" style="font-size: 16px;">Clinic Management System</div>
                    <div class="school-accreditation" style="font-size: 12px; font-weight: bold;">LEVEL II PASSCOI ACCREDITED</div>
                </div>
                
                <div class="print-table-container" style="margin-bottom: 30px;">
                    <h3 style="text-align: center; margin: 0 0 15px 0; background: rgba(67, 97, 238, 0.9); color: white; padding: 10px 15px; border-radius: 6px; font-size: 18px;">
                        Detailed Clinic Records (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)
                    </h3>
                    
                    <table class="simple-table" style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                        <thead>
                            <tr>
                                <th style="border: 2px solid #000; padding: 10px; text-align: center; width: 5%;">#</th>
                                <th style="border: 2px solid #000; padding: 10px; text-align: center; width: 12%;">Student ID</th>
                                <th style="border: 2px solid #000; padding: 10px; text-align: center; width: 20%;">Name</th>
                                <th style="border: 2px solid #000; padding: 10px; text-align: center; width: 20%;">Grade & Section</th>
                                <th style="border: 2px solid #000; padding: 10px; text-align: center; width: 15%;">Complaint</th>
                                <th style="border: 2px solid #000; padding: 10px; text-align: center; width: 15%;">Treatment</th>
                                <th style="border: 2px solid #000; padding: 10px; text-align: center; width: 8%;">Date</th>
                                <th style="border: 2px solid #000; padding: 10px; text-align: center; width: 5%;">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $current_number = $start_number;
                            $detailed_result->data_seek(0);
                            while ($row = $detailed_result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td style="border: 1px solid #666; padding: 8px; text-align: center; font-weight: bold;"><?php echo $current_number++; ?></td>
                                    <td style="border: 1px solid #666; padding: 8px; text-align: center;"><?php echo cleanData($row['student_id']); ?></td>
                                    <td style="border: 1px solid #666; padding: 8px; text-align: left;"><?php echo cleanData($row['name']); ?></td>
                                    <td style="border: 1px solid #666; padding: 8px; text-align: left;"><?php echo cleanData($row['grade_section']); ?></td>
                                    <td style="border: 1px solid #666; padding: 8px; text-align: left;"><?php echo cleanData($row['complaint']); ?></td>
                                    <td style="border: 1px solid #666; padding: 8px; text-align: left;"><?php echo cleanData($row['treatment']); ?></td>
                                    <td style="border: 1px solid #666; padding: 8px; text-align: center;"><?php echo date('Y-m-d', strtotime($row['date'])); ?></td>
                                    <td style="border: 1px solid #666; padding: 8px; text-align: center;"><?php echo formatTime($row['time']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 20px; font-size: 12px; color: #666; background: rgba(255,255,255,0.95); padding: 10px; border-radius: 6px; border: 1px solid #666; text-align: center;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?> • 
                        Records <?php echo min($offset + 1, $total_records); ?>-<?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?>
                    </div>
                </div>
                
   
            </div>
        </div>
        <?php else: ?>
        <!-- If no detailed records, still show a page with message -->
        <div class="print-page">
            <div class="print-content">
                <div class="document-header" style="text-align: center; margin-bottom: 30px; padding: 15px; background: rgba(255,255,255,0.95); border-radius: 8px; border: 2px solid #4361ee;">
                    <div class="school-name" style="font-size: 24px; font-weight: bold;">HOLY CROSS OF MINTAL</div>
                    <div class="school-subtitle" style="font-size: 16px;">Clinic Management System</div>
                    <div class="school-accreditation" style="font-size: 12px; font-weight: bold;">LEVEL II PASSCOI ACCREDITED</div>
                </div>
                
                <div class="print-table-container" style="margin-bottom: 30px; text-align: center;">
                    <h3 style="text-align: center; margin: 0 0 15px 0; background: rgba(67, 97, 238, 0.9); color: white; padding: 10px 15px; border-radius: 6px; font-size: 18px;">Detailed Clinic Records</h3>
                    <p style="text-align: center; font-weight: bold; padding: 20px; border: 1px solid #666; background: rgba(255,255,255,0.95); margin: 0 auto; max-width: 600px;">No detailed records found for selected filters.</p>
                </div>
                
                <div class="report-footer" style="margin-top: 50px; background: rgba(255,255,255,0.95); padding: 15px; border-radius: 8px; border: 1px solid #666;">
                    <div class="signature-line" style="display: flex; justify-content: space-around; align-items: center;">
                        <div class="signature-box" style="text-align: center; flex: 1; max-width: 300px;">
                            <div class="signature-title" style="margin-bottom: 40px; font-weight: bold;">Prepared by:</div>
                            <div class="signature-name" style="font-weight: bold; border-top: 2px solid #000; padding-top: 10px; font-size: 14px; width: 100%;">CLINIC ADMINISTRATOR</div>
                        </div>
                        
                        <div class="signature-box" style="text-align: center; flex: 1; max-width: 300px;">
                            <div class="signature-title" style="margin-bottom: 40px; font-weight: bold;">Noted by:</div>
                            <div class="signature-name" style="font-weight: bold; border-top: 2px solid #000; padding-top: 10px; font-size: 14px; width: 100%;">SCHOOL PRINCIPAL</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // ==================== FIXED PRINT WHOLE PAGE FUNCTION ====================
        function printWholePage() {
            // Store the current display state
            const mainContent = document.querySelector('.main-content');
            const printTemplate = document.querySelector('.print-template');
            
            if (!printTemplate) {
                console.error('Print template not found');
                alert('Print template not found. Please check the page structure.');
                return;
            }
            
            // Hide main content
            if (mainContent) {
                mainContent.style.display = 'none';
            }
            
            // Show print template
            printTemplate.style.display = 'block';
            
            // Force reflow to ensure template is rendered
            printTemplate.offsetHeight;
            
            // Use setTimeout to ensure DOM is updated before printing
            setTimeout(() => {
                // Print the document
                window.print();
                
                // Restore visibility after printing
                setTimeout(() => {
                    if (mainContent) {
                        mainContent.style.display = 'block';
                    }
                    printTemplate.style.display = 'none';
                }, 100);
            }, 100);
        }
        
        // Alternative method using a new window (more reliable)
        function printWholePageAlternative() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const gradeSection = document.getElementById('gradeSectionFilter').value;
            
            // Get current page data
            const totalPages = <?php echo $total_pages; ?>;
            const currentPage = <?php echo $page; ?>;
            const totalRecords = <?php echo $total_records; ?>;
            const startRecord = <?php echo min($offset + 1, $total_records); ?>;
            const endRecord = <?php echo min($offset + $records_per_page, $total_records); ?>;
            
            // Open new window for printing
            const printWindow = window.open('', '_blank', 'width=1200,height=800');
            
            // Write the print content
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Complaint Report - Complete Print</title>
                    <style>
                        @page {
                            size: A4;
                            margin: 15mm;
                        }
                        body {
                            font-family: Arial, sans-serif;
                            margin: 0;
                            padding: 20px;
                            color: #000;
                        }
                        .page-break {
                            page-break-after: always;
                        }
                        .no-break {
                            page-break-inside: avoid;
                        }
                        .print-header {
                            text-align: center;
                            margin-bottom: 30px;
                            border-bottom: 3px double #000;
                            padding-bottom: 20px;
                        }
                        .school-name {
                            font-size: 24px;
                            font-weight: bold;
                            margin-bottom: 5px;
                        }
                        .school-subtitle {
                            font-size: 16px;
                            margin-bottom: 5px;
                        }
                        .school-accreditation {
                            font-size: 12px;
                            font-weight: bold;
                            margin-bottom: 15px;
                        }
                        .report-title {
                            font-size: 20px;
                            font-weight: bold;
                            text-transform: uppercase;
                            margin-top: 20px;
                        }
                        .report-info {
                            display: flex;
                            justify-content: space-between;
                            margin: 20px 0;
                            padding: 15px;
                            background: #f8f9fa;
                            border: 1px solid #ddd;
                        }
                        .stats-container {
                            display: flex;
                            justify-content: space-around;
                            margin: 30px 0;
                            padding: 20px;
                            border: 2px solid #4361ee;
                            background: #f8f9fa;
                        }
                        .stat-item {
                            text-align: center;
                        }
                        .stat-number {
                            font-size: 24px;
                            font-weight: bold;
                            color: #4361ee;
                        }
                        .stat-label {
                            font-weight: bold;
                            margin-top: 5px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 20px 0;
                            font-size: 11px;
                        }
                        th {
                            background: #f0f0f0;
                            border: 1px solid #000;
                            padding: 8px;
                            font-weight: bold;
                            text-align: left;
                        }
                        td {
                            border: 1px solid #000;
                            padding: 6px;
                        }
                        tr:nth-child(even) {
                            background: #f9f9f9;
                        }
                        .signature-section {
                            margin-top: 50px;
                            padding-top: 20px;
                            border-top: 2px solid #000;
                        }
                        .signature-line {
                            display: flex;
                            justify-content: space-around;
                            margin-top: 40px;
                        }
                        .signature-box {
                            text-align: center;
                            width: 300px;
                        }
                        .signature-space {
                            height: 50px;
                            border-top: 1px solid #000;
                            margin-top: 20px;
                        }
                        @media print {
                            body {
                                padding: 15mm;
                            }
                            .no-print {
                                display: none;
                            }
                        }
                    </style>
                </head>
                <body>
                    <!-- Page 1: Header and Complaint Summary -->
                    <div class="print-header">
                        <div class="school-name">HOLY CROSS OF MINTAL</div>
                        <div class="school-subtitle">Clinic Management System</div>
                        <div class="school-accreditation">LEVEL II PASSCOI ACCREDITED</div>
                        <div class="report-title">Complaint Analysis Report</div>
                    </div>
                    
                    <div class="report-info">
                        <div>
                            <strong>Date Range:</strong> ${new Date(startDate).toLocaleDateString()} to ${new Date(endDate).toLocaleDateString()}
                        </div>
                        <div>
                            <strong>Generated:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
                        </div>
                        ${gradeSection ? `<div><strong>Filtered by:</strong> ${gradeSection}</div>` : ''}
                    </div>
                    
                    <div class="stats-container">
                        <div class="stat-item">
                            <div class="stat-number">${<?php echo $complaint_result ? $complaint_result->num_rows : 0; ?>}</div>
                            <div class="stat-label">Complaint Types</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">${<?php 
                                $total_cases = 0;
                                if ($complaint_result && $complaint_result->num_rows > 0) {
                                    $complaint_result->data_seek(0);
                                    while ($row = $complaint_result->fetch_assoc()) {
                                        $total_cases += $row['total_cases'];
                                    }
                                }
                                echo $total_cases; 
                            ?>}</div>
                            <div class="stat-label">Total Cases</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">${<?php echo $total_records; ?>}</div>
                            <div class="stat-label">Total Records</div>
                        </div>
                    </div>
                    
                    <h3 style="text-align: center; margin: 30px 0 15px 0;">Complaint Summary</h3>
                    
                    ${document.querySelector('.complaint-summary-table').outerHTML}
                    
                    <!-- Page Break -->
                    <div style="page-break-before: always;"></div>
                    
                    <!-- Page 2: Detailed Records -->
                    <div class="print-header">
                        <div class="school-name">HOLY CROSS OF MINTAL</div>
                        <div class="school-subtitle">Clinic Management System</div>
                        <div class="report-title">Detailed Clinic Records</div>
                    </div>
                    
                    <div class="report-info">
                        <div>
                            <strong>Page:</strong> ${currentPage} of ${totalPages}
                        </div>
                        <div>
                            <strong>Records:</strong> ${startRecord} to ${endRecord} of ${totalRecords}
                        </div>
                    </div>
                    
                    ${document.querySelector('.simple-table').outerHTML}
                    
                    <div class="signature-section">
                        <div class="signature-line">
                            <div class="signature-box">
                                <div style="margin-bottom: 40px; font-weight: bold;">Prepared by:</div>
                                <div class="signature-space"></div>
                                <div style="font-weight: bold; margin-top: 10px;">CLINIC ADMINISTRATOR</div>
                            </div>
                            <div class="signature-box">
                                <div style="margin-bottom: 40px; font-weight: bold;">Noted by:</div>
                                <div class="signature-space"></div>
                                <div style="font-weight: bold; margin-top: 10px;">SCHOOL PRINCIPAL</div>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            
            // Wait for content to load, then print
            setTimeout(() => {
                printWindow.focus();
                printWindow.print();
                
                // Close the print window after printing
                setTimeout(() => {
                    printWindow.close();
                }, 500);
            }, 500);
        }
        
        // Updated print functions for individual tables
        function printComplaintTable() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const gradeSection = document.getElementById('gradeSectionFilter').value;
            
            const win = window.open('', '', 'width=1200,height=700');
            win.document.write(`
                <html>
                <head>
                    <title>Complaint Summary Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h1 { text-align: center; color: #333; margin-bottom: 10px; }
                        .report-info { text-align: center; margin-bottom: 20px; color: #666; font-size: 14px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
                        th { background: #f2f2f2; border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold; }
                        td { border: 1px solid #ddd; padding: 6px 8px; }
                        tr:nth-child(even) { background: #f9f9f9; }
                        .percentage-bar { display: flex; align-items: center; gap: 10px; }
                        .bar-container { flex: 1; height: 15px; background: #e5e7eb; border-radius: 10px; overflow: hidden; }
                        .bar-fill { height: 100%; background: #4361ee; border-radius: 10px; }
                        @media print {
                            body { padding: 10px; }
                            table { font-size: 10px; }
                        }
                    </style>
                </head>
                <body>
                    <h1>COMPLAINT SUMMARY REPORT</h1>
                    <div class="report-info">
                        Date Range: ${new Date(startDate).toLocaleDateString()} to ${new Date(endDate).toLocaleDateString()}<br>
                        ${gradeSection ? `Section: ${gradeSection}<br>` : ''}
                        Generated: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
                    </div>
                    
                    ${document.querySelector('.complaint-summary-table').outerHTML}
                </body>
                </html>
            `);
            win.document.close();
            win.focus();
            setTimeout(() => win.print(), 500);
        }
        
        function printDetailedTable() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const gradeSection = document.getElementById('gradeSectionFilter').value;
            
            const win = window.open('', '', 'width=1200,height=700');
            win.document.write(`
                <html>
                <head>
                    <title>Clinic Records Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h1 { text-align: center; color: #333; margin-bottom: 10px; }
                        .report-info { text-align: center; margin-bottom: 20px; color: #666; font-size: 14px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; table-layout: fixed; }
                        th { background: #f2f2f2; border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold; }
                        td { border: 1px solid #ddd; padding: 6px 8px; word-wrap: break-word; }
                        tr:nth-child(even) { background: #f9f9f9; }
                        th:nth-child(1), td:nth-child(1) { width: 5%; }
                        th:nth-child(2), td:nth-child(2) { width: 12%; }
                        th:nth-child(3), td:nth-child(3) { width: 22%; }
                        th:nth-child(4), td:nth-child(4) { width: 18%; }
                        th:nth-child(5), td:nth-child(5) { width: 15%; }
                        th:nth-child(6), td:nth-child(6) { width: 15%; }
                        th:nth-child(7), td:nth-child(7) { width: 8%; }
                        th:nth-child(8), td:nth-child(8) { width: 5%; }
                        @media print {
                            body { padding: 10px; }
                            table { font-size: 10px; }
                        }
                    </style>
                </head>
                <body>
                    <h1>CLINIC RECORDS REPORT</h1>
                    <div class="report-info">
                        Date Range: ${new Date(startDate).toLocaleDateString()} to ${new Date(endDate).toLocaleDateString()}<br>
                        ${gradeSection ? `Section: ${gradeSection}<br>` : ''}
                        Page: <?php echo $page; ?> of <?php echo $total_pages; ?><br>
                        Generated: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
                    </div>
                    
                    ${document.querySelector('.simple-table').outerHTML}
                    
                    <div style="margin-top: 20px; text-align: center; font-size: 12px; color: #666;">
                        Records <?php echo min($offset + 1, $total_records); ?>-<?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?>
                    </div>
                </body>
                </html>
            `);
            win.document.close();
            win.focus();
            setTimeout(() => win.print(), 500);
        }
        
        // Your existing filter functions
        function applyFilters() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const reportType = document.getElementById('reportType').value;
            const gradeSection = document.getElementById('gradeSectionFilter').value;
            const perPageSelect = document.getElementById('recordsPerPageSelect');
            const currentPerPage = perPageSelect ? perPageSelect.value : 10;
            
            let url = `?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}&page=1&per_page=${currentPerPage}`;
            
            if (gradeSection) {
                url += `&grade_section=${encodeURIComponent(gradeSection)}`;
            }
            
            window.location.href = url;
        }
        
        function applyDateFilter() {
            applyFilters();
        }
        
        function resetDateFilter() {
            // Reset to Today's Analysis
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0];
            
            document.getElementById('startDate').value = todayStr;
            document.getElementById('endDate').value = todayStr;
            document.getElementById('reportType').value = 'today';
            document.getElementById('gradeSectionFilter').value = '';
            
            const perPageSelect = document.getElementById('recordsPerPageSelect');
            if (perPageSelect) {
                perPageSelect.value = '10';
            }
            
            applyFilters();
        }
        
        function changePage(newPage) {
            if (newPage < 1 || newPage > <?php echo $total_pages; ?>) return;
            
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const reportType = document.getElementById('reportType').value;
            const gradeSection = document.getElementById('gradeSectionFilter').value;
            const perPageSelect = document.getElementById('recordsPerPageSelect');
            const currentPerPage = perPageSelect ? perPageSelect.value : 10;
            
            let url = `?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}&page=${newPage}&per_page=${currentPerPage}`;
            
            if (gradeSection) {
                url += `&grade_section=${encodeURIComponent(gradeSection)}`;
            }
            
            window.location.href = url;
        }
        
        function changeRecordsPerPage(perPage) {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const reportType = document.getElementById('reportType').value;
            const gradeSection = document.getElementById('gradeSectionFilter').value;
            
            let url = `?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}&page=1&per_page=${perPage}`;
            
            if (gradeSection) {
                url += `&grade_section=${encodeURIComponent(gradeSection)}`;
            }
            
            window.location.href = url;
        }
        
        function exportToExcel() {
            window.location.href = `?export=true&month=<?php echo $selected_month; ?>`;
        }
        
        // Search function for detailed table
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const rows = document.querySelectorAll('.simple-table tbody tr');
            
            if (searchInput && rows.length > 0) {
                searchInput.addEventListener('keyup', () => {
                    const value = searchInput.value.toLowerCase();
                    
                    rows.forEach(row => {
                        if (row.innerText.toLowerCase().includes(value)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
            
            // Animate percentage bars on load
            const bars = document.querySelectorAll('.bar-fill');
            bars.forEach((bar, index) => {
                const currentWidth = bar.style.width;
                bar.style.width = '0%';
                
                setTimeout(() => {
                    bar.style.transition = 'width 1s ease-out';
                    bar.style.width = currentWidth;
                }, index * 100);
            });
        });
        
        // Auto-update date range based on report type
        document.getElementById('reportType').addEventListener('change', function() {
            const reportType = this.value;
            const endDateInput = document.getElementById('endDate');
            const startDateInput = document.getElementById('startDate');
            
            if (reportType === 'today') {
                const today = new Date();
                const todayStr = today.toISOString().split('T')[0];
                startDateInput.value = todayStr;
                endDateInput.value = todayStr;
                return;
            }
            
            const endDate = new Date(endDateInput.value);
            let startDate = new Date(endDate);
            
            switch(reportType) {
                case 'weekly':
                    startDate.setDate(startDate.getDate() - 7);
                    break;
                case 'monthly':
                    const firstDayOfMonth = new Date(endDate.getFullYear(), endDate.getMonth(), 1);
                    const lastDayOfMonth = new Date(endDate.getFullYear(), endDate.getMonth() + 1, 0);
                    startDate = firstDayOfMonth;
                    endDateInput.value = lastDayOfMonth.toISOString().split('T')[0];
                    break;
                case 'yearly':
                    startDate.setFullYear(startDate.getFullYear() - 1);
                    break;
            }
            
            const formatDate = (date) => {
                return date.toISOString().split('T')[0];
            };
            
            startDateInput.value = formatDate(startDate);
            
            // Clear grade section filter when report type changes
            document.getElementById('gradeSectionFilter').value = '';
        });
        
        // Clear grade section when dates change manually
        document.getElementById('startDate').addEventListener('change', function() {
            document.getElementById('gradeSectionFilter').value = '';
        });
        
        document.getElementById('endDate').addEventListener('change', function() {
            document.getElementById('gradeSectionFilter').value = '';
        });
    </script>
</body>
</html>

<?php
// Close the prepared statement for grade sections if it exists
if (isset($all_grades_stmt)) {
    $all_grades_stmt->close();
}
if(isset($conn)) $conn->close();
?>