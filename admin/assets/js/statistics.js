        // Chart instances
        let visitsChart;
        let complaintChartInstance;
        let classesChartInstance;
        let currentChartType = 'daily';

        const statsDataEl = document.getElementById('statistics-data');
        const statsData = statsDataEl ? {
            reportType: JSON.parse(statsDataEl.dataset.reportType || '"daily"'),
            dailyLabels: JSON.parse(statsDataEl.dataset.dailyLabels || '[]'),
            dailyVisits: JSON.parse(statsDataEl.dataset.dailyVisits || '[]'),
            dailyDates: JSON.parse(statsDataEl.dataset.dailyDates || '[]'),
            weeklyLabels: JSON.parse(statsDataEl.dataset.weeklyLabels || '[]'),
            weeklyVisits: JSON.parse(statsDataEl.dataset.weeklyVisits || '[]'),
            weeklyPatients: JSON.parse(statsDataEl.dataset.weeklyPatients || '[]'),
            monthlyLabels: JSON.parse(statsDataEl.dataset.monthlyLabels || '[]'),
            monthlyVisits: JSON.parse(statsDataEl.dataset.monthlyVisits || '[]'),
            monthlyPatients: JSON.parse(statsDataEl.dataset.monthlyPatients || '[]'),
            complaintLabels: JSON.parse(statsDataEl.dataset.complaintLabels || '[]'),
            complaintData: JSON.parse(statsDataEl.dataset.complaintData || '[]'),
            complaintColors: JSON.parse(statsDataEl.dataset.complaintColors || '[]'),
            topClassesLabels: JSON.parse(statsDataEl.dataset.topClassesLabels || '[]'),
            topClassesData: JSON.parse(statsDataEl.dataset.topClassesData || '[]')
        } : {
            reportType: 'daily',
            dailyLabels: [],
            dailyVisits: [],
            dailyDates: [],
            weeklyLabels: [],
            weeklyVisits: [],
            weeklyPatients: [],
            monthlyLabels: [],
            monthlyVisits: [],
            monthlyPatients: [],
            complaintLabels: [],
            complaintData: [],
            complaintColors: [],
            topClassesLabels: [],
            topClassesData: []
        };

        const REPORT_TYPE = statsData.reportType;

        function setActive(btn) {
            document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
        }

        // DAILY
        function initDailyChart() {
            const ctx = document.getElementById('visitsChart').getContext('2d');
            if (visitsChart) visitsChart.destroy();

            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(67, 97, 238, 0.2)');
            gradient.addColorStop(1, 'rgba(67, 97, 238, 0.05)');

            visitsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: statsData.dailyLabels,
                    datasets: [{
                        label: 'Daily Visits',
                        data: statsData.dailyVisits,
                        borderColor: '#4361ee',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#4361ee',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    const date = statsData.dailyDates[context.dataIndex];
                                    return `${date}: ${context.raw} visits`;
                                },
                                title: function() { return ''; }
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } },
                        x: { ticks: { maxRotation: 45 } }
                    }
                }
            });
        }

        // WEEKLY
        function initWeeklyChart() {
            const ctx = document.getElementById('visitsChart').getContext('2d');
            if (visitsChart) visitsChart.destroy();

            const gradient1 = ctx.createLinearGradient(0, 0, 0, 300);
            gradient1.addColorStop(0, 'rgba(67, 97, 238, 0.9)');
            gradient1.addColorStop(1, 'rgba(67, 97, 238, 0.6)');

            const gradient2 = ctx.createLinearGradient(0, 0, 0, 300);
            gradient2.addColorStop(0, 'rgba(16, 185, 129, 0.9)');
            gradient2.addColorStop(1, 'rgba(16, 185, 129, 0.6)');

            visitsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: statsData.weeklyLabels,
                    datasets: [
                        {
                            label: 'Total Visits',
                            data: statsData.weeklyVisits,
                            backgroundColor: gradient1,
                            borderColor: '#4361ee',
                            borderWidth: 1,
                            borderRadius: 4
                        },
                        {
                            label: 'Unique Patients',
                            data: statsData.weeklyPatients,
                            backgroundColor: gradient2,
                            borderColor: '#10b981',
                            borderWidth: 1,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: true, position: 'top' } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        }

        // MONTHLY (Yearly Analysis)
        function initMonthlyChart() {
            const ctx = document.getElementById('visitsChart').getContext('2d');
            if (visitsChart) visitsChart.destroy();

            const gradient1 = ctx.createLinearGradient(0, 0, 0, 300);
            gradient1.addColorStop(0, 'rgba(67, 97, 238, 0.9)');
            gradient1.addColorStop(1, 'rgba(67, 97, 238, 0.6)');

            const gradient2 = ctx.createLinearGradient(0, 0, 0, 300);
            gradient2.addColorStop(0, 'rgba(16, 185, 129, 0.9)');
            gradient2.addColorStop(1, 'rgba(16, 185, 129, 0.6)');

            visitsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: statsData.monthlyLabels,
                    datasets: [
                        {
                            label: 'Monthly Visits',
                            data: statsData.monthlyVisits,
                            backgroundColor: gradient1,
                            borderColor: '#4361ee',
                            borderWidth: 1,
                            borderRadius: 4
                        },
                        {
                            label: 'Monthly Unique Patients',
                            data: statsData.monthlyPatients,
                            backgroundColor: gradient2,
                            borderColor: '#10b981',
                            borderWidth: 1,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: true, position: 'top' } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        }

        // Complaint Chart
        function initComplaintChart() {
            const ctx = document.getElementById('complaintChart').getContext('2d');
            if (complaintChartInstance) complaintChartInstance.destroy();

            complaintChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: statsData.complaintLabels,
                    datasets: [{
                        data: statsData.complaintData,
                        backgroundColor: statsData.complaintColors,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right' } },
                    cutout: '65%'
                }
            });
        }

        // Classes Chart
        function initClassesChart() {
            const ctx = document.getElementById('classesChart').getContext('2d');
            if (classesChartInstance) classesChartInstance.destroy();

            const colors = [
                'rgba(67, 97, 238, 0.9)',
                'rgba(58, 12, 163, 0.9)',
                'rgba(114, 9, 183, 0.9)',
                'rgba(247, 37, 133, 0.9)',
                'rgba(76, 201, 240, 0.9)'
            ];

            const gradients = colors.map((color) => {
                const g = ctx.createLinearGradient(0, 0, 0, 300);
                g.addColorStop(0, color);
                g.addColorStop(1, color.replace('0.9', '0.6'));
                return g;
            });

            classesChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: statsData.topClassesLabels,
                    datasets: [{
                        label: 'Number of Visits',
                        data: statsData.topClassesData,
                        backgroundColor: gradients,
                        borderColor: colors.map(c => c.replace('0.9', '1')),
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });
        }

        function showDailyChart(btn) {
            currentChartType = 'daily';
            setActive(btn);
            initDailyChart();
        }

        function showWeeklyChart(btn) {
            currentChartType = 'weekly';
            setActive(btn);
            initWeeklyChart();
        }

        function showMonthlyChart(btn) {
            currentChartType = 'monthly';
            setActive(btn);
            initMonthlyChart();
        }

        function toggleYearSelect(type) {
            const wrap = document.getElementById('yearSelectWrap');
            const btnMonthly = document.getElementById('btnMonthly');

            if (type === 'yearly') {
                wrap.style.display = '';
                btnMonthly.style.display = '';
            } else {
                wrap.style.display = 'none';
                btnMonthly.style.display = 'none';
            }
        }

        // Initialize all charts on page load
        document.addEventListener('DOMContentLoaded', function() {
            initComplaintChart();
            initClassesChart();

            // Default chart selection
            if (REPORT_TYPE === 'yearly') {
                document.getElementById('btnMonthly').style.display = '';
                setActive(document.getElementById('btnMonthly'));
                initMonthlyChart();
            } else {
                // default daily
                setActive(document.getElementById('btnDaily'));
                initDailyChart();
            }

            // Animate stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Animate chart containers
            const chartContainers = document.querySelectorAll('.chart-container');
            chartContainers.forEach((container, index) => {
                container.style.opacity = '0';
                container.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    container.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    container.style.opacity = '1';
                    container.style.transform = 'translateY(0)';
                }, 300 + (index * 100));
            });
        });
