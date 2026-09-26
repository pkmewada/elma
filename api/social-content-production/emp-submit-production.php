<?php
/*
|--------------------------------------------------------------------------
| Submit Production — Video Editor
|--------------------------------------------------------------------------
|
| The one path for handing back finished work: a Google Drive link, or an
| uploaded file. multipart/form-data only (required for $_FILES; a JSON
| body can't carry a file), which is why this is a dedicated endpoint
| rather than another action inside emp-update-task.php.
|
| Ownership is checked twice: once here (before touching the filesystem,
| so a request for someone else's task never gets as far as writing a
| file) and again inside SocialContentProductionEngine::submitProduction()
| (the actual authority — never trust the client-side check alone).
|
*/
require_once __DIR__ . '/../../includes/emp-auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/SocialContentProductionEngine.php';

header('Content-Type: application/json');

try {
    requireValidCsrfToken();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

try {
    // video first (finished reels are the common case), then static-post images
    $allowedMediaMimeToExt = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    $maxMediaBytes = 100 * 1024 * 1024; // 100MB — see uploads/production/.user.ini

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $submissionType = isset($_POST['submissionType']) ? trim($_POST['submissionType']) : '';
    $remark = $_POST['remark'] ?? null;
    $editorId = (int)$_SESSION['candidateId'];

    if ($id <= 0 || !in_array($submissionType, ['drive', 'media'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid submission request.']);
        exit;
    }

    // ownership pre-check — never write a file for a task that isn't this
    // editor's, even before the engine gets involved
    $stmt = mysqli_prepare($con, 'SELECT id, status FROM socialContentProduction WHERE id = ? AND assignedEditorId = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $editorId);
    mysqli_stmt_execute($stmt);
    $owned = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$owned) {
        echo json_encode(['success' => false, 'message' => 'Task not found, or not assigned to you.']);
        exit;
    }

    $submissionUrl = '';
    $uploadedPath = null; // tracked so it can be cleaned up if the engine call below fails

    if ($submissionType === 'drive') {
        $driveUrl = trim((string)($_POST['submissionUrl'] ?? ''));
        if (!preg_match('#^https://(drive|docs)\.google\.com/#i', $driveUrl)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid Google Drive link.']);
            exit;
        }
        $submissionUrl = $driveUrl;
    } else {
        if (!isset($_FILES['media']) || $_FILES['media']['error'] === UPLOAD_ERR_NO_FILE) {
            echo json_encode(['success' => false, 'message' => 'Please choose a file to upload.']);
            exit;
        }

        $file = $_FILES['media'];

        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            echo json_encode(['success' => false, 'message' => 'That file is too large to upload.']);
            exit;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Upload failed. Please try again.']);
            exit;
        }
        if ($file['size'] <= 0 || $file['size'] > $maxMediaBytes) {
            echo json_encode(['success' => false, 'message' => 'File must be under 100MB.']);
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset($allowedMediaMimeToExt[$mimeType])) {
            echo json_encode(['success' => false, 'message' => 'Unsupported file type. Please upload a video (mp4, mov, webm) or image (jpg, png).']);
            exit;
        }

        // the extension is derived from the verified MIME type, never from
        // the client-supplied filename/extension
        $extension = $allowedMediaMimeToExt[$mimeType];
        $targetDir = __DIR__ . '/../../uploads/production/' . $id . '/';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            echo json_encode(['success' => false, 'message' => 'Could not prepare storage for the upload.']);
            exit;
        }

        $fileName = 'production_' . $id . '_' . time() . '.' . $extension;
        $targetPath = $targetDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to store the uploaded file.']);
            exit;
        }

        $uploadedPath = $targetPath;
        $submissionUrl = 'uploads/production/' . $id . '/' . $fileName;
    }

    $engine = new SocialContentProductionEngine($con);

    try {
        $task = $engine->submitProduction($id, $editorId, $submissionType, $submissionUrl, $remark);
    } catch (Exception $e) {
        if ($uploadedPath !== null && is_file($uploadedPath)) {
            @unlink($uploadedPath);
        }
        throw $e;
    }

    echo json_encode(['success' => true, 'data' => $task]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
