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

// Get filter parameters
$start_date    = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date      = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type   = isset($_GET['report_type']) ? $_GET['report_type'] : "today";
$grade_section = isset($_GET['grade_section']) ? $_GET['grade_section'] : '';

// For backward compatibility
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Update dates based on report type (only if dates are default or empty)
if ((empty($_GET['start_date']) && empty($_GET['end_date'])) ||
    ($start_date == date('Y-m-d') && $end_date == date('Y-m-d'))) {

    $endDateObj   = new DateTime($end_date);
    $startDateObj = new DateTime($end_date);

    switch ($report_type) {
        case "today":
            $start_date = $end_date = date('Y-m-d');
            break;
        case 'weekly':
            $startDateObj->modify('-7 days');
            $start_date = $startDateObj->format('Y-m-d');
            break;
        case 'monthly':
            $startDateObj = new DateTime(date('Y-m-01'));
            $start_date = $startDateObj->format('Y-m-d');
            $endDateObj = new DateTime(date('Y-m-t'));
            $end_date = $endDateObj->format('Y-m-d');
            break;
        case 'yearly':
            $startDateObj->modify('-1 year');
            $start_date = $startDateObj->format('Y-m-d');
            break;
    }
}

/* =======================
   Helpers
======================= */
function cleanData($data) {
    $data = htmlspecialchars($data ?? '');
    $data = trim($data);
    $data = preg_replace('/[^\x20-\x7E]/', '', $data);
    return $data;
}

function formatTime($time) {
    if (empty($time)) return 'N/A';
    $time = trim($time);

    if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $time, $matches)) {
        $hour = intval($matches[1]);
        $minute = $matches[2];
        $ampm = strtoupper($matches[3]);

        if ($hour >= 12) {
            if ($hour > 12) $hour -= 12;
            $ampm = 'PM';
        } else {
            if ($hour == 0) $hour = 12;
            $ampm = 'AM';
        }
        return sprintf('%d:%s %s', $hour, $minute, $ampm);
    }

    if (preg_match('/\d{1,2}/', $time, $matches)) {
        $hour = intval($matches[0]);
        $ampm = ($hour >= 12) ? 'PM' : 'AM';
        if ($hour > 12) $hour -= 12;
        if ($hour == 0) $hour = 12;
        return sprintf('%d:00 %s', $hour, $ampm);
    }

    try {
        return date('h:i A', strtotime($time));
    } catch (Exception $e) {
        return $time;
    }
}

/* =======================
   ✅ PRINT PAGINATION HELPERS (SMART, NO CSS DESIGN CHANGE)
======================= */

// Detailed rows: estimate row "height units" based on longest wrapping column
function estimateDetailedRowUnits(array $row): int {
    $name      = (string)($row['name'] ?? '');
    $section   = (string)($row['grade_section'] ?? '');
    $complaint = (string)($row['complaint'] ?? '');
    $treatment = (string)($row['treatment'] ?? '');

    $maxLen = max(strlen($name), strlen($section), strlen($complaint), strlen($treatment));
    $extra = (int) floor(max(0, $maxLen - 30) / 30);
    return min(4, 1 + $extra);
}

function paginateDetailedByUnits(array $rows, int $maxUnitsPerPage): array {
    $pages = [];
    $current = [];
    $used = 0;

    foreach ($rows as $r) {
        $u = estimateDetailedRowUnits($r);
        if (!empty($current) && ($used + $u) > $maxUnitsPerPage) {
            $pages[] = $current;
            $current = [];
            $used = 0;
        }
        $current[] = $r;
        $used += $u;
    }
    if (!empty($current)) $pages[] = $current;
    return $pages;
}

// Complaint rows: estimate row "height units" based on complaint text length
function estimateComplaintUnits(array $row): int {
    $complaint = (string)($row['complaint'] ?? '');
    $maxLen = strlen($complaint);
    $extra = (int) floor(max(0, $maxLen - 34) / 34);
    return min(3, 1 + $extra);
}

function paginateComplaintsByUnits(array $rows, int $maxUnitsPerPage): array {
    $pages = [];
    $current = [];
    $used = 0;

    foreach ($rows as $r) {
        $u = estimateComplaintUnits($r);
        if (!empty($current) && ($used + $u) > $maxUnitsPerPage) {
            $pages[] = $current;
            $current = [];
            $used = 0;
        }
        $current[] = $r;
        $used += $u;
    }
    if (!empty($current)) $pages[] = $current;
    return $pages;
}

/* =========================================================
   ✅ ON-SCREEN PER PAGE SELECTORS (10 / 25 / 50 / all)
========================================================= */

// Complaints per page
$complaints_per_page = isset($_GET['complaints_per_page']) ? $_GET['complaints_per_page'] : 10;
if ($complaints_per_page !== 'all') {
    $complaints_per_page = (int)$complaints_per_page;
    if ($complaints_per_page <= 0) $complaints_per_page = 10;
}
$complaints_page = isset($_GET['complaints_page']) ? (int)$_GET['complaints_page'] : 1;
if ($complaints_page < 1) $complaints_page = 1;
$complaints_offset = 0;
if ($complaints_per_page !== 'all') {
    $complaints_offset = ($complaints_page - 1) * $complaints_per_page;
}

// Detailed per page
$records_per_page = isset($_GET['records_per_page']) ? $_GET['records_per_page'] : 10;
if ($records_per_page !== 'all') {
    $records_per_page = (int)$records_per_page;
    if ($records_per_page <= 0) $records_per_page = 10;
}
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = 0;
if ($records_per_page !== 'all') {
    $offset = ($page - 1) * $records_per_page;
}

/* =========================================================
   ✅ COMPLAINTS (ON SCREEN) - COUNT + LIST
========================================================= */
$complaints_count_sql = "
    SELECT COUNT(DISTINCT complaint) AS total
    FROM clinic_records
    WHERE complaint IS NOT NULL AND complaint != ''
      AND date BETWEEN ? AND ?
";
$complaints_count_params = [$start_date, $end_date];
$complaints_count_types = "ss";

if (!empty($grade_section)) {
    $complaints_count_sql .= " AND grade_section = ?";
    $complaints_count_params[] = $grade_section;
    $complaints_count_types .= "s";
}

$complaints_count_stmt = $conn->prepare($complaints_count_sql);
$complaints_count_stmt->bind_param($complaints_count_types, ...$complaints_count_params);
$complaints_count_stmt->execute();
$complaints_count_result = $complaints_count_stmt->get_result();
$complaints_total = (int)(($complaints_count_result->fetch_assoc())['total'] ?? 0);
$complaints_count_stmt->close();

// complaints total pages
if ($complaints_per_page === 'all') {
    $complaints_total_pages = 1;
    $complaints_page = 1;
    $complaints_offset = 0;
} else {
    $complaints_total_pages = (int)ceil(($complaints_total ?: 1) / $complaints_per_page);
    if ($complaints_total_pages < 1) $complaints_total_pages = 1;
    if ($complaints_page > $complaints_total_pages) {
        $complaints_page = $complaints_total_pages;
        $complaints_offset = ($complaints_page - 1) * $complaints_per_page;
    }
}

$complaint_sql = "
    SELECT complaint, COUNT(*) AS total_cases
    FROM clinic_records
    WHERE complaint IS NOT NULL AND complaint != ''
      AND date BETWEEN ? AND ?
";
$complaint_params = [$start_date, $end_date];
$complaint_types = "ss";

if (!empty($grade_section)) {
    $complaint_sql .= " AND grade_section = ?";
    $complaint_params[] = $grade_section;
    $complaint_types .= "s";
}

$complaint_sql .= " GROUP BY complaint ORDER BY total_cases DESC ";

if ($complaints_per_page !== 'all') {
    $complaint_sql .= " LIMIT ? OFFSET ? ";
    $complaint_params[] = $complaints_per_page;
    $complaint_params[] = $complaints_offset;
    $complaint_types .= "ii";
}

$complaint_stmt = $conn->prepare($complaint_sql);
$complaint_stmt->bind_param($complaint_types, ...$complaint_params);
$complaint_stmt->execute();
$complaint_result = $complaint_stmt->get_result();

/* =========================================================
   ✅ COMPLAINTS (FOR PRINT / TOTALS) - NO LIMIT
========================================================= */
$complaint_print_sql = "
    SELECT complaint, COUNT(*) AS total_cases
    FROM clinic_records
    WHERE complaint IS NOT NULL AND complaint != ''
      AND date BETWEEN ? AND ?
";
$complaint_print_params = [$start_date, $end_date];
$complaint_print_types = "ss";

if (!empty($grade_section)) {
    $complaint_print_sql .= " AND grade_section = ?";
    $complaint_print_params[] = $grade_section;
    $complaint_print_types .= "s";
}
$complaint_print_sql .= " GROUP BY complaint ORDER BY total_cases DESC";

$complaint_print_stmt = $conn->prepare($complaint_print_sql);
$complaint_print_stmt->bind_param($complaint_print_types, ...$complaint_print_params);
$complaint_print_stmt->execute();
$complaint_print_result = $complaint_print_stmt->get_result();

$complaint_print_rows = [];
$complaint_print_total = 0;
if ($complaint_print_result && $complaint_print_result->num_rows > 0) {
    while ($r = $complaint_print_result->fetch_assoc()) {
        $complaint_print_rows[] = $r;
        $complaint_print_total += (int)$r['total_cases'];
    }
}
$complaint_print_stmt->close();

/* =========================================================
   ✅ DETAILED (ON SCREEN) COUNT + LIST
========================================================= */
$count_sql = "
    SELECT COUNT(*) as total
    FROM clinic_records
    WHERE date BETWEEN ? AND ?
";
$count_params = [$start_date, $end_date];
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
$total_records = (int)($total_row['total'] ?? 0);
$count_stmt->close();

// detailed total pages
if ($records_per_page === 'all') {
    $total_pages = 1;
    $page = 1;
    $offset = 0;
} else {
    $total_pages = (int)ceil(($total_records ?: 1) / $records_per_page);
    if ($total_pages < 1) $total_pages = 1;
    if ($page > $total_pages) {
        $page = $total_pages;
        $offset = ($page - 1) * $records_per_page;
    }
}

$detailed_sql = "
    SELECT student_id, name, grade_section, complaint, treatment, date, time
    FROM clinic_records
    WHERE date BETWEEN ? AND ?
";
$detailed_params = [$start_date, $end_date];
$detailed_types = "ss";

if (!empty($grade_section)) {
    $detailed_sql .= " AND grade_section = ?";
    $detailed_params[] = $grade_section;
    $detailed_types .= "s";
}

$detailed_sql .= " ORDER BY date DESC, time DESC ";

if ($records_per_page !== 'all') {
    $detailed_sql .= " LIMIT ? OFFSET ? ";
    $detailed_params[] = $records_per_page;
    $detailed_params[] = $offset;
    $detailed_types .= "ii";
}

$detailed_stmt = $conn->prepare($detailed_sql);
$detailed_stmt->bind_param($detailed_types, ...$detailed_params);
$detailed_stmt->execute();
$detailed_result = $detailed_stmt->get_result();

$start_number = ($records_per_page === 'all') ? 1 : (($page - 1) * $records_per_page + 1);

/* =========================================================
   ✅ PRINT: GET ALL DETAILED ROWS (NO LIMIT) FOR PRINT
========================================================= */
$print_sql = "
    SELECT student_id, name, grade_section, complaint, treatment, date, time
    FROM clinic_records
    WHERE date BETWEEN ? AND ?
";
$print_params = [$start_date, $end_date];
$print_types = "ss";

if (!empty($grade_section)) {
    $print_sql .= " AND grade_section = ?";
    $print_params[] = $grade_section;
    $print_types .= "s";
}
$print_sql .= " ORDER BY date DESC, time DESC";

$print_stmt = $conn->prepare($print_sql);
$print_stmt->bind_param($print_types, ...$print_params);
$print_stmt->execute();
$print_result = $print_stmt->get_result();

$print_rows = [];
if ($print_result && $print_result->num_rows > 0) {
    while ($r = $print_result->fetch_assoc()) {
        $print_rows[] = $r;
    }
}
$print_stmt->close();

/* =========================================================
   ✅ Grade sections dropdown
========================================================= */
$all_grades_sql = "SELECT DISTINCT grade_section FROM clinic_records WHERE grade_section IS NOT NULL AND grade_section != ''";
$all_grades_params = [];
$all_grades_types = "";
if (!empty($start_date) && !empty($end_date)) {
    $all_grades_sql .= " AND date BETWEEN ? AND ?";
    $all_grades_params[] = $start_date;
    $all_grades_params[] = $end_date;
    $all_grades_types .= "ss";
}
$all_grades_sql .= " ORDER BY grade_section ASC";

if (!empty($all_grades_params)) {
    $all_grades_stmt = $conn->prepare($all_grades_sql);
    $all_grades_stmt->bind_param($all_grades_types, ...$all_grades_params);
    $all_grades_stmt->execute();
    $all_grades_result = $all_grades_stmt->get_result();
} else {
    $all_grades_result = $conn->query($all_grades_sql);
}

/* =========================================================
   ✅ Stats
========================================================= */
$complaint_types_count = (int)$complaints_total;
$total_cases = (int)$complaint_print_total;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Complaint Report</title>
    <link rel="stylesheet" href="../assets/css/reportscomplain.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php if ($image_exists): ?>
        <link rel="preload" as="image" href="<?php echo $web_path; ?>">
    <?php endif; ?>

    </head>

<body>
    <div
        id="reportscomplaint-data"
        data-report-bg-url="<?php echo htmlspecialchars($image_exists ? $web_path : '', ENT_QUOTES); ?>"
    ></div>
<div class="image-preloader"></div>

<!-- MAIN CONTENT -->
<div class="main-content report-background no-print">
    <div class="container report-content">
        <div class="header">
            <h1><i class="fa-solid fa-chart-column"></i> Complaint Analysis Report</h1>
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
                <label for="startDate"><i class="fas fa-calendar-day"></i> Start Date</label>
                <input type="date" id="startDate" name="start_date" value="<?php echo $start_date; ?>">
            </div>

            <div class="date-group">
                <label for="endDate"><i class="fas fa-calendar-day"></i> End Date</label>
                <input type="date" id="endDate" name="end_date" value="<?php echo $end_date; ?>">
            </div>

            <div class="date-group">
                <label for="reportType"><i class="fas fa-chart-bar"></i> Report Type</label>
                <select id="reportType" name="report_type">
                    <option value="today"  <?php echo $report_type == 'today' ? 'selected' : ''; ?>>Today's Analysis</option>
                    <option value="weekly" <?php echo $report_type == 'weekly' ? 'selected' : ''; ?>>Weekly Analysis</option>
                    <option value="monthly"<?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly Analysis</option>
                    <option value="yearly" <?php echo $report_type == 'yearly' ? 'selected' : ''; ?>>Yearly Analysis</option>
                </select>
            </div>

            <div class="filter-actions">
                <button class="filter-btn btn-apply" onclick="applyDateFilter()">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
                <button class="filter-btn btn-reset" onclick="resetDateFilter()">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="month-display">
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="number"><?php echo $complaint_types_count; ?></div>
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

        <div class="content">

            <!-- Complaint Details -->
            <div class="table-container">
                <div class="section-title">
                    <div><i class="fas fa-table"></i> Complaint Details</div>
                    <div class="table-actions">
                        <label style="display:flex;align-items:center;gap:6px;font-size:14px;color:#4b5563;">
                            Show:
                            <select id="complaintsPerPage" class="grade-section-select" style="min-width:120px;" onchange="applyComplaintPerPage()">
                                <?php $cpp = $complaints_per_page; ?>
                                <option value="10"  <?php echo ($cpp === 10) ? 'selected' : ''; ?>>10</option>
                                <option value="25"  <?php echo ($cpp === 25) ? 'selected' : ''; ?>>25</option>
                                <option value="50"  <?php echo ($cpp === 50) ? 'selected' : ''; ?>>50</option>
                                <option value="all" <?php echo ($cpp === 'all') ? 'selected' : ''; ?>>All</option>
                            </select>
                        </label>

                        <button class="action-btn print" onclick="printComplaintTableOnly()">
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
                            $count = ($complaints_per_page === 'all') ? 1 : ($complaints_offset + 1);
                            while ($row = $complaint_result->fetch_assoc()):
                                $percentage = $complaint_print_total > 0
                                    ? round(((int)$row['total_cases'] / $complaint_print_total) * 100, 1)
                                    : 0;
                                ?>
                                <tr>
                                    <td><?php echo $count++; ?></td>
                                    <td><?php echo cleanData($row['complaint']); ?></td>
                                    <td style="text-align:center;"><span class="case-count"><?php echo (int)$row['total_cases']; ?></span></td>
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

                <?php if ($complaints_total_pages > 1): ?>
                    <div class="pagination">
                        <button class="pagination-btn <?php echo $complaints_page <= 1 ? 'disabled' : ''; ?>"
                                onclick="changeComplaintsPage(<?php echo $complaints_page - 1; ?>)"
                            <?php echo $complaints_page <= 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>

                        <div class="page-numbers">
                            <?php if ($complaints_page > 3): ?>
                                <button class="page-number" onclick="changeComplaintsPage(1)">1</button>
                                <?php if ($complaints_page > 4): ?>
                                    <span class="page-number" style="border:none;background:transparent;">...</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = max(1, $complaints_page - 2); $i <= min($complaints_total_pages, $complaints_page + 2); $i++): ?>
                                <button class="page-number <?php echo $i == $complaints_page ? 'active' : ''; ?>"
                                        onclick="changeComplaintsPage(<?php echo $i; ?>)">
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; ?>

                            <?php if ($complaints_page < $complaints_total_pages - 2): ?>
                                <?php if ($complaints_page < $complaints_total_pages - 3): ?>
                                    <span class="page-number" style="border:none;background:transparent;">...</span>
                                <?php endif; ?>
                                <button class="page-number" onclick="changeComplaintsPage(<?php echo $complaints_total_pages; ?>)">
                                    <?php echo $complaints_total_pages; ?>
                                </button>
                            <?php endif; ?>
                        </div>

                        <button class="pagination-btn <?php echo $complaints_page >= $complaints_total_pages ? 'disabled' : ''; ?>"
                                onclick="changeComplaintsPage(<?php echo $complaints_page + 1; ?>)"
                            <?php echo $complaints_page >= $complaints_total_pages ? 'disabled' : ''; ?>>
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>

                    <div class="pagination-info">
                        Page <?php echo $complaints_page; ?> of <?php echo $complaints_total_pages; ?> •
                        Complaints <?php echo min($complaints_offset + 1, $complaints_total); ?>-<?php echo min($complaints_offset + ($complaints_per_page === 'all' ? $complaints_total : $complaints_per_page), $complaints_total); ?>
                        of <?php echo $complaints_total; ?> •
                        Showing <?php echo ($complaints_per_page === 'all') ? 'All' : (int)$complaints_per_page; ?> per page
                    </div>
                <?php elseif ($complaints_total > 0): ?>
                    <div class="pagination-info">
                        Showing <?php echo ($complaints_per_page === 'all') ? 'All' : (int)$complaints_per_page; ?> per page
                    </div>
                <?php endif; ?>
            </div>

            <!-- Detailed Clinic Records -->
            <div class="table-container">
                <div class="section-title">
                    <div><i class="fas fa-list"></i> Detailed Clinic Records</div>
                    <div class="table-actions">
                        <button class="action-btn print" onclick="printDetailedTableOnly()">
                            <i class="fas fa-print"></i> Print Table
                        </button>
                    </div>
                </div>

                <div class="table-controls">
                    <div class="search-section">
                        <input type="text" id="searchInput" class="search-box" placeholder="Search records...">

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

                        <!-- ✅ per-page selector for detailed table -->
                        <label style="display:flex;align-items:center;gap:6px;font-size:14px;color:#4b5563;">
                            Show:
                            <select id="recordsPerPage" class="grade-section-select" style="min-width:120px;" onchange="applyRecordsPerPage()">
                                <?php $rpp = $records_per_page; ?>
                                <option value="10"  <?php echo ($rpp === 10) ? 'selected' : ''; ?>>10</option>
                                <option value="25"  <?php echo ($rpp === 25) ? 'selected' : ''; ?>>25</option>
                                <option value="50"  <?php echo ($rpp === 50) ? 'selected' : ''; ?>>50</option>
                                <option value="all" <?php echo ($rpp === 'all') ? 'selected' : ''; ?>>All</option>
                            </select>
                        </label>
                    </div>

                    <div class="table-info">
                        <?php
                        $showing = 0;
                        if ($total_records > 0) {
                            if ($records_per_page === 'all') {
                                $showing = $total_records;
                            } else {
                                $showing = min($records_per_page, max(0, $total_records - $offset));
                            }
                        }
                        ?>
                        Showing <?php echo $showing; ?> of <?php echo $total_records; ?> records
                        (<?php echo ($records_per_page === 'all') ? 'All' : (int)$records_per_page; ?> per page)
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
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-clipboard-list"></i>
                            <h3>No detailed records found for selected filters</h3>
                            <p>Try selecting a different date range or section.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <button class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>"
                                onclick="changePage(<?php echo $page - 1; ?>)"
                            <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>

                        <div class="page-numbers">
                            <?php if ($page > 3): ?>
                                <button class="page-number" onclick="changePage(1)">1</button>
                                <?php if ($page > 4): ?>
                                    <span class="page-number" style="border:none;background:transparent;">...</span>
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
                                    <span class="page-number" style="border:none;background:transparent;">...</span>
                                <?php endif; ?>
                                <button class="page-number" onclick="changePage(<?php echo $total_pages; ?>)"><?php echo $total_pages; ?></button>
                            <?php endif; ?>
                        </div>

                        <button class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"
                                onclick="changePage(<?php echo $page + 1; ?>)"
                            <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>

                    <div class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?> •
                        Records <?php echo min($offset + 1, $total_records); ?>-<?php echo min($offset + ($records_per_page === 'all' ? $total_records : $records_per_page), $total_records); ?>
                        of <?php echo $total_records; ?> •
                        Showing <?php echo ($records_per_page === 'all') ? 'All' : (int)$records_per_page; ?> per page
                    </div>
                <?php elseif ($total_records > 0): ?>
                    <div class="pagination-info">
                        Showing <?php echo ($records_per_page === 'all') ? 'All' : (int)$records_per_page; ?> per page
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- ===================== PRINT TEMPLATE (WHOLE PAGE) ===================== -->
<div class="print-template">
    <?php
    // Complaint pages (SMART)
    $maxComplaintUnitsPerPage = 18;
    $complaint_chunks = paginateComplaintsByUnits($complaint_print_rows, $maxComplaintUnitsPerPage);
    $complaint_pages = count($complaint_chunks);
    $complaint_global_no = 1;

    // Detailed pages (SMART)
    $maxUnitsPerPrintPage = 10;
    $chunks = paginateDetailedByUnits($print_rows, $maxUnitsPerPrintPage);
    $total_print_pages = count($chunks);
    $global_row_no = 1;
    ?>

    <!-- COMPLAINT DETAILS PRINT PAGES -->
    <?php if ($complaint_pages > 0): ?>
        <?php for ($cp = 0; $cp < $complaint_pages; $cp++): ?>
            <div class="print-page">
                <div class="print-content">
                    <div class="document-header" style="text-align:center; margin-bottom:18px; padding:15px; background:rgba(255,255,255,0.95); border-radius:8px; border:2px solid #4361ee;">
                        <div style="font-size:24px; font-weight:bold;">HOLY CROSS OF MINTAL</div>
                        <div style="font-size:16px;">Clinic Management System</div>
                        
                    </div>

                    <?php if ($cp === 0): ?>
                        <div class="report-header" style="text-align:center; margin-bottom:14px; background:rgba(255,255,255,0.95); padding:12px; border-radius:8px; border:1px solid #666;">
                            <div style="font-size:20px; font-weight:bold; text-transform:uppercase;">Complaint Analysis Report</div>
                            <div>Analysis of patient complaints</div>
                        </div>

                        <div class="report-info" style="display:flex; justify-content:space-between; margin-bottom:14px; background:rgba(255,255,255,0.95); padding:12px; border-radius:8px; border:1px solid #666;">
                            <div><strong>Date Range:</strong> <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?></div>
                            <div><strong>Generated:</strong> <?php echo date('F d, Y h:i A'); ?></div>
                        </div>

                        <?php if (!empty($grade_section)): ?>
                            <div style="margin-bottom:14px; background:rgba(255,255,255,0.95); padding:10px 12px; border-radius:8px; border:1px solid #666; text-align:center;">
                                <strong>Filtered by:</strong> <?php echo htmlspecialchars($grade_section); ?>
                            </div>
                        <?php endif; ?>

                        <div style="display:flex; justify-content:space-around; margin-bottom:18px; border:2px solid #4361ee; padding:12px; background:rgba(255,255,255,0.95); border-radius:8px;">
                            <div style="text-align:center;">
                                <div style="font-size:22px; font-weight:bold; color:#4361ee;"><?php echo $complaint_types_count; ?></div>
                                <div style="font-weight:bold;">Complaint Types</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:22px; font-weight:bold; color:#4361ee;"><?php echo $total_cases; ?></div>
                                <div style="font-weight:bold;">Total Cases</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:22px; font-weight:bold; color:#4361ee;"><?php echo $total_records; ?></div>
                                <div style="font-weight:bold;">Total Records</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <h3 style="text-align:center; margin:0 0 10px 0; background:rgba(67,97,238,0.92); color:white; padding:10px 12px; border-radius:6px; font-size:18px;">
                        Complaint Details (Page <?php echo ($cp + 1); ?> of <?php echo $complaint_pages; ?>)
                    </h3>

                    <table class="complaint-summary-table" style="width:100%; border-collapse:collapse;">
                        <thead>
                        <tr>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:10%;">#</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:50%;">Complaint</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:20%;">Total Cases</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:20%;">Percentage</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($complaint_chunks[$cp] as $row):
                            $num = $complaint_global_no++;
                            $pct = $complaint_print_total > 0 ? round(((int)$row['total_cases'] / $complaint_print_total) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td style="border:1px solid #666; padding:6px; text-align:center; font-weight:bold;"><?php echo $num; ?></td>
                                <td style="border:1px solid #666; padding:6px;"><?php echo cleanData($row['complaint']); ?></td>
                                <td style="border:1px solid #666; padding:6px; text-align:center; font-weight:bold;"><?php echo (int)$row['total_cases']; ?></td>
                                <td style="border:1px solid #666; padding:6px; text-align:center; font-weight:bold;"><?php echo $pct; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endfor; ?>
    <?php endif; ?>

    <!-- DETAILED RECORDS PRINT PAGES -->
    <?php if ($total_print_pages > 0): ?>
        <?php for ($p = 0; $p < $total_print_pages; $p++): ?>
            <div class="print-page">
                <div class="print-content">
                    <div class="document-header" style="text-align:center; margin-bottom:14px; padding:15px; background:rgba(255,255,255,0.95); border-radius:8px; border:2px solid #4361ee;">
                        <div style="font-size:24px; font-weight:bold;">HOLY CROSS OF MINTAL</div>
                        <div style="font-size:16px;">Clinic Management System</div>
                
                    </div>

                    <h3 style="text-align:center; margin:0 0 10px 0; background:rgba(67,97,238,0.92); color:white; padding:10px 12px; border-radius:6px; font-size:18px;">
                        Detailed Clinic Records (Page <?php echo ($p + 1); ?> of <?php echo $total_print_pages; ?>)
                    </h3>

                    <table class="simple-table" style="width:100%; border-collapse:collapse; table-layout:fixed;">
                        <thead>
                        <tr>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:5%;">#</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:12%;">Student ID</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:20%;">Name</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:20%;">Grade & Section</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:15%;">Complaint</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:15%;">Treatment</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:8%;">Date</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:5%;">Time</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($chunks[$p] as $row): ?>
                            <tr>
                                <td style="border:1px solid #666; padding:6px; text-align:center; font-weight:bold;"><?php echo $global_row_no++; ?></td>
                                <td style="border:1px solid #666; padding:6px; text-align:center;"><?php echo cleanData($row['student_id']); ?></td>
                                <td style="border:1px solid #666; padding:6px;"><?php echo cleanData($row['name']); ?></td>
                                <td style="border:1px solid #666; padding:6px;"><?php echo cleanData($row['grade_section']); ?></td>
                                <td style="border:1px solid #666; padding:6px;"><?php echo cleanData($row['complaint']); ?></td>
                                <td style="border:1px solid #666; padding:6px;"><?php echo cleanData($row['treatment']); ?></td>
                                <td style="border:1px solid #666; padding:6px; text-align:center;"><?php echo date('Y-m-d', strtotime($row['date'])); ?></td>
                                <td style="border:1px solid #666; padding:6px; text-align:center;"><?php echo formatTime($row['time']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endfor; ?>
    <?php endif; ?>
</div>

<!-- ✅ PRINT COMPLAINT ONLY -->
<div class="print-complaint-only">
    <?php
    $maxComplaintUnitsPerPage_only = 20;
    $complaint_chunks_only = paginateComplaintsByUnits($complaint_print_rows, $maxComplaintUnitsPerPage_only);
    $complaint_pages_only = count($complaint_chunks_only);
    $complaint_global_no_only = 1;
    ?>
    <?php if ($complaint_pages_only > 0): ?>
        <?php for ($cp = 0; $cp < $complaint_pages_only; $cp++): ?>
            <div class="print-page">
                <div class="print-content">
                    <div class="document-header" style="text-align:center; margin-bottom:18px; padding:15px; background:rgba(255,255,255,0.95); border-radius:8px; border:2px solid #4361ee;">
                        <div style="font-size:24px; font-weight:bold;">HOLY CROSS OF MINTAL</div>
                        <div style="font-size:16px;">Clinic Management System</div>
                   
                    </div>

                    <div class="report-header" style="text-align:center; margin-bottom:14px; background:rgba(255,255,255,0.95); padding:12px; border-radius:8px; border:1px solid #666;">
                        <div style="font-size:20px; font-weight:bold; text-transform:uppercase;">Complaint Details</div>
                        <div>Complaint Analysis Report</div>
                    </div>

                    <div class="report-info" style="display:flex; justify-content:space-between; margin-bottom:14px; background:rgba(255,255,255,0.95); padding:12px; border-radius:8px; border:1px solid #666;">
                        <div><strong>Date Range:</strong> <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?></div>
                        <div><strong>Generated:</strong> <?php echo date('F d, Y h:i A'); ?></div>
                    </div>

                    <h3 style="text-align:center; margin:0 0 10px 0; background:rgba(67,97,238,0.92); color:white; padding:10px 12px; border-radius:6px; font-size:18px;">
                        Complaint Details (Page <?php echo ($cp + 1); ?> of <?php echo $complaint_pages_only; ?>)
                    </h3>

                    <table class="complaint-summary-table" style="width:100%; border-collapse:collapse;">
                        <thead>
                        <tr>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:10%;">#</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:50%;">Complaint</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:20%;">Total Cases</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:20%;">Percentage</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($complaint_chunks_only[$cp] as $row):
                            $num = $complaint_global_no_only++;
                            $pct = $complaint_print_total > 0 ? round(((int)$row['total_cases'] / $complaint_print_total) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td style="border:1px solid #666; padding:6px; text-align:center; font-weight:bold;"><?php echo $num; ?></td>
                                <td style="border:1px solid #666; padding:6px;"><?php echo cleanData($row['complaint']); ?></td>
                                <td style="border:1px solid #666; padding:6px; text-align:center; font-weight:bold;"><?php echo (int)$row['total_cases']; ?></td>
                                <td style="border:1px solid #666; padding:6px; text-align:center; font-weight:bold;"><?php echo $pct; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endfor; ?>
    <?php endif; ?> 
</div>

<!-- ✅ PRINT DETAILED ONLY -->
<div class="print-detailed-only">
    <?php
    $maxUnitsPerPrintPage_only = 10;
    $chunks_only = paginateDetailedByUnits($print_rows, $maxUnitsPerPrintPage_only);
    $pages_only = count($chunks_only);
    $global_row_no_only = 1;
    ?>
    <?php if ($pages_only > 0): ?>
        <?php for ($p = 0; $p < $pages_only; $p++): ?>
            <div class="print-page">
                <div class="print-content">
                    <div class="document-header" style="text-align:center; margin-bottom:14px; padding:15px; background:rgba(255,255,255,0.95); border-radius:8px; border:2px solid #4361ee;">
                        <div style="font-size:24px; font-weight:bold;">HOLY CROSS OF MINTAL</div>
                        <div style="font-size:16px;">Clinic Management System</div>
                   
                    </div>

                    <div class="report-info" style="display:flex; justify-content:space-between; margin-bottom:14px; background:rgba(255,255,255,0.95); padding:12px; border-radius:8px; border:1px solid #666;">
                        <div><strong>Date Range:</strong> <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?></div>
                        <div><strong>Generated:</strong> <?php echo date('F d, Y h:i A'); ?></div>
                    </div>

                    <h3 style="text-align:center; margin:0 0 10px 0; background:rgba(67,97,238,0.92); color:white; padding:10px 12px; border-radius:6px; font-size:18px;">
                        Detailed Clinic Records (Page <?php echo ($p + 1); ?> of <?php echo $pages_only; ?>)
                    </h3>

                    <table class="simple-table" style="width:100%; border-collapse:collapse; table-layout:fixed;">
                        <thead>
                        <tr>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:5%;">#</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:12%;">Student ID</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:20%;">Name</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:20%;">Grade & Section</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:15%;">Complaint</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:15%;">Treatment</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:8%;">Date</th>
                            <th style="border:2px solid #000; padding:8px; text-align:center; width:5%;">Time</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($chunks_only[$p] as $row): ?>
                            <tr>
                                <td style="border:1px solid #666; padding:6px; text-align:center; font-weight:bold;"><?php echo $global_row_no_only++; ?></td>
                                <td style="border:1px solid #666; padding:6px; text-align:center;"><?php echo cleanData($row['student_id']); ?></td>
                                <td style="border:1px solid #666; padding:6px;"><?php echo cleanData($row['name']); ?></td>
                                <td style="border:1px solid #666; padding:6px;"><?php echo cleanData($row['grade_section']); ?></td>
                                <td style="border:1px solid #666; padding:6px;"><?php echo cleanData($row['complaint']); ?></td>
                                <td style="border:1px solid #666; padding:6px;"><?php echo cleanData($row['treatment']); ?></td>
                                <td style="border:1px solid #666; padding:6px; text-align:center;"><?php echo date('Y-m-d', strtotime($row['date'])); ?></td>
                                <td style="border:1px solid #666; padding:6px; text-align:center;"><?php echo formatTime($row['time']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                </div>
            </div>
        <?php endfor; ?>
    <?php endif; ?>
</div>

<script src="../assets/js/reportscomplain.js"></script>
</body>
</html>

<?php
if (isset($all_grades_stmt)) $all_grades_stmt->close();
if (isset($conn)) $conn->close();
?>


