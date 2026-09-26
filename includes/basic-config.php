<?php


function getMailConfig(): array
{
    $config = getBasicConfig();

    return [
        'username' => trim((string)($config['gmail_username'] ?? '')),
        'password' => trim((string)($config['gmail_app_password'] ?? ''))
    ];
}


function getBasicConfigPath(): string
{
    return dirname(__DIR__) . '/storage/basic-config.json';
}

function getBasicConfigDefaults(): array
{
    return [
        'gmail_username' => '',
        'gmail_app_password' => '',
        'roles' => [],
        'organizationRoles' => [],
        'departments' => [],
        'deductionTypes' => [],
        'expenseTypes' => [],
        'terms_and_conditions_html' => '<h2>Terms & Conditions</h2><p>These Terms & Conditions govern the acknowledgment and onboarding process between the candidate and the organization.</p><h3>1. Acceptance of Terms</h3><p>By proceeding, you agree to these terms.</p><h3>2. Accuracy of Information</h3><p>You confirm all information submitted is accurate and complete.</p><h3>3. Employment Conditions</h3><ul><li>Employment is subject to document verification.</li><li>Background checks may be conducted.</li><li>Final employment remains subject to company approval.</li></ul><h3>4. Confidentiality</h3><p>Information shared during hiring is confidential.</p><h3>5. Code of Conduct</h3><p>You agree to follow company policies and standards.</p><h3>6. Acknowledgment</h3><p>By proceeding, you acknowledge and accept these terms.</p>',
        'terms_last_updated' => '',
    ];
}

function getBasicConfig(): array
{
    $defaults = getBasicConfigDefaults();
    $configPath = getBasicConfigPath();

    if (!file_exists($configPath)) {
        return $defaults;
    }

    $raw = file_get_contents($configPath);

    if ($raw === false || trim($raw) === '') {
        return $defaults;
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        return $defaults;
    }

    $rolesRaw = $decoded['roles'] ?? ($decoded['organizationRoles'] ?? []);
    $roles = is_array($rolesRaw)
    ? array_values(array_filter(
        array_map('trim', $rolesRaw),
        static fn($item) => $item !== ''
    ))
    : [];

    $departmentsRaw = $decoded['departments'] ?? [];
    $departments = is_array($departmentsRaw)
    ? array_values(array_filter(
        array_map('trim', $departmentsRaw),
        static fn($item) => $item !== ''
    ))
    : [];
    
    $deductionTypesRaw = $decoded['deductionTypes'] ?? [];

    $deductionTypes = is_array($deductionTypesRaw)
    ? array_values(array_filter(
        array_map('trim', $deductionTypesRaw),
        static fn($item) => $item !== ''
    ))
    : [];
    
    $expenseTypesRaw = $decoded['expenseTypes'] ?? [];
    
    $expenseTypes = is_array($expenseTypesRaw)
    ? array_values(array_filter(
        array_map('trim', $expenseTypesRaw),
        static fn($item) => $item !== ''
    ))
    : [];

    $config = array_merge($defaults, [
        'gmail_username' => trim((string) ($decoded['gmail_username'] ?? '')),
        'gmail_app_password' => trim((string) ($decoded['gmail_app_password'] ?? '')),
        'roles' => $roles,
        'organizationRoles' => $roles, // compatibility for existing UI usage
        'departments' => $departments,
        'deductionTypes' => $deductionTypes,
        'expenseTypes' => $expenseTypes,
        'terms_and_conditions_html' => trim((string) ($decoded['terms_and_conditions_html'] ?? '')),
        'terms_last_updated' => trim((string) ($decoded['terms_last_updated'] ?? '')),
    ]);

    // Auto-migrate old JSON structures to include canonical keys.
    $needsMigration = !array_key_exists('roles', $decoded) || !array_key_exists('departments', $decoded);
    if ($needsMigration) {
        saveBasicConfig($config);
    }

    return $config;
}

function saveBasicConfig(array $config): bool
{
    $configPath = getBasicConfigPath();
    $configDirectory = dirname($configPath);

    if (
        !is_dir($configDirectory) &&
        !mkdir($configDirectory, 0777, true) &&
        !is_dir($configDirectory)
    ) {
        return false;
    }

    $rolesInput = $config['roles'] ?? $config['organizationRoles'] ?? [];
    $roles = is_array($rolesInput) ? array_values($rolesInput) : [];

    $departmentsInput = $config['departments'] ?? [];
    $departments = [];

    if (is_array($departmentsInput)) {
        foreach ($departmentsInput as $item) {
            $name = trim((string) $item);

            if ($name !== '') {
                $departments[] = $name;
            }
        }

        $departments = array_values(array_unique($departments));
    }
    
    $deductionTypesInput = $config['deductionTypes'] ?? [];
        $deductionTypes = [];
        
        if (is_array($deductionTypesInput)) {
        
            foreach ($deductionTypesInput as $item) {
        
                $name = trim((string) $item);
        
                if ($name !== '') {
                    $deductionTypes[] = $name;
                }
            }
        
            $deductionTypes = array_values(array_unique($deductionTypes));
        }
        
        $expenseTypesInput = $config['expenseTypes'] ?? [];
        $expenseTypes = [];
        
        if (is_array($expenseTypesInput)) {
        
            foreach ($expenseTypesInput as $item) {
        
                $name = trim((string) $item);
        
                if ($name !== '') {
                    $expenseTypes[] = $name;
                }
            }
        
            $expenseTypes = array_values(array_unique($expenseTypes));
        }
    
    

    $payload = [
        'gmail_username' => trim((string) ($config['gmail_username'] ?? '')),
        'gmail_app_password' => trim((string) ($config['gmail_app_password'] ?? '')),
        'roles' => $roles,
        'departments' => $departments,
        'deductionTypes' => $deductionTypes,
        'expenseTypes' => $expenseTypes,
        'terms_and_conditions_html' => trim((string) ($config['terms_and_conditions_html'] ?? '')),
        'terms_last_updated' => trim((string) ($config['terms_last_updated'] ?? '')),
    ];

    $encoded = json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

    if ($encoded === false) {
        return false;
    }

    $fp = fopen($configPath, 'c+');

    if ($fp === false) {
        return false;
    }

    $ok = false;

    try {
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);

            $bytes = fwrite($fp, $encoded . PHP_EOL);

            fflush($fp);
            flock($fp, LOCK_UN);

            $ok = ($bytes !== false);
        }
    } finally {
        fclose($fp);
    }

    return $ok;
}