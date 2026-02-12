function getPerPage() {
        const sel = document.getElementById('perPageSelect');
        return sel ? sel.value : '10';
    }

    function buildUrl(page = 1, perPage = null) {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const reportType = document.getElementById('reportType').value;
        const gradeSection = document.getElementById('gradeSectionFilter').value;

        const per = (perPage !== null) ? perPage : getPerPage();

        let url = `?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&report_type=${encodeURIComponent(reportType)}&page=${page}`;

        if (gradeSection) url += `&grade_section=${encodeURIComponent(gradeSection)}`;
        if (per) url += `&per_page=${encodeURIComponent(per)}`;

        return url;
    }

    function applyFilters() {
        window.location.href = buildUrl(1);
    }

    function resetFilters() {
        const today = new Date();
        const todayStr = today.toISOString().split('T')[0];

        document.getElementById('startDate').value = todayStr;
        document.getElementById('endDate').value = todayStr;
        document.getElementById('reportType').value = "Today's Analysis";
        document.getElementById('gradeSectionFilter').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('searchInput').value = '';

        window.location.href = `?start_date=${encodeURIComponent(todayStr)}&end_date=${encodeURIComponent(todayStr)}&report_type=${encodeURIComponent("Today's Analysis")}&page=1&per_page=10`;
    }

    function changePage(newPage) {
        if (newPage < 1) return;
        window.location.href = buildUrl(newPage);
    }

    // ✅ per-page change: always go back to page 1, choices won't disappear
function changeRecordsPerPage(perPage) {
    window.location.href = buildUrl(1, perPage);
}

function confirmDelete(form, label) {
    const id = form.querySelector('input[name="delete_id"]')?.value || '';
    const labelText = label || 'record';
    return confirm(`Delete this ${labelText}${id ? ` (#${id})` : ''}?`);
}

    // Auto-update date range when report type changes
    document.getElementById('reportType').addEventListener('change', function() {
        const reportType = this.value;
        const endDateInput = document.getElementById('endDate');
        const startDateInput = document.getElementById('startDate');

        if (reportType === "Today's Analysis") {
            const todayStr = new Date().toISOString().split('T')[0];
            startDateInput.value = todayStr;
            endDateInput.value = todayStr;
            return;
        }

        const endDate = new Date(endDateInput.value);
        let startDate = new Date(endDate);

        switch(reportType) {
            case 'Weekly Analysis':
                startDate.setDate(startDate.getDate() - 7);
                break;
            case 'Monthly Analysis':
                startDate = new Date(endDate.getFullYear(), endDate.getMonth(), 1);
                break;
            case 'Yearly Analysis':
                startDate.setFullYear(startDate.getFullYear() - 1);
                break;
        }
        startDateInput.value = startDate.toISOString().split('T')[0];
    });

    // Search function (client-side)
    function searchTable() {
        const input = document.getElementById("searchInput");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("patientsTable");
        if (!table) return;

        const tr = table.getElementsByTagName("tr");
        for (let i = 1; i < tr.length; i++) {
            const tds = tr[i].getElementsByTagName("td");
            let found = false;

            for (let j = 0; j < tds.length; j++) {
                const txtValue = (tds[j].textContent || tds[j].innerText || '').toUpperCase();
                if (txtValue.indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
            tr[i].style.display = found ? "" : "none";
        }
    }

    // Filter table by status (client-side)
    function filterTableByStatus() {
        const filter = document.getElementById("statusFilter").value.toUpperCase();
        const table = document.getElementById("patientsTable");
        if (!table) return;

        const tr = table.getElementsByTagName("tr");
        for (let i = 1; i < tr.length; i++) {
            const statusTd = tr[i].getElementsByTagName("td")[6]; // Status column
            if (!statusTd) continue;

            const statusText = (statusTd.textContent || statusTd.innerText || '').toUpperCase();
            const status = statusText.includes("TREATED") ? "TREATED" : "PENDING";

            if (filter === "" || (filter === "TREATED" && status === "TREATED") || (filter === "PENDING" && status === "PENDING")) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
