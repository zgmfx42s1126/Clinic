const reportDataEl = document.getElementById('report-data');
if (reportDataEl) {
  const bgUrl = reportDataEl.dataset.reportBgUrl || '';
  if (bgUrl) {
    document.documentElement.style.setProperty('--report-bg-url', `url('${bgUrl}')`);
  }
}
