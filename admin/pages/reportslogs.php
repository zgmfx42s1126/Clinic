<?php
// Database connection
include '../includes/conn.php';
include $_SERVER['DOCUMENT_ROOT'] . '/clinic/admin/includes/sidebar.php';

/* ===========================
   ✅ Background Image Setup
   =========================== */
$web_path = '/clinic/assets/pictures/format.png';
$server_path = $_SERVER['DOCUMENT_ROOT'] . $web_path;
$image_exists = file_exists($server_path);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ===========================
   ✅ Filters (DEFAULT: TODAY)
   =========================== */
$report_type   = isset($_GET['report_type']) ? $_GET['report_type'] : 'today';
$grade_section = isset($_GET['grade_section']) ? $_GET['grade_section'] : '';

if ($report_type === 'today') {
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
    $end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
} else {
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
}

/* ===========================
   ✅ Pagination (UI) + Persist per_page
   =========================== */
$allowed_per_page = [10, 25, 50, 100];
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($records_per_page, $allowed_per_page, true)) {
    $records_per_page = 10;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $records_per_page;

/* ===========================
   ✅ TOTAL RECORDS (FILTERED)
   =========================== */
$count_sql    = "SELECT COUNT(*) as total FROM clinic_log WHERE date BETWEEN ? AND ?";
$count_params = [$start_date, $end_date];
$count_types  = "ss";

if (!empty($grade_section)) {
    $count_sql .= " AND grade_section = ?";
    $count_params[] = $grade_section;
    $count_types .= "s";
}

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($count_types, ...$count_params);
$count_stmt->execute();
$count_result  = $count_stmt->get_result();
$total_row     = $count_result->fetch_assoc();
$total_records = (int)($total_row['total'] ?? 0);
$count_stmt->close();

/* ===========================
   ✅ TOTAL RECORDS (ALL - SAME DATES, NO GRADE FILTER)
   Used for Percentage card denominator
   =========================== */
$total_all_sql  = "SELECT COUNT(*) as total_all FROM clinic_log WHERE date BETWEEN ? AND ?";
$total_all_stmt = $conn->prepare($total_all_sql);
$total_all_stmt->bind_param("ss", $start_date, $end_date);
$total_all_stmt->execute();
$total_all_result  = $total_all_stmt->get_result();
$total_all_row     = $total_all_result->fetch_assoc();
$total_all_records = (int)($total_all_row['total_all'] ?? 0);
$total_all_stmt->close();

/* ===========================
   ✅ Total Number of Classes (CARD)
   UNIQUE DATES overall in current filter
   =========================== */
$card_classes_sql = "SELECT COUNT(DISTINCT DATE(date)) as total_classes
                     FROM clinic_log
                     WHERE date BETWEEN ? AND ?
                     AND grade_section IS NOT NULL
                     AND grade_section != ''";
$card_classes_params = [$start_date, $end_date];
$card_classes_types  = "ss";

if (!empty($grade_section)) {
    $card_classes_sql .= " AND grade_section = ?";
    $card_classes_params[] = $grade_section;
    $card_classes_types .= "s";
}

$card_classes_stmt = $conn->prepare($card_classes_sql);
$card_classes_stmt->bind_param($card_classes_types, ...$card_classes_params);
$card_classes_stmt->execute();
$card_classes_result = $card_classes_stmt->get_result();
$card_classes_row = $card_classes_result->fetch_assoc();
$total_classes = (int)($card_classes_row['total_classes'] ?? 0);
$card_classes_stmt->close();

/* ===========================
   ✅ Average Users Visits per Day
   =========================== */
$days_diff     = max(1, (int)ceil((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24)) + 1);
$average_daily = $total_records > 0 ? round($total_records / $days_diff, 1) : 0;

/* ===========================
   ✅ Percentage Card
   =========================== */
$percentage_value = 0;
if ($total_all_records > 0) {
    $percentage_value = (int)round(($total_records / $total_all_records) * 100);
}

/* ===========================
   ✅ Chart: Daily visits trend
   =========================== */
$chart_sql = "SELECT DATE(date) as visit_date, COUNT(*) as visit_count
              FROM clinic_log
              WHERE date BETWEEN ? AND ?";

$chart_params = [$start_date, $end_date];
$chart_types  = "ss";

if (!empty($grade_section)) {
    $chart_sql .= " AND grade_section = ?";
    $chart_params[] = $grade_section;
    $chart_types .= "s";
}

$chart_sql .= " GROUP BY DATE(date) ORDER BY date ASC";

$chart_stmt = $conn->prepare($chart_sql);
$chart_stmt->bind_param($chart_types, ...$chart_params);
$chart_stmt->execute();
$chart_result = $chart_stmt->get_result();

$chart_labels = [];
$chart_data   = [];
while ($chart_row = $chart_result->fetch_assoc()) {
    $chart_labels[] = date('M d', strtotime($chart_row['visit_date']));
    $chart_data[]   = (int)$chart_row['visit_count'];
}
$chart_stmt->close();

/* ===========================
   ✅ Pie: Distribution by class
   =========================== */
$pie_sql = "SELECT grade_section, COUNT(*) as section_count
            FROM clinic_log
            WHERE date BETWEEN ? AND ?
            AND grade_section IS NOT NULL
            AND grade_section != ''";

$pie_params = [$start_date, $end_date];
$pie_types  = "ss";

if (!empty($grade_section)) {
    $pie_sql .= " AND grade_section = ?";
    $pie_params[] = $grade_section;
    $pie_types .= "s";
}

$pie_sql .= " GROUP BY grade_section ORDER BY section_count DESC LIMIT 10";

$pie_stmt = $conn->prepare($pie_sql);
$pie_stmt->bind_param($pie_types, ...$pie_params);
$pie_stmt->execute();
$pie_result = $pie_stmt->get_result();

$pie_labels = [];
$pie_data   = [];
$pie_colors = [];

$color_palette = [
    '#4361ee', '#3a56d4', '#4cc9f0', '#4895ef', '#560bad',
    '#7209b7', '#b5179e', '#f72585', '#7209b7', '#3a0ca3'
];

$color_index = 0;
while ($pie_row = $pie_result->fetch_assoc()) {
    $pie_labels[] = $pie_row['grade_section'];
    $pie_data[]   = (int)$pie_row['section_count'];
    $pie_colors[] = $color_palette[$color_index % count($color_palette)];
    $color_index++;
}
$pie_stmt->close();

/* ===========================
   ✅ Pagination fix
   =========================== */
$total_pages = ($records_per_page > 0) ? (int)ceil($total_records / $records_per_page) : 1;
if ($total_pages < 1) $total_pages = 1;

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $records_per_page;
}

/* ===========================
   ✅ Main logs query (FILTERED + PAGINATED)
   =========================== */
$logs_sql = "
    SELECT id, clinic_id, name, grade_section, date, time
    FROM clinic_log
    WHERE date BETWEEN ? AND ?
";
$logs_params = [$start_date, $end_date];
$logs_types  = "ss";

if (!empty($grade_section)) {
    $logs_sql .= " AND grade_section = ?";
    $logs_params[] = $grade_section;
    $logs_types .= "s";
}

$logs_sql .= " ORDER BY date DESC, time DESC LIMIT ? OFFSET ?";
$logs_params[] = $records_per_page;
$logs_params[] = $offset;
$logs_types   .= "ii";

$logs_stmt = $conn->prepare($logs_sql);
$logs_stmt->bind_param($logs_types, ...$logs_params);
$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();

$start_number = ($page - 1) * $records_per_page + 1;

/* ===========================
   ✅ Dropdown: grade sections
   =========================== */
$all_grades_sql = "SELECT DISTINCT grade_section
                   FROM clinic_log
                   WHERE date BETWEEN ? AND ?
                   AND grade_section IS NOT NULL
                   AND grade_section != ''
                   ORDER BY grade_section ASC";
$all_grades_stmt = $conn->prepare($all_grades_sql);
$all_grades_stmt->bind_param("ss", $start_date, $end_date);
$all_grades_stmt->execute();
$all_grades_result = $all_grades_stmt->get_result();

$grade_sections = [];
if ($all_grades_result && $all_grades_result->num_rows > 0) {
    while ($row = $all_grades_result->fetch_assoc()) {
        $grade_sections[] = $row;
    }
}
$all_grades_stmt->close();

/* ===========================
   ✅ Grade Level Statistics Table
   =========================== */
$gradeStatsQuery = "
SELECT 
    SUBSTRING_INDEX(grade_section, ' - ', 1) as grade_level,
    COUNT(DISTINCT DATE(date)) as total_classes,
    COUNT(DISTINCT name) as total_users,
    CONCAT(ROUND((COUNT(*) * 100.0 / NULLIF(?, 0)), 2), '%') as percentage
FROM clinic_log
WHERE date BETWEEN ? AND ?
  AND grade_section IS NOT NULL
  AND grade_section != ''
";

$gradeStatsParams = [(int)$total_records, $start_date, $end_date];
$gradeStatsTypes  = "iss";

if (!empty($grade_section)) {
    $gradeStatsQuery .= " AND grade_section = ?";
    $gradeStatsParams[] = $grade_section;
    $gradeStatsTypes .= "s";
}

$gradeStatsQuery .= "
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
    END
";

$gradeStatsStmt = $conn->prepare($gradeStatsQuery);
$gradeStatsStmt->bind_param($gradeStatsTypes, ...$gradeStatsParams);
$gradeStatsStmt->execute();
$gradeStatsResult = $gradeStatsStmt->get_result();

$gradeStats = [];
if ($gradeStatsResult) {
    while ($row = $gradeStatsResult->fetch_assoc()) {
        $gradeStats[] = $row;
    }
}
$gradeStatsStmt->close();

/* ===========================
   ✅ TOTAL ROW (unique days overall, distinct users overall)
   =========================== */
$total_row_sql = "
    SELECT 
        COUNT(DISTINCT DATE(date)) as total_unique_days,
        COUNT(DISTINCT name) as total_unique_users
    FROM clinic_log
    WHERE date BETWEEN ? AND ?
      AND grade_section IS NOT NULL
      AND grade_section != ''
";
$total_row_params = [$start_date, $end_date];
$total_row_types  = "ss";

if (!empty($grade_section)) {
    $total_row_sql .= " AND grade_section = ?";
    $total_row_params[] = $grade_section;
    $total_row_types .= "s";
}

$total_row_stmt = $conn->prepare($total_row_sql);
$total_row_stmt->bind_param($total_row_types, ...$total_row_params);
$total_row_stmt->execute();
$total_row_result = $total_row_stmt->get_result();
$total_row_data   = $total_row_result->fetch_assoc();

$total_unique_days  = (int)($total_row_data['total_unique_days'] ?? 0);
$total_unique_users = (int)($total_row_data['total_unique_users'] ?? 0);

$total_row_stmt->close();

/* ===========================
   ✅ Helper: shown now
   =========================== */
$shown_now = max(0, min($records_per_page, $total_records - $offset));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monthly Logs Report</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/reportslogs.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if ($image_exists): ?>
<link rel="preload" as="image" href="<?php echo $web_path; ?>">
<?php endif; ?>
</head>
<body>
    <div
        id="reportslogs-data"
        data-chart-labels="<?php echo htmlspecialchars(json_encode($chart_labels), ENT_QUOTES); ?>"
        data-chart-data="<?php echo htmlspecialchars(json_encode($chart_data), ENT_QUOTES); ?>"
        data-pie-labels="<?php echo htmlspecialchars(json_encode($pie_labels), ENT_QUOTES); ?>"
        data-pie-data="<?php echo htmlspecialchars(json_encode($pie_data), ENT_QUOTES); ?>"
        data-pie-colors="<?php echo htmlspecialchars(json_encode($pie_colors), ENT_QUOTES); ?>"
        data-total-pages="<?php echo (int)$total_pages; ?>"
        data-report-bg-url="<?php echo htmlspecialchars($image_exists ? $web_path : '', ENT_QUOTES); ?>"
    ></div>

<div class="image-preloader"></div>

<div class="main-content report-background">
    <div class="report-content">
        <div class="page-container">

            <!-- Header -->
            <div class="header no-print">
                <h1><i class="fas fa-clipboard-list"></i> Records Logs Reports </h1>
                <p>Comprehensive analysis of clinic visits with charts and statistics</p>
                <div class="table-actions">
                    <button class="action-btn print" onclick="printWholePage()">
                        <i class="fas fa-print"></i> Print Page
                    </button>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="date-range-filter no-print">
                <div class="filter-title">
                    <i class="fas fa-filter"></i> Filter Options
                </div>

                <div class="filter-row">
                    <div class="filter-group">
                        <label for="startDate">Start Date</label>
                        <input type="date" id="startDate" class="filter-input" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="endDate">End Date</label>
                        <input type="date" id="endDate" class="filter-input" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="reportType">Report Type</label>
                        <select id="reportType" class="filter-select">
                            <option value="today"   <?php echo $report_type == 'today'   ? 'selected' : ''; ?>>Today's Report</option>
                            <option value="weekly"  <?php echo $report_type == 'weekly'  ? 'selected' : ''; ?>>Weekly Analysis</option>
                            <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly Analysis</option>
                            <option value="yearly"  <?php echo $report_type == 'yearly'  ? 'selected' : ''; ?>>Yearly Analysis</option>
                        </select>
                    </div>
                </div>

                <div class="filter-buttons">
                    <button class="btn btn-primary" onclick="applyFilters()">
                        <i class="fas fa-check"></i> Apply Filter
                    </button>
                    <button class="btn btn-secondary" onclick="resetDateFilter()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards no-print">
                <div class="stat-card total-classes">
                    <div class="stat-icon"><i class="fas fa-school"></i></div>
                    <div class="stat-number"><?php echo (int)$total_classes; ?></div>
                    <div class="stat-label">Total Number of Classes</div>
                </div>

                <div class="stat-card average-daily">
                    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-number"><?php echo $average_daily; ?></div>
                    <div class="stat-label">Average Users Visits</div>
                </div>

                <div class="stat-card average-weekly">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo (int)$percentage_value; ?>%</div>
                    <div class="stat-label">Percentage</div>
                </div>
            </div>

            <!-- Grade Level Statistics Table -->
            <div class="grade-stats-section no-print">
                <div class="section-title" style="margin-bottom: 0; border-radius: 8px 8px 0 0;">
                    <div>
                        <i class="fas fa-graduation-cap"></i>
                        Grade Level Statistics for <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?>
                        <?php if (!empty($grade_section)): ?>
                            <span style="font-size: 14px; color: #eaf2ff; margin-left: 10px;">
                                (Filtered by: <?php echo htmlspecialchars($grade_section); ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="table-actions">
                        <button class="action-btn print" onclick="printGradeStats()">
                            <i class="fas fa-print"></i> Print Grade Stats
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="grade-stats-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Grade Level</th>
                                <th>Total Numbers of Classes</th>
                                <th>Total Numbers of Users</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($gradeStats)): ?>
                            <?php $id = 1; ?>
                            <?php foreach ($gradeStats as $stat): ?>
                                <?php
                                $percentageNum = (float)str_replace('%', '', $stat['percentage']);
                                $percentageClass = 'percentage-low';
                                if ($percentageNum > 20) $percentageClass = 'percentage-high';
                                elseif ($percentageNum > 10) $percentageClass = 'percentage-medium';
                                ?>
                                <tr>
                                    <td><?php echo $id++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($stat['grade_level']); ?></strong></td>
                                    <td><?php echo (int)$stat['total_classes']; ?></td>
                                    <td><?php echo (int)$stat['total_users']; ?></td>
                                    <td class="<?php echo $percentageClass; ?>"><?php echo htmlspecialchars($stat['percentage']); ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="total-row">
                                <td></td>
                                <td><strong>TOTAL</strong></td>
                                <td><strong><?php echo (int)$total_unique_days; ?></strong></td>
                                <td><strong><?php echo (int)$total_unique_users; ?></strong></td>
                                <td><strong><?php echo ($total_records > 0 ? '100.00' : '0.00'); ?>%</strong></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:40px; color:#7f8c8d;">
                                    No grade level statistics found for the selected period.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Charts -->
            <div class="chart-section no-print">
                <div class="chart-title">
                    <i class="fas fa-chart-line"></i> Statistics Report with Charts
                </div>

                <div class="charts-container">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3><i class="fas fa-chart-line"></i> Daily Visits Trend</h3>
                            <span class="table-info"><?php echo count($chart_data); ?> days analyzed</span>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="dailyVisitsChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h3><i class="fas fa-chart-pie"></i> Distribution by Class</h3>
                            <span class="table-info">Top <?php echo count($pie_labels); ?> classes</span>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="classDistributionChart"></canvas>
                        </div>

                        <?php if(!empty($pie_labels)): ?>
                        <div class="chart-legend">
                            <?php foreach($pie_labels as $index => $label): ?>
                            <div class="legend-item">
                                <div class="legend-color" style="background-color: <?php echo $pie_colors[$index]; ?>"></div>
                                <span><?php echo htmlspecialchars($label); ?> (<?php echo (int)$pie_data[$index]; ?>)</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Logs Header -->
            <div class="section-title" style="margin-bottom: 0; border-radius: 8px 8px 0 0;">
                <div>
                    <i class="fas fa-calendar-week"></i>
                    Analysis for <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?>
                    <?php if (!empty($grade_section)): ?>
                        <span style="font-size: 14px; color: #666; margin-left: 10px;">
                            (Filtered by: <?php echo htmlspecialchars($grade_section); ?>)
                        </span>
                    <?php endif; ?>
                </div>
                <div class="table-actions">
                    <button class="action-btn print" onclick="printTable()">
                        <i class="fas fa-print"></i> Print Table
                    </button>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="table-container">
                <div class="table-controls no-print">
                    <div class="controls-left">
                        <input type="text" id="searchInput" class="search-box" placeholder="Search logs...">

                        <select id="gradeSectionFilter" class="grade-section-select">
                            <option value="">All Grades & Sections</option>
                            <?php if(!empty($grade_sections)): ?>
                                <?php foreach($grade_sections as $section): ?>
                                    <option value="<?php echo htmlspecialchars($section['grade_section']); ?>"
                                        <?php echo $section['grade_section'] == $grade_section ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($section['grade_section']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No grade sections found for selected date range</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="controls-right">
                        <div class="per-page-wrap">
                            <span style="font-weight:600;color:#4b5563;">Show:</span>
                            <select id="recordsPerPageSelect" onchange="changeRecordsPerPage(this.value)">
                                <option value="10"  <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25"  <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50"  <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>

                        <div class="table-info">
                            <?php if (!empty($grade_section)): ?>
                                Showing <?php echo $shown_now; ?> of <?php echo (int)$total_records; ?> logs for <?php echo htmlspecialchars($grade_section); ?>
                            <?php else: ?>
                                Showing <?php echo $shown_now; ?> of <?php echo (int)$total_records; ?> logs
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="table-wrapper">
                    <?php if($logs_result && $logs_result->num_rows > 0): ?>
                    <table id="logsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Clinic ID</th>
                                <th>Name</th>
                                <th>Grade & Section</th>
                                <th>Date</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $num = $start_number; while($row = $logs_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $num++; ?></td>
                                <td><?php echo htmlspecialchars($row['clinic_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['grade_section']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($row['date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($row['time'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No logs found for selected filters</h3>
                        <p>Try selecting a different date range or section.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination no-print">
                    <button class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>"
                            onclick="changePage(<?php echo $page - 1; ?>)"
                            <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>

                    <div class="page-numbers">
                        <?php if ($page > 3): ?>
                            <button class="page-number" onclick="changePage(1)">1</button>
                            <?php if ($page > 4): ?>
                                <span class="page-number" style="border:none;background:transparent;cursor:default;">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <button class="page-number <?php echo $i == $page ? 'active' : ''; ?>"
                                    onclick="changePage(<?php echo $i; ?>)">
                                <?php echo $i; ?>
                            </button>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages - 2): ?>
                            <?php if ($page < $total_pages - 3): ?>
                                <span class="page-number" style="border:none;background:transparent;cursor:default;">...</span>
                            <?php endif; ?>
                            <button class="page-number" onclick="changePage(<?php echo $total_pages; ?>)">
                                <?php echo $total_pages; ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <button class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"
                            onclick="changePage(<?php echo $page + 1; ?>)"
                            <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <div class="pagination-info no-print">
                    Page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?> •
                    Records <?php echo min($offset + 1, $total_records); ?>-<?php echo min($offset + $records_per_page, $total_records); ?>
                    of <?php echo (int)$total_records; ?>
                </div>
                <?php endif; ?>

            </div>

        </div>
    </div>
</div>

<script src="../assets/js/reportslogs.js"></script>
</body>
</html>
<?php if(isset($logs_stmt)) $logs_stmt->close(); ?>
<?php if(isset($conn)) $conn->close(); ?>



