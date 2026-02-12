        // Chart.js configurations
        let visitsChart = null;
        let statusChart = null;

        const dashboardDataEl = document.getElementById('dashboard-data');
        const dashboardData = dashboardDataEl ? {
            graphLabels: JSON.parse(dashboardDataEl.dataset.graphLabels || '[]'),
            graphValues: JSON.parse(dashboardDataEl.dataset.graphValues || '[]'),
            statusLabels: JSON.parse(dashboardDataEl.dataset.statusLabels || '[]'),
            statusCounts: JSON.parse(dashboardDataEl.dataset.statusCounts || '[]'),
            statusColors: JSON.parse(dashboardDataEl.dataset.statusColors || '[]')
        } : {
            graphLabels: [],
            graphValues: [],
            statusLabels: [],
            statusCounts: [],
            statusColors: []
        };
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            initVisitsChart();
            initStatusChart();
            
            // Link the select all checkboxes
            const selectAllHeader = document.getElementById('select-all');
            const selectAllFooter = document.getElementById('check-all-footer');
            
            if(selectAllHeader && selectAllFooter) {
                selectAllHeader.addEventListener('change', function() {
                    selectAllFooter.checked = this.checked;
                    toggleAllCheckboxes();
                });
                
                selectAllFooter.addEventListener('change', function() {
                    selectAllHeader.checked = this.checked;
                    toggleAllCheckboxes();
                });
            }
            
            // Form submission
            document.getElementById('recordForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveRecord();
            });
        });
        
        function initVisitsChart() {
            const ctx = document.getElementById('visitsChart').getContext('2d');
            
            const labels = dashboardData.graphLabels;
            const dataValues = dashboardData.graphValues;
            
            visitsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Clinic Visits',
                        data: dataValues,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#4361ee',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return `Visits: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
        
        function initStatusChart() {
            const ctx = document.getElementById('statusChart').getContext('2d');
            
            const labels = dashboardData.statusLabels;
            const data = dashboardData.statusCounts;
            const backgroundColors = dashboardData.statusColors;
            
            statusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        borderWidth: 1,
                        borderColor: '#fff',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            createLegend(labels, backgroundColors, data);
        }
        
        function createLegend(labels, colors, data) {
            const legendContainer = document.getElementById('statusLegend');
            legendContainer.innerHTML = '';
            
            labels.forEach((label, index) => {
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                legendItem.onclick = () => filterByStatus(label.toLowerCase());
                
                const colorBox = document.createElement('div');
                colorBox.className = 'legend-color';
                colorBox.style.backgroundColor = colors[index];
                
                const text = document.createElement('span');
                text.textContent = `${label}: ${data[index]}`;
                
                legendItem.appendChild(colorBox);
                legendItem.appendChild(text);
                legendContainer.appendChild(legendItem);
            });
        }
        
        function updateChart() {
            const days = document.getElementById('time-period').value;
            alert(`Would fetch data for last ${days} days in a real application`);
            // AJAX call to fetch new data would go here
        }
        
        // Modal Functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Clinic Record';
            document.getElementById('recordForm').reset();
            document.getElementById('recordId').value = '';
            document.getElementById('date').value = new Date().toISOString().split('T')[0];
            document.getElementById('time').value = new Date().toTimeString().slice(0, 5);
            document.getElementById('recordModal').style.display = 'flex';
        }
        
        function editRecord(id) {
            // In real application, fetch record data via AJAX
            alert(`Would fetch record ${id} for editing`);
            document.getElementById('modalTitle').textContent = 'Edit Clinic Record';
            document.getElementById('recordId').value = id;
            // Populate form with record data
            document.getElementById('recordModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('recordModal').style.display = 'none';
        }
        
        function saveRecord() {
            const form = document.getElementById('recordForm');
            const formData = new FormData(form);
            
            // In real application, submit via AJAX
            alert('Would save record via AJAX');
            closeModal();
            // location.reload(); // Reload to show new/updated record
        }
        
        // Filter Functions
        function showTodayRecords() {
            const today = new Date().toISOString().split('T')[0];
            filterTableByDate(today);
        }
        
        function filterByStatus(status) {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                if (status === 'all' || rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            alert(`Showing ${status} records`);
        }
        
        function filterTableByDate(date) {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const dateCell = row.querySelector('td:nth-child(9)');
                if (dateCell && dateCell.textContent === date) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            alert(`Showing records for ${date}`);
        }
        
        // Toggle all checkboxes
        function toggleAllCheckboxes() {
            const checkAll = document.getElementById('select-all') || document.getElementById('check-all-footer');
            const checkboxes = document.querySelectorAll('.row-select');
            const isChecked = checkAll.checked;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
        }
        
        // Copy record function
        function copyRecord(id) {
            if(confirm(`Duplicate record ID: ${id}?`)) {
                alert(`Copying record ID: ${id}`);
                // AJAX call would go here
            }
        }
        
        // Delete record function
        function deleteRecord(id) {
            if(confirm(`Are you sure you want to delete record ID: ${id}?`)) {
                alert(`Deleting record ID: ${id}`);
                // AJAX call would go here
            }
        }
        
        // Bulk action function
        function performBulkAction() {
            const action = document.getElementById('bulk-action').value;
            const selectedIds = [];
            const checkboxes = document.querySelectorAll('.row-select:checked');
            
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const id = row.querySelector('td:nth-child(2)').textContent;
                selectedIds.push(id);
            });
            
            if(selectedIds.length === 0) {
                alert('Please select at least one record.');
                return;
            }
            
            if(!action) {
                alert('Please select an action.');
                return;
            }
            
            if(confirm(`Perform ${action} on ${selectedIds.length} record(s)?`)) {
                alert(`Performing ${action} on records: ${selectedIds.join(', ')}`);
                // AJAX call would go here
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('recordModal');
            if (event.target === modal) {
                closeModal();
            }
        }
