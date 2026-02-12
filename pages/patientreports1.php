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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/clinic/assets/css/patientreports1.css">
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
    
    <script src="/clinic/assets/js/patientreports1.js"></script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>
