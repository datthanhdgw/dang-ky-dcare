/**
 * Reports Dropdown Handler
 */

document.addEventListener('DOMContentLoaded', function () {
    const btnReports = document.getElementById('btnReports');
    const reportsMenu = document.getElementById('reportsMenu');

    if (!btnReports || !reportsMenu) {
        return;
    }

    btnReports.addEventListener('click', function (e) {
        e.stopPropagation();
        reportsMenu.classList.toggle('show');
        btnReports.classList.toggle('active');
    });

    document.addEventListener('click', function (e) {
        if (!btnReports.contains(e.target) && !reportsMenu.contains(e.target)) {
            reportsMenu.classList.remove('show');
            btnReports.classList.remove('active');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            reportsMenu.classList.remove('show');
            btnReports.classList.remove('active');
        }
    });
});
