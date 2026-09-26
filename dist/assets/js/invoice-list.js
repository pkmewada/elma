(function () {
    "use strict"

    // for invoice stats
    var options = {
        series: [{
            name: 'Total Invoices',
            data: [44, 42, 57, 86, 58, 55, 70, 43, 23, 54, 77, 34],
        }, {
            name: 'Paid Invoices',
            data: [74, 72, 87, 116, 88, 85, 100, 73, 53, 84, 107, 64],
        }, {
            name: 'Pending Invoices',
            data: [84, 82, 97, 126, 98, 95, 110, 83, 63, 94, 117, 74],
        }, {
            name: 'Overdue Invoices',
            data: [34, 22, 37, 56, 21, 35, 60, 34, 56, 78, 89, 53],
        }],
      chart: {
        type: "bar",
        height: 175,
        stacked: true,
        toolbar: {
            show: false,
        }
      },
      plotOptions: {
          bar: {
              columnWidth: '25%',
              borderRadius: 1,
          }
      },
      grid: {
          show: false,
          borderColor: '#f2f6f7',
      },
      dataLabels: {
        enabled: false,
      },
      colors: ["rgba(254, 124, 88,1)", "rgba(12, 215, 177,1)", "rgba(215, 124, 247,1)", "var(--primary-color)"],
      stroke: {
        width: 0,
      },
      legend: {
        show: true,
        position: 'top',
        horizontalAlign: 'center', 
      },
      xaxis: {
        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'sep', 'oct', 'nov', 'dec'],
        labels: {
          show: true,
          style: {
            colors: "#8c9097",
            fontSize: "11px",
            fontWeight: 500,
            cssClass: "apexcharts-xaxis-label",
          },
        },
      },
      yaxis: {
        title: {
          style: {
            color: "#8c9097",
          },
        },
        labels: {
          show: true,
          style: {
            colors: "#8c9097",
            fontSize: "11px",
            fontWeight: 500,
            cssClass: "apexcharts-xaxis-label",
          },
        },
      }, 
    };
    var chart = new ApexCharts(
      document.querySelector("#invoice-list-stats"),
      options
    );
    chart.render();

    //delete Btn
    let invoicebtn = document.querySelectorAll(".invoice-btn");
    invoicebtn.forEach((eleBtn) => {
        eleBtn.onclick = () => {
            let invoice = eleBtn.closest(".invoice-list")
            invoice.remove();
        }
    })
    
})();
