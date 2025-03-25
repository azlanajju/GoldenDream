// Dashboard Charts and Functionality

// Show loading spinner
function showLoading() {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(overlay);
}

// Hide loading spinner
function hideLoading() {
    const overlay = document.querySelector('.loading-overlay');
    if (overlay) {
        overlay.remove();
    }
}

// Initialize Revenue Chart
function initRevenueChart(labels, values) {
    const options = {
        series: [{
            name: 'Revenue',
            data: values
        }],
        chart: {
            height: 300,
            type: 'area',
            toolbar: {
                show: false
            },
            fontFamily: 'Poppins, sans-serif',
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth',
            width: 3,
            colors: ['#3a7bd5']
        },
        xaxis: {
            categories: labels,
            labels: {
                style: {
                    colors: '#6c757d',
                    fontSize: '12px',
                    fontFamily: 'Poppins, sans-serif',
                }
            }
        },
        yaxis: {
            labels: {
                formatter: function(value) {
                    if (value >= 100000) {
                        return '₹' + (value / 100000).toFixed(1) + 'L';
                    } else if (value >= 1000) {
                        return '₹' + (value / 1000).toFixed(1) + 'K';
                    } else {
                        return '₹' + value.toFixed(0);
                    }
                },
                style: {
                    colors: '#6c757d',
                    fontSize: '12px',
                    fontFamily: 'Poppins, sans-serif',
                }
            }
        },
        tooltip: {
            y: {
                formatter: function(value) {
                    return '₹' + value.toLocaleString();
                }
            }
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.7,
                opacityTo: 0.2,
                stops: [0, 90, 100],
                colorStops: [
                    {
                        offset: 0,
                        color: '#3a7bd5',
                        opacity: 0.4
                    },
                    {
                        offset: 100,
                        color: '#3a7bd5',
                        opacity: 0.1
                    }
                ]
            }
        },
        grid: {
            borderColor: '#f1f1f1',
            row: {
                colors: ['transparent', 'transparent'],
                opacity: 0.5
            }
        },
        markers: {
            size: 4,
            colors: ['#3a7bd5'],
            strokeColors: '#fff',
            strokeWidth: 2,
            hover: {
                size: 7,
            }
        }
    };

    const chart = new ApexCharts(document.querySelector("#revenue-chart"), options);
    chart.render();
    
    // Save chart instance to window for updating later
    window.revenueChart = chart;
}

// Initialize Customers Chart
function initCustomersChart(labels, newCustomers, returningCustomers) {
    const options = {
        series: [{
            name: 'New Customers',
            data: newCustomers
        }, {
            name: 'Returning Customers',
            data: returningCustomers
        }],
        chart: {
            type: 'bar',
            height: 300,
            toolbar: {
                show: false
            },
            fontFamily: 'Poppins, sans-serif',
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                borderRadius: 5,
                endingShape: 'rounded'
            },
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: labels,
            labels: {
                style: {
                    colors: '#6c757d',
                    fontSize: '12px',
                    fontFamily: 'Poppins, sans-serif',
                }
            }
        },
        yaxis: {
            title: {
                text: 'Customers',
                style: {
                    color: '#6c757d',
                    fontSize: '14px',
                    fontFamily: 'Poppins, sans-serif',
                }
            },
            labels: {
                style: {
                    colors: '#6c757d',
                    fontSize: '12px',
                    fontFamily: 'Poppins, sans-serif',
                }
            }
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: "vertical",
                shadeIntensity: 0.25,
                gradientToColors: undefined,
                inverseColors: true,
                opacityFrom: 1,
                opacityTo: 0.85,
                stops: [50, 100]
            },
        },
        colors: ['#3a7bd5', '#00d2ff'],
        tooltip: {
            y: {
                formatter: function (val) {
                    return val + " customers"
                }
            }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            fontFamily: 'Poppins, sans-serif',
            fontSize: '13px',
            markers: {
                width: 12,
                height: 12,
                radius: 12
            }
        }
    };

    const chart = new ApexCharts(document.querySelector("#customers-chart"), options);
    chart.render();
    
    // Save chart instance to window for updating later
    window.customersChart = chart;
}

// Set up period controls for charts
function setupChartPeriodControls() {
    const chartActions = document.querySelectorAll('.chart-action');
    chartActions.forEach(button => {
        button.addEventListener('click', function() {
            // Get parent chart card
            const chartCard = this.closest('.chart-card');
            
            // If already active, do nothing
            if (this.classList.contains('active')) {
                return;
            }
            
            // Remove active class from all siblings
            chartCard.querySelectorAll('.chart-action').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Add active class to this button
            this.classList.add('active');
            
            // Update chart based on selected period
            const period = this.getAttribute('data-period');
            const chartId = chartCard.querySelector('.chart-container').id;
            
            // Show loading spinner
            showLoading();
            
            // Fetch new data from server based on period
            fetch(`../api/chart-data.php?chart=${chartId}&period=${period}`)
                .then(response => response.json())
                .then(data => {
                    if (chartId === 'revenue-chart') {
                        window.revenueChart.updateOptions({
                            series: [{
                                data: data.values
                            }],
                            xaxis: {
                                categories: data.labels
                            }
                        });
                    } else if (chartId === 'customers-chart') {
                        window.customersChart.updateOptions({
                            series: [{
                                name: 'New Customers',
                                data: data.new
                            }, {
                                name: 'Returning Customers',
                                data: data.returning
                            }],
                            xaxis: {
                                categories: data.labels
                            }
                        });
                    }
                    hideLoading();
                })
                .catch(error => {
                    console.error('Error fetching chart data:', error);
                    hideLoading();
                    
                    // Fallback to simulated data if API fails
                    simulatePeriodChange(chartId, period);
                });
        });
    });
}

// Simulate period change with fake data if API call fails
function simulatePeriodChange(chartId, period) {
    if (chartId === 'revenue-chart') {
        let newData = [];
        let newCategories = [];
        
        if (period === 'day') {
            newData = [12000, 18000, 15000, 22000, 19000, 25000, 20000, 17000, 21000, 24000, 22000, 18000];
            newCategories = ['8AM', '9AM', '10AM', '11AM', '12PM', '1PM', '2PM', '3PM', '4PM', '5PM', '6PM', '7PM'];
        } else if (period === 'week') {
            newData = [48000, 65000, 42000, 76000, 95000, 80000, 54000];
            newCategories = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        } else if (period === 'month') {
            newData = [150000, 220000, 180000, 250000, 210000, 290000, 240000, 260000, 230000, 270000, 300000, 280000];
            newCategories = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        }
        
        window.revenueChart.updateOptions({
            series: [{
                data: newData
            }],
            xaxis: {
                categories: newCategories
            }
        });
    } else if (chartId === 'customers-chart') {
        let newDataNew = [];
        let newDataReturning = [];
        let newCategories = [];
        
        if (period === 'day') {
            newDataNew = [15, 20, 25, 30, 35, 25, 30, 20, 25, 30, 35, 25];
            newDataReturning =