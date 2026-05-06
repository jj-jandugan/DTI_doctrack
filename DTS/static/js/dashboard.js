document.addEventListener('DOMContentLoaded', function() {
    // 1. Force Update Modal Trigger
    const forceModalEl = document.getElementById('forceUpdateModal');
    if (forceModalEl) {
        const updateModal = new bootstrap.Modal(forceModalEl, {
            backdrop: 'static',
            keyboard: false
        });
        updateModal.show();
    }

    // 2. Status Distribution Pie Chart
    const ctxPie = document.getElementById('statusPieChart');
    if (ctxPie && typeof dashboardData !== 'undefined') {
        new Chart(ctxPie.getContext('2d'), {
            type: 'pie',
            data: {
                labels: ['Incoming Encoded', 'Ready to Dispatch', 'On Hand', 'Closed/Released'],
                datasets: [{
                    data: dashboardData.pieData,
                    backgroundColor: ['#3b82f6', '#f59e0b', '#8b5cf6', '#10b981'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // 3. Monthly Processing Volume Bar Chart
    const ctxBar = document.getElementById('volumeBarChart');
    if (ctxBar && typeof dashboardData !== 'undefined') {
        new Chart(ctxBar.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Documents Handled',
                    data: dashboardData.barData,
                    backgroundColor: '#263D81',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { display: false } },
                    x: { grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }
});