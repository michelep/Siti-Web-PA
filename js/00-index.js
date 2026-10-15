document.addEventListener('DOMContentLoaded', function () {
    fetch('/ajax/get_stats')
        .then(res => res.json())
        .then(data => {
            // Update Summary Stats
            document.getElementById('stat-totali').textContent = new Intl.NumberFormat('it-IT').format(data.enti_totali || 0);
            document.getElementById('stat-siti').textContent = new Intl.NumberFormat('it-IT').format(data.enti_siti_web || 0);
            document.getElementById('stat-errori').textContent = new Intl.NumberFormat('it-IT').format(data.enti_errori || 0);

            const palette = ['#0d6efd', '#198754', '#ffc107', '#0dcaf0', '#6c757d'];

            // 1. Render Hosting Pie Chart
            if (data.top_hosting && data.top_hosting.length > 0) {
                const hostingLabels = data.top_hosting.map(item => item.provider);
                const hostingValues = data.top_hosting.map(item => parseInt(item.total, 10));

                new Chart(document.getElementById('chart-hosting'), {
                    type: 'pie',
                    data: {
                        labels: hostingLabels,
                        datasets: [{
                            data: hostingValues,
                            backgroundColor: palette,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }

            // 2. Render Mail Provider Pie Chart
            if (data.top_mail && data.top_mail.length > 0) {
                const mailLabels = data.top_mail.map(item => item.provider);
                const mailValues = data.top_mail.map(item => parseInt(item.total, 10));

                new Chart(document.getElementById('chart-mail'), {
                    type: 'pie',
                    data: {
                        labels: mailLabels,
                        datasets: [{
                            data: mailValues,
                            backgroundColor: palette,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }
        })
        .catch(err => console.error('Error fetching dashboard stats:', err));
});
