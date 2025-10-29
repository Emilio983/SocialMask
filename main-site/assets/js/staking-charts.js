/**
 * Staking Charts
 * Manejo de visualizaciones con Chart.js
 */

class StakingCharts {
    constructor() {
        this.charts = {};
        this.defaultColors = {
            primary: 'rgba(102, 126, 234, 1)',
            success: 'rgba(56, 239, 125, 1)',
            warning: 'rgba(245, 87, 108, 1)',
            info: 'rgba(33, 150, 243, 1)',
            primaryTransparent: 'rgba(102, 126, 234, 0.2)',
            successTransparent: 'rgba(56, 239, 125, 0.2)',
            warningTransparent: 'rgba(245, 87, 108, 0.2)',
            infoTransparent: 'rgba(33, 150, 243, 0.2)'
        };
    }

    /**
     * Initialize rewards history chart
     */
    initRewardsChart(canvasId, data) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        // Destroy existing chart
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        // Create gradient
        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, this.defaultColors.primaryTransparent);
        gradient.addColorStop(1, 'rgba(102, 126, 234, 0)');

        this.charts[canvasId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Rewards (SPHE)',
                    data: data.values || [],
                    borderColor: this.defaultColors.primary,
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: this.defaultColors.primary,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: this.defaultColors.primary,
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'Rewards: ' + parseFloat(context.parsed.y).toFixed(4) + ' SPHE';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6c757d'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [5, 5],
                            color: '#e9ecef'
                        },
                        ticks: {
                            color: '#6c757d',
                            callback: function(value) {
                                return value.toFixed(2);
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });

        return this.charts[canvasId];
    }

    /**
     * Initialize pool distribution pie chart
     */
    initPoolDistributionChart(canvasId, poolData) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        const colors = [
            this.defaultColors.primary,
            this.defaultColors.success,
            this.defaultColors.info,
            this.defaultColors.warning
        ];

        this.charts[canvasId] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: poolData.map(p => p.name),
                datasets: [{
                    data: poolData.map(p => p.tvl),
                    backgroundColor: colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = parseFloat(context.parsed).toFixed(2);
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${label}: ${value} SPHE (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        return this.charts[canvasId];
    }

    /**
     * Initialize APY comparison bar chart
     */
    initAPYComparisonChart(canvasId, pools) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        this.charts[canvasId] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: pools.map(p => p.name),
                datasets: [{
                    label: 'APY (%)',
                    data: pools.map(p => p.apy),
                    backgroundColor: [
                        this.defaultColors.primary,
                        this.defaultColors.success,
                        this.defaultColors.info,
                        this.defaultColors.warning
                    ],
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return 'APY: ' + context.parsed.y + '%';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [5, 5]
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });

        return this.charts[canvasId];
    }

    /**
     * Initialize projection chart
     */
    initProjectionChart(canvasId, projectionData) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        this.charts[canvasId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: projectionData.labels,
                datasets: [
                    {
                        label: 'Staked Amount',
                        data: projectionData.stakedAmounts,
                        borderColor: this.defaultColors.info,
                        backgroundColor: this.defaultColors.infoTransparent,
                        borderWidth: 2,
                        fill: false
                    },
                    {
                        label: 'Total with Rewards',
                        data: projectionData.totals,
                        borderColor: this.defaultColors.success,
                        backgroundColor: this.defaultColors.successTransparent,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [5, 5]
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toFixed(2) + ' SPHE';
                            }
                        }
                    }
                }
            }
        });

        return this.charts[canvasId];
    }

    /**
     * Update chart data
     */
    updateChart(canvasId, newData) {
        const chart = this.charts[canvasId];
        if (!chart) return;

        chart.data.labels = newData.labels;
        chart.data.datasets.forEach((dataset, i) => {
            dataset.data = newData.datasets[i];
        });

        chart.update();
    }

    /**
     * Destroy specific chart
     */
    destroyChart(canvasId) {
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
            delete this.charts[canvasId];
        }
    }

    /**
     * Destroy all charts
     */
    destroyAll() {
        Object.keys(this.charts).forEach(canvasId => {
            this.destroyChart(canvasId);
        });
    }

    /**
     * Generate mock data for testing
     */
    generateMockRewardsData(days = 30) {
        const labels = [];
        const values = [];
        
        for (let i = 0; i <= days; i++) {
            labels.push(`Day ${i}`);
            // Simulate growing rewards
            values.push((i * 0.5) + (Math.random() * 0.2));
        }

        return { labels, values };
    }
}

// Initialize charts on page load
let stakingCharts;

function initializeCharts() {
    stakingCharts = new StakingCharts();

    // Initialize rewards chart with mock data
    const mockData = stakingCharts.generateMockRewardsData(30);
    stakingCharts.initRewardsChart('rewardsChart', mockData);
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = StakingCharts;
}
