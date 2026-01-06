<?php
// Include database connection and sidebar
include 'conn.php';
include 'sidebar.php';

// Check if connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all patient records from clinic_records table
$sql = "SELECT * FROM clinic_records ORDER BY date DESC, time DESC";
$result = $conn->query($sql);

// Count total records
$total_records = $result ? $result->num_rows : 0;

// Get current date for report header
$report_date = date('F d, Y');
$report_time = date('h:i A');

// Get statistics for the report
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN treatment IS NOT NULL AND treatment != '' THEN 1 ELSE 0 END) as treated,
                SUM(CASE WHEN treatment IS NULL OR treatment = '' THEN 1 ELSE 0 END) as pending
              FROM clinic_records";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total' => 0, 'treated' => 0, 'pending' => 0];

// Define the image path
$image_path = 'pictures/format.png';
$image_exists = file_exists($image_path);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Reports - Clinic Management System</title>
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
        }
        
        .main-content {
            margin-left: 250px; /* Adjust based on your sidebar width */
            padding: 20px;
            min-height: 100vh;
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
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            padding: 25px 30px;
            position: relative;
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
            margin-bottom: 10px;
        }
        
        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
            padding: 15px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .report-date {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .total-records {
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-print {
            background-color: #28a745;
            color: white;
        }
        
        .btn-print:hover {
            background-color: #218838;
        }
        
        .btn-export {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-export:hover {
            background-color: #138496;
        }
        
        .btn-refresh {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-refresh:hover {
            background-color: #5a6268;
        }
        
        .table-controls {
            background: #f8f9fa;
            padding: 15px 30px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-filter {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-box {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            min-width: 200px;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }
        
        .table-info {
            font-size: 14px;
            color: #6c757d;
        }
        
        .table-container {
            padding: 30px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
            font-size: 14px;
        }
        
        thead {
            background-color: #f1f5fd;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            background-color: #f1f5fd;
        }
        
        td {
            padding: 15px 12px;
            border-bottom: 1px solid #eef0f3;
            color: #555;
            vertical-align: top;
        }
        
        tr:hover {
            background-color: #f8fbff;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-treated {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
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
        
        /* PRINT TEMPLATE STYLES - Hidden by default */
        .print-template {
            display: none;
        }
        
        /* Print Styles */
        @media print {
            /* Hide everything except print template */
            body * {
                visibility: hidden;
                margin: 0;
                padding: 0;
            }
            
            /* Show print template */
            .print-template, .print-template * {
                visibility: visible !important;
            }
            
            .print-template {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 0;
                background: white;
                font-family: 'Times New Roman', serif;
                font-size: 12pt;
                line-height: 1.5;
            }
            
            /* Very Light Watermark - Almost Invisible */
            .watermark-container {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                opacity: 0.05; /* Very light - almost invisible */
                z-index: -1;
                width: 100%;
                max-width: 800px;
                text-align: center;
            }
            
            .watermark-image {
                max-width: 80%;
                max-height: 80vh;
                opacity: 0.05; /* Very light */
            }
            
            /* Text Watermark Fallback */
            .text-watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                opacity: 0.05;
                z-index: -1;
                text-align: center;
                width: 80%;
                font-size: 36pt;
                color: #cccccc;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 2px;
            }
            
            /* Reset all margins and padding */
            body, html {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                height: 100% !important;
            }
            
            /* Page setup */
            @page {
                size: A4 portrait;
                margin: 1.5cm;
                margin-top: 2cm;
            }
            
            /* Main Document Container */
            .document-container {
                position: relative;
                z-index: 10;
                background: white;
            }
            
            /* Word-like Document Header */
            .document-header {
                text-align: center;
                padding: 20px 0;
                border-bottom: 3px double #1a365d;
                margin-bottom: 25px;
                page-break-after: avoid;
            }
            
            .school-name {
                font-size: 24pt;
                font-weight: bold;
                color: #1a365d;
                letter-spacing: 1px;
                margin-bottom: 5px;
                font-family: 'Georgia', serif;
                text-transform: uppercase;
            }
            
            .school-subtitle {
                font-size: 14pt;
                font-weight: bold;
                color: #2d3748;
                margin-bottom: 8px;
            }
            
            .school-accreditation {
                font-size: 11pt;
                color: #4a5568;
                font-weight: bold;
                margin-bottom: 10px;
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }
            
            /* Report Header */
            .report-header {
                text-align: center;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 2px solid #1a365d;
            }
            
            .report-title {
                font-size: 18pt;
                font-weight: bold;
                color: #000;
                margin-bottom: 10px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .report-subtitle {
                font-size: 14pt;
                color: #4a5568;
                margin-bottom: 15px;
                font-style: italic;
            }
            
            /* Report Info */
            .report-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
                padding: 15px;
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-left: 4px solid #1a365d;
                font-size: 11pt;
            }
            
            .info-section {
                flex: 1;
            }
            
            .info-label {
                font-weight: bold;
                color: #2d3748;
                margin-bottom: 5px;
                font-size: 10pt;
            }
            
            .info-value {
                color: #4a5568;
                font-size: 11pt;
            }
            
            /* Statistics */
            .report-stats {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-bottom: 25px;
                padding: 15px;
                background: #f1f5fd;
                border: 1px solid #cbd5e0;
                border-radius: 5px;
            }
            
            .stat-item {
                text-align: center;
                padding: 10px;
            }
            
            .stat-number {
                font-size: 20pt;
                font-weight: bold;
                color: #2b6cb0;
                margin-bottom: 5px;
            }
            
            .stat-label {
                font-size: 11pt;
                color: #4a5568;
                text-transform: uppercase;
                font-weight: bold;
            }
            
            /* Table Styles for Print */
            .print-table-container {
                margin-top: 20px;
                overflow: visible;
            }
            
            .print-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10pt;
                page-break-inside: avoid;
                border: 1px solid #cbd5e0;
            }
            
            .print-table thead {
                background-color: #1a365d !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                display: table-header-group;
            }
            
            .print-table th {
                padding: 10px 8px;
                border: 1px solid #2d3748;
                background-color: #1a365d !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                font-weight: bold;
                color: white !important;
                text-align: left;
                vertical-align: middle;
                font-size: 10pt;
            }
            
            .print-table td {
                padding: 8px;
                border: 1px solid #cbd5e0;
                color: #000;
                vertical-align: middle;
                font-size: 9.5pt;
            }
            
            .print-table tr:nth-child(even) {
                background-color: #f7fafc !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            
            /* Status Styling for Print */
            .print-status {
                font-weight: bold;
                text-transform: uppercase;
                font-size: 9pt;
                padding: 3px 8px;
                border-radius: 3px;
                display: inline-block;
            }
            
            .print-status-treated {
                background-color: #c6f6d5 !important;
                color: #22543d !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                border: 1px solid #9ae6b4;
            }
            
            .print-status-pending {
                background-color: #fed7d7 !important;
                color: #742a2a !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                border: 1px solid #fc8181;
            }
            
            /* Report Footer */
            .report-footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 2px solid #1a365d;
                text-align: center;
                font-size: 10pt;
                color: #718096;
            }
            
            .signature-line {
                margin-top: 40px;
                padding: 0 40px;
            }
            
            .signature-box {
                display: inline-block;
                width: 200px;
                text-align: center;
                padding-top: 40px;
            }
            
            .signature-name {
                font-weight: bold;
                margin-top: 5px;
                border-top: 1px solid #000;
                padding-top: 5px;
                display: inline-block;
                min-width: 150px;
            }
            
            .signature-title {
                font-size: 9pt;
                color: #718096;
                margin-top: 3px;
            }
            
            .page-number {
                margin-top: 15px;
                font-weight: bold;
                font-size: 10pt;
            }
            
            /* Ensure proper page breaks */
            .page-break {
                page-break-before: always;
            }
            
            /* Hide table controls and buttons */
            .table-controls, .action-buttons, .pagination,
            .btn, .search-filter, .print-button,
            .main-content, .container, .header {
                display: none !important;
            }
            
            /* Force background colors to print */
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }
        
        /* Preview Modal */
        .preview-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            align-items: center;
            justify-content: center;
        }
        
        .preview-content {
            background-color: white;
            width: 90%;
            height: 90%;
            border-radius: 8px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
        }
        
        .preview-header {
            padding: 20px;
            background: #4361ee;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 8px 8px 0 0;
        }
        
        .preview-body {
            flex: 1;
            padding: 20px;
            overflow: auto;
            background: #f5f5f5;
        }
        
        .preview-print-template {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            padding: 20mm;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: relative;
            font-family: 'Times New Roman', serif;
        }
        
        .close-preview {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0 10px;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .preview-print-template {
                width: 100%;
                min-height: auto;
                padding: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .table-container {
                padding: 15px;
            }
            
            .table-controls {
                flex-direction: column;
                align-items: stretch;
                padding: 15px;
            }
            
            .search-filter {
                flex-direction: column;
                width: 100%;
            }
            
            .search-box, .filter-select {
                width: 100%;
            }
            
            .header {
                padding: 20px 15px;
            }
            
            .header-info {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar is included via sidebar.php -->
    
    <div class="main-content">
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-file-medical-alt"></i> Patient Reports</h1>
                <p>View all patient clinic visit records</p>
                
                <div class="header-info">
                    <div>
                        <div class="report-date">Report Date: <?php echo $report_date; ?></div>
                        <div class="total-records">Total Records: <?php echo $total_records; ?></div>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="btn btn-print" onclick="showPrintPreview()">
                            <i class="fas fa-eye"></i> Preview & Print
                        </button>
                        <button class="btn btn-export" onclick="exportReport()">
                            <i class="fas fa-download"></i> Export as PDF
                        </button>
                        <button class="btn btn-refresh" onclick="refreshPage()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="table-controls">
                <div class="search-filter">
                    <input type="text" class="search-box" placeholder="Search patients..." onkeyup="searchTable()" id="searchInput">
                    <select class="filter-select" onchange="filterTable()" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="treated">Treated</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                
                <div class="table-info">
                    Showing <?php echo $total_records; ?> record(s)
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
                                <th>Date Created</th>
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
                                <td><?php echo !empty($row['date_created']) ? date('Y-m-d h:i A', strtotime($row['date_created'])) : ''; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No patient records found</h3>
                        <p>There are no records in the database.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PRINT TEMPLATE - Hidden until print -->
    <div class="print-template">
        <div class="document-container">
            <!-- Watermark/Background Image - Very Light -->
            <div class="watermark-container">
                <?php if ($image_exists): ?>
                    <img src="pictures/format.png" alt="Holy Cross of Mintal Logo" class="watermark-image">
                <?php else: ?>
                    <!-- Fallback text watermark if image doesn't exist -->
                    <div class="text-watermark">
                        HOLY CROSS OF MINTAL<br>
                        LEVEL II PASSCOI ACCREDITED
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="document-header">
                <div class="school-name">HOLY CROSS OF MINTAL</div>
                <div class="school-subtitle">Clinic Management System</div>
                <div class="school-accreditation">LEVEL II PASSCOI ACCREDITED</div>
            </div>
            
            <div class="report-header">
                <div class="report-title">Patient Clinic Visit Report</div>
                <div class="report-subtitle">Complete Patient Records</div>
            </div>
            
            <div class="report-info">
                <div class="info-section">
                    <div class="info-label">Report Generated:</div>
                    <div class="info-value"><?php echo $report_date . ' at ' . $report_time; ?></div>
                </div>
                <div class="info-section">
                    <div class="info-label">School Year:</div>
                    <div class="info-value">2025-2026</div>
                </div>
                <div class="info-section">
                    <div class="info-label">Report ID:</div>
                    <div class="info-value">PCR-<?php echo date('Ymd-His'); ?></div>
                </div>
            </div>
            
            <div class="report-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['treated']; ?></div>
                    <div class="stat-label">Treated Cases</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending Cases</div>
                </div>
            </div>
            
            <div class="print-table-container">
                <table class="print-table">
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
                            <th>Date Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Reset result pointer and fetch again for print template
                        $result->data_seek(0);
                        while($row = $result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['student_id'] ?? ''); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['name'] ?? ''); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['grade_section'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['complaint'] ?? 'Not specified'); ?></td>
                            <td><?php echo !empty($row['treatment']) ? htmlspecialchars($row['treatment']) : 'No treatment yet'; ?></td>
                            <td>
                                <?php if (!empty($row['treatment'])): ?>
                                    <span class="print-status print-status-treated">TREATED</span>
                                <?php else: ?>
                                    <span class="print-status print-status-pending">PENDING</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($row['date']) ? date('Y-m-d', strtotime($row['date'])) : ''; ?></td>
                            <td><?php echo !empty($row['time']) ? date('h:i A', strtotime($row['time'])) : ''; ?></td>
                            <td><?php echo !empty($row['date_created']) ? date('Y-m-d h:i A', strtotime($row['date_created'])) : ''; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="report-footer">
                <div class="signature-line">
                    <div class="signature-box" style="float: left;">
                        <div class="signature-title">Prepared by:</div>
                        <div class="signature-name">CLINIC ADMINISTRATOR</div>
                        <div class="signature-title">Clinic In-Charge</div>
                    </div>
                    
                    <div class="signature-box" style="float: right;">
                        <div class="signature-title">Noted by:</div>
                        <div class="signature-name">SCHOOL PRINCIPAL</div>
                        <div class="signature-title">School Head</div>
                    </div>
                    
                    <div style="clear: both;"></div>
                </div>
                
                <div class="page-number" style="margin-top: 60px;">
                    Page 1 of 1 • Generated by Clinic Management System
                </div>
                <div style="margin-top: 10px; font-size: 9pt; color: #718096;">
                    This is a system-generated document. No signature is required for reference purposes.
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="preview-modal" id="previewModal">
        <div class="preview-content">
            <div class="preview-header">
                <h3 style="margin: 0;">Print Preview - Patient Clinic Visit Report</h3>
                <button class="close-preview" onclick="closePreview()">&times;</button>
            </div>
            <div class="preview-body" id="previewBody">
                <!-- Preview content will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        // Print preview function
        function showPrintPreview() {
            // Get the print template content
            const printTemplate = document.querySelector('.print-template');
            
            // Clone it for preview
            const previewContent = printTemplate.cloneNode(true);
            previewContent.classList.add('preview-print-template');
            previewContent.style.display = 'block';
            previewContent.style.position = 'relative';
            previewContent.style.margin = '20px auto';
            previewContent.style.padding = '20mm';
            previewContent.style.background = 'white';
            previewContent.style.fontFamily = "'Times New Roman', serif";
            previewContent.style.fontSize = '12pt';
            previewContent.style.lineHeight = '1.5';
            previewContent.style.boxShadow = '0 0 20px rgba(0,0,0,0.1)';
            
            // Adjust watermark for preview
            const watermark = previewContent.querySelector('.watermark-container');
            if (watermark) {
                watermark.style.position = 'absolute';
                watermark.style.opacity = '0.05';
                watermark.style.zIndex = '0';
                watermark.style.width = '100%';
                watermark.style.maxWidth = '800px';
                watermark.style.top = '50%';
                watermark.style.left = '50%';
                watermark.style.transform = 'translate(-50%, -50%) rotate(-45deg)';
            }
            
            // Show preview modal
            const previewBody = document.getElementById('previewBody');
            previewBody.innerHTML = '';
            previewBody.appendChild(previewContent);
            
            // Add print button to preview
            const printBtn = document.createElement('button');
            printBtn.className = 'btn btn-print';
            printBtn.style.position = 'fixed';
            printBtn.style.bottom = '20px';
            printBtn.style.right = '20px';
            printBtn.style.zIndex = '1001';
            printBtn.innerHTML = '<i class="fas fa-print"></i> Print Report';
            printBtn.onclick = function() {
                printReport();
            };
            previewBody.appendChild(printBtn);
            
            // Show modal
            document.getElementById('previewModal').style.display = 'flex';
        }
        
        // Close preview modal
        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
        }
        
        // Print report function
        function printReport() {
            // First close the preview
            closePreview();
            
            // Short delay to ensure DOM is ready
            setTimeout(() => {
                window.print();
            }, 100);
        }
        
        // Export as PDF function
        function exportReport() {
            if (confirm('Export as PDF? The browser will open print dialog. Choose "Save as PDF" as printer.')) {
                printReport();
            }
        }
        
        // Refresh page function
        function refreshPage() {
            window.location.reload();
        }
        
        // Search table function
        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('patientsTable');
            
            if (!table) return;
            
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    const cell = cells[j];
                    if (cell) {
                        const text = cell.textContent || cell.innerText;
                        if (text.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        }
        
        // Filter table by status
        function filterTable() {
            const filter = document.getElementById('statusFilter').value;
            const table = document.getElementById('patientsTable');
            
            if (!table) return;
            
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const statusCell = row.querySelector('td:nth-child(7)');
                
                if (statusCell) {
                    const statusBadge = statusCell.querySelector('.status-badge');
                    let status = '';
                    
                    if (statusBadge) {
                        status = statusBadge.classList.contains('status-treated') ? 'treated' : 'pending';
                    }
                    
                    if (filter === '' || status === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            }
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listener for Enter key in search
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchTable();
                }
            });
            
            // Close preview on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closePreview();
                }
            });
            
            // Log image status
            console.log('Image exists: <?php echo $image_exists ? "Yes" : "No"; ?>');
            console.log('Image path: <?php echo $image_path; ?>');
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + P for print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                showPrintPreview();
            }
            
            // Ctrl + E for export
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportReport();
            }
            
            // Ctrl + R for refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshPage();
            }
        });
        
        // Close preview when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('previewModal');
            if (event.target === modal) {
                closePreview();
            }
        }
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>