<?php
include '../includes/conn.php'; 
include $_SERVER['DOCUMENT_ROOT'] . '/clinic/admin/includes/sidebar.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$sql = "SELECT * FROM clinic_log ORDER BY date DESC, time DESC";
$result = $conn->query($sql);
$total = $result->num_rows;
$today = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Patient Logs</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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
    margin-left: 250px;
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
}

.header h1 {
    font-size: 28px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.header p {
    opacity: 0.9;
    margin-top: 5px;
}

.header-info {
    display: flex;
    justify-content: space-between;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid rgba(255,255,255,0.2);
}

.total-records {
    background: rgba(255,255,255,0.15);
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 600;
}

.table-controls {
    background: #f8f9fa;
    padding: 15px 30px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.search-box {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    min-width: 250px;
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
    min-width: 900px;
}

thead {
    background-color: #f1f5fd;
    position: sticky;
    top: 0;
    z-index: 10;
}

th {
    padding: 14px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
}

td {
    padding: 14px;
    border-bottom: 1px solid #eef0f3;
}

tr:hover {
    background-color: #f8fbff;
}

</style>
</head>

<body>

<div class="main-content">
<div class="container">

    <div class="header">
        <h1><i class="fas fa-clipboard-list"></i> Patient Logs</h1>
        <p>Clinic visit log records</p>

        <div class="header-info">
            <div>Date Generated: <?= $today ?></div>
            <div class="total-records">Total Logs: <?= $total ?></div>
        </div>
    </div>

    <div class="table-controls">
        <input type="text" id="searchInput" class="search-box"
               placeholder="Search by clinic ID or name...">
        <div class="table-info">
            Showing <span id="visibleCount"><?= $total ?></span> records
        </div>
    </div>

    <div class="table-container">
        <table id="logsTable">
            <thead>
                <tr>
                    <th>Clinic ID</th>
                    <th>Name</th>
                    <th>Grade & Section</th>
                    <th>Date</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            <?php if($total > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['clinic_id']) ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['grade_section']) ?></td>
                    <td><?= $row['date'] ?></td>
                    <td><?= date('h:i A', strtotime($row['time'])) ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;color:#777">
                        No patient logs found
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const rows = document.querySelectorAll('#logsTable tbody tr');
const visibleCount = document.getElementById('visibleCount');

searchInput.addEventListener('keyup', () => {
    const value = searchInput.value.toLowerCase();
    let count = 0;

    rows.forEach(row => {
        if (row.innerText.toLowerCase().includes(value)) {
            row.style.display = '';
            count++;
        } else {
            row.style.display = 'none';
        }
    });

    visibleCount.textContent = count;
});
</script>

</body>
</html>
