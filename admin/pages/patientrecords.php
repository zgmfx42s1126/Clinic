<?php
// Database connection
include '../includes/conn.php';
include $_SERVER['DOCUMENT_ROOT'] . '/clinic/admin/includes/sidebar.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    if ($delete_id > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM clinic_records WHERE id = ? LIMIT 1");
        $delete_stmt->bind_param("i", $delete_id);
        $delete_stmt->execute();
        $delete_stmt->close();
    }
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $_SERVER['PHP_SELF'] . ($qs ? ('?' . $qs) : ''));
    exit;
}

// Get filter parameters
$start_date    = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date      = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type   = isset($_GET['report_type']) ? $_GET['report_type'] : "Today's Analysis";
$grade_section = isset($_GET['grade_section']) ? $_GET['grade_section'] : '';

/* =========================================================
   ✅ Per-page + Pagination parameters (10/25/50/100/all)
========================================================= */
$per_page_param = isset($_GET['per_page']) ? $_GET['per_page'] : '10';

if ($per_page_param === 'all') {
    $records_per_page_int = 999999999; // disable pagination
} else {
    $records_per_page_int = (int)$per_page_param;
    if ($records_per_page_int <= 0) $records_per_page_int = 10;
    // normalize displayed value in case weird input
    $per_page_param = (string)$records_per_page_int;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$offset = ($page - 1) * $records_per_page_int;

// Only auto-detect grade section if not provided via filter
if (empty($grade_section)) {
    if (isset($_SESSION['user_grade_section']) && !empty($_SESSION['user_grade_section'])) {
        $grade_section = $_SESSION['user_grade_section'];
    } elseif (isset($_SESSION['user_id'])) {
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

// Update dates based on report type (only if dates are default or empty)
if ((empty($_GET['start_date']) && empty($_GET['end_date'])) ||
    ($start_date == date('Y-m-01') && $end_date == date('Y-m-t'))) {

    $endDateObj   = new DateTime($end_date);
    $startDateObj = new DateTime($end_date);

    switch ($report_type) {
        case "Today's Analysis":
            $start_date = $end_date = date('Y-m-d');
            break;
        case 'Weekly Analysis':
            $startDateObj->modify('-7 days');
            $start_date = $startDateObj->format('Y-m-d');
            break;
        case 'Monthly Analysis':
            // keep month defaults
            break;
        case 'Yearly Analysis':
            $startDateObj->modify('-1 year');
            $start_date = $startDateObj->format('Y-m-d');
            break;
    }
}

/* =========================================================
   ✅ COUNT query
========================================================= */
$count_sql = "SELECT COUNT(*) as total FROM clinic_records WHERE 1=1";
$count_params = [];
$count_types = "";

// Add date filter
if (!empty($start_date) && !empty($end_date)) {
    $count_sql .= " AND date BETWEEN ? AND ?";
    $count_params[] = $start_date;
    $count_params[] = $end_date;
    $count_types .= "ss";
}

// Add grade/section filter
if (!empty($grade_section)) {
    $count_sql .= " AND grade_section = ?";
    $count_params[] = $grade_section;
    $count_types .= "s";
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_row = $count_result->fetch_assoc();
$total_records = (int)($total_row['total'] ?? 0);
$count_stmt->close();

// total pages
if ($per_page_param === 'all') {
    $total_pages = 1;
    $page = 1;
    $offset = 0;
} else {
    $total_pages = (int)ceil(($total_records ?: 1) / $records_per_page_int);
    if ($total_pages < 1) $total_pages = 1;
    if ($page > $total_pages) {
        $page = $total_pages;
        $offset = ($page - 1) * $records_per_page_int;
    }
}

/* =========================================================
   ✅ MAIN query (LIMIT/OFFSET when not all)
========================================================= */
$sql = "SELECT * FROM clinic_records WHERE 1=1";
$params = [];
$types  = "";

// Add date filter
if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

// Add grade/section filter
if (!empty($grade_section)) {
    $sql .= " AND grade_section = ?";
    $params[] = $grade_section;
    $types .= "s";
}

$sql .= " ORDER BY date DESC, time DESC";

if ($per_page_param !== 'all') {
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $records_per_page_int;
    $params[] = $offset;
    $types .= "ii";
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

/* =========================================================
   ✅ Grade sections dropdown (based on date filter)
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
   ✅ Stats (optional)
========================================================= */
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN treatment IS NOT NULL AND treatment != '' THEN 1 ELSE 0 END) as treated,
                SUM(CASE WHEN treatment IS NULL OR treatment = '' THEN 1 ELSE 0 END) as pending
              FROM clinic_records WHERE 1=1";

$stats_params = [];
$stats_types  = "";

if (!empty($start_date) && !empty($end_date)) {
    $stats_sql .= " AND date BETWEEN ? AND ?";
    $stats_params[] = $start_date;
    $stats_params[] = $end_date;
    $stats_types .= "ss";
}
if (!empty($grade_section)) {
    $stats_sql .= " AND grade_section = ?";
    $stats_params[] = $grade_section;
    $stats_types .= "s";
}

$stats_stmt = $conn->prepare($stats_sql);
if (!empty($stats_params)) {
    $stats_stmt->bind_param($stats_types, ...$stats_params);
}
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total' => 0, 'treated' => 0, 'pending' => 0];
$stats_stmt->close();

// For "Showing X of Y"
$shown_now = ($per_page_param === 'all')
    ? $total_records
    : max(0, min($records_per_page_int, $total_records - $offset));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Reports - Clinic Management System</title>

    <link rel="stylesheet" href="../assets/css/patient.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    </head>
<body>

<div class="main-content">
    <div class="container">
        <div class="header">
            <h1><i class="fa fa-file-medical-alt"></i> Patient Records</h1>
            <p>View all patient clinic visit records</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="startDate"><i class="fas fa-calendar-day"></i> Start Date</label>
                    <input type="date" id="startDate" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>

                <div class="filter-group">
                    <label for="endDate"><i class="fas fa-calendar-day"></i> End Date</label>
                    <input type="date" id="endDate" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>

                <div class="filter-group">
                    <label for="reportType"><i class="fas fa-chart-bar"></i> Report Type</label>
                    <select id="reportType" name="report_type">
                        <option value="Today's Analysis" <?php echo $report_type == "Today's Analysis" ? 'selected' : ''; ?>>Today's Analysis</option>
                        <option value="Weekly Analysis" <?php echo $report_type == 'Weekly Analysis' ? 'selected' : ''; ?>>Weekly Analysis</option>
                        <option value="Monthly Analysis" <?php echo $report_type == 'Monthly Analysis' ? 'selected' : ''; ?>>Monthly Analysis</option>
                        <option value="Yearly Analysis" <?php echo $report_type == 'Yearly Analysis' ? 'selected' : ''; ?>>Yearly Analysis</option>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <button class="filter-btn btn-reset" onclick="resetFilters()">
                    <i class="fas fa-redo"></i> Reset
                </button>
                <button class="filter-btn btn-apply" onclick="applyFilters()">
                    <i class="fas fa-filter"></i> Apply Filter
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

                <select class="filter-select grade-section-select" onchange="applyFilters()" id="gradeSectionFilter">
                    <option value="">All Grades & Sections</option>
                    <?php if ($all_grades_result && $all_grades_result->num_rows > 0): ?>
                        <?php while ($grade_row = $all_grades_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($grade_row['grade_section']); ?>"
                                <?php echo $grade_row['grade_section'] == $grade_section ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($grade_row['grade_section']); ?>
                            </option>
                        <?php endwhile; ?>
                        <?php $all_grades_result->data_seek(0); ?>
                    <?php endif; ?>
                </select>

                <!-- ✅ SHOW selector (won't disappear) -->
                <div class="show-per-page">
                    <span>Show:</span>
                    <select id="perPageSelect" onchange="changeRecordsPerPage(this.value)">
                        <option value="10"  <?php echo ($per_page_param === '10') ? 'selected' : ''; ?>>10</option>
                        <option value="25"  <?php echo ($per_page_param === '25') ? 'selected' : ''; ?>>25</option>
                        <option value="50"  <?php echo ($per_page_param === '50') ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo ($per_page_param === '100') ? 'selected' : ''; ?>>100</option>
                        <option value="all" <?php echo ($per_page_param === 'all') ? 'selected' : ''; ?>>All</option>
                    </select>
                </div>
            </div>

            <div class="table-info">
                <?php if (!empty($grade_section)): ?>
                    Showing <?php echo $shown_now; ?> of <?php echo $total_records; ?> record(s) for <?php echo htmlspecialchars($grade_section); ?>
                    (<?php echo ($per_page_param === 'all') ? 'All' : (int)$per_page_param; ?> per page)
                <?php else: ?>
                    Showing <?php echo $shown_now; ?> of <?php echo $total_records; ?> record(s)
                    (<?php echo ($per_page_param === 'all') ? 'All' : (int)$per_page_param; ?> per page)
                <?php endif; ?>
            </div>
        </div>

        <div class="table-container">
            <?php if ($result && $result->num_rows > 0): ?>
                <table id="patientsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Grade & Section</th>
                            <th>Complaint</th>
                            <th>Treatment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rownum = ($per_page_param === 'all') ? 1 : ($offset + 1);
                        while ($row = $result->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?php echo $rownum++; ?></td>
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
                                <td>
                                    <form method="POST" class="action-form" onsubmit="return confirmDelete(this, 'record');">
                                        <input type="hidden" name="delete_id" value="<?php echo (int)($row['id'] ?? 0); ?>">
                                        <button type="submit" class="btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <?php if ($per_page_param !== 'all' && $total_pages > 1): ?>
                    <!-- ✅ Pagination style + behavior like your other page (with ... ) -->
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
                        Records <?php echo min($offset + 1, $total_records); ?>-<?php echo min($offset + $records_per_page_int, $total_records); ?>
                        of <?php echo $total_records; ?> •
                        Showing <?php echo (int)$per_page_param; ?> per page
                    </div>
                <?php elseif ($per_page_param === 'all' && $total_records > 0): ?>
                    <div class="pagination-info">Showing All records</div>
                <?php endif; ?>

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

<script src="../assets/js/patientrecords.js"></script>
</body>
</html>

<?php
if (isset($all_grades_stmt)) $all_grades_stmt->close();
if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();
?>

