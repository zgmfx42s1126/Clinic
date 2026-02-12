<?php
include 'conn.php'; // your DB connection

$success = false;
$error = '';
$name = $grade_section = '';

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');
$date = date('Y-m-d');
$time = date('H:i');

// Only save when user clicks the button
if(isset($_POST['submit'])){
    $clinic_id = $conn->real_escape_string($_POST['clinic_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $grade_section = $conn->real_escape_string($_POST['grade_section']);
    $date = $conn->real_escape_string(string: $_POST['date']);
    $time = $conn->real_escape_string($_POST['time']);

    $sql = "INSERT INTO clinic_log (clinic_id, name, grade_section, date, time)
            VALUES ('$clinic_id','$name','$grade_section','$date','$time')";

    if($conn->query($sql) === TRUE){
        $success = true;
        $error = ''; // CLEAR ANY PREVIOUS ERROR
        $_POST = array(); // Clear POST data
        
        // Clear form after successful submission
        $name = $grade_section = '';
        $clinic_id_value = '';
        $date = date('Y-m-d');
        $time = date('H:i');
    } else {
        $error = $conn->error;
        // Keep values if there's an error
        $clinic_id_value = htmlspecialchars($_POST['clinic_id']);
    }
}

// AJAX: Fetch student info based on RFID scan
if(isset($_GET['rfid'])){
    $rfid = $conn->real_escape_string($_GET['rfid']);
    
    // First try with rfid_number (from your clinic form code)
    $sql = "SELECT * FROM students_records WHERE rfid_number='$rfid' LIMIT 1";
    $result = $conn->query($sql);

    // If not found, try with rfid_id (from your original clinic logbook code)
    if($result && $result->num_rows == 0){
        $sql = "SELECT * FROM students_records WHERE rfid_id='$rfid' LIMIT 1";
        $result = $conn->query($sql);
    }

    if($result && $result->num_rows > 0){
        $row = $result->fetch_assoc();
        echo json_encode([
            'student_id' => $row['student_id'] ?? '',
            'fullname' => $row['fullname'] ?? '',
            'grade_section' => $row['grade_section'] ?? ''
        ]);
    } else {
        echo json_encode([]);
    }
    exit;
}

// AJAX search for students by name
if(isset($_GET['search_name'])){
    $search = $conn->real_escape_string($_GET['search_name']);
    $results = [];
    
    if(strlen($search) >= 2){ // Only search if at least 2 characters
        $sql = "SELECT student_id, fullname, grade_section FROM students_records 
                WHERE fullname LIKE '%$search%' 
                ORDER BY fullname LIMIT 5";
        $result = $conn->query($sql);
        
        while($row = $result->fetch_assoc()){
            $results[] = [
                'student_id' => $row['student_id'],
                'fullname' => $row['fullname'],
                'grade_section' => $row['grade_section']
            ];
        }
    }
    
    echo json_encode($results);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Clinic Log Book</title>

<link rel="stylesheet" href="./assets/css/navbar.css">
<link rel="stylesheet" href="./assets/css/clinic_logbook.css">

</head>

<body>
<div
  id="clinic-logbook-data"
  data-success="<?php echo $success ? '1' : '0'; ?>"
  data-error="<?php echo !empty($error) ? htmlspecialchars($error, ENT_QUOTES) : ''; ?>"
></div>

<!-- ✅ Navbar MUST be inside body -->
<div class="navbar">
  <a href="./index.php" class="logo">
    CLINIC
    <img src="./pictures/logohcmNOBG.png" alt="Logo" class="logo-img">
  </a>
  <a href="./admin/adminlogin.php" class="admin-profile">
    <img src="./assets/pictures/adminpfp.jpg" alt="Admin" />
  </a>
</div>

<!-- Success Popup -->
<div class="popup-overlay" id="successPopup">
    <div class="popup-content">
        <div class="popup-icon">✓</div>
        <h3 class="popup-title">Success!</h3>
        <p class="popup-message">Record saved successfully!</p>
        <button class="popup-close-btn" onclick="closePopup('successPopup')">OK</button>
    </div>
</div>

<!-- Error Popup -->
<div class="popup-overlay error-popup" id="errorPopup">
    <div class="popup-content">
        <div class="popup-icon">✗</div>
        <h3 class="popup-title">Error</h3>
        <p class="popup-message" id="errorMessage"></p>
        <button class="popup-close-btn" onclick="closePopup('errorPopup')">OK</button>
    </div>
</div>

<div class="clinic-card">
    <h2 class="clinic-title">Clinic Log Book</h2>

    <form method="POST" action="" id="clinicForm">
        <div class="form-group">
            <label>Student ID </label>
            <input type="text" name="clinic_id" id="clinic_id" placeholder="Scan RFID or search by name" required autofocus value="<?php echo isset($clinic_id_value) ? $clinic_id_value : ''; ?>" readonly>
        </div>

        <div class="form-group" style="position:relative;">
            <label>Full Name (Input Full Name, If No RFID)</label>
            <input type="text" name="name" id="student_name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Type student name..." required>
            <div id="nameSuggestions"></div>
        </div>

        <div class="form-group">
            <label>Grade & Section</label>
            <input type="text" name="grade_section" id="student_grade" value="<?php echo htmlspecialchars($grade_section); ?>" readonly required>
        </div>

        <div class="form-group">
            <label>Date</label>
            <input type="date" name="date" id="date" value="<?php echo $date; ?>" readonly>
        </div>

        <div class="form-group">
            <label>Time</label>
            <input type="time" name="time" id="time" value="<?php echo $time; ?>" readonly>
        </div>

        <button type="submit" name="submit" class="submit-btn">Save Record</button>
        <button type="button" class="submit-btn" style="background:#6c757d; margin-top:10px;" onclick="clearForm(); return false;">Clear Form</button>
    </form>
</div>

<script src="./assets/js/clinic_logbook.js"></script>
</body>
</html>

