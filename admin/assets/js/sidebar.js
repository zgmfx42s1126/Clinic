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
            } else {
                icon.className = 'fas fa-chevron-left';
            }
        });

        // ✅ ACTIVE BLUE ON CURRENT PAGE (FIX)
        function setActiveFromURL() {
            const currentPath = window.location.pathname.replace(/\/+$/, '');
            const navItems = document.querySelectorAll('.nav-item');

            navItems.forEach(item => {
                const href = item.getAttribute('href') || '';
                if (!href || href === '#') return;

                const linkPath = new URL(href, window.location.origin).pathname.replace(/\/+$/, '');
                if (linkPath === currentPath) {
                    navItems.forEach(n => n.classList.remove('active'));
                    item.classList.add('active');
                }
            });
        }

        // Click active (instant) + allow navigation
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                const href = this.getAttribute('href');

                // Keep active effect instantly
                navItems.forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');

                // Only prevent default for "#" links
                if (href === '#') {
                    e.preventDefault();
                }
            });
        });

        // Run on load
        document.addEventListener('DOMContentLoaded', function() {
            setActiveFromURL();
        });
