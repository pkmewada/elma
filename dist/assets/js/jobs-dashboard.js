(function () {
    "use strict";

    /* application statistics */
    var options = {
        series: [{
            name: 'Total Applications',
            data: [44, 55, 41, 67, 42, 22, 43, 21, 41, 56, 27, 43]
        }, {
            name: 'Rejected',
            data: [30, 25, 46, 28, 21, 45, 35, 64, 52, 59, 36, 39]
        }, {
            name: 'Shortlisted',
            data: [23, 11, 22, 35, 17, 28, 22, 37, 21, 44, 22, 30]
        },],
        chart: {
            height: 330,
            type: 'area',
            toolbar: {
                show: false,
            }
        },
        grid: {
            borderColor: '#f2f6f7',
            padding: {
                bottom: -10
            }
        },
        dataLabels: {
            enabled: false
        },
        legend: {
            position: 'top',
            fontFamily: "Montserrat",
        },
        colors: ["rgb(12, 215, 177)", "rgb(215, 124, 247)", "rgb(84, 109, 254)"],
        stroke: {
            width: [0, 0, 0],
            curve: 'smooth',
        }, 
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.1,
                stops: [0, 90, 100],
                colorStops: [

                    [
                        {
                            offset: 0,
                            color: 'rgba(12, 215, 177, 0.6)',
                            opacity: 1
                        },
                        {
                            offset: 85,
                            color: 'rgba(12, 215, 177, 0.6)',
                            opacity: 0.1
                        },
                        {
                            offset: 100,
                            color: 'rgba(255, 255, 255, 0.1)',
                            opacity: 1
                        }
                    ],
                    [
                        {
                            offset: 0,
                            color: 'rgba(215, 124, 247, 0.6)',
                            opacity: 1
                        },
                        {
                            offset: 85,
                            color: 'rgba(215, 124, 247, 0.6)',
                            opacity: 0.1
                        },
                        {
                            offset: 100,
                            color: 'rgba(255, 255, 255, 0.1)',
                            opacity: 1
                        }
                    ],
                    [
                        {
                            offset: 0,
                            color: "var(--primary06)",
                            opacity: 50
                        },
                        {
                            offset: 85,
                            color: "var(--primary06)",
                            opacity: 0.1
                        },
                        {
                            offset: 100,
                            color: 'rgba(255, 255, 255, 0.1)',
                            opacity: 0.1
                        }
                    ],
                ]
            }
        },
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        tooltip: {
            shared: true,
            theme: "dark",
        }
    };
    var chart = new ApexCharts(document.querySelector("#application-statistics"), options);
    chart.render();
    /* application statistics */

    /* job statistics */
    var options = {
        series: [{
            name: 'Male',
            data: [20, 30, 40, 80, 20, 80],
        }, {
            name: 'Female',
            data: [44, 76, 78, 13, 43, 10],
        }],
        chart: {
            height: 280,
            type: 'radar',
            toolbar: {
                show: false,
            }
        },
        title: {
            align: 'left',
            style: {
                fontSize: '13px',
                fontWeight: 'bold',
                color: '#8c9097'
            },
        },
        plotOptions: {
            radar: {
                size: 80,
                polygons: {
                    fill: {
                        colors: ['var(--primary005)', 'var(--primary01)']
                    },

                }
            }
        },
        colors: ["var(--primary08)", "rgba(215, 124, 247, 0.8)"],
        stroke: {
            width: 0
        },
        fill: {
            opacity: 0.1
        },
        legend: {
            show: true,
            fontSize: "12px",
            position: 'top',
            horizontalAlign: 'center',
            fontFamily: "Montserrat",
            fontWeight: 500,
            offsetX: 0,
            offsetY: -8,
            height: 50,
            labels: {
                colors: '#9ba5b7',
            },
            markers: {
                width: 7,
                height: 7,
                strokeWidth: 0,
                strokeColor: '#fff',
                fillColors: undefined,
                radius: 7,
                offsetX: 0,
                offsetY: 0
            },
        },
        markers: {
            size: 0
        },
        xaxis: {
            categories: ['2011', '2012', '2013', '2014', '2015', '2016']
        },
        yaxis: {
            tickAmount: 6,
            labels: {
                formatter: function (val, i) {
                    if (i % 5 === 0) {
                        return val
                    }
                }
            }
        }
    };
    var chart = new ApexCharts(document.querySelector("#job-applicants"), options);
    chart.render();
    /* job statistics */

    /* total revenue */
    var options = {
        series: [
            {
                name: "Income",
                data: [44, 42, 57, 86, 58, 55, 70],
            },
            {
                name: "Expenses",
                data: [-34, -22, -37, -56, -21, -35, -60],
            },
        ],
        chart: {
            stacked: true,
            type: "bar",
            height: 263,
            fontFamily: "Poppins, sans-serif",
            toolbar: {
                show: false,
            },
        },
        grid: {
            borderColor: "#f1f1f1",
            strokeDashArray: 2,
        },
        colors: ["var(--primary-color)", "rgba(215, 124, 247, 1)"],
        plotOptions: {
            bar: {
                columnWidth: "40%",
                borderRadius: [5],
                borderRadiusWhenStacked: "all",
            },
        },
        stroke: {
            width: [5, 5]
        },
        dataLabels: {
            enabled: false,
        },
        legend: {
            show: true,
            position: "top",
        },
        yaxis: {
            show: false,
            title: {
                text: undefined,
            },
            labels: {
                formatter: function (y) {
                    return y.toFixed(0) + "";
                },
            },
        },
        xaxis: {
            show: false,
            type: "week",
            categories: [
                "Mon",
                "Tue",
                "Wed",
                "Thu",
                "Fri",
                "Sat",
                "Sun",
            ],
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
            labels: {
                show: true,
                style: {
                    colors: "#d4d7d9",
                    fontSize: "10px",
                    fontWeight: 500,
                },
            },
        },
        tooltip: {
            enabled: true,
            shared: true,
            intersect: false,
            theme: "dark",
            x: {
                show: false,
            },
        },
    };
    var chart = new ApexCharts(document.querySelector("#total-revenue"), options);
    chart.render();
    /* total revenue */

})();