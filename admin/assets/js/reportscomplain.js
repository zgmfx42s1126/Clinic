const reportDataEl = document.getElementById('reportscomplaint-data');
if (reportDataEl) {
    const bgUrl = reportDataEl.dataset.reportBgUrl || '';
    if (bgUrl) {
        document.documentElement.style.setProperty('--report-bg-url', `url('${bgUrl}')`);
    }
}

function withCommonParams() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const reportType = document.getElementById('reportType').value;
        const gradeSection = document.getElementById('gradeSectionFilter') ? document.getElementById('gradeSectionFilter').value : '';

        const complaintsPerPage = document.getElementById('complaintsPerPage') ? document.getElementById('complaintsPerPage').value : '10';
        const recordsPerPage = document.getElementById('recordsPerPage') ? document.getElementById('recordsPerPage').value : '10';

        return { startDate, endDate, reportType, gradeSection, complaintsPerPage, recordsPerPage };
    }

    // ✅ PRINT whole report (both complaint + detailed)
    function printWholePage() {
        const mainContent = document.querySelector('.main-content');
        const t = document.querySelector('.print-template');
        if (!t) return alert('Print template not found.');

        if (mainContent) mainContent.style.display = 'none';
        t.classList.add('print-active');

        t.offsetHeight;

        setTimeout(() => {
            window.print();
            setTimeout(() => {
                if (mainContent) mainContent.style.display = 'block';
                t.classList.remove('print-active');
            }, 200);
        }, 200);
    }

    // ✅ PRINT complaint table only
    function printComplaintTableOnly() {
        const mainContent = document.querySelector('.main-content');
        const t = document.querySelector('.print-complaint-only');
        if (!t) return alert('Complaint print template not found.');

        if (mainContent) mainContent.style.display = 'none';
        t.classList.add('print-active');

        t.offsetHeight;

        setTimeout(() => {
            window.print();
            setTimeout(() => {
                if (mainContent) mainContent.style.display = 'block';
                t.classList.remove('print-active');
            }, 200);
        }, 200);
    }

    // ✅ PRINT detailed table only
    function printDetailedTableOnly() {
        const mainContent = document.querySelector('.main-content');
        const t = document.querySelector('.print-detailed-only');
        if (!t) return alert('Detailed print template not found.');

        if (mainContent) mainContent.style.display = 'none';
        t.classList.add('print-active');

        t.offsetHeight;

        setTimeout(() => {
            window.print();
            setTimeout(() => {
                if (mainContent) mainContent.style.display = 'block';
                t.classList.remove('print-active');
            }, 200);
        }, 200);
    }

    // ✅ Filters
    function applyFilters() {
        const { startDate, endDate, reportType, gradeSection, complaintsPerPage, recordsPerPage } = withCommonParams();
        let url = `?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}&page=1&complaints_page=1&complaints_per_page=${complaintsPerPage}&records_per_page=${recordsPerPage}`;
        if (gradeSection) url += `&grade_section=${encodeURIComponent(gradeSection)}`;
        window.location.href = url;
    }
    function applyDateFilter(){ applyFilters(); }

    function resetDateFilter() {
        const today = new Date().toISOString().split('T')[0];
        window.location.href = `?start_date=${today}&end_date=${today}&report_type=today&page=1&complaints_page=1&complaints_per_page=10&records_per_page=10`;
    }

    // ✅ change per-page for complaints
    function applyComplaintPerPage() {
        const { startDate, endDate, reportType, gradeSection, complaintsPerPage, recordsPerPage } = withCommonParams();
        let url = `?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}&page=1&complaints_page=1&complaints_per_page=${complaintsPerPage}&records_per_page=${recordsPerPage}`;
        if (gradeSection) url += `&grade_section=${encodeURIComponent(gradeSection)}`;
        window.location.href = url;
    }

    // ✅ change per-page for detailed
    function applyRecordsPerPage() {
        const { startDate, endDate, reportType, gradeSection, complaintsPerPage, recordsPerPage } = withCommonParams();
        let url = `?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}&page=1&complaints_page=1&complaints_per_page=${complaintsPerPage}&records_per_page=${recordsPerPage}`;
        if (gradeSection) url += `&grade_section=${encodeURIComponent(gradeSection)}`;
        window.location.href = url;
    }

    // ✅ Detailed pagination (keeps complaint page + per-page)
    function changePage(newPage) {
        if (newPage < 1) return;

        const { startDate, endDate, reportType, gradeSection, complaintsPerPage, recordsPerPage } = withCommonParams();
        const urlParams = new URLSearchParams(window.location.search);
        const cPage = urlParams.get('complaints_page') || '1';

        let url = `?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}&page=${newPage}&complaints_page=${cPage}&complaints_per_page=${complaintsPerPage}&records_per_page=${recordsPerPage}`;
        if (gradeSection) url += `&grade_section=${encodeURIComponent(gradeSection)}`;
        window.location.href = url;
    }

    // ✅ Complaint pagination (keeps detailed page + per-page)
    function changeComplaintsPage(newPage) {
        if (newPage < 1) return;

        const { startDate, endDate, reportType, gradeSection, complaintsPerPage, recordsPerPage } = withCommonParams();
        const urlParams = new URLSearchParams(window.location.search);
        const dPage = urlParams.get('page') || '1';

        let url = `?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}&page=${dPage}&complaints_page=${newPage}&complaints_per_page=${complaintsPerPage}&records_per_page=${recordsPerPage}`;
        if (gradeSection) url += `&grade_section=${encodeURIComponent(gradeSection)}`;
        window.location.href = url;
    }

    // Search (on screen only)
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const rows = document.querySelectorAll('.simple-table tbody tr');

        if (searchInput && rows.length > 0) {
            searchInput.addEventListener('keyup', () => {
                const value = searchInput.value.toLowerCase();
                rows.forEach(row => {
                    row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
                });
            });
        }

        // Animate bars
        const bars = document.querySelectorAll('.bar-fill');
        bars.forEach((bar, index) => {
            const currentWidth = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.transition = 'width 1s ease-out';
                bar.style.width = currentWidth;
            }, index * 100);
        });
    });

    // Auto-update date range based on report type
    document.getElementById('reportType').addEventListener('change', function() {
        const reportType = this.value;
        const endDateInput = document.getElementById('endDate');
        const startDateInput = document.getElementById('startDate');

        if (reportType === 'today') {
            const today = new Date().toISOString().split('T')[0];
            startDateInput.value = today;
            endDateInput.value = today;
            if (document.getElementById('gradeSectionFilter')) document.getElementById('gradeSectionFilter').value = '';
            return;
        }

        const endDate = new Date(endDateInput.value);
        let startDate = new Date(endDate);

        switch(reportType) {
            case 'weekly':
                startDate.setDate(startDate.getDate() - 7);
                break;
            case 'monthly':
                startDate = new Date(endDate.getFullYear(), endDate.getMonth(), 1);
                const lastDay = new Date(endDate.getFullYear(), endDate.getMonth() + 1, 0);
                endDateInput.value = lastDay.toISOString().split('T')[0];
                break;
            case 'yearly':
                startDate.setFullYear(startDate.getFullYear() - 1);
                break;
        }
        startDateInput.value = startDate.toISOString().split('T')[0];
        if (document.getElementById('gradeSectionFilter')) document.getElementById('gradeSectionFilter').value = '';
    });
