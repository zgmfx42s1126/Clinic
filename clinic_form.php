<?php
include 'conn.php'; // database connection

// =======================
// SAVE FORM
// =======================
if(isset($_POST['submit'])){
    $student_id = $conn->real_escape_string($_POST['student_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $grade_section = $conn->real_escape_string($_POST['grade_section']);
    $complaint = $conn->real_escape_string($_POST['complaint']);
    $treatment = $conn->real_escape_string($_POST['treatment']);

    date_default_timezone_set('Asia/Manila');
    $date = date('Y-m-d');
    $time = date('H:i');

    $sql = "INSERT INTO clinic_records
            (student_id, name, grade_section, complaint, treatment, date, time)
            VALUES
            ('$student_id','$name','$grade_section','$complaint','$treatment','$date','$time')";

    if($conn->query($sql)){
        $success = true;
    } else {
        $error = $conn->error;
    }
}

// =======================
// RFID SCAN
// =======================
if(isset($_GET['rfid'])){
    $rfid = $conn->real_escape_string($_GET['rfid']);
    $sql = "SELECT * FROM students_records WHERE rfid_number='$rfid' LIMIT 1";
    $res = $conn->query($sql);

    if($res->num_rows){
        $row = $res->fetch_assoc();
        echo json_encode([
            'student_id' => $row['student_id'],
            'fullname' => $row['fullname'],
            'grade_section' => $row['grade_section']
        ]);
    } else {
        echo json_encode([]);
    }
    exit;
}

// =======================
// NAME SEARCH (NO RFID)
// =======================
if(isset($_GET['search_name'])){
    $name = $conn->real_escape_string($_GET['search_name']);
    $sql = "SELECT * FROM students_records
            WHERE fullname LIKE '%$name%' LIMIT 5";
    $res = $conn->query($sql);

    $data = [];
    while($row = $res->fetch_assoc()){
        $data[] = [
            'student_id' => $row['student_id'],
            'fullname' => $row['fullname'],
            'grade_section' => $row['grade_section']
        ];
    }
    echo json_encode($data);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Clinic Form</title>

<link rel="stylesheet" href="./assets/css/navbar.css">
<link rel="stylesheet" href="./assets/css/clinic_form.css">

</head>

<body>
<div
  id="clinic-form-data"
  data-success="<?php echo isset($success) ? '1' : '0'; ?>"
  data-error="<?php echo isset($error) ? htmlspecialchars($error, ENT_QUOTES) : ''; ?>"
></div>

<!-- ✅ Navbar -->
<div class="navbar">
  <a href="./index.php" class="logo">
    CLINIC
    <img src="./pictures/logohcmNOBG.png" alt="Logo" class="logo-img">
  </a>

  <a href="./admin/adminlogin.php" class="admin-profile">
    <img src="./assets/pictures/adminpfp.jpg" alt="Admin" />
  </a>
</div>


<!-- SUCCESS POPUP -->
<div class="popup-overlay success" id="successPopup">
  <div class="popup-content">
    <div class="popup-icon">✓</div>
    <h3>Success</h3>
    <p>Record saved successfully</p>
    <button class="popup-btn" onclick="closePopup('successPopup')">OK</button>
  </div>
</div>

<!-- ERROR POPUP -->
<div class="popup-overlay error" id="errorPopup">
  <div class="popup-content">
    <div class="popup-icon">✗</div>
    <h3>Error</h3>
    <p id="errorMessage"></p>
    <button class="popup-btn" onclick="closePopup('errorPopup')">OK</button>
  </div>
</div>

<div class="clinic-card">
<h2 class="clinic-title">Clinic</h2>

<form method="POST">

<div class="form-group">
<label>Student ID</label>
<input type="text" id="student_id" name="student_id" readonly required>
</div>

<div class="form-group">
<label>Full Name (Input Full Name, If No RFID)</label>
<input type="text" id="student_name" name="name" required>
<div id="nameSuggestions"></div>
</div>

<div class="form-group">
<label>Grade & Section</label>
<input type="text" id="student_grade" name="grade_section" readonly required>
</div>

<div class="form-group">
<label>Complaint / Sickness</label>
<textarea name="complaint" id="complaint" required></textarea>
</div>

<div class="form-group">
<label>Treatment</label>
<textarea name="treatment" required></textarea>
</div>

<button class="submit-btn" name="submit">Save Record</button>
<button type="button" class="clear-btn" onclick="clearForm()">Clear</button>

</form>
</div>

<script src="./assets/js/clinic_form.js"></script>
</body>
</html>


