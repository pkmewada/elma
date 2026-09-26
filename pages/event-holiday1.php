<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$todayMonthDay = date('m-d');
$currentMonth = date('m');

$birthdayToday = 0;
$birthdayMonth = 0;
$anniversaryToday = 0;
$upcomingEvents = 0;

/*
|--------------------------------------------------------------------------
| Dashboard Counts
|--------------------------------------------------------------------------
*/
$countSql = mysqli_query($con, "
    SELECT 
        SUM(CASE WHEN DATE_FORMAT(dateOfBirth,'%m-%d') = '$todayMonthDay' THEN 1 ELSE 0 END) AS birthdayToday,
        SUM(CASE WHEN MONTH(dateOfBirth) = '$currentMonth' THEN 1 ELSE 0 END) AS birthdayMonth,
        SUM(CASE WHEN DATE_FORMAT(joiningDate,'%m-%d') = '$todayMonthDay' THEN 1 ELSE 0 END) AS anniversaryToday
    FROM employeeusers
");

if ($countSql && mysqli_num_rows($countSql) > 0) {
    $row = mysqli_fetch_assoc($countSql);
    $birthdayToday   = (int)$row['birthdayToday'];
    $birthdayMonth   = (int)$row['birthdayMonth'];
    $anniversaryToday = (int)$row['anniversaryToday'];
}

$eventCount = mysqli_query($con, "
    SELECT COUNT(*) AS total 
    FROM eventHolidayMaster
    WHERE eventDate >= CURDATE()
    AND status = 'active'
");

if ($eventCount && mysqli_num_rows($eventCount) > 0) {
    $row = mysqli_fetch_assoc($eventCount);
    $upcomingEvents = (int)$row['total'];
}

/*
|--------------------------------------------------------------------------
| Birthday List
|--------------------------------------------------------------------------
*/
$birthdayResult = mysqli_query($con, "
    SELECT id, fullName, dateOfBirth
    FROM employeeusers
    WHERE accountStatus = 'active' AND dateOfBirth IS NOT NULL
    ORDER BY MONTH(dateOfBirth), DAY(dateOfBirth)
");

/*
|--------------------------------------------------------------------------
| Anniversary List
|--------------------------------------------------------------------------
*/
$anniversaryResult = mysqli_query($con, "
    SELECT id, fullName, joiningDate
    FROM employeeusers
    WHERE accountStatus = 'active' AND joiningDate IS NOT NULL
    ORDER BY MONTH(joiningDate), DAY(joiningDate)
");
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<style>
.status-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid transparent;
}
.status-chip-success {
    color: rgb(var(--success-rgb));
    border-color: rgba(var(--success-rgb), 0.3);
}
.status-chip-info {
    color: rgb(var(--info-rgb));
    border-color: rgba(var(--info-rgb), 0.3);
}
</style>    

<div class="main-content app-content">
    <div class="container-fluid">

        <!-- PAGE HEADER -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Event / Holiday Management</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Event / Holiday Management</li>
                </ol>
            </div>

            <div>
                <!-- ADD EVENT BUTTON (replace existing button) -->
                <button class="btn btn-primary btn-wave waves-effect waves-light" data-bs-toggle="modal"
                    data-bs-target="#addEventHolidayModal">
                    <i class="ri-add-line me-1"></i> Add Event
                </button>
            </div>
        </div>

        <!-- SUMMARY CARDS -->
        <div class="row">

            <div class="col-xl-3 col-md-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <p class="mb-1 text-muted">Today's Birthdays</p>
                        <h3 class="fw-semibold mb-0"><?= $birthdayToday ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <p class="mb-1 text-muted">This Month Birthdays</p>
                        <h3 class="fw-semibold mb-0"><?= $birthdayMonth ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <p class="mb-1 text-muted">Today's Anniversaries</p>
                        <h3 class="fw-semibold mb-0"><?= $anniversaryToday ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <p class="mb-1 text-muted">Upcoming Events</p>
                        <h3 class="fw-semibold mb-0" id="upcomingEventsCount"><?= $upcomingEvents ?></h3>
                    </div>
                </div>
            </div>

        </div>

        <!-- TABS -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">

                    <div class="card-header">
                        <ul class="nav nav-tabs tab-style-1" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#all">
                                    All
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#birthdays">
                                    Birthdays
                                </button>
                            </li>

                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#anniversaries">
                                    Work Anniversaries
                                </button>
                            </li>

                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#events">
                                    Events
                                </button>
                            </li>

                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#holidays">
                                    Holidays
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body">

                        <div class="tab-content">
                            
                            
                            <!-- ALL TAB -->
                            <!-- ALL TAB -->
<div class="tab-pane fade show active" id="all">
    
    <?php
    // =====================================================
    // 1. GET EVENTS & HOLIDAYS (Current Year + Future)
    // =====================================================
    $currentYear = date('Y');
    $today = date('Y-m-d');
    
    $eventResult = mysqli_query($con, "
        SELECT 
            id,
            eventTitle AS title,
            eventDate AS date,
            eventTime AS time,
            eventCategory AS category,
            eventType AS type,
            location,
            description,
            reminderDays,
            mailEnabled,
            status,
            NULL AS employeeName,
            NULL AS employeeId
        FROM eventHolidayMaster
        WHERE eventType IN ('event', 'holiday')
        AND status = 'active'
        AND YEAR(eventDate) >= $currentYear
        AND eventDate >= '$today'
        ORDER BY eventDate ASC
    ");
    
    $allItems = [];
    
    if ($eventResult) {
        while ($row = mysqli_fetch_assoc($eventResult)) {
            $row['displayType'] = $row['type'] === 'event' ? 'Event' : 'Holiday';
            $row['badgeClass'] = $row['type'] === 'event' ? 'bg-info' : 'bg-warning text-dark';
            $row['icon'] = $row['type'] === 'event' ? 'ri-calendar-event-line' : 'ri-calendar-check-line';
            $row['isUpcoming'] = true;
            $row['extraInfo'] = null;
            $row['sortDate'] = strtotime($row['date']); // Add sortable timestamp
            $allItems[] = $row;
        }
    }
    
    // =====================================================
    // 2. GET BIRTHDAYS (Upcoming in Current Year)
    // =====================================================
    $birthdayResult = mysqli_query($con, "
        SELECT 
            id AS employeeId,
            fullName AS title,
            dateOfBirth AS dob,
            NULL AS time,
            'Birthday' AS category,
            'birthday' AS type,
            'Birthday' AS displayType,
            'bg-pink text-white' AS badgeClass,
            'ri-cake-3-line' AS icon,
            NULL AS location,
            NULL AS description,
            NULL AS reminderDays,
            NULL AS mailEnabled,
            'active' AS status,
            DATE_FORMAT(dateOfBirth, '%m-%d') AS monthDay
        FROM employeeusers
        WHERE accountStatus = 'active' 
        AND dateOfBirth IS NOT NULL
    ");
    
    if ($birthdayResult) {
        while ($row = mysqli_fetch_assoc($birthdayResult)) {
            $monthDay = $row['monthDay'] ?? '01-01';
            $dob = $row['dob'] ?? null;
            
            // Get next birthday date in current year
            $birthdayDate = date('Y') . '-' . $monthDay;
            
            // If birthday passed this year, use next year
            if (strtotime($birthdayDate) < strtotime($today)) {
                $birthdayDate = (date('Y') + 1) . '-' . $monthDay;
            }
            
            // Only include if upcoming (today or future)
            if (strtotime($birthdayDate) >= strtotime($today)) {
                $row['date'] = $birthdayDate;
                
                // Calculate age correctly
                if ($dob) {
                    $birthYear = date('Y', strtotime($dob));
                    $currentYearForAge = date('Y', strtotime($birthdayDate));
                    $row['age'] = $currentYearForAge - $birthYear;
                    $row['extraInfo'] = 'Age: ' . $row['age'];
                } else {
                    $row['extraInfo'] = null;
                }
                
                $row['isUpcoming'] = true;
                $row['sortDate'] = strtotime($birthdayDate); // Add sortable timestamp
                $allItems[] = $row;
            }
        }
    }
    
    // =====================================================
    // 3. GET ANNIVERSARIES (Upcoming in Current Year)
    // =====================================================
    $anniversaryResult = mysqli_query($con, "
        SELECT 
            id AS employeeId,
            fullName AS title,
            joiningDate AS joining,
            NULL AS time,
            'Work Anniversary' AS category,
            'anniversary' AS type,
            'Anniversary' AS displayType,
            'bg-purple text-white' AS badgeClass,
            'ri-medal-2-line' AS icon,
            NULL AS location,
            NULL AS description,
            NULL AS reminderDays,
            NULL AS mailEnabled,
            'active' AS status,
            DATE_FORMAT(joiningDate, '%m-%d') AS monthDay
        FROM employeeusers
        WHERE accountStatus = 'active' 
        AND joiningDate IS NOT NULL
    ");
    
    if ($anniversaryResult) {
        while ($row = mysqli_fetch_assoc($anniversaryResult)) {
            $monthDay = $row['monthDay'] ?? '01-01';
            $joining = $row['joining'] ?? null;
            
            // Get next anniversary date in current year
            $anniversaryDate = date('Y') . '-' . $monthDay;
            
            // If anniversary passed this year, use next year
            if (strtotime($anniversaryDate) < strtotime($today)) {
                $anniversaryDate = (date('Y') + 1) . '-' . $monthDay;
            }
            
            // Only include if upcoming (today or future)
            if (strtotime($anniversaryDate) >= strtotime($today)) {
                $row['date'] = $anniversaryDate;
                
                // Calculate years correctly
                if ($joining) {
                    $joinYear = date('Y', strtotime($joining));
                    $currentYearForAnniversary = date('Y', strtotime($anniversaryDate));
                    $row['years'] = $currentYearForAnniversary - $joinYear;
                    $row['extraInfo'] = $row['years'] . ' Years';
                } else {
                    $row['extraInfo'] = null;
                }
                
                $row['isUpcoming'] = true;
                $row['sortDate'] = strtotime($anniversaryDate); // Add sortable timestamp
                $allItems[] = $row;
            }
        }
    }
    
    // =====================================================
    // 4. SORT ALL ITEMS BY DATE (using the sortDate timestamp)
    // =====================================================
    usort($allItems, function($a, $b) {
        // Use sortDate if available, otherwise fallback to strtotime of date
        $dateA = isset($a['sortDate']) ? $a['sortDate'] : strtotime($a['date'] ?? '1970-01-01');
        $dateB = isset($b['sortDate']) ? $b['sortDate'] : strtotime($b['date'] ?? '1970-01-01');
        
        if ($dateA == $dateB) {
            return 0;
        }
        return ($dateA < $dateB) ? -1 : 1;
    });
    
    $allCount = count($allItems);
    ?>

    <!-- Toolbar -->
    <div class="card custom-card mb-3 border">
        <div class="card-body p-3">
            <div class="row align-items-center g-2">
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!-- Export -->
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a href="#" class="dropdown-item all-export-btn" data-type="csv">CSV</a></li>
                                <li><a href="#" class="dropdown-item all-export-btn" data-type="excel">Excel</a></li>
                                <li><a href="#" class="dropdown-item all-export-btn" data-type="pdf">PDF</a></li>
                                <li><a href="#" class="dropdown-item all-export-btn" data-type="print">Print</a></li>
                            </ul>
                        </div>

                        <!-- Filters -->
                        <select id="allTypeFilter" class="form-select w-auto">
                            <option value="">All Types</option>
                            <option value="event">Events</option>
                            <option value="holiday">Holidays</option>
                            <option value="birthday">Birthdays</option>
                            <option value="anniversary">Anniversaries</option>
                        </select>

                        <select id="allMonthFilter" class="form-select w-auto">
                            <option value="">Month</option>
                            <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?= date('m', mktime(0,0,0,$m,1)) ?>">
                                <?= date('M', mktime(0,0,0,$m,1)) ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-md-end">
                        <input type="text" id="allSearch" class="form-control" placeholder="Search..." style="max-width:250px;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table id="allTable" class="table table-hover text-nowrap w-100">
            <thead>
                <tr>
                    <th>SNo</th>
                    <th>Title / Employee</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Details</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($allCount > 0): ?>
                    <?php $i=1; foreach($allItems as $row): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge <?= $row['badgeClass'] ?? 'bg-secondary' ?>">
                                    <i class="<?= $row['icon'] ?? 'ri-information-line' ?>"></i>
                                </span>
                                <span><?= htmlspecialchars($row['title'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $date = $row['date'] ?? null;
                            echo $date ? date('d M Y', strtotime($date)) : '-'; 
                            ?>
                            <?php if (!empty($row['extraInfo'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($row['extraInfo'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                            <?php if (!empty($row['isUpcoming']) && $date && strtotime($date) <= strtotime('+7 days') && strtotime($date) >= strtotime($today)): ?>
                                <span class="badge bg-danger ms-1">Soon</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $time = $row['time'] ?? null;
                            // Check if time is valid (not midnight placeholder)
                            if ($time && $time !== '00:00:00' && $time !== '00:00') {
                                echo date('h:i A', strtotime($time));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($row['category'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge <?= $row['badgeClass'] ?? 'bg-secondary' ?>">
                                <?= htmlspecialchars($row['displayType'] ?? $row['type'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['type'] === 'event' || $row['type'] === 'holiday'): ?>
                                <?php if (!empty($row['location'])): ?>
                                    <small class="d-block text-muted">
                                        <i class="ri-map-pin-line"></i> <?= htmlspecialchars($row['location'], ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                <?php endif; ?>
                                <?php if (!empty($row['description'])): ?>
                                    <small class="d-block text-muted text-truncate" style="max-width:150px;">
                                        <?= htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['type'] === 'event' || $row['type'] === 'holiday'): ?>
                                <button class="btn btn-sm btn-warning edit-record-btn me-1"
                                    data-id="<?= (int)($row['id'] ?? 0) ?>"
                                    data-title="<?= htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-type="<?= htmlspecialchars($row['type'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-category="<?= htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-date="<?= htmlspecialchars($row['date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-time="<?= htmlspecialchars($row['time'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-location="<?= htmlspecialchars($row['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-description="<?= htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-reminder="<?= (int)($row['reminderDays'] ?? 0) ?>"
                                    data-mail="<?= (int)($row['mailEnabled'] ?? 0) ?>"
                                    data-status="<?= htmlspecialchars($row['status'] ?? 'active', ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="ri-edit-line"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-record-btn" data-id="<?= (int)($row['id'] ?? 0) ?>">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            <?php else: ?>
                                <span class="text-muted">--</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="ri-inbox-line fs-4 d-block mb-2"></i>
                            No upcoming records found.
                            <br><small>All events, holidays, birthdays, and anniversaries are completed for this year.</small>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

                            <!-- BIRTHDAY TAB -->
                            <div class="tab-pane fade show" id="birthdays">

                                <!-- Toolbar -->
                                <div class="card custom-card mb-3 border">
                                    <div class="card-body p-3">

                                        <div class="row align-items-center g-2">

                                            <!-- LEFT : Export + Filter -->
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center gap-2 flex-wrap">

                                                    <!-- Export -->
                                                    <div class="btn-group">
                                                        <button type="button"
                                                            class="btn btn-outline-primary dropdown-toggle"
                                                            data-bs-toggle="dropdown">
                                                            Export
                                                        </button>

                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a href="#" class="dropdown-item birthday-export-btn"
                                                                    data-type="csv">
                                                                    CSV
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item birthday-export-btn"
                                                                    data-type="excel">
                                                                    Excel
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item birthday-export-btn"
                                                                    data-type="pdf">
                                                                    PDF
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item birthday-export-btn"
                                                                    data-type="print">
                                                                    Print
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>

                                                    <!-- Filter -->
                                                    <select id="birthdayMonthFilter" class="form-select w-auto">
                                                        <option value="">Month</option>
                                                        <option value="Jan">Jan</option>
                                                        <option value="Feb">Feb</option>
                                                        <option value="Mar">Mar</option>
                                                        <option value="Apr">Apr</option>
                                                        <option value="May">May</option>
                                                        <option value="Jun">Jun</option>
                                                        <option value="Jul">Jul</option>
                                                        <option value="Aug">Aug</option>
                                                        <option value="Sep">Sep</option>
                                                        <option value="Oct">Oct</option>
                                                        <option value="Nov">Nov</option>
                                                        <option value="Dec">Dec</option>
                                                    </select>

                                                </div>
                                            </div>

                                            <!-- RIGHT : Search -->
                                            <div class="col-md-6">
                                                <div class="d-flex justify-content-md-end">
                                                    <input type="text" id="birthdaySearch" class="form-control"
                                                        placeholder="Search birthdays..." style="max-width:250px;">
                                                </div>
                                            </div>

                                        </div>

                                    </div>
                                </div>

                                <!-- Table -->
                                <div class="table-responsive">
                                    <table id="birthdaysTable"  data-ui-table="mamix"
                                        class="table table-hover text-nowrap">

                                        <thead>
                                            <tr>
                                                <th>SNo</th>
                                                <th>Employee Name</th>
                                                <th>Date of Birth</th>
                                                <th>Age</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>

                                        <tbody>

                                            <?php $i = 1; ?>
                                            <?php while($row = mysqli_fetch_assoc($birthdayResult)): ?>

                                            <?php
                                    $age = date('Y') - date('Y', strtotime($row['dateOfBirth']));
                                    $isToday = date('m-d', strtotime($row['dateOfBirth'])) == $todayMonthDay;
                                    ?>

                                            <tr>

                                                <td><?= $i++ ?></td>

                                                <td><?= htmlspecialchars($row['fullName']) ?></td>

                                                <td><?= date('d M', strtotime($row['dateOfBirth'])) ?></td>

                                                <td><?= $age ?></td>

                                                <td>
                                                    <?php if($isToday): ?>
                                                    <span class="status-chip status-chip-success">Today</span>
                                                    <?php else: ?>
                                                    <span class="status-chip status-chip-info">Upcoming</span>
                                                    <?php endif; ?>
                                                </td>

                                            </tr>

                                            <?php endwhile; ?>

                                        </tbody>

                                    </table>
                                </div>

                            </div>

                            <!-- ANNIVERSARY TAB -->
                            <div class="tab-pane fade" id="anniversaries">

                                <!-- Toolbar -->
                                <div class="card custom-card mb-3 border">
                                    <div class="card-body p-3">

                                        <div class="row align-items-center g-2">

                                            <!-- LEFT : Export + Filter -->
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center gap-2 flex-wrap">

                                                    <!-- Export -->
                                                    <div class="btn-group">
                                                        <button type="button"
                                                            class="btn btn-outline-primary dropdown-toggle"
                                                            data-bs-toggle="dropdown">
                                                            Export
                                                        </button>

                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a href="#" class="dropdown-item anniversary-export-btn"
                                                                    data-type="csv">
                                                                    CSV
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item anniversary-export-btn"
                                                                    data-type="excel">
                                                                    Excel
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item anniversary-export-btn"
                                                                    data-type="pdf">
                                                                    PDF
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item anniversary-export-btn"
                                                                    data-type="print">
                                                                    Print
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>

                                                    <!-- Filter -->
                                                    <select id="anniversaryStatusFilter" class="form-select w-auto">
                                                        <option value="">Status</option>
                                                        <option value="Today">Today</option>
                                                        <option value="Upcoming">Upcoming</option>
                                                    </select>

                                                </div>
                                            </div>

                                            <!-- RIGHT : Search -->
                                            <div class="col-md-6">
                                                <div class="d-flex justify-content-md-end">
                                                    <input type="text" id="anniversarySearch" class="form-control"
                                                        placeholder="Search anniversaries..." style="max-width:250px;">
                                                </div>
                                            </div>

                                        </div>

                                    </div>
                                </div>

                                <!-- Table -->
                                <div class="table-responsive">
                                    <table id="anniversariesTable" class="table table-hover text-nowrap w-100">

                                        <thead>
                                            <tr>
                                                <th>SNo</th>
                                                <th>Employee Name</th>
                                                <th>Joining Date</th>
                                                <th>Years</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>

                                        <tbody>

                                            <?php $j = 1; ?>
                                            <?php while($row = mysqli_fetch_assoc($anniversaryResult)): ?>

                                            <?php
                                                $years = date('Y') - date('Y', strtotime($row['joiningDate']));
                                                $isToday = date('m-d', strtotime($row['joiningDate'])) == $todayMonthDay;
                                            ?>

                                            <tr>

                                                <td><?= $j++ ?></td>

                                                <td><?= htmlspecialchars($row['fullName']) ?></td>

                                                <td><?= date('d M Y', strtotime($row['joiningDate'])) ?></td>

                                                <td><?= $years ?> Years</td>

                                                <td>
                                                    <?php if($isToday): ?>
                                                    <span class="badge bg-primary">Today</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-light text-dark">Upcoming</span>
                                                    <?php endif; ?>
                                                </td>

                                            </tr>

                                            <?php endwhile; ?>

                                        </tbody>

                                    </table>
                                </div>

                            </div>

                            <?php
                                $eventResult = mysqli_query($con,"
                                    SELECT *
                                    FROM eventHolidayMaster
                                    WHERE eventType='event'
                                    ORDER BY eventDate ASC
                                ");

                                $holidayResult = mysqli_query($con,"
                                    SELECT *
                                    FROM eventHolidayMaster
                                    WHERE eventType='holiday'
                                    ORDER BY eventDate ASC
                                ");
                            ?>

                            <!-- EVENTS TAB -->
                            <div class="tab-pane fade" id="events">

                                <!-- Toolbar -->
                                <div class="card custom-card mb-3 border">
                                    <div class="card-body p-3">

                                        <div class="row align-items-center g-2">

                                            <!-- LEFT : Export + Filter -->
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center gap-2 flex-wrap">

                                                    <!-- Export -->
                                                    <div class="btn-group">
                                                        <button type="button"
                                                            class="btn btn-outline-primary dropdown-toggle"
                                                            data-bs-toggle="dropdown">
                                                            Export
                                                        </button>

                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a href="#" class="dropdown-item event-export-btn"
                                                                    data-type="csv">
                                                                    CSV
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item event-export-btn"
                                                                    data-type="excel">
                                                                    Excel
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item event-export-btn"
                                                                    data-type="pdf">
                                                                    PDF
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item event-export-btn"
                                                                    data-type="print">
                                                                    Print
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>

                                                    <!-- Filter -->
                                                    <select id="eventStatusFilter" class="form-select w-auto">
                                                        <option value="">Status</option>
                                                        <option value="Active">Active</option>
                                                        <option value="Inactive">Inactive</option>
                                                    </select>

                                                </div>
                                            </div>

                                            <!-- RIGHT : Search -->
                                            <div class="col-md-6">
                                                <div class="d-flex justify-content-md-end">
                                                    <input type="text" id="eventSearch" class="form-control"
                                                        placeholder="Search events..." style="max-width:250px;">
                                                </div>
                                            </div>

                                        </div>

                                    </div>
                                </div>

                                <!-- Table -->
                                <div class="table-responsive">
                                    <table id="eventsTable" class="table table-hover text-nowrap w-100">

                                        <thead>
                                            <tr>
                                                <th>SNo</th>
                                                <th>Title</th>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Category</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>

                                        <tbody>

                                            <?php $i=1; while($row=mysqli_fetch_assoc($eventResult)): ?>

                                            <tr>

                                                <td><?= $i++ ?></td>

                                                <td><?= htmlspecialchars($row['eventTitle']) ?></td>

                                                <td><?= date('d M Y', strtotime($row['eventDate'])) ?></td>

                                                <td>
                                                    <?= $row['eventTime'] ? date('h:i A', strtotime($row['eventTime'])) : '-' ?>
                                                </td>

                                                <td><?= htmlspecialchars($row['eventCategory']) ?></td>

                                                <td>
                                                    <span class="badge bg-success">
                                                        <?= ucfirst($row['status']) ?>
                                                    </span>
                                                </td>

                                                <td>

                                                    <button class="btn btn-sm btn-warning edit-record-btn me-1"
                                                        data-id="<?= $row['id'] ?>"
                                                        data-title="<?= htmlspecialchars($row['eventTitle']) ?>"
                                                        data-type="<?= $row['eventType'] ?>"
                                                        data-category="<?= htmlspecialchars($row['eventCategory']) ?>"
                                                        data-date="<?= $row['eventDate'] ?>"
                                                        data-time="<?= $row['eventTime'] ?>"
                                                        data-location="<?= htmlspecialchars($row['location']) ?>"
                                                        data-description="<?= htmlspecialchars($row['description']) ?>"
                                                        data-reminder="<?= $row['reminderDays'] ?>"
                                                        data-mail="<?= $row['mailEnabled'] ?>"
                                                        data-status="<?= $row['status'] ?>">
                                                        <i class="ri-edit-line"></i>
                                                    </button>

                                                    <button class="btn btn-sm btn-danger delete-record-btn"
                                                        data-id="<?= $row['id'] ?>">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>

                                                </td>

                                            </tr>

                                            <?php endwhile; ?>

                                        </tbody>

                                    </table>
                                </div>

                            </div>


                            <!-- HOLIDAYS TAB -->
                            <div class="tab-pane fade" id="holidays">

                                <!-- Toolbar -->
                                <div class="card custom-card mb-3 border">
                                    <div class="card-body p-3">

                                        <div class="row align-items-center g-2">

                                            <!-- LEFT : Export + Filter -->
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center gap-2 flex-wrap">

                                                    <!-- Export -->
                                                    <div class="btn-group">
                                                        <button type="button"
                                                            class="btn btn-outline-primary dropdown-toggle"
                                                            data-bs-toggle="dropdown">
                                                            Export
                                                        </button>

                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a href="#" class="dropdown-item holiday-export-btn"
                                                                    data-type="csv">
                                                                    CSV
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item holiday-export-btn"
                                                                    data-type="excel">
                                                                    Excel
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item holiday-export-btn"
                                                                    data-type="pdf">
                                                                    PDF
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item holiday-export-btn"
                                                                    data-type="print">
                                                                    Print
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>

                                                    <!-- Filter -->
                                                    <select id="holidayStatusFilter" class="form-select w-auto">
                                                        <option value="">Status</option>
                                                        <option value="Active">Active</option>
                                                        <option value="Inactive">Inactive</option>
                                                    </select>

                                                </div>
                                            </div>

                                            <!-- RIGHT : Search -->
                                            <div class="col-md-6">
                                                <div class="d-flex justify-content-md-end">
                                                    <input type="text" id="holidaySearch" class="form-control"
                                                        placeholder="Search holidays..." style="max-width:250px;">
                                                </div>
                                            </div>

                                        </div>

                                    </div>
                                </div>

                                <!-- Table -->
                                <div class="table-responsive">
                                    <table id="holidaysTable" class="table table-hover text-nowrap w-100">

                                        <thead>
                                            <tr>
                                                <th>SNo</th>
                                                <th>Title</th>
                                                <th>Date</th>
                                                <th>Category</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>

                                        <tbody>

                                            <?php $j=1; while($row=mysqli_fetch_assoc($holidayResult)): ?>

                                            <tr>

                                                <td><?= $j++ ?></td>

                                                <td><?= htmlspecialchars($row['eventTitle']) ?></td>

                                                <td><?= date('d M Y', strtotime($row['eventDate'])) ?></td>

                                                <td><?= htmlspecialchars($row['eventCategory']) ?></td>

                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?= ucfirst($row['status']) ?>
                                                    </span>
                                                </td>

                                                <td>

                                                    <button class="btn btn-sm btn-warning edit-record-btn me-1"
                                                        data-id="<?= $row['id'] ?>"
                                                        data-title="<?= htmlspecialchars($row['eventTitle']) ?>"
                                                        data-type="<?= $row['eventType'] ?>"
                                                        data-category="<?= htmlspecialchars($row['eventCategory']) ?>"
                                                        data-date="<?= $row['eventDate'] ?>"
                                                        data-time="<?= $row['eventTime'] ?>"
                                                        data-location="<?= htmlspecialchars($row['location']) ?>"
                                                        data-description="<?= htmlspecialchars($row['description']) ?>"
                                                        data-reminder="<?= $row['reminderDays'] ?>"
                                                        data-mail="<?= $row['mailEnabled'] ?>"
                                                        data-status="<?= $row['status'] ?>">
                                                        <i class="ri-edit-line"></i>
                                                    </button>

                                                    <button class="btn btn-sm btn-danger delete-record-btn"
                                                        data-id="<?= $row['id'] ?>">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>

                                                </td>

                                            </tr>

                                            <?php endwhile; ?>

                                        </tbody>

                                    </table>
                                </div>

                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<!-- ADD EVENT / HOLIDAY MODAL -->
<div class="modal fade" id="addEventHolidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Add Event / Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="addEventHolidayForm">

                <div class="modal-body">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Title</label>
                            <input type="text" name="eventTitle" class="form-control" placeholder="Enter title"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Type</label>
                            <select name="eventType" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="event">Event</option>
                                <option value="holiday">Holiday</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <input type="text" name="eventCategory" class="form-control"
                                placeholder="Meeting / Festival / Public Holiday">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="eventDate" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Time</label>
                            <input type="time" name="eventTime" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="Enter location">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Reminder Days Before</label>
                            <input type="number" name="reminderDays" class="form-control" min="0" value="0">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Mail Notification</label>
                            <select name="mailEnabled" class="form-select">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"
                                placeholder="Enter description"></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" class="btn btn-primary" id="saveEventBtn">

                        <span class="spinner-border spinner-border-sm me-2 d-none" id="saveEventSpinner"></span>

                        <span id="saveEventText">Save Event</span>
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- EDIT EVENT / HOLIDAY MODAL -->
<div class="modal fade" id="editEventHolidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Edit Event / Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="editEventHolidayForm">

                <input type="hidden" name="id" id="editId">

                <div class="modal-body">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Title</label>
                            <input type="text" name="eventTitle" id="editTitle" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Type</label>
                            <select name="eventType" id="editType" class="form-select" required>
                                <option value="event">Event</option>
                                <option value="holiday">Holiday</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <input type="text" name="eventCategory" id="editCategory" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="eventDate" id="editDate" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Time</label>
                            <input type="time" name="eventTime" id="editTime" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="editLocation" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Reminder Days</label>
                            <input type="number" name="reminderDays" id="editReminder" class="form-control" min="0">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Mail Notification</label>
                            <select name="mailEnabled" id="editMail" class="form-select">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Status</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" class="btn btn-primary" id="updateEventBtn">

                        <span class="spinner-border spinner-border-sm me-2 d-none" id="updateEventSpinner"></span>

                        <span id="updateEventText">
                            Update Record
                        </span>

                    </button>

                </div>

            </form>

        </div>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
/* ==========================================================
   EVENT / HOLIDAY MANAGEMENT - FINAL V2 (Production Safe)
   Full JS Rewrite
   Covers:
   - 4 DataTables
   - Search / Filters / Export
   - Add / Edit / Delete
   - Modal Prefill API
   - Safe Rendering
   - Tab Resize Fix
   - Loading States
========================================================== */

/* ==========================================================
| GLOBAL TABLE INSTANCES
========================================================== */
var birthdayTable = null;
var anniversaryTable = null;
var eventsTable = null;
var holidaysTable = null;

/* ==========================================================
| HELPERS
========================================================== */
function escapeHtml(value) {
    return $('<div>').text(value || '').html();
}

function toast(type, msg) {
    if (window.showToast) window.showToast(type, msg);
}

/* ==========================================================
| REARRANGE SERIAL NUMBERS
========================================================== */
function reArrangeSno() {
    if (birthdayTable) birthdayTable.draw(false);
    if (anniversaryTable) anniversaryTable.draw(false);
    if (eventsTable) eventsTable.draw(false);
    if (holidaysTable) holidaysTable.draw(false);
}

/* ==========================================================
| REFRESH UPCOMING COUNT
========================================================== */
function refreshUpcomingCount() {
    $.ajax({
        url: API_BASE + '/holidays/getUpcomingEventCount.php',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                $('#upcomingEventsCount').text(res.total);
            }
        }
    });
}

$(function() {

    /* ======================================================
    | DATATABLE INIT
    ====================================================== */
    function initSimpleTable(selector, prefix, exportCols) {

        if (!$(selector).length) return null;

        return $(selector).DataTable({
            pageLength: 10,
            ordering: false,
            responsive: true,
            autoWidth: false,
            dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",

            buttons: [{
                    extend: 'csvHtml5',
                    className: 'd-none buttons-' + prefix + '-csv',
                    exportOptions: {
                        columns: exportCols
                    }
                },
                {
                    extend: 'excelHtml5',
                    className: 'd-none buttons-' + prefix + '-excel',
                    exportOptions: {
                        columns: exportCols
                    }
                },
                {
                    extend: 'pdfHtml5',
                    className: 'd-none buttons-' + prefix + '-pdf',
                    exportOptions: {
                        columns: exportCols
                    }
                },
                {
                    extend: 'print',
                    className: 'd-none buttons-' + prefix + '-print',
                    exportOptions: {
                        columns: exportCols
                    }
                }
            ],

            drawCallback: function() {
                var api = this.api();

                api.column(0, {
                    search: 'applied',
                    order: 'applied'
                }).nodes().each(function(cell, i) {
                    cell.innerHTML = i + 1;
                });
            }
        });
    }

    /* ======================================================
    | INIT TABLES
    ====================================================== */
    birthdayTable = initSimpleTable('#birthdaysTable', 'birthday', [0, 1, 2, 3, 4]);
    anniversaryTable = initSimpleTable('#anniversariesTable', 'anniversary', [0, 1, 2, 3, 4]);
    eventsTable = initSimpleTable('#eventsTable', 'event', [0, 1, 2, 3, 4, 5]);
    holidaysTable = initSimpleTable('#holidaysTable', 'holiday', [0, 1, 2, 3, 4]);
    
    // Initialize All Table
    var allTable = null;
    allTable = $('#allTable').DataTable({
        pageLength: 10,
        ordering: true,
        order: [[2, 'asc']], // Sort by date ascending
        responsive: true,
        autoWidth: false,
        dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
        buttons: [
            { extend: 'csvHtml5', className: 'd-none buttons-all-csv', exportOptions: { columns: [0,1,2,3,4,5] } },
            { extend: 'excelHtml5', className: 'd-none buttons-all-excel', exportOptions: { columns: [0,1,2,3,4,5] } },
            { extend: 'pdfHtml5', className: 'd-none buttons-all-pdf', exportOptions: { columns: [0,1,2,3,4,5] } },
            { extend: 'print', className: 'd-none buttons-all-print', exportOptions: { columns: [0,1,2,3,4,5] } }
        ],
        drawCallback: function() {
            var api = this.api();
            api.column(0, { search: 'applied', order: 'applied' }).nodes().each(function(cell, i) {
                cell.innerHTML = i + 1;
            });
        }
    });

    /* ======================================================
    | TAB FIX
    ====================================================== */
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
        $.fn.dataTable.tables({
            visible: true,
            api: true
        }).columns.adjust();
    });

    /* ======================================================
    | SEARCH
    ====================================================== */
    $('#birthdaySearch').on('keyup', function() {
        if (birthdayTable) birthdayTable.search(this.value).draw();
    });

    $('#anniversarySearch').on('keyup', function() {
        if (anniversaryTable) anniversaryTable.search(this.value).draw();
    });

    $('#eventSearch').on('keyup', function() {
        if (eventsTable) eventsTable.search(this.value).draw();
    });

    $('#holidaySearch').on('keyup', function() {
        if (holidaysTable) holidaysTable.search(this.value).draw();
    });

    /* ======================================================
    | EXPORT
    ====================================================== */
    $(document).on('click', '.birthday-export-btn', function(e) {
        e.preventDefault();
        birthdayTable.buttons('.buttons-birthday-' + $(this).data('type')).trigger();
    });

    $(document).on('click', '.anniversary-export-btn', function(e) {
        e.preventDefault();
        anniversaryTable.buttons('.buttons-anniversary-' + $(this).data('type')).trigger();
    });

    $(document).on('click', '.event-export-btn', function(e) {
        e.preventDefault();
        eventsTable.buttons('.buttons-event-' + $(this).data('type')).trigger();
    });

    $(document).on('click', '.holiday-export-btn', function(e) {
        e.preventDefault();
        holidaysTable.buttons('.buttons-holiday-' + $(this).data('type')).trigger();
    });

    /* ======================================================
    | FILTERS
    ====================================================== */
    $.fn.dataTable.ext.search.push(function(settings, data) {

        var tableId = settings.nTable.id;

        if (tableId === 'birthdaysTable') {
            let month = $('#birthdayMonthFilter').val();
            if (month && data[2].indexOf(month) === -1) return false;
        }

        if (tableId === 'anniversariesTable') {
            let val = $('#anniversaryStatusFilter').val();
            if (val && data[4].toLowerCase().indexOf(val.toLowerCase()) === -1) return false;
        }

        if (tableId === 'eventsTable') {
            let val = $('#eventStatusFilter').val();
            if (val && data[5].toLowerCase().indexOf(val.toLowerCase()) === -1) return false;
        }

        if (tableId === 'holidaysTable') {
            let val = $('#holidayStatusFilter').val();
            if (val && data[4].toLowerCase().indexOf(val.toLowerCase()) === -1) return false;
        }

        return true;
    });

    $('#birthdayMonthFilter').on('change', function() {
        birthdayTable.draw();
    });
    $('#anniversaryStatusFilter').on('change', function() {
        anniversaryTable.draw();
    });
    $('#eventStatusFilter').on('change', function() {
        eventsTable.draw();
    });
    $('#holidayStatusFilter').on('change', function() {
        holidaysTable.draw();
    });

    /* ======================================================
    | BUTTON HTML
    ====================================================== */
    function buildActionButtons(data) {

        return '' +
            '<button class="btn btn-sm btn-warning edit-record-btn me-1" ' +
            'data-id="' + data.id + '">' +
            '<i class="ri-edit-line"></i>' +
            '</button>' +

            '<button class="btn btn-sm btn-danger delete-record-btn" ' +
            'data-id="' + data.id + '">' +
            '<i class="ri-delete-bin-line"></i>' +
            '</button>';
    }

    /* ======================================================
    | ADD RECORD
    ====================================================== */
    $('#addEventHolidayForm').on('submit', function(e) {

        e.preventDefault();

        let form = this;
        let formData = new FormData(form);

        let btn = $('#saveEventBtn');
        let spinner = $('#saveEventSpinner');
        let text = $('#saveEventText');

        btn.prop('disabled', true);
        spinner.removeClass('d-none');
        text.text('Saving...');

        $.ajax({
            url: API_BASE + '/holidays/addEventHoliday.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',

            success: function(response) {

                if (!response.success) {
                    toast('danger', response.message || 'Unable to save.');
                    return;
                }

                let data = response.data;
                let actions = buildActionButtons(data);

                if (data.eventType === 'event') {

                    eventsTable.row.add([
                        '',
                        escapeHtml(data.eventTitle),
                        data.formattedDate,
                        data.formattedTime || '-',
                        escapeHtml(data.eventCategory || '-'),
                        '<span class="badge bg-success">' + escapeHtml(data
                            .status) + '</span>',
                        actions
                    ]).draw(false);

                } else {

                    holidaysTable.row.add([
                        '',
                        escapeHtml(data.eventTitle),
                        data.formattedDate,
                        escapeHtml(data.eventCategory || '-'),
                        '<span class="badge bg-primary">' + escapeHtml(data
                            .status) + '</span>',
                        actions
                    ]).draw(false);
                }

                $('#addEventHolidayModal').modal('hide');
                form.reset();

                refreshUpcomingCount();
                toast('success', response.message);

            },

            error: function() {
                toast('danger', 'Server error.');
            },

            complete: function() {
                btn.prop('disabled', false);
                spinner.addClass('d-none');
                text.text('Save Event');
            }
        });

    });

   /* ======================================================
    | DELETE RECORD
    ====================================================== */
    $(document).on('click', '.delete-record-btn', function() {
    
        let button = $(this);
        let id = button.data('id');
    
        Swal.fire({
            title: 'Delete Record?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes Delete',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
    
            if (!result.isConfirmed) return;
    
            button.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm"></span>'
            );
    
            $.ajax({
                url: API_BASE + '/holidays/deleteEventHoliday.php',
                type: 'POST',
                data: {
                    id: id
                },
                dataType: 'json',
    
                success: function(response) {
    
                    if (!response.success) {
                        button.prop('disabled', false).html(
                            '<i class="ri-delete-bin-line"></i>');
                        toast('danger', response.message);
                        return;
                    }
    
                    let row = button.closest('tr');
    
                    // Remove from events table
                    if (row.closest('#eventsTable').length) {
                        eventsTable.row(row).remove().draw(false);
                    }
                    
                    // Remove from holidays table
                    if (row.closest('#holidaysTable').length) {
                        holidaysTable.row(row).remove().draw(false);
                    }
                    
                    // Remove from all table
                    if (row.closest('#allTable').length) {
                        allTable.row(row).remove().draw(false);
                    }
    
                    refreshUpcomingCount();
                    toast('success', response.message);
                },
    
                error: function() {
                    button.prop('disabled', false).html(
                        '<i class="ri-delete-bin-line"></i>');
                    toast('danger', 'Server error.');
                }
            });
    
        });
    
    });

    /* ======================================================
    | OPEN EDIT MODAL
    ====================================================== */
    $(document).on('click', '.edit-record-btn', function() {

        let id = $(this).data('id');

        $('#editEventHolidayForm')[0].reset();

        $('#updateEventBtn').prop('disabled', true);
        $('#updateEventSpinner').removeClass('d-none');
        $('#updateEventText').text('Loading...');

        $('#editEventHolidayModal').modal('show');

        $.ajax({
            url: API_BASE + '/holidays/getEventHolidayById.php',
            type: 'GET',
            data: {
                id: id
            },
            dataType: 'json',

            success: function(response) {

                if (!response.success) {
                    $('#editEventHolidayModal').modal('hide');
                    toast('danger', response.message);
                    return;
                }

                let data = response.data;

                $('#editId').val(data.id);
                $('#editTitle').val(data.eventTitle);
                $('#editType').val(data.eventType);
                $('#editCategory').val(data.eventCategory);
                $('#editDate').val(data.eventDate);
                $('#editTime').val(data.eventTime);
                $('#editLocation').val(data.location);
                $('#editDescription').val(data.description);
                $('#editReminder').val(data.reminderDays);
                $('#editMail').val(data.mailEnabled);
                $('#editStatus').val(data.status);
            },

            error: function() {
                $('#editEventHolidayModal').modal('hide');
                toast('danger', 'Unable to load record.');
            },

            complete: function() {
                $('#updateEventBtn').prop('disabled', false);
                $('#updateEventSpinner').addClass('d-none');
                $('#updateEventText').text('Update Record');
            }
        });

    });

    /* ======================================================
    | UPDATE RECORD
    ====================================================== */
    $('#editEventHolidayForm').on('submit', function(e) {

        e.preventDefault();

        let form = this;
        let formData = new FormData(form);

        let btn = $('#updateEventBtn');
        let spinner = $('#updateEventSpinner');
        let text = $('#updateEventText');

        btn.prop('disabled', true);
        spinner.removeClass('d-none');
        text.text('Updating...');

        $.ajax({
            url: API_BASE + '/holidays/updateEventHoliday.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',

            success: function(response) {

                if (!response.success) {
                    toast('danger', response.message);
                    return;
                }

                let data = response.data;
                let row = $('.edit-record-btn[data-id="' + data.id + '"]').closest('tr');
                let actions = buildActionButtons(data);

                if (data.eventType === 'event') {

                    if (row.closest('#eventsTable').length) {

                        eventsTable.row(row).data([
                            '',
                            escapeHtml(data.eventTitle),
                            data.formattedDate,
                            data.formattedTime || '-',
                            escapeHtml(data.eventCategory || '-'),
                            '<span class="badge bg-success">' + escapeHtml(data
                                .status) + '</span>',
                            actions
                        ]).draw(false);

                    } else {

                        holidaysTable.row(row).remove().draw(false);

                        eventsTable.row.add([
                            '',
                            escapeHtml(data.eventTitle),
                            data.formattedDate,
                            data.formattedTime || '-',
                            escapeHtml(data.eventCategory || '-'),
                            '<span class="badge bg-success">' + escapeHtml(data
                                .status) + '</span>',
                            actions
                        ]).draw(false);
                    }

                } else {

                    if (row.closest('#holidaysTable').length) {

                        holidaysTable.row(row).data([
                            '',
                            escapeHtml(data.eventTitle),
                            data.formattedDate,
                            escapeHtml(data.eventCategory || '-'),
                            '<span class="badge bg-primary">' + escapeHtml(data
                                .status) + '</span>',
                            actions
                        ]).draw(false);

                    } else {

                        eventsTable.row(row).remove().draw(false);

                        holidaysTable.row.add([
                            '',
                            escapeHtml(data.eventTitle),
                            data.formattedDate,
                            escapeHtml(data.eventCategory || '-'),
                            '<span class="badge bg-primary">' + escapeHtml(data
                                .status) + '</span>',
                            actions
                        ]).draw(false);
                    }
                }

                refreshUpcomingCount();

                $('#editEventHolidayModal').modal('hide');

                toast('success', response.message);

            },

            error: function() {
                toast('danger', 'Server error.');
            },

            complete: function() {
                btn.prop('disabled', false);
                spinner.addClass('d-none');
                text.text('Update Record');
            }
        });

    });
    
    
    // All Tab Search
    $('#allSearch').on('keyup', function() {
        if (allTable) allTable.search(this.value).draw();
    });
    
    // All Tab Filters
    $.fn.dataTable.ext.search.push(function(settings, data) {
        if (settings.nTable.id !== 'allTable') return true;
        
        var typeFilter = $('#allTypeFilter').val();
        var monthFilter = $('#allMonthFilter').val();
        
        if (typeFilter) {
            var typeCell = data[5].toLowerCase();
            if (typeCell.indexOf(typeFilter) === -1) return false;
        }
        
        if (monthFilter) {
            var dateCell = data[2] || '';
            if (dateCell.indexOf(monthFilter) === -1) return false;
        }
        
        return true;
    });
    
    $('#allTypeFilter, #allMonthFilter').on('change', function() {
        if (allTable) allTable.draw();
    });
    
    // All Export
    $(document).on('click', '.all-export-btn', function(e) {
        e.preventDefault();
        if (allTable) allTable.buttons('.buttons-all-' + $(this).data('type')).trigger();
    });
    
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>