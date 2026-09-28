<?php
/*
|--------------------------------------------------------------------------
| Private file storage (lead + project documents)
|--------------------------------------------------------------------------
| Files live under storage/ (web-blocked by .htaccess), are validated by
| their real MIME type (finfo), stored under generated names and served only
| by authorised endpoints via streamPrivateFile(). Browser MIME types and
| original file names are never trusted (the name is kept as metadata only).
|
| Callers pass a JSON error callback so each API keeps its response format.
*/

const PRIVATE_FILE_MAX_BYTES = 10 * 1024 * 1024;

/** Real MIME type => stored extension, per use. */
const PRIVATE_FILE_TYPES_PDF = ['application/pdf' => 'pdf'];
const PRIVATE_FILE_TYPES_IMAGE = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const PRIVATE_FILE_TYPES_DOCUMENT = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function iniSizeToBytes(string $value): int
{
    $value = trim($value);
    $number = (int)$value;

    switch (strtolower(substr($value, -1))) {
        case 'g': return $number * 1024 * 1024 * 1024;
        case 'm': return $number * 1024 * 1024;
        case 'k': return $number * 1024;
        default: return $number;
    }
}

/** Effective per-file limit: app cap or the server's upload_max_filesize, whichever is lower. */
function getPrivateFileLimitBytes(): int
{
    $serverLimit = iniSizeToBytes((string)ini_get('upload_max_filesize'));

    return $serverLimit > 0 ? min(PRIVATE_FILE_MAX_BYTES, $serverLimit) : PRIVATE_FILE_MAX_BYTES;
}

function formatPrivateFileLimit(): string
{
    return round(getPrivateFileLimitBytes() / 1024 / 1024, 1) . ' MB';
}

/** True when the request body exceeded post_max_size (PHP then drops $_POST/$_FILES). */
function isRequestBodyTooLarge(): bool
{
    $postMaxBytes = iniSizeToBytes((string)ini_get('post_max_size'));

    return $postMaxBytes > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $postMaxBytes;
}

function getPrivateStorageRoot(): string
{
    return dirname(__DIR__) . '/storage';
}

/**
 * Validates and stores one uploaded file in storage/<subDirectory>/.
 * Returns ['fileName' => generated, 'mimeType' => real, 'size' => bytes].
 */
function storePrivateFile(array $file, string $subDirectory, array $allowedTypes, callable $fail): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        $fail(422, 'File must be smaller than ' . formatPrivateFileLimit() . '.');
    }

    if ($uploadError !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
        $fail(422, 'Please select a valid file.');
    }

    $size = (int)($file['size'] ?? 0);

    if ($size <= 0 || $size > getPrivateFileLimitBytes()) {
        $fail(422, 'File must be smaller than ' . formatPrivateFileLimit() . '.');
    }

    $mimeType = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);

    if (!isset($allowedTypes[$mimeType])) {
        $fail(422, 'Unsupported file type. Allowed: ' . strtoupper(implode(', ', array_unique($allowedTypes))) . '.');
    }

    $directory = getPrivateStorageRoot() . '/' . trim($subDirectory, '/');

    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        error_log('Private file directory could not be created: ' . $directory);
        $fail(500, 'Upload failed.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];

    if (!move_uploaded_file((string)$file['tmp_name'], $directory . '/' . $fileName)) {
        $fail(500, 'Upload failed.');
    }

    return ['fileName' => $fileName, 'mimeType' => $mimeType, 'size' => $size];
}

/** Safe display copy of the browser-supplied name (metadata only, never a path). */
function cleanOriginalFileName(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[^\w .()-]/u', '', $name) ?? '';

    return mb_substr(trim($name) !== '' ? trim($name) : 'document', 0, 200);
}

function deletePrivateFile(string $subDirectory, string $fileName): void
{
    $path = getPrivateStorageRoot() . '/' . trim($subDirectory, '/') . '/' . basename($fileName);

    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Streams a stored file after re-checking it stays inside its folder and
 * still has an allowed MIME type. Never takes a path from the request.
 */
function streamPrivateFile(string $subDirectory, string $fileName, string $downloadName, bool $asAttachment, array $allowedTypes, callable $fail): void
{
    $directory = realpath(getPrivateStorageRoot() . '/' . trim($subDirectory, '/'));
    $path = $directory !== false ? realpath($directory . '/' . basename($fileName)) : false;

    if ($path === false || strpos($path, $directory . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
        $fail(404, 'Document not found.');
    }

    $mimeType = (string)(new finfo(FILEINFO_MIME_TYPE))->file($path);

    if (!isset($allowedTypes[$mimeType])) {
        $fail(404, 'Document not found.');
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . ($asAttachment ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', cleanOriginalFileName($downloadName)) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');

    readfile($path);
    exit;
}
