document.addEventListener('DOMContentLoaded', function () {
    fetch('/ajax/get_stats')
        .then(res => res.json())
        .then(data => {
            // Aggiornamento statistiche sintetiche contatori
            const elTotali = document.getElementById('stat-totali');
            const elSiti   = document.getElementById('stat-siti');
            const elErrori = document.getElementById('stat-errori');

            if (elTotali) elTotali.textContent = new Intl.NumberFormat('it-IT').format(data.enti_totali || 0);
            if (elSiti)   elSiti.textContent   = new Intl.NumberFormat('it-IT').format(data.enti_siti_web || 0);
            if (elErrori) elErrori.textContent = new Intl.NumberFormat('it-IT').format(data.enti_errori || 0);

            // Palette colori per le torte a categorie multiple
            const palette = ['#0d6efd', '#198754', '#ffc107', '#0dcaf0', '#6f42c1', '#6c757d'];

            // Opzioni comuni riutilizzabili per Chart.js con calcolo percentuale nel tooltip
            const basePieOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const dataset = context.dataset;
                                const total = dataset.data.reduce((acc, curr) => acc + curr, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return ` ${label}: ${new Intl.NumberFormat('it-IT').format(value)} (${percentage}%)`;
                            }
                        }
                    }
                }
            };

            // 1. Render Hosting Pie Chart
            const elHosting = document.getElementById('chart-hosting');
            if (elHosting && data.top_hosting && data.top_hosting.length > 0) {
                new Chart(elHosting, {
                    type: 'pie',
                    data: {
                        labels: data.top_hosting.map(item => item.provider),
                        datasets: [{
                            data: data.top_hosting.map(item => parseInt(item.total, 10)),
                            backgroundColor: palette,
                            borderWidth: 1
                        }]
                    },
                    options: basePieOptions
                });
            }

            // 2. Render Mail Provider Pie Chart
            const elMail = document.getElementById('chart-mail');
            if (elMail && data.top_mail && data.top_mail.length > 0) {
                new Chart(elMail, {
                    type: 'pie',
                    data: {
                        labels: data.top_mail.map(item => item.provider),
                        datasets: [{
                            data: data.top_mail.map(item => parseInt(item.total, 10)),
                            backgroundColor: palette,
                            borderWidth: 1
                        }]
                    },
                    options: basePieOptions
                });
            }

            // 3. Render Top CMS Pie Chart (Top 5 + Altri)
            const elCms = document.getElementById('chart-cms');
            if (elCms && data.top_cms && data.top_cms.length > 0) {
                new Chart(elCms, {
                    type: 'doughnut',
                    data: {
                        labels: data.top_cms.map(item => item.cms),
                        datasets: [{
                            data: data.top_cms.map(item => parseInt(item.total, 10)),
                            backgroundColor: palette,
                            borderWidth: 1
                        }]
                    },
                    options: basePieOptions
                });
            }

            // 4. Render PHP Versions Pie Chart
            const elPhp = document.getElementById('chart-php');
            if (elPhp && data.top_php && data.top_php.length > 0) {
                new Chart(elPhp, {
                    type: 'pie',
                    data: {
                        labels: data.top_php.map(item => `PHP ${item.version}`),
                        datasets: [{
                            data: data.top_php.map(item => parseInt(item.total, 10)),
                            backgroundColor: ['#4f5b93', '#8892bf', '#20c997', '#fd7e14', '#dc3545', '#6c757d'],
                            borderWidth: 1
                        }]
                    },
                    options: basePieOptions
                });
            }

            // 5. Render Email Security Pie Chart (SPF & DKIM)
            const elSecMail = document.getElementById('chart-email-security');
            if (elSecMail && data.email_security) {
                const sec = data.email_security;
                new Chart(elSecMail, {
                    type: 'doughnut',
                    data: {
                        labels: [
                            'SPF Configurato',
                            'SPF Assente / Non Valido',
                            'DKIM Rilevato',
                            'DKIM Non Rilevato'
                        ],
                        datasets: [{
                            data: [
                                parseInt(sec.spf_present || 0, 10),
                                parseInt(sec.spf_missing || 0, 10),
                                parseInt(sec.dkim_present || 0, 10),
                                parseInt(sec.dkim_missing || 0, 10)
                            ],
                            backgroundColor: ['#198754', '#ffc107', '#0d6efd', '#dc3545'],
                            borderWidth: 1
                        }]
                    },
                    options: basePieOptions
                });
            }
        })
        .catch(err => console.error('Error fetching dashboard stats:', err));
});
