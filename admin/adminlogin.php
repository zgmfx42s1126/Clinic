<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/navbar.css">
<link rel="stylesheet" href="assets/css/adminlogin.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="login-card">
  <h2>Admin Login</h2>
  <form class="login-form" action="admin.php" method="POST">
    <div class="input-group">
      <input type="text" name="username" placeholder="Username" required>
      <i class="fas fa-user"></i>
    </div>
    
    <div class="input-group">
      <input type="password" name="password" id="password" placeholder="Password" required>
      <i class="fas fa-eye" id="togglePassword"></i>
    </div>
    
    <button type="submit" class="login-btn">Login</button>
  </form>
  <footer>© 2025 Clinic Management</footer>
</div>

<script src="assets/js/adminlogin.js"></script>
</body>
</html>


