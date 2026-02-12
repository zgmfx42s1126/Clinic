function buildUrl(page = 1, perPage = null) {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const reportType = document.getElementById('reportType').value;
    const gradeSection = document.getElementById('gradeSectionFilter').value;

    let url = `?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&report_type=${encodeURIComponent(reportType)}&page=${page}`;

    if (gradeSection) url += `&grade_section=${encodeURIComponent(gradeSection)}`;

    const currentPerPage = perPage ?? (document.getElementById('perPageSelect') ? document.getElementById('perPageSelect').value : null);
    if (currentPerPage) url += `&per_page=${encodeURIComponent(currentPerPage)}`;

    return url;
}

function applyFilters() { window.location.href = buildUrl(1); }

function resetFilters() {
    const todayStr = new Date().toISOString().split('T')[0];
    window.location.href = `?start_date=${encodeURIComponent(todayStr)}&end_date=${encodeURIComponent(todayStr)}&report_type=${encodeURIComponent("Today's Analysis")}&page=1&per_page=10`;
}

function changePage(newPage) { window.location.href = buildUrl(newPage); }

function changeRecordsPerPage(perPage) { window.location.href = buildUrl(1, perPage); }

function confirmDelete(form, label) {
    const id = form.querySelector('input[name="delete_id"]')?.value || '';
    const labelText = label || 'record';
    return confirm(`Delete this ${labelText}${id ? ` (#${id})` : ''}?`);
}

function searchTable() {
    const input = document.getElementById("searchInput");
    const filter = input.value.toUpperCase();
    const table = document.getElementById("logsTable");
    const tr = table.getElementsByTagName("tr");

    for (let i = 0; i < tr.length; i++) {
        const td = tr[i].getElementsByTagName("td");
        let found = false;
        for (let j = 0; j < td.length; j++) {
            const cell = td[j];
            if (cell) {
                const txtValue = cell.textContent || cell.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) { found = true; break; }
            }
        }
        tr[i].style.display = found ? "" : "none";
    }
}

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
        case 'Weekly Analysis': startDate.setDate(startDate.getDate() - 7); break;
        case 'Monthly Analysis': startDate = new Date(endDate.getFullYear(), endDate.getMonth(), 1); break;
        case 'Yearly Analysis': startDate.setFullYear(startDate.getFullYear() - 1); break;
    }
    startDateInput.value = startDate.toISOString().split('T')[0];
});
