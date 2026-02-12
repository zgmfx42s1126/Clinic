<?php
include '../includes/conn.php'; 
include $_SERVER['DOCUMENT_ROOT'] . '/clinic/admin/includes/sidebar.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    if ($delete_id > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM clinic_log WHERE id = ? LIMIT 1");
        $delete_stmt->bind_param("i", $delete_id);
        $delete_stmt->execute();
        $delete_stmt->close();
    }
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $_SERVER['PHP_SELF'] . ($qs ? ('?' . $qs) : ''));
    exit;
}

// Get filter parameters
$start_date   = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date     = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type  = isset($_GET['report_type']) ? $_GET['report_type'] : "Today's Analysis";
$grade_section = isset($_GET['grade_section']) ? $_GET['grade_section'] : '';

// Update dates based on report type (only if dates are default or empty)
if ((empty($_GET['start_date']) && empty($_GET['end_date'])) || 
    ($start_date == date('Y-m-01') && $end_date == date('Y-m-t'))) {

    $endDateObj   = new DateTime($end_date);
    $startDateObj = new DateTime($end_date);

    switch($report_type) {
        case "Today's Analysis":
            $start_date = $end_date = date('Y-m-d');
            break;
        case 'Weekly Analysis':
            $startDateObj->modify('-7 days');
            $start_date = $startDateObj->format('Y-m-d');
            break;
        case 'Monthly Analysis':
            break;
        case 'Yearly Analysis':
            $startDateObj->modify('-1 year');
            $start_date = $startDateObj->format('Y-m-d');
            break;
    }
}

/* =========================================================
   ✅ Pagination parameters (supports per_page + All)
========================================================= */
$records_per_page = isset($_GET['per_page']) ? $_GET['per_page'] : 10;

if ($records_per_page === 'all') {
    $records_per_page_int = 999999999; // disable pagination
} else {
    $records_per_page_int = (int)$records_per_page;
    if ($records_per_page_int <= 0) $records_per_page_int = 10;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$offset = ($page - 1) * $records_per_page_int;

/* =========================================================
   ✅ COUNT query
========================================================= */
$count_sql = "SELECT COUNT(*) as total FROM clinic_log WHERE 1=1";
$count_params = [];
$count_types  = "";

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

// Calculate total pages
if ($records_per_page === 'all') {
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
   ✅ MAIN query
========================================================= */
$sql = "SELECT * FROM clinic_log WHERE 1=1";
$params = [];
$types  = "";

if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

if (!empty($grade_section)) {
    $sql .= " AND grade_section = ?";
    $params[] = $grade_section;
    $types .= "s";
}

$sql .= " ORDER BY date DESC, time DESC";

if ($records_per_page !== 'all') {
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
   ✅ Grade sections dropdown
========================================================= */
$all_grades_sql = "SELECT DISTINCT grade_section FROM clinic_log WHERE grade_section IS NOT NULL AND grade_section != ''";
$all_grades_params = [];
$all_grades_types  = "";

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Logs - Clinic Management System</title>

<link rel="stylesheet" href="../assets/css/patient.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/recordslogs.css">
</head>
<body>

<div class="main-content no-print">
<div class="container">
    <div class="header">
        <h1><i class="fas fa-clipboard-list"></i> Patient Logs</h1>
        <p>Clinic visit log records</p>
    </div>

    <!-- Filter Section (unchanged) -->
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
                    <option value="Weekly Analysis" <?php echo $report_type == "Weekly Analysis" ? 'selected' : ''; ?>>Weekly Analysis</option>
                    <option value="Monthly Analysis" <?php echo $report_type == "Monthly Analysis" ? 'selected' : ''; ?>>Monthly Analysis</option>
                    <option value="Yearly Analysis" <?php echo $report_type == "Yearly Analysis" ? 'selected' : ''; ?>>Yearly Analysis</option>
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

    <!-- ✅ Controls row like screenshot -->
    <div class="table-controls">
        <div class="controls-left">
            <input type="text" class="search-box" placeholder="Search by clinic ID or name..." onkeyup="searchTable()" id="searchInput">

            <select class="grade-section-select" onchange="applyFilters()" id="gradeSectionFilter">
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

            <!-- ✅ Show selector positioned beside filters -->
            <div class="per-page-wrap">
                <span>Show:</span>
                <select id="perPageSelect" class="per-page-select" onchange="changeRecordsPerPage(this.value)">
                    <option value="10"  <?php echo ($records_per_page_int == 10 && $records_per_page !== 'all') ? 'selected' : ''; ?>>10</option>
                    <option value="25"  <?php echo ($records_per_page_int == 25) ? 'selected' : ''; ?>>25</option>
                    <option value="50"  <?php echo ($records_per_page_int == 50) ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo ($records_per_page_int == 100) ? 'selected' : ''; ?>>100</option>
                    <option value="all" <?php echo ($records_per_page === 'all') ? 'selected' : ''; ?>>All</option>
                </select>
            </div>
        </div>

        <div class="table-info">
            <?php
            $showing = 0;
            if ($total_records > 0) {
                if ($records_per_page === 'all') $showing = $total_records;
                else $showing = min($records_per_page_int, max(0, $total_records - $offset));
            }
            ?>
            Showing <?php echo $showing; ?> of <?php echo $total_records; ?> records
            (<?php echo ($records_per_page === 'all') ? 'All' : (int)$records_per_page_int; ?> per page)
        </div>
    </div>

    <div class="table-container">
        <?php if ($result && $result->num_rows > 0): ?>
            <table id="logsTable">
                <thead>
                    <tr>
                        <th>Clinic ID</th>
                        <th>Name</th>
                        <th>Grade & Section</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['clinic_id'] ?? ''); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['name'] ?? ''); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['grade_section'] ?? ''); ?></td>
                            <td><?php echo !empty($row['date']) ? date('Y-m-d', strtotime($row['date'])) : ''; ?></td>
                            <td><?php echo !empty($row['time']) ? date('h:i A', strtotime($row['time'])) : ''; ?></td>
                            <td>
                                <form method="POST" class="action-form" onsubmit="return confirmDelete(this, 'log');">
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

            <!-- ✅ Pagination centered bottom + "..." logic like screenshot -->
            <?php if ($records_per_page !== 'all' && $total_pages > 1): ?>
                <div class="pagination-wrap">
                    <div class="pagination">
                        <button class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>"
                                onclick="changePage(<?php echo $page - 1; ?>)"
                                <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>

                        <div class="page-numbers">
                            <?php
                            // show first page + dots if needed
                            if ($page > 3) {
                                echo '<button class="page-number" onclick="changePage(1)">1</button>';
                                if ($page > 4) echo '<span class="page-dots">…</span>';
                            }

                            // window around current page
                            $start = max(1, $page - 1);
                            $end   = min($total_pages, $page + 1);

                            for ($i = $start; $i <= $end; $i++) {
                                $active = ($i == $page) ? 'active' : '';
                                echo '<button class="page-number '.$active.'" onclick="changePage('.$i.')">'.$i.'</button>';
                            }

                            // show last page + dots if needed
                            if ($page < $total_pages - 2) {
                                if ($page < $total_pages - 3) echo '<span class="page-dots">…</span>';
                                echo '<button class="page-number" onclick="changePage('.$total_pages.')">'.$total_pages.'</button>';
                            }
                            ?>
                        </div>

                        <button class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"
                                onclick="changePage(<?php echo $page + 1; ?>)"
                                <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="pagination-info">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?> •
                    Records <?php echo min($offset + 1, $total_records); ?>-<?php echo min($offset + $records_per_page_int, $total_records); ?> of <?php echo $total_records; ?>
                    • Showing <?php echo (int)$records_per_page_int; ?> per page
                </div>
            <?php elseif ($records_per_page === 'all' && $total_records > 0): ?>
                <div class="pagination-info">Showing All records</div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h3>No patient logs found</h3>
                <p>There are no logs matching your filter criteria<?php echo !empty($grade_section) ? ' for ' . htmlspecialchars($grade_section) : ''; ?>.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<script src="../assets/js/recordslogs.js"></script>
<?php
if (isset($all_grades_stmt)) $all_grades_stmt->close();
if (isset($conn)) $conn->close();
?>
</body>
</html>

   
