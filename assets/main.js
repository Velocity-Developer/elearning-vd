(() => {
    const chartElementId = 'elvdDashboardChart';

    function createDashboardChart() {
        const canvas = document.getElementById(chartElementId);
        const chartData = window.elvdDashboardChartData;

        if (!canvas || !window.Chart || !chartData || canvas.dataset.elvdChartReady === 'true') {
            return;
        }

        const labels = Array.isArray(chartData.labels) ? chartData.labels : [];
        const values = Array.isArray(chartData.values) ? chartData.values.map((value) => Number(value) || 0) : [];

        canvas.dataset.elvdChartReady = 'true';
        canvas.closest('.elvd-panel')?.classList.add('has-chart');

        new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        data: values,
                        backgroundColor: '#024ad8',
                        borderRadius: 4,
                        borderSkipped: false,
                        barThickness: 28,
                        maxBarThickness: 36,
                    },
                ],
            },
            options: {
                animation: {
                    duration: 600,
                },
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        backgroundColor: '#1a1a1a',
                        bodyColor: '#ffffff',
                        displayColors: false,
                        padding: 12,
                        titleColor: '#ffffff',
                    },
                },
                scales: {
                    x: {
                        border: {
                            display: false,
                        },
                        grid: {
                            display: false,
                        },
                        ticks: {
                            color: '#3d3d3d',
                            font: {
                                family: '"Forma DJR Micro", Manrope, Inter, Arial, sans-serif',
                                size: 12,
                                weight: 500,
                            },
                            maxRotation: 0,
                            minRotation: 0,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        border: {
                            display: false,
                        },
                        grid: {
                            color: '#e8e8e8',
                        },
                        ticks: {
                            color: '#636363',
                            precision: 0,
                            font: {
                                family: '"Forma DJR Micro", Manrope, Inter, Arial, sans-serif',
                                size: 12,
                            },
                        },
                    },
                },
            },
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createDashboardChart);
        return;
    }

    createDashboardChart();
})();
