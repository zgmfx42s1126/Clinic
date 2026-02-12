const reportsDataEl = document.getElementById('reportslogs-data');
const reportsData = reportsDataEl ? {
    chartLabels: JSON.parse(reportsDataEl.dataset.chartLabels || '[]'),
    chartData: JSON.parse(reportsDataEl.dataset.chartData || '[]'),
    pieLabels: JSON.parse(reportsDataEl.dataset.pieLabels || '[]'),
    pieData: JSON.parse(reportsDataEl.dataset.pieData || '[]'),
    pieColors: JSON.parse(reportsDataEl.dataset.pieColors || '[]'),
    totalPages: parseInt(reportsDataEl.dataset.totalPages || '1', 10),
    reportBgUrl: reportsDataEl.dataset.reportBgUrl || ''
} : {
    chartLabels: [],
    chartData: [],
    pieLabels: [],
    pieData: [],
    pieColors: [],
    totalPages: 1,
    reportBgUrl: ''
};

const chartLabels = reportsData.chartLabels;
const chartData = reportsData.chartData;
const pieLabels = reportsData.pieLabels;
const pieData = reportsData.pieData;
const pieColors = reportsData.pieColors;
const reportBgUrl = reportsData.reportBgUrl;

if (reportBgUrl) {
    document.documentElement.style.setProperty('--report-bg-url', `url('${reportBgUrl}')`);
}

document.addEventListener('DOMContentLoaded', function() {
    // Daily Visits Line Chart
    const daily = document.getElementById('dailyVisitsChart');
    if (daily) {
        const dailyCtx = daily.getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Daily Visits',
                    data: chartData,
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#4361ee',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Number of Visits' }, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { title: { display: true, text: 'Date' }, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { maxRotation: 45, minRotation: 45 } }
                }
            }
        });
    }

    // Pie Chart
    const pie = document.getElementById('classDistributionChart');
    if (pie) {
        const pieCtx = pie.getContext('2d');
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{
                    data: pieData,
                    backgroundColor: pieColors,
                    borderColor: '#fff',
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total ? Math.round((value / total) * 100) : 0;
                                return `${label}: ${value} visits (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
    }

    // Search (client-side)
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('logsTable');
    if (searchInput && table) {
        const rows = table.querySelectorAll('tbody tr');
        searchInput.addEventListener('keyup', () => {
            const value = searchInput.value.toLowerCase();
            rows.forEach(row => row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none');
        });
    }

    // Auto apply grade/section
    const gradeSectionFilter = document.getElementById('gradeSectionFilter');
    if (gradeSectionFilter) {
        gradeSectionFilter.addEventListener('change', function() {
            setTimeout(() => { applyFilters(); }, 60);
        });
    }
});

/* ===========================
   ✅ FILTERS (keep per_page persistent)
   =========================== */
function getPerPage() {
    const sel = document.getElementById('recordsPerPageSelect');
    return sel ? sel.value : '10';
}

function buildUrl(page = 1, perPage = null) {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const reportType = document.getElementById('reportType').value;
    const gradeSection = document.getElementById('gradeSectionFilter') ? document.getElementById('gradeSectionFilter').value : '';
    const per = perPage !== null ? perPage : getPerPage();

    let url = `?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&report_type=${encodeURIComponent(reportType)}&page=${page}&per_page=${encodeURIComponent(per)}`;
    if (gradeSection) url += `&grade_section=${encodeURIComponent(gradeSection)}`;
    return url;
}

function applyFilters() {
    window.location.href = buildUrl(1);
}

function resetDateFilter() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('startDate').value = today;
    document.getElementById('endDate').value = today;
    document.getElementById('reportType').value = 'today';
    if (document.getElementById('gradeSectionFilter')) document.getElementById('gradeSectionFilter').value = '';
    if (document.getElementById('recordsPerPageSelect')) document.getElementById('recordsPerPageSelect').value = '10';
    window.location.href = buildUrl(1, '10');
}

function changePage(newPage) {
    if (newPage < 1 || newPage > reportsData.totalPages) return;
    window.location.href = buildUrl(newPage);
}

function changeRecordsPerPage(perPage) {
    window.location.href = buildUrl(1, perPage);
}

/* Auto update date range based on reportType */
document.getElementById('reportType').addEventListener('change', function() {
    const reportType = this.value;
    const today = new Date();
    const formatDate = (d) => d.toISOString().split('T')[0];

    let startDate = new Date(today);
    let endDate = new Date(today);

    switch(reportType) {
        case 'today':
            break;
        case 'weekly':
            startDate.setDate(today.getDate() - 7);
            break;
        case 'monthly':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            break;
        case 'yearly':
            startDate = new Date(today.getFullYear(), 0, 1);
            startDate.setFullYear(today.getFullYear() - 1);
            break;
    }

    document.getElementById('startDate').value = formatDate(startDate);
    document.getElementById('endDate').value = formatDate(endDate);
});

/* ===========================
   ✅ PRINT HELPERS (unchanged)
   =========================== */
function buildPagedTableHTML(tableEl, rowsPerPage = 10) {
    const thead = tableEl.querySelector('thead')?.outerHTML || '';
    const rows = Array.from(tableEl.querySelectorAll('tbody tr'));
    if (!rows.length) return [];

    const pages = [];
    for (let i = 0; i < rows.length; i += rowsPerPage) {
        const chunk = rows.slice(i, i + rowsPerPage).map(r => r.outerHTML).join('');
        pages.push(`
            <table>
                ${thead}
                <tbody>${chunk}</tbody>
            </table>
        `);
    }
    return pages;
}

function printBaseStyles(bgUrl) {
    return `
        @page{ size:A4; margin:0; }
        *{ box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
        body{
            margin:0; padding:0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            background:white;
        }
        .print-page{
            width:210mm; height:297mm;
            position:relative;
            page-break-after:always;
            overflow:hidden;
            background:#fff;
            ${bgUrl ? `background-image:url('${bgUrl}');` : ''}
            background-size: cover;
            background-position: center top;
            background-repeat:no-repeat;
        }
        .print-page:last-child{ page-break-after:auto; }
        .print-content{ padding:22mm 18mm 42mm 18mm; }
        .print-header{
            text-align:center;
            margin-bottom:16px;
            padding:12px;
            background:rgba(255,255,255,0.95);
            border-radius:8px;
            border:2px solid #4361ee;
        }
        .print-header h1{ margin:0; font-size:22px; color:#4361ee; }
        .subtitle{ margin-top:6px; color:#666; font-size:14px; }
        .print-info{
            margin:12px 0 16px 0;
            background:rgba(255,255,255,0.95);
            border:1px solid #ddd;
            border-radius:8px;
            padding:10px 12px;
            font-size:12px;
            display:flex; justify-content:space-between;
            gap:12px; flex-wrap:wrap;
        }
        .section-title{
            margin: 14px 0 10px 0;
            background: rgba(67,97,238,0.92);
            color:#fff;
            padding:10px 12px;
            border-radius:6px;
            font-size:14px;
            font-weight:700;
        }
        table{
            width:100%;
            border-collapse:collapse;
            background:rgba(255,255,255,0.95);
            font-size:11px;
        }
        th, td{ border:1px solid #000; padding:6px; vertical-align:top; }
        th{ background:#f0f0f0; color:#000; font-weight:700; }
        tr{ page-break-inside:avoid; }
        thead{ display:table-header-group; }
        .print-footer{
            position:absolute;
            left:18mm; right:18mm; bottom:14mm;
            text-align:center;
            font-size:11px;
            color:#555;
            background:rgba(255,255,255,0.90);
            border:1px solid #ddd;
            border-radius:6px;
            padding:6px 10px;
        }
    `;
}

function printGradeStats() {
    const gradeStatsTable = document.querySelector('.grade-stats-table');
    if (!gradeStatsTable) { alert('Grade statistics table not found'); return; }

    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const reportType = document.getElementById('reportType').value;
    const gradeSection = document.getElementById('gradeSectionFilter') ? document.getElementById('gradeSectionFilter').value : '';

    const startDateFormatted = new Date(startDate).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    const endDateFormatted   = new Date(endDate).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });

    const bgUrl = reportBgUrl;

    const win = window.open('', '', 'width=1200,height=700');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Grade Level Statistics</title>
                <link rel="stylesheet" href="../assets/css/reportslogs.css">
</head>
        <body>
            <div class="print-page">
                <div class="print-content">
                    <div class="print-header">
                        <h1>Monthly Logs Reports</h1>
                        <div class="subtitle">Comprehensive Analysis of Clinic Visits</div>
                    </div>

                    <div class="print-info">
                        <div><strong>Report Period:</strong> ${startDateFormatted}${startDate === endDate ? '' : ' to ' + endDateFormatted}</div>
                        <div><strong>Report Type:</strong> ${reportType === 'today' ? "Today's Report" : reportType.charAt(0).toUpperCase() + reportType.slice(1) + ' Analysis'}</div>
                        ${gradeSection ? `<div><strong>Class Filter:</strong> ${gradeSection}</div>` : ''}
                        <div><strong>Generated:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
                    </div>

                    <div class="section-title">Grade Level Statistics</div>
                    ${gradeStatsTable.outerHTML}

                    <div class="print-footer">Report generated by Clinic Management System</div>
                </div>
            </div>

            <script>
                window.onload = function(){
                    window.print();
                    window.onafterprint = function(){ window.close(); }
                }
            <\/script>
        </body>
        </html>
    `);
    win.document.close();
}

function printTable() {
    const table = document.getElementById('logsTable');
    if (!table) { alert('Table not found'); return; }

    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const reportType = document.getElementById('reportType').value;
    const gradeSection = document.getElementById('gradeSectionFilter').value;

    const startDateFormatted = new Date(startDate).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    const endDateFormatted   = new Date(endDate).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });

    const bgUrl = reportBgUrl;

    const pages = buildPagedTableHTML(table, 10);
    if (!pages.length) { alert('No rows to print'); return; }

    const htmlPages = pages.map((pageTable, idx) => `
        <div class="print-page">
            <div class="print-content">
                <div class="print-header">
                    <h1>Monthly Logs Reports</h1>
                    <div class="subtitle">Comprehensive Analysis of Clinic Visits</div>
                </div>

                <div class="print-info">
                    <div><strong>Report Period:</strong> ${startDateFormatted}${startDate === endDate ? '' : ' to ' + endDateFormatted}</div>
                    <div><strong>Report Type:</strong> ${reportType === 'today' ? "Today's Report" : reportType.charAt(0).toUpperCase() + reportType.slice(1) + ' Analysis'}</div>
                    ${gradeSection ? `<div><strong>Class Filter:</strong> ${gradeSection}</div>` : ''}
                    <div><strong>Generated:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
                </div>

                <div class="section-title">Clinic Visits Log (Page ${idx + 1} of ${pages.length})</div>
                ${pageTable}

                <div class="print-footer">Report generated by Clinic Management System</div>
            </div>
        </div>
    `).join('');

    const win = window.open('', '', 'width=1200,height=700');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Clinic Visits Log</title>
                <link rel="stylesheet" href="../assets/css/reportslogs.css">
</head>
        <body>
            ${htmlPages}
            <script>
                window.onload = function(){
                    window.print();
                    window.onafterprint = function(){ window.close(); }
                }
            <\/script>
        </body>
        </html>
    `);
    win.document.close();
}

function printWholePage() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const reportType = document.getElementById('reportType').value;
    const gradeSection = document.getElementById('gradeSectionFilter').value;

    const startDateFormatted = new Date(startDate).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    const endDateFormatted   = new Date(endDate).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });

    const table = document.getElementById('logsTable');
    const gradeStatsTable = document.querySelector('.grade-stats-table');
    const bgUrl = reportBgUrl;

    const logPages = table ? buildPagedTableHTML(table, 10) : [];

    let htmlPages = `
        <div class="print-page">
            <div class="print-content">
                <div class="print-header">
                    <h1>Monthly Logs Reports</h1>
                    <div class="subtitle">Comprehensive Analysis of Clinic Visits</div>
                </div>

                <div class="print-info">
                    <div><strong>Report Period:</strong> ${startDateFormatted}${startDate === endDate ? '' : ' to ' + endDateFormatted}</div>
                    <div><strong>Report Type:</strong> ${reportType === 'today' ? "Today's Report" : reportType.charAt(0).toUpperCase() + reportType.slice(1) + ' Analysis'}</div>
                    ${gradeSection ? `<div><strong>Class Filter:</strong> ${gradeSection}</div>` : ''}
                    <div><strong>Generated:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
                </div>

                <div class="section-title">Grade Level Statistics</div>
                ${gradeStatsTable ? gradeStatsTable.outerHTML : '<div style="background:rgba(255,255,255,0.95);padding:12px;border:1px solid #000;">No grade level statistics available.</div>'}

                <div class="print-footer">Report generated by Clinic Management System</div>
            </div>
        </div>
    `;

    if (logPages.length) {
        htmlPages += logPages.map((pageTable, idx) => `
            <div class="print-page">
                <div class="print-content">
                    <div class="print-header">
                        <h1>Monthly Logs Reports</h1>
                        <div class="subtitle">Comprehensive Analysis of Clinic Visits</div>
                    </div>

                    <div class="print-info">
                        <div><strong>Report Period:</strong> ${startDateFormatted}${startDate === endDate ? '' : ' to ' + endDateFormatted}</div>
                        <div><strong>Report Type:</strong> ${reportType === 'today' ? "Today's Report" : reportType.charAt(0).toUpperCase() + reportType.slice(1) + ' Analysis'}</div>
                        ${gradeSection ? `<div><strong>Class Filter:</strong> ${gradeSection}</div>` : ''}
                        <div><strong>Generated:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
                    </div>

                    <div class="section-title">Clinic Visits Log (Page ${idx + 1} of ${logPages.length})</div>
                    ${pageTable}

                    <div class="print-footer">Report generated by Clinic Management System</div>
                </div>
            </div>
        `).join('');
    }

    const win = window.open('', '', 'width=1200,height=700');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Monthly Logs Report</title>
                <link rel="stylesheet" href="../assets/css/reportslogs.css">
</head>
        <body>
            ${htmlPages}
            <script>
                window.onload = function(){
                    window.print();
                    window.onaffterprint = function(){ window.close(); }
                }
            <\/script>
        </body>
        </html>
    `);
    win.document.close();
}
