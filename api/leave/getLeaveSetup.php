<?php
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/db.php";

header("Content-Type: application/json; charset=UTF-8");

# ===============================
# 🔹 RESPONSE HELPER
# ===============================
function respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);
    exit();
}

# ===============================
# 🔹 DEFAULT SETTINGS
# ===============================
$settings = [
    "workingDays" => [],
    "weekendPolicy" => "exclude",
    "sandwichRule" => 0,
    "carryForward" => 0,
    "carryForwardLimit" => 0,
    "maxLeavesPerRequest" => 0,
    "minNoticeDays" => 0,
    "setupCompleted" => 0,
];

# ===============================
# 🔹 FETCH SETTINGS (NO companyId)
# ===============================
$stmt = mysqli_prepare(
    $con,
    "SELECT workingDays, weekendPolicy, sandwichRule, carryForward, carryForwardLimit, maxLeavesPerRequest, minNoticeDays, setupCompleted 
     FROM leaveSettings 
     LIMIT 1"
);

if (!$stmt) {
    respond(false, "Failed to load leave settings.");
}

mysqli_stmt_execute($stmt);

mysqli_stmt_bind_result(
    $stmt,
    $workingDaysJson,
    $weekendPolicy,
    $sandwichRule,
    $carryForward,
    $carryForwardLimit,
    $maxLeavesPerRequest,
    $minNoticeDays,
    $setupCompleted
);

if (mysqli_stmt_fetch($stmt)) {

    $decoded = json_decode((string)$workingDaysJson, true);
    $normalizedDays = [];

    if (is_array($decoded)) {
        foreach ($decoded as $d) {
            $d = strtolower(trim((string)$d));
            if (in_array($d, ["mon","tue","wed","thu","fri","sat","sun"], true)) {
                $normalizedDays[] = $d;
            }
        }
    }

    $settings = [
        "workingDays" => array_values(array_unique($normalizedDays)),
        "weekendPolicy" => $weekendPolicy === "include" ? "include" : "exclude",
        "sandwichRule" => (int)$sandwichRule,
        "carryForward" => (int)$carryForward,
        "carryForwardLimit" => max(0, (int)$carryForwardLimit),
        "maxLeavesPerRequest" => max(0, (int)$maxLeavesPerRequest),
        "minNoticeDays" => max(0, (int)$minNoticeDays),
        "setupCompleted" => (int)$setupCompleted,
    ];
}

mysqli_stmt_close($stmt);

# ===============================
# 🔹 FETCH LEAVE TYPES (NO companyId)
# ===============================
$leaveTypes = [];
$activeLeaveTypes = [];

$typeStmt = mysqli_prepare(
    $con,
    "SELECT 
        id, name, code, isPaid, allocationType, totalLeaves, isActive,
        allowHalfDay, maxConsecutiveDays, minServiceDays, applicableGender, allowNegative
     FROM leaveTypes 
     ORDER BY id ASC"
);

if (!$typeStmt) {
    respond(false, "Failed to load leave types.");
}

mysqli_stmt_execute($typeStmt);
$result = mysqli_stmt_get_result($typeStmt);

while ($row = mysqli_fetch_assoc($result)) {

    $item = [
        "id" => (int)$row["id"],
        "name" => trim((string)$row["name"]),
        "code" => strtoupper(trim((string)$row["code"])),
        "isPaid" => (int)$row["isPaid"],
        "allocationType" =>
            (string)$row["allocationType"] === "monthly" ? "monthly" : "yearly",
        "totalLeaves" => max(0, (int)$row["totalLeaves"]),
        "isActive" => (int)$row["isActive"],

        "allowHalfDay" => (int)$row["allowHalfDay"],
        "maxConsecutiveDays" => (int)$row["maxConsecutiveDays"],
        "minServiceDays" => (int)$row["minServiceDays"],
        "applicableGender" => $row["applicableGender"] ?: "all",
        "allowNegative" => (int)$row["allowNegative"],
    ];

    $leaveTypes[] = $item;

    if ((int)$row["isActive"] === 1) {
        $activeLeaveTypes[] = $item;
    }
}

mysqli_stmt_close($typeStmt);

# ===============================
# 🔹 RESPONSE
# ===============================
respond(true, "Leave setup loaded successfully.", [
    "leaveSettings" => $settings,
    "leaveTypes" => $leaveTypes,
    "activeLeaveTypes" => $activeLeaveTypes
]);