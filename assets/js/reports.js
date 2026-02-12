// Auto-refresh for today filter every 5 minutes
        ' . ($filter == 'today' ? '
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes
        ' : '') . '
        
        // Add filter persistence
        document.addEventListener("DOMContentLoaded", function() {
            const filterBtns = document.querySelectorAll(".filter-btn");
            filterBtns.forEach(btn => {
                btn.addEventListener("click", function(e) {
                    e.preventDefault();
                    const filter = this.getAttribute("href").split("=")[1];
                    window.location.href = "?filter=" + filter;
                });
            });
        });
