(function () {
    "use strict"

    // for tasks stats
    var options = {
        series: [{
            name: 'New Tasks',
            data: [44, 42, 57, 86, 58, 55, 70, 43, 23, 54, 77, 34],
        }, {
            name: 'Pending Tasks',
            data: [74, 72, 87, 116, 88, 85, 100, 73, 53, 84, 107, 64],
        }, {
            name: 'Completed Tasks',
            data: [84, 82, 97, 126, 98, 95, 110, 83, 63, 94, 117, 74],
        }, {
            name: 'In Progress Tasks',
            data: [34, 22, 37, 56, 21, 35, 60, 34, 56, 78, 89, 53],
        }],
      chart: {
        type: "bar",
        height: 193,
        stacked: true,
        toolbar: {
            show: false,
        }
      },
      plotOptions: {
          bar: {
              columnWidth: '25%',
              borderRadius: 2,
          }
      },
      grid: {
          show: false,
          borderColor: '#f2f6f7',
      },
      dataLabels: {
        enabled: false,
      },
      colors: ["rgba(254, 124, 88, 0.9)", "rgba(12, 215, 177, 0.9)", "rgba(215, 124, 247, 0.9)", "var(--primary09)"],
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
      document.querySelector("#task-list-stats"),
      options
    );
    chart.render();

    let checkAll = document.querySelector('.check-all');
    checkAll.addEventListener('click', checkAllFn)

    function checkAllFn() {
        if (checkAll.checked) {
            document.querySelectorAll('.task-checkbox input').forEach(function (e) {
                e.closest('.task-list').classList.add('selected');
                e.checked = true;
            });
        }
        else {
            document.querySelectorAll('.task-checkbox input').forEach(function (e) {
                e.closest('.task-list').classList.remove('selected');
                e.checked = false;
            });
        }
    }

    //delete Btn
    let taskbtn = document.querySelectorAll(".task-delete-btn");

    taskbtn.forEach((eleBtn) => {
        eleBtn.onclick = () => {
            let task = eleBtn.closest(".task-list")
            task.remove();
        }
    })

    /* multi select with remove button */
    const multipleCancelButton = new Choices(
        '#choices-multiple-remove-button1',
        {
            allowHTML: true,
            removeItemButton: true,
        }
    );

    /* AssignedDate Picker */
    flatpickr("#assignedDate", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
    });

    /* DueDate Picker */
    flatpickr("#dueDate", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
    });
    
})();
