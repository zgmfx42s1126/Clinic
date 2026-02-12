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
