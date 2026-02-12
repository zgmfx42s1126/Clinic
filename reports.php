<?php
// database_config.php
$host = 'localhost';
$dbname = 'hcm';
$username = 'root'; // Change as needed
$password = ''; // Change as needed

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function generateClinicReportTable($pdo, $filter = 'all') {
    $report = '';
    
    // Set date filter conditions
    $dateCondition = '';
    $filterLabel = 'All Time';
    
    switch($filter) {
        case 'today':
            $dateCondition = "WHERE DATE(CONCAT(date, ' ', time)) = CURDATE()";
            $filterLabel = 'Today';
            break;
        case 'this_week':
            $dateCondition = "WHERE YEARWEEK(CONCAT(date, ' ', time), 1) = YEARWEEK(CURDATE(), 1)";
            $filterLabel = 'This Week';
            break;
        case 'last_week':
            $dateCondition = "WHERE YEARWEEK(CONCAT(date, ' ', time), 1) = YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 1)";
            $filterLabel = 'Last Week';
            break;
        case 'this_month':
            $dateCondition = "WHERE MONTH(CONCAT(date, ' ', time)) = MONTH(CURDATE()) AND YEAR(CONCAT(date, ' ', time)) = YEAR(CURDATE())";
            $filterLabel = 'This Month';
            break;
        case 'all':
        default:
            $dateCondition = "";
            $filterLabel = 'All Time';
            break;
    }
    
    // Get grade level statistics with filter
    $query = "
    SELECT 
        SUBSTRING_INDEX(grade_section, ' - ', 1) as grade_level,
        COUNT(DISTINCT SUBSTRING_INDEX(grade_section, ' - ', -1)) as total_sections,
        COUNT(DISTINCT name) as total_students,
        COUNT(*) as total_visits,
        CONCAT(ROUND((COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM clinic_log $dateCondition), 0)), 2), '%') as percentage
    FROM clinic_log
    $dateCondition
    GROUP BY SUBSTRING_INDEX(grade_section, ' - ', 1)
    ORDER BY 
        CASE SUBSTRING_INDEX(grade_section, ' - ', 1)
            WHEN 'Kindergarten' THEN 0
            WHEN 'Grade 1' THEN 1
            WHEN 'Grade 2' THEN 2
            WHEN 'Grade 3' THEN 3
            WHEN 'Grade 4' THEN 4
            WHEN 'Grade 5' THEN 5
            WHEN 'Grade 6' THEN 6
            WHEN 'Grade 7' THEN 7
            WHEN 'Grade 8' THEN 8
            WHEN 'Grade 9' THEN 9
            WHEN 'Grade 10' THEN 10
            WHEN 'Grade 11' THEN 11
            WHEN 'Grade 12' THEN 12
            ELSE 99
        END";
    
    $stmt = $pdo->query($query);
    $gradeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent visits for the selected filter
    $recentQuery = "
    SELECT 
        id,
        clinic_id,
        name,
        grade_section,
        date,
        time,
        DATE_FORMAT(CONCAT(date, ' ', time), '%Y-%m-%d %h:%i %p') as formatted_time
    FROM clinic_log
    $dateCondition
    ORDER BY CONCAT(date, ' ', time) DESC
    LIMIT 50";
    
    $recentStmt = $pdo->query($recentQuery);
    $recentVisits = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total counts for summary
    $totalQuery = "SELECT COUNT(*) as total_visits, COUNT(DISTINCT name) as unique_students FROM clinic_log $dateCondition";
    $totalStmt = $pdo->query($totalQuery);
    $totalStats = $totalStmt->fetch(PDO::FETCH_ASSOC);
    
    // Start HTML report
    $report .= '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Clinic Log Report</title>
        <link rel="stylesheet" href="assets/css/reports.css">
    </head>
    <body>
        <div class="report-header">
            <h2>🏥 Clinic Visit Report</h2>
            <p>Grade Level Statistics - <span class="date-badge">' . $filterLabel . '</span></p>
        </div>
        
        <div class="filter-container">
            <h3>📅 Filter by Date Range:</h3>
            <div class="filter-buttons">
                <a href="?filter=today" class="filter-btn today-btn ' . ($filter == 'today' ? 'active' : '') . '">Today</a>
                <a href="?filter=this_week" class="filter-btn week-btn ' . ($filter == 'this_week' ? 'active' : '') . '">This Week</a>
                <a href="?filter=last_week" class="filter-btn week-btn ' . ($filter == 'last_week' ? 'active' : '') . '">Last Week</a>
                <a href="?filter=this_month" class="filter-btn month-btn ' . ($filter == 'this_month' ? 'active' : '') . '">This Month</a>
                <a href="?filter=all" class="filter-btn all-btn ' . ($filter == 'all' ? 'active' : '') . '">All Time</a>
            </div>
        </div>
        
        <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>
        <div style="clear: both;"></div>
        
        <div class="report-info">
            <strong>Report Date:</strong> ' . date('F j, Y') . ' | 
            <strong>Filter:</strong> ' . $filterLabel . ' | 
            <strong>Database:</strong> hcm | 
            <strong>Table:</strong> clinic_log | 
            <strong>Generated:</strong> ' . date('g:i A') . '
        </div>
        
        <div class="summary-stats">
            <div class="stat-card">
                <div class="stat-label">Total Visits</div>
                <div class="stat-value">' . $totalStats['total_visits'] . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Unique Students</div>
                <div class="stat-value">' . $totalStats['unique_students'] . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Grade Levels</div>
                <div class="stat-value">' . count($gradeStats) . '</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Date Range</div>
                <div class="stat-value">' . $filterLabel . '</div>
            </div>
        </div>
        
        <h3>📊 Grade Level Statistics</h3>';
    
    if (!empty($gradeStats)) {
        $report .= '
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Grade Level</th>
                    <th>Total Departments</th>
                    <th>Total Users</th>
                   
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>';
        
        // Calculate totals
        $totalDepartments = 0;
        $totalUsers = 0;
        $totalVisits = 0;
        
        // Add rows for each grade level
        $id = 1;
        foreach ($gradeStats as $stat) {
            // Determine percentage class
            $percentageNum = floatval(str_replace('%', '', $stat['percentage']));
            $percentageClass = 'percentage-low';
            if ($percentageNum > 20) {
                $percentageClass = 'percentage-high';
            } elseif ($percentageNum > 10) {
                $percentageClass = 'percentage-medium';
            }
            
            $totalDepartments += $stat['total_sections'];
            $totalUsers += $stat['total_students'];
            $totalVisits += $stat['total_visits'];
            
            $report .= '
                <tr>
                    <td>' . $id . '</td>
                    <td><strong>' . htmlspecialchars($stat['grade_level']) . '</strong></td>
                    <td>' . $stat['total_sections'] . '</td>
                    <td>' . $stat['total_students'] . '</td>
                    <td>' . $stat['total_visits'] . '</td>
                    <td class="' . $percentageClass . '">' . $stat['percentage'] . '</td>
                </tr>';
            $id++;
        }
        
        // Add total row
        $overallPercentage = $totalVisits > 0 ? '100%' : '0%';
        $report .= '
                <tr class="total-row">
                    <td></td>
                    <td><strong>TOTAL</strong></td>
                    <td><strong>' . $totalDepartments . '</strong></td>
                    <td><strong>' . $totalUsers . '</strong></td>
                    <td><strong>' . $totalVisits . '</strong></td>
                    <td><strong>' . $overallPercentage . '</strong></td>
                </tr>
            </tbody>
        </table>';
    } else {
        $report .= '<div class="empty-state">No clinic visits found for ' . $filterLabel . '</div>';
    }
    
    // Recent Visits Section
    $report .= '
        <div class="visit-details">
            <h3>🩺 Recent Clinic Visits (' . $filterLabel . ')</h3>';
    
    if (!empty($recentVisits)) {
        $report .= '
            <table class="visit-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Clinic ID</th>
                        <th>Student Name</th>
                        <th>Grade & Section</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Formatted Time</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($recentVisits as $visit) {
            $report .= '
                    <tr>
                        <td>' . $visit['id'] . '</td>
                        <td>' . htmlspecialchars($visit['clinic_id']) . '</td>
                        <td><strong>' . htmlspecialchars($visit['name']) . '</strong></td>
                        <td>' . htmlspecialchars($visit['grade_section']) . '</td>
                        <td>' . htmlspecialchars($visit['date']) . '</td>
                        <td>' . htmlspecialchars($visit['time']) . '</td>
                        <td>' . htmlspecialchars($visit['formatted_time']) . '</td>
                    </tr>';
        }
        
        $report .= '
                </tbody>
            </table>';
    } else {
        $report .= '<div class="empty-state">No recent visits found for ' . $filterLabel . '</div>';
    }
    
    $report .= '
        </div>
        
        <div style="margin-top: 30px; padding: 15px; background: #ecf0f1; border-radius: 5px;">
            <h3>📋 Report Summary - ' . $filterLabel . '</h3>';
    
    if (!empty($gradeStats)) {
        // Find most active grade
        $mostActive = $gradeStats[0];
        foreach ($gradeStats as $stat) {
            if ($stat['total_visits'] > $mostActive['total_visits']) {
                $mostActive = $stat;
            }
        }
        
        $report .= '<p><strong>Most Active Grade:</strong> ' . $mostActive['grade_level'] . ' with ' . $mostActive['total_visits'] . ' visits (' . $mostActive['percentage'] . ' of total)</p>';
        $report .= '<p><strong>Total Departments/Sections:</strong> ' . $totalDepartments . ' different sections across all grade levels</p>';
        $report .= '<p><strong>Total Unique Students:</strong> ' . $totalUsers . ' different students visited the clinic</p>';
        $report .= '<p><strong>Total Clinic Visits:</strong> ' . $totalVisits . ' visits recorded</p>';
        $report .= '<p><strong>Average Visits per Student:</strong> ' . ($totalUsers > 0 ? round($totalVisits / $totalUsers, 2) : 0) . ' visits</p>';
        $report .= '<p><strong>Average Departments per Grade:</strong> ' . (count($gradeStats) > 0 ? round($totalDepartments / count($gradeStats), 2) : 0) . ' departments</p>';
    } else {
        $report .= '<p>No data available for the selected filter period.</p>';
    }
    
    $report .= '
        </div>
        
        <div style="margin-top: 20px; text-align: center; color: #7f8c8d; font-size: 12px;">
            <p>Clinic Management System | Generated Automatically | Filter: ' . $filterLabel . '</p>
        </div>
        
        <script src="assets/js/reports.js"></script>
        
    </body>
    </html>';
    
    return $report;
}

// Get filter from URL
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$allowedFilters = ['today', 'this_week', 'last_week', 'this_month', 'all'];

if (!in_array($filter, $allowedFilters)) {
    $filter = 'all';
}

// Usage

echo generateClinicReportTable($pdo, $filter);
?>
