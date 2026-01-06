<?php

include '../includes/conn.php'; 
include $_SERVER['DOCUMENT_ROOT'] . '/clinic/admin/includes/sidebar.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM clinic_records ORDER BY date DESC, time DESC";
$result = $conn->query($sql);

$total_records = $result ? $result->num_rows : 0;

$report_date = date('F d, Y');
$report_time = date('h:i A');

$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN treatment IS NOT NULL AND treatment != '' THEN 1 ELSE 0 END) as treated,
                SUM(CASE WHEN treatment IS NULL OR treatment = '' THEN 1 ELSE 0 END) as pending
              FROM clinic_records";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total' => 0, 'treated' => 0, 'pending' => 0];

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

            .main-content, .sidebar, .header, .table-controls, .no-print {
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
    </style>
</head>
<body>

    <div style="background-image: url('<?php echo $web_path; ?>'); width:0; height:0; overflow:hidden; visibility:hidden; position:absolute;"></div>

    <div class="main-content no-print">
        <div class="container">
            <div class="header">
                <h1><i class="fa fa-file-medical-alt" style="color: rgba(0, 102, 255, 1);"></i> Patient Reports</h1>
                <p>View all patient clinic visit records</p>
                
                <div class="header-info">
                    <div>
                        <div class="report-date">Report Date: <?php echo $report_date; ?></div>
                        <div class="total-records">Total Records: <?php echo $total_records; ?></div>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="btn btn-print" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                        <button class="btn btn-refresh" onclick="location.reload()">
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
                                        <span class="status-badge status-treated" style="color: green; font-weight: bold;">Treated</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending" style="color: orange; font-weight: bold;">Pending</span>
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
                        <p>There are no records in the database.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PRINT TEMPLATE -->
    <div class="print-template">
        <div class="page">
            
            <!-- If your format.png already has the logo, you can remove this header block -->
            <div class="document-header" style="text-align: center; margin-bottom: 30px; position: relative; z-index: 10;">
                <div class="school-name" style="font-size: 24px; font-weight: bold;">HOLY CROSS OF MINTAL</div>
                <div class="school-subtitle" style="font-size: 16px;">Clinic Management System</div>
                <div class="school-accreditation" style="font-size: 12px; font-weight: bold;">LEVEL II PASSCOI ACCREDITED</div>
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
                    <strong>School Year:</strong> 2025-2026
                </div>
            </div>
            
            <!-- Added background white with some transparency to ensure text is readable over the image -->
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
                            <th style="border: 1px solid #000; padding: 8px;">ID</th>
                            <th style="border: 1px solid #000; padding: 8px;">Name</th>
                            <th style="border: 1px solid #000; padding: 8px;">Grade/Sec</th>
                            <th style="border: 1px solid #000; padding: 8px;">Complaint</th>
                            <th style="border: 1px solid #000; padding: 8px;">Treatment</th>
                            <th style="border: 1px solid #000; padding: 8px;">Status</th>
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
                            <td style="border: 1px solid #000; padding: 8px;"><?php echo htmlspecialchars($row['treatment'] ?? ''); ?></td>
                            <td style="border: 1px solid #000; padding: 8px;">
                                <?php echo !empty($row['treatment']) ? 'TREATED' : 'PENDING'; ?>
                            </td>
                            <td style="border: 1px solid #000; padding: 8px;"><?php echo !empty($row['date']) ? date('Y-m-d', strtotime($row['date'])) : ''; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="report-footer" style="margin-top: 50px; position: relative; z-index: 10;">
                <div class="signature-line" style="display: flex; justify-content: space-between;">
                    <div class="signature-box" style="text-align: center;">
                        <div class="signature-title" style="margin-bottom: 40px;">Prepared by:</div>
                        <div class="signature-name" style="font-weight: bold; border-top: 1px solid #000; padding-top: 5px;">CLINIC ADMINISTRATOR</div>
                    </div>
                    
                    <div class="signature-box" style="text-align: center;">
                        <div class="signature-title" style="margin-bottom: 40px;">Noted by:</div>
                        <div class="signature-name" style="font-weight: bold; border-top: 1px solid #000; padding-top: 5px;">SCHOOL PRINCIPAL</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</body>
</html>

<?php

if(isset($conn)) $conn->close();
?>
<script src="/clinic/admin/assets/js/patient.js"></script>