<?php 
  include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html, body {
  width: 100%;
  height: 100%;
  font-family: 'Segoe UI', sans-serif;
  background: url('../assets/pictures/landingbg.jpg') no-repeat center center fixed;
  background-size: cover;
  display: flex;
  justify-content: center;
  align-items: center;
}

/* White overlay for better readability */
body::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.85);
  z-index: -1;
}

/* Centered login card */
.login-card {
  background: #ffffff;
  padding: 40px 35px;
  border-radius: 16px;
  width: 380px;
  text-align: center;
  box-shadow: 0 15px 35px rgba(30, 136, 229, 0.1),
              0 5px 15px rgba(0, 0, 0, 0.08);
  border-top: 5px solid #1e88e5;
  position: relative;
  overflow: hidden;
}


.login-card > * {
  position: relative;
  z-index: 1;
}

/* Title */
.login-card h2 {
  color: #1e88e5;
  margin-bottom: 25px;
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.5px;
}

/* Form styling */
.login-form {
  width: 100%;
}

/* Input groups */
.input-group {
  position: relative;
  margin-bottom: 20px;
}

.input-group input {
  width: 100%;
  padding: 14px 45px 14px 15px;
  border-radius: 10px;
  border: 1.5px solid #bbdefb;
  font-size: 16px;
  transition: all 0.3s ease;
  background-color: #f8fbff;
}

.input-group input:focus {
  outline: none;
  border-color: #1e88e5;
  box-shadow: 0 0 0 3px rgba(30, 136, 229, 0.2);
  background-color: #ffffff;
}

.input-group input::placeholder {
  color: #90a4ae;
}

/* Icons inside inputs */
.input-group i {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: #78909c;
  cursor: pointer;
  transition: color 0.3s ease;
  font-size: 18px;
}

.input-group i:hover {
  color: #1e88e5;
}

/* Username icon */
.input-group:first-child i.fa-user {
  cursor: default;
  pointer-events: none;
}

/* Password toggle icon */
#togglePassword {
  color: #78909c;
  font-size: 18px;
}

#togglePassword:hover {
  color: #1e88e5;
}

/* Login button */
.login-btn {
  width: 100%;
  padding: 15px;
  margin-top: 10px;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, #1e88e5, #1565c0);
  color: #fff;
  font-size: 17px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  letter-spacing: 0.5px;
  box-shadow: 0 4px 15px rgba(30, 136, 229, 0.3);
}

.login-btn:hover {
  background: linear-gradient(135deg, #1565c0, #0d47a1);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(21, 101, 192, 0.4);
}

.login-btn:active {
  transform: translateY(0);
}

/* Footer */
.login-card footer {
  margin-top: 25px;
  font-size: 13px;
  color: #607d8b;
  padding-top: 15px;
  border-top: 1px solid #e0e0e0;
}

/* Error message styling (optional) */
.error-message {
  color: #f44336;
  font-size: 14px;
  margin-top: 10px;
  display: none;
}

/* Responsive */
@media (max-width: 480px) {
  .login-card {
    width: 90%;
    padding: 30px 25px;
    margin: 20px;
  }
  
  .login-card h2 {
    font-size: 24px;
  }
}
</style>
</head>
<body>

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

<script>
// Toggle password visibility
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

togglePassword.addEventListener('click', function() {
  // Toggle the type attribute
  const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
  passwordInput.setAttribute('type', type);
  
  // Toggle the eye icon
  this.classList.toggle('fa-eye');
  this.classList.toggle('fa-eye-slash');
});

// Optional: Add focus effects
const inputs = document.querySelectorAll('.input-group input');
inputs.forEach(input => {
  input.addEventListener('focus', function() {
    this.parentElement.querySelector('i').style.color = '#1e88e5';
  });
  
  input.addEventListener('blur', function() {
    this.parentElement.querySelector('i').style.color = '#78909c';
  });
});
</script>

</body>
</html>