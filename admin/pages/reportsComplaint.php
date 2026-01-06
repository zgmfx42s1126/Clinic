<?php
// Database connection
include '../includes/conn.php'; 
include $_SERVER['DOCUMENT_ROOT'] . '/clinic/admin/includes/sidebar.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if this is an export request
if (isset($_GET['export']) && $_GET['export'] == 'true') {
    exportComplaintReport();
    exit;
}

// Get date parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // Start of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'monthly';

// Get selected month from GET parameter (for backward compatibility)
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Get available months for dropdown
$months_sql = "SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') AS month 
               FROM clinic_records 
               WHERE complaint IS NOT NULL AND complaint != ''
               ORDER BY month DESC";
$months_result = $conn->query($months_sql);

// =======================
// COMPLAINT REPORT QUERY (using date range)
// =======================
$sql = "
    SELECT 
        complaint,
        COUNT(*) AS total_cases
    FROM clinic_records
    WHERE complaint IS NOT NULL AND complaint != ''
    AND date BETWEEN ? AND ?
    GROUP BY complaint
    ORDER BY total_cases DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// Reset pointer for table
$result->data_seek(0);

// =======================
// DETAILED RECORDS QUERY
// =======================
$detailed_sql = "
    SELECT 
        id,
        student_id,
        name,
        grade_section,
        complaint,
        treatment,
        date,
        time
    FROM clinic_records
    WHERE date BETWEEN ? AND ?
    ORDER BY date DESC, time DESC
";

$detailed_stmt = $conn->prepare($detailed_sql);
$detailed_stmt->bind_param("ss", $start_date, $end_date);
$detailed_stmt->execute();
$detailed_result = $detailed_stmt->get_result();

// =======================
// EXPORT FUNCTION
// =======================
function exportComplaintReport() {
    global $conn;
    
    $selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
    
    $sql = "
        SELECT 
            complaint,
            COUNT(*) AS total_cases
        FROM clinic_records
        WHERE complaint IS NOT NULL AND complaint != ''
        AND DATE_FORMAT(date, '%Y-%m') = ?
        GROUP BY complaint
        ORDER BY total_cases DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $selected_month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Calculate total cases
    $total_cases = 0;
    if ($result && $result->num_rows > 0) {
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            $total_cases += $row['total_cases'];
        }
        $result->data_seek(0);
    }
    
    // Headers
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="monthly_complaint_report_' . $selected_month . '.xls"');
    
    echo "<html>";
    echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">";
    echo "<table border='1'>";
    echo "<tr><th colspan='4' style='background:#0066cc;color:white;font-size:16px;padding:10px;'>MONTHLY COMPLAINT ANALYSIS REPORT</th></tr>";
    echo "<tr><th colspan='4' style='padding:8px;'>Month: " . date('F Y', strtotime($selected_month . '-01')) . "</th></tr>";
    echo "<tr><th colspan='4' style='padding:8px;'>Generated: " . date('F d, Y h:i A') . "</th></tr>";
    echo "<tr><th colspan='4' style='padding:8px;'>Total Cases: " . $total_cases . "</th></tr>";
    echo "<tr><td colspan='4'></td></tr>";
    echo "<tr style='background:#f2f2f2;'><th>#</th><th>Complaint Type</th><th>Total Cases</th><th>Percentage</th></tr>";
    
    if ($result && $result->num_rows > 0) {
        $count = 1;
        while ($row = $result->fetch_assoc()) {
            $percentage = $total_cases > 0 ? round(($row['total_cases'] / $total_cases) * 100, 2) : 0;
            echo "<tr>";
            echo "<td style='padding:6px;border:1px solid #000;'>" . $count++ . "</td>";
            echo "<td style='padding:6px;border:1px solid #000;'>" . htmlspecialchars($row['complaint']) . "</td>";
            echo "<td style='padding:6px;border:1px solid #000;'>" . $row['total_cases'] . "</td>";
            echo "<td style='padding:6px;border:1px solid #000;'>" . $percentage . "%</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='4' style='padding:10px;text-align:center;'>No data available for selected month</td></tr>";
    }
    
    echo "</table></html>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Complaint Report</title>
    <link rel="stylesheet" href="../assets/css/reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom styles for date range filter */
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
        
        /* Keep existing styles */
        .table-container {
            margin-bottom: 30px;
            min-height: 500px;
            display: flex;
            flex-direction: column;
        }
        
        .table-wrapper {
            flex: 1;
            min-height: 400px;
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: white;
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            margin-top: 20px;
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
        }
        
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #eef0f3;
            vertical-align: middle;
        }
        
        tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .stats-cards {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            flex: 1;
            background: linear-gradient(135deg, #ffffffff 0%, #ffffffff 100%);
            color: white;
            padding: 25px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.2);
            text-align: center;
        }
        
        .stat-card .number {
            font-size: 42px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .stat-card .label {
            font-size: 16px;
            opacity: 0.9;
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
        }
        
        .month-selector select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            min-width: 200px;
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
        
        .percentage-bar {
            display: flex;
            align-items: center;
            gap: 15px;
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
        
        .month-display {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .month-display h2 {
            color: #4361ee;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 22px;
        }
        
        /* Column widths for detailed table */
        #detailedTable th:nth-child(1), #detailedTable td:nth-child(1) { width: 5%; }
        #detailedTable th:nth-child(2), #detailedTable td:nth-child(2) { width: 10%; }
        #detailedTable th:nth-child(3), #detailedTable td:nth-child(3) { width: 15%; }
        #detailedTable th:nth-child(4), #detailedTable td:nth-child(4) { width: 15%; }
        #detailedTable th:nth-child(5), #detailedTable td:nth-child(5) { width: 15%; }
        #detailedTable th:nth-child(6), #detailedTable td:nth-child(6) { width: 15%; }
        #detailedTable th:nth-child(7), #detailedTable td:nth-child(7) { width: 10%; }
        #detailedTable th:nth-child(8), #detailedTable td:nth-child(8) { width: 15%; }
    </style>
</head>
<body>
    <!-- Main Content Area with sidebar offset -->
    <div class="main-content">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <h1>
                    <i class="fa-solid fa-chart-column"></i>
                    Monthly Complaint Report
                </h1>
                <p>Analysis of patient complaints by month</p>
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

            <!-- Controls (Original Month Selector) -->
            <div class="controls">
                <div class="month-selector">
                    <label for="monthSelect"><i class="fas fa-calendar-alt"></i> Select Month:</label>
                    <select id="monthSelect" onchange="changeMonth(this.value)">
                        <?php if ($months_result && $months_result->num_rows > 0): ?>
                            <?php while ($month_row = $months_result->fetch_assoc()): ?>
                                <option value="<?php echo $month_row['month']; ?>" 
                                    <?php echo $month_row['month'] == $selected_month ? 'selected' : ''; ?>>
                                    <?php echo date('F Y', strtotime($month_row['month'] . '-01')); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="<?php echo date('Y-m'); ?>"><?php echo date('F Y'); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="export-buttons">
                  
                    <button class="btn-export" onclick="printFullReport()">
                        <i class="fas fa-print"></i>
                        Print Report
                    </button>
                </div>
            </div>

            <!-- Month Display -->
            <div class="month-display">
                <h2>
                    <i class="fas fa-calendar-week"></i>
                    Analysis for <?php echo date('F Y', strtotime($selected_month . '-01')); ?>
                </h2>
                
                <!-- Stats Cards -->
                <div class="stats-cards">
                    <?php 
                    $total_cases = 0;
                    $total_records = 0;
                    if ($result && $result->num_rows > 0) {
                        $result->data_seek(0);
                        while ($row = $result->fetch_assoc()) {
                            $total_cases += $row['total_cases'];
                        }
                        $result->data_seek(0);
                    }
                    ?>
                    <div class="stat-card">
                        <div class="number"><?php echo $result ? $result->num_rows : 0; ?></div>
                        <div class="label">Complaint Types</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $total_cases; ?></div>
                        <div class="label">Total Cases</div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="content">
                <!-- Complaint Table -->
                <div class="table-container">
                    <div class="section-title">
                        <div><i class="fas fa-table"></i> Complaint Details</div>
                        <div class="table-actions">
                            <button class="action-btn print" onclick="printTable('complaintTable', 'Complaint Report')">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-wrapper">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <table id="complaintTable">
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
                                    $result->data_seek(0);
                                    while ($row = $result->fetch_assoc()) $total_cases += $row['total_cases'];
                                    $result->data_seek(0);
                                    while ($row = $result->fetch_assoc()):
                                        $percentage = $total_cases > 0 ? round(($row['total_cases'] / $total_cases) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo $count++; ?></td>
                                            <td><?php echo htmlspecialchars($row['complaint']); ?></td>
                                            <td><span class="case-count"><?php echo $row['total_cases']; ?></span></td>
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
                                <h3>No complaint data for selected date range</h3>
                                <p>Try selecting a different date range from the filter above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Detailed Records Table -->
                <div class="table-container">
                    <div class="section-title">
                        <div><i class="fas fa-list"></i> Detailed Clinic Records</div>
                        <div class="table-actions">
                            <button class="action-btn print" onclick="printTable('detailedTable', 'Clinic Records Report')">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-wrapper">
                        <?php if ($detailed_result && $detailed_result->num_rows > 0): ?>
                            <table id="detailedTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
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
                                    $i = 1;
                                    while ($row = $detailed_result->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><?php echo $row['id']; ?></td>
                                            <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['grade_section']); ?></td>
                                            <td><?php echo htmlspecialchars($row['complaint']); ?></td>
                                            <td><?php echo htmlspecialchars($row['treatment']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($row['date'])); ?></td>
                                            <td><?php echo date('h:i A', strtotime($row['time'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-clipboard-list"></i>
                                <h3>No detailed records found for selected date range</h3>
                                <p>Try selecting a different date range from the filter above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function changeMonth(month) {
            window.location.href = `?month=${month}`;
        }
        
        function applyDateFilter() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const reportType = document.getElementById('reportType').value;
            
            window.location.href = `?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}`;
        }
        
        function resetDateFilter() {
            const today = new Date().toISOString().split('T')[0];
            const firstDayOfMonth = new Date();
            firstDayOfMonth.setDate(1);
            const firstDayStr = firstDayOfMonth.toISOString().split('T')[0];
            
            document.getElementById('startDate').value = firstDayStr;
            document.getElementById('endDate').value = today;
            document.getElementById('reportType').value = 'monthly';
            
            applyDateFilter();
        }
        
        function exportToExcel() {
            window.location.href = `?export=true&month=<?php echo $selected_month; ?>`;
        }
        
        function printTable(tableId, title) {
            const table = document.getElementById(tableId);
            if (!table) {
                alert('Table not found');
                return;
            }
            
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const reportType = document.getElementById('reportType').value;
            
            const win = window.open('', '', 'width=1200,height=700');
            win.document.write(`
                <html>
                <head>
                    <title>${title}</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 30px; }
                        h1 { text-align: center; color: #4361ee; margin-bottom: 10px; }
                        .report-info { text-align: center; margin-bottom: 30px; color: #6c757d; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                        th { background: #f8f9fa; font-weight: bold; color: #4361ee; }
                        tr:nth-child(even) { background: #f9fafb; }
                        @media print {
                            body { padding: 10px; }
                            table { font-size: 11px; }
                        }
                    </style>
                </head>
                <body>
                    <h1>${title}</h1>
                    <div class="report-info">
                        Date Range: ${new Date(startDate).toLocaleDateString()} to ${new Date(endDate).toLocaleDateString()}<br>
                        Report Type: ${reportType.charAt(0).toUpperCase() + reportType.slice(1)} Analysis<br>
                        Generated: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
                    </div>
                    ${table.outerHTML}
                </body>
                </html>
            `);
            win.document.close();
            win.focus();
            setTimeout(() => win.print(), 500);
        }
        
        function printFullReport() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const reportType = document.getElementById('reportType').value;
            
            const printWindow = window.open('', '', 'width=1400,height=900');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Full Clinic Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 40px; background: #f5f7fa; }
                        .report-container { max-width: 1200px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
                        .report-header { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 3px solid #4361ee; }
                        .report-header h1 { color: #4361ee; font-size: 32px; margin-bottom: 10px; }
                        .report-info { text-align: center; margin-bottom: 30px; color: #6c757d; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th { background: #4361ee; color: white; padding: 15px; text-align: left; }
                        td { padding: 12px; border-bottom: 1px solid #eef0f3; }
                        .section-title { color: #4361ee; font-size: 20px; margin: 30px 0 15px 0; }
                        @media print {
                            body { padding: 0; background: white; }
                            .report-container { box-shadow: none; padding: 20px; }
                        }
                    </style>
                </head>
                <body>
                    <div class="report-container">
                        <div class="report-header">
                            <h1>Clinic Records Report</h1>
                            <div class="report-info">
                                Date Range: ${new Date(startDate).toLocaleDateString()} to ${new Date(endDate).toLocaleDateString()}<br>
                                Report Type: ${reportType.charAt(0).toUpperCase() + reportType.slice(1)} Analysis<br>
                                Generated on ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}
                            </div>
                        </div>
                        
                        <div class="section-title">Complaint Summary</div>
                        ${document.getElementById('complaintTable') ? document.getElementById('complaintTable').outerHTML : '<p>No complaint summary available</p>'}
                        
                        <div class="section-title">Detailed Clinic Records</div>
                        ${document.getElementById('detailedTable') ? document.getElementById('detailedTable').outerHTML : '<p>No detailed records available</p>'}
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            
            setTimeout(() => {
                printWindow.focus();
                printWindow.print();
            }, 500);
        }
        
        // Animate percentage bars on load
        document.addEventListener('DOMContentLoaded', function() {
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
            
            const endDate = new Date(endDateInput.value);
            let startDate = new Date(endDate);
            
            switch(reportType) {
                case 'weekly':
                    startDate.setDate(startDate.getDate() - 7);
                    break;
                case 'monthly':
                    startDate.setMonth(startDate.getMonth() - 1);
                    break;
                case 'yearly':
                    startDate.setFullYear(startDate.getFullYear() - 1);
                    break;
            }
            
            // Format date to YYYY-MM-DD
            const formatDate = (date) => {
                return date.toISOString().split('T')[0];
            };
            
            startDateInput.value = formatDate(startDate);
        });
    </script>
</body>
</html>