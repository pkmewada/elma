<?php
declare(strict_types=1);

// Replace with your actual domain path and a strong secret token.
$repoDir = '/home/u438562206/domains/modlus.in/public_html';
$deployScript = $repoDir . '/deploy.sh';
$logFile = $repoDir . '/deploy.log';
// Set on the server only (e.g. `SetEnv MODLUS_DEPLOY_TOKEN ...` in the server
// .htaccess); never commit it. Empty means every deploy request is refused.
$secretToken = (string)(getenv('MODLUS_DEPLOY_TOKEN') ?: '');

header('Content-Type: application/json; charset=utf-8');

function respond(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, [
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ]);
}

$providedToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
if (empty($secretToken) || !hash_equals($secretToken, $providedToken)) {
    respond(403, [
        'success' => false,
        'message' => 'Invalid deploy token.',
    ]);
}

if (!is_file($deployScript) || !is_executable($deployScript)) {
    respond(500, [
        'success' => false,
        'message' => 'Deploy script missing or not executable.',
        'script' => $deployScript,
    ]);
}

$command = escapeshellcmd($deployScript) . ' 2>&1';
$output = '';
$returnVar = 1;

if (function_exists('proc_open')) {
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (is_resource($process)) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnVar = proc_close($process);
        $output = trim($stdout . PHP_EOL . $stderr);
    } else {
        $output = 'Failed to start deploy script process.';
    }
} elseif (function_exists('shell_exec')) {
    $output = shell_exec($command) ?: 'No output from shell_exec.';
    $returnVar = 0;
} else {
    respond(500, [
        'success' => false,
        'message' => 'No available shell execution function.',
    ]);
}

$logEntry = sprintf(
    "[%s] POST deploy request from %s\nReturn code: %d\nOutput:\n%s\n---\n",
    date('c'),
    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    $returnVar,
    $output
);
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

if ($returnVar === 0) {
    respond(200, [
        'success' => true,
        'message' => 'Deploy completed successfully.',
        'output' => $output,
    ]);
}

respond(500, [
    'success' => false,
    'message' => 'Deploy failed.',
    'output' => $output,
]);
