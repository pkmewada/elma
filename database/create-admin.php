<?php
/*
|--------------------------------------------------------------------------
| Create a CRM admin (CLI only)
|--------------------------------------------------------------------------
| The CRM database starts with no admin and there is no public signup.
|
|   php database/create-admin.php "Full Name" admin@example.com
|
| Prints a generated password once; change it after first login via
| Forgot Password. Uses the same CRM_DB_* configuration as the app.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$fullName = trim((string)($argv[1] ?? ''));
$email = strtolower(trim((string)($argv[2] ?? '')));

if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php database/create-admin.php \"Full Name\" admin@example.com\n");
    exit(1);
}

$check = mysqli_prepare($con, 'SELECT id FROM users WHERE email = ? LIMIT 1');
mysqli_stmt_bind_param($check, 's', $email);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
$exists = mysqli_stmt_num_rows($check) > 0;
mysqli_stmt_close($check);

if ($exists) {
    fwrite(STDERR, "An admin with that email already exists.\n");
    exit(1);
}

$password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'Ab'), '=');
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($con, 'INSERT INTO users (fullName, email, password, otp, isVerified) VALUES (?, ?, ?, NULL, 1)');
mysqli_stmt_bind_param($stmt, 'sss', $fullName, $email, $passwordHash);

if (!mysqli_stmt_execute($stmt)) {
    fwrite(STDERR, "Unable to create admin.\n");
    exit(1);
}

$dbName = (string)(mysqli_fetch_row(mysqli_query($con, 'SELECT DATABASE()'))[0] ?? '');

echo "Admin created in database {$dbName}.\n";
echo "Email:    {$email}\n";
echo "Password: {$password}\n";
