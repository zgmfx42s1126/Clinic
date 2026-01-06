<?php
include 'conn.php';

if(isset($_GET['rfid'])){
    $rfid = $conn->real_escape_string($_GET['rfid']);
    $sql = "SELECT * FROM student_records WHERE rfid='$rfid' LIMIT 1";
    $result = $conn->query($sql);

    if($result->num_rows > 0){
        $row = $result->fetch_assoc();
        echo json_encode([
            'id' => $row['id'],
            'name' => $row['name'],
            'grade_section' => $row['grade_section']
        ]);
    } else {
        echo json_encode([]);
    }
}
?>
