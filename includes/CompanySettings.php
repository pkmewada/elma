<?php

require_once __DIR__ . '/config.php';

function ensureCompanySettingsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS companySettings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            companyName VARCHAR(180) NOT NULL DEFAULT '',
            legalName VARCHAR(180) NOT NULL DEFAULT '',
            addressLine1 VARCHAR(255) NOT NULL DEFAULT '',
            addressLine2 VARCHAR(255) NOT NULL DEFAULT '',
            city VARCHAR(120) NOT NULL DEFAULT '',
            stateName VARCHAR(120) NOT NULL DEFAULT '',
            pincode VARCHAR(20) NOT NULL DEFAULT '',
            country VARCHAR(120) NOT NULL DEFAULT 'India',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            email VARCHAR(160) NOT NULL DEFAULT '',
            website VARCHAR(180) NOT NULL DEFAULT '',
            gstNumber VARCHAR(30) NOT NULL DEFAULT '',
            panNumber VARCHAR(20) NOT NULL DEFAULT '',
            cinNumber VARCHAR(40) NOT NULL DEFAULT '',
            companyLogo VARCHAR(255) NOT NULL DEFAULT '',
            setupCompleted TINYINT(1) NOT NULL DEFAULT 0,
            isActive TINYINT(1) NOT NULL DEFAULT 1,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    companySettingsEnsureColumn($con, 'legalName', "VARCHAR(180) NOT NULL DEFAULT ''");
    companySettingsEnsureColumn($con, 'addressLine1', "VARCHAR(255) NOT NULL DEFAULT ''");
    companySettingsEnsureColumn($con, 'addressLine2', "VARCHAR(255) NOT NULL DEFAULT ''");
    companySettingsEnsureColumn($con, 'city', "VARCHAR(120) NOT NULL DEFAULT ''");
    companySettingsEnsureColumn($con, 'stateName', "VARCHAR(120) NOT NULL DEFAULT ''");
    companySettingsEnsureColumn($con, 'pincode', "VARCHAR(20) NOT NULL DEFAULT ''");
    companySettingsEnsureColumn($con, 'country', "VARCHAR(120) NOT NULL DEFAULT 'India'");
    companySettingsEnsureColumn($con, 'phone', "VARCHAR(40) NOT NULL DEFAULT ''");
    companySettingsEnsureColumn($con, 'email', "VARCHAR(160) NOT NULL DEFAULT ''");
    companySettingsEnsureColumn($con, 'website', "VARCHAR(180) NOT NULL DEFAULT ''");
    companySettingsEnsureColumn($con, 'gstNumber', "VARCHAR(30) NOT NULL DEFAULT ''");
    companySettingsEnsureColumn($con, 'panNumber', "VARCHAR(20) NOT NULL DEFAULT ''");
    companySettingsEnsureColumn($con, 'cinNumber', "VARCHAR(40) NOT NULL DEFAULT ''");
    companySettingsEnsureColumn($con, 'companyLogo', "VARCHAR(255) NOT NULL DEFAULT ''");
}

function companySettingsEnsureColumn(mysqli $con, string $column, string $definition): void
{
    $columnName = mysqli_real_escape_string($con, $column);
    $result = mysqli_query($con, "SHOW COLUMNS FROM companySettings LIKE '{$columnName}'");

    if ($result && mysqli_num_rows($result) > 0) {
        return;
    }

    mysqli_query($con, "ALTER TABLE companySettings ADD COLUMN {$column} {$definition}");
}

function getCompanySettingsDefaults(): array
{
    return [
        'companyName' => '',
        'legalName' => '',
        'addressLine1' => '',
        'addressLine2' => '',
        'city' => '',
        'stateName' => '',
        'pincode' => '',
        'country' => 'India',
        'phone' => '',
        'email' => '',
        'website' => '',
        'gstNumber' => '',
        'panNumber' => '',
        'cinNumber' => '',
        'companyLogo' => '',
        'setupCompleted' => 0,
    ];
}

function getCompanySettings(mysqli $con): array
{
    ensureCompanySettingsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT *
         FROM companySettings
         WHERE isActive = 1
         ORDER BY id DESC
         LIMIT 1"
    );

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $settings = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    $settings = array_merge(getCompanySettingsDefaults(), $settings ?: []);
    $settings['companyAddress'] = companySettingsFullAddress($settings);
    $settings['address'] = $settings['companyAddress'];
    $settings['companyLogoUrl'] = companySettingsLogoUrl((string)$settings['companyLogo']);
    $settings['companyLogoDataUri'] = companySettingsLogoDataUri((string)$settings['companyLogo']);

    return $settings;
}

function saveCompanySettings(mysqli $con, array $settings): bool
{
    ensureCompanySettingsTable($con);

    $normalized = normalizeCompanySettings($settings);
    $fields = [
        'companyName',
        'legalName',
        'addressLine1',
        'addressLine2',
        'city',
        'stateName',
        'pincode',
        'country',
        'phone',
        'email',
        'website',
        'gstNumber',
        'panNumber',
        'cinNumber',
        'companyLogo',
    ];

    $stmt = mysqli_prepare(
        $con,
        "SELECT id
         FROM companySettings
         WHERE isActive = 1
         ORDER BY id DESC
         LIMIT 1"
    );

    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    $values = [];
    foreach ($fields as $field) {
        $values[] = $normalized[$field];
    }

    $types = str_repeat('s', count($fields));

    if ($existing) {
        $assignments = implode(', ', array_map(
            static fn(string $field): string => "{$field} = ?",
            $fields
        ));
        $stmt = mysqli_prepare(
            $con,
            "UPDATE companySettings
             SET {$assignments}, setupCompleted = 1
             WHERE id = ?"
        );

        if (!$stmt) {
            return false;
        }

        $values[] = (int)$existing['id'];
        $types .= 'i';
    } else {
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $stmt = mysqli_prepare(
            $con,
            "INSERT INTO companySettings (" . implode(', ', $fields) . ", setupCompleted, isActive)
             VALUES ({$placeholders}, 1, 1)"
        );

        if (!$stmt) {
            return false;
        }
    }

    companySettingsBindParams($stmt, $types, $values);
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $saved;
}

function normalizeCompanySettings(array $settings): array
{
    $defaults = getCompanySettingsDefaults();
    $settings = array_merge($defaults, $settings);

    return [
        'companyName' => companySettingsCleanText($settings['companyName'], 180),
        'legalName' => companySettingsCleanText($settings['legalName'], 180),
        'addressLine1' => companySettingsCleanText($settings['addressLine1'], 255),
        'addressLine2' => companySettingsCleanText($settings['addressLine2'], 255),
        'city' => companySettingsCleanText($settings['city'], 120),
        'stateName' => companySettingsCleanText($settings['stateName'], 120),
        'pincode' => companySettingsCleanText($settings['pincode'], 20),
        'country' => companySettingsCleanText($settings['country'], 120) ?: 'India',
        'phone' => companySettingsCleanText($settings['phone'], 40),
        'email' => companySettingsCleanText($settings['email'], 160),
        'website' => companySettingsCleanText($settings['website'], 180),
        'gstNumber' => strtoupper(companySettingsCleanText($settings['gstNumber'], 30)),
        'panNumber' => strtoupper(companySettingsCleanText($settings['panNumber'], 20)),
        'cinNumber' => strtoupper(companySettingsCleanText($settings['cinNumber'], 40)),
        'companyLogo' => companySettingsCleanText($settings['companyLogo'], 255),
    ];
}

function companySettingsCleanText($value, int $maxLength): string
{
    $value = preg_replace('/\s+/', ' ', trim((string)$value));

    return substr((string)$value, 0, $maxLength);
}

function companySettingsFullAddress(array $settings): string
{
    $parts = [
        $settings['addressLine1'] ?? '',
        $settings['addressLine2'] ?? '',
        $settings['city'] ?? '',
        $settings['stateName'] ?? '',
        $settings['pincode'] ?? '',
        $settings['country'] ?? '',
    ];

    return implode(', ', array_values(array_filter(array_map(
        static fn($part): string => trim((string)$part),
        $parts
    ))));
}

function companySettingsLogoUrl(string $logoPath): string
{
    $logoPath = trim($logoPath);

    if ($logoPath === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $logoPath)) {
        return $logoPath;
    }

    return BASE_URL . '/' . ltrim($logoPath, '/');
}

function companySettingsLogoDataUri(string $logoPath): string
{
    $logoPath = trim($logoPath);

    if ($logoPath === '' || preg_match('/^https?:\/\//i', $logoPath)) {
        return '';
    }

    $fullPath = dirname(__DIR__) . '/' . ltrim($logoPath, '/');

    if (!is_file($fullPath)) {
        return '';
    }

    $mimeType = mime_content_type($fullPath) ?: 'image/png';
    $bytes = file_get_contents($fullPath);

    if ($bytes === false) {
        return '';
    }

    return 'data:' . $mimeType . ';base64,' . base64_encode($bytes);
}

function companySettingsBindParams(mysqli_stmt $stmt, string $types, array $values): bool
{
    $params = [$types];

    foreach ($values as $key => $value) {
        $params[] = &$values[$key];
    }

    return call_user_func_array([$stmt, 'bind_param'], $params);
}
