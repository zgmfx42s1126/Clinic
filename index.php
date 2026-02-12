<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Clinic System</title>
<link rel="stylesheet" href="./assets/css/navbar.css">
<link rel="stylesheet" href="./assets/css/index.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<!-- Landing Card -->
<div class="landing-card">
  <div class="landing-title">Clinic System</div>
  <div class="landing-subtitle">Select an option to continue</div>

  <button class="btn btn-clinic" onclick="location.href='clinic_form.php'">
    Clinic
  </button>

  <button class="btn btn-log" onclick="location.href='clinic_logbook.php'">
    Clinic Log Book
  </button>

  <footer>
    © 2026 Clinic Management
  </footer>
</div>

</body>
</html>
