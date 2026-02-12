// Simple sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const logoToggle = document.getElementById('logoToggle');
        const toggleBtn = document.getElementById('toggleBtn');
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');

        // Desktop toggle - Logo click
        logoToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const icon = toggleBtn.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.className = 'fas fa-chevron-right';
                icon.style.transform = 'rotate(0deg)';
            } else {
                icon.className = 'fas fa-chevron-left';
                icon.style.transform = 'rotate(0deg)';
            }
        });

        // Mobile menu toggle with animation
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            const icon = mobileMenuBtn.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.className = 'fas fa-times';
            } else {
                icon.className = 'fas fa-bars';
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
                    sidebar.classList.remove('active');
                    const icon = mobileMenuBtn.querySelector('i');
                    icon.className = 'fas fa-bars';
                }
            }
        });

        // Set active nav item with ripple effect
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all items
                navItems.forEach(nav => nav.classList.remove('active'));
                
                // Add active class to clicked item
                this.classList.add('active');
                
                // Create ripple effect
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(52, 152, 219, 0.2);
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    width: ${size}px;
                    height: ${size}px;
                    top: ${y}px;
                    left: ${x}px;
                    pointer-events: none;
                `;
                
                this.appendChild(ripple);
                
                // Remove ripple after animation
                setTimeout(() => {
                    ripple.remove();
                }, 600);
                
                // Close sidebar on mobile after selection
                if (window.innerWidth <= 1024) {
                    sidebar.classList.remove('active');
                    const icon = mobileMenuBtn.querySelector('i');
                    icon.className = 'fas fa-bars';
                }
            });
        });

        // Add ripple animation to CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Check screen size for mobile
        function checkScreenSize() {
            if (window.innerWidth <= 1024) {
                mobileMenuBtn.style.display = 'block';
                sidebar.classList.remove('collapsed');
            } else {
                mobileMenuBtn.style.display = 'none';
                sidebar.classList.remove('active');
                const icon = mobileMenuBtn.querySelector('i');
                icon.className = 'fas fa-bars';
            }
        }

        // Run on page load and resize
        document.addEventListener('DOMContentLoaded', checkScreenSize);
        window.addEventListener('resize', checkScreenSize);
