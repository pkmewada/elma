<?php

require_once __DIR__ . '/permission-helper.php';

$buttonPermissionRequestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$buttonPermissionBasePath = rtrim((string)(parse_url(BASE_URL, PHP_URL_PATH) ?? ''), '/');

if (
    $buttonPermissionBasePath !== ''
    && strpos($buttonPermissionRequestPath, $buttonPermissionBasePath) === 0
) {
    $buttonPermissionRequestPath = substr(
        $buttonPermissionRequestPath,
        strlen($buttonPermissionBasePath)
    );
}

$buttonPermissionRequestPath = '/' . ltrim($buttonPermissionRequestPath, '/');
$buttonPermissionRequestPath = rtrim($buttonPermissionRequestPath, '/') ?: '/';
$buttonPermissionActions = [];

$buttonPermissionStmt = $con->prepare("
    SELECT
        pa.actionKey,
        pa.actionLabel,
        pa.buttonSelector
    FROM permissionActions pa
    INNER JOIN routesMaster rm ON rm.id = pa.routeId
    WHERE rm.routePath = ?
    AND rm.isActive = 1
    AND pa.isActive = 1
    ORDER BY pa.sortOrder ASC, pa.actionLabel ASC
");

if (!$buttonPermissionStmt) {
    error_log('Button permission query failed: ' . $con->error);
    return;
}

$buttonPermissionStmt->bind_param('s', $buttonPermissionRequestPath);
$buttonPermissionStmt->execute();
$buttonPermissionResult = $buttonPermissionStmt->get_result();

while ($buttonPermissionAction = $buttonPermissionResult->fetch_assoc()) {
    $actionKey = (string)$buttonPermissionAction['actionKey'];

    $buttonPermissionActions[] = [
        'key' => $actionKey,
        'label' => (string)$buttonPermissionAction['actionLabel'],
        'selector' => trim((string)($buttonPermissionAction['buttonSelector'] ?? '')),
        'allowed' => hasActionPermission($buttonPermissionRequestPath, $actionKey),
    ];
}

$buttonPermissionStmt->close();

if (!$buttonPermissionActions) {
    return;
}

$buttonPermissionJson = json_encode(
    $buttonPermissionActions,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

if ($buttonPermissionJson === false) {
    return;
}
?>

<style>
[data-modlus-permission-disabled="1"] {
    cursor: not-allowed !important;
    opacity: .55 !important;
}
</style>

<script>
(function() {
    'use strict';

    const actions = <?= $buttonPermissionJson; ?>;
    const deniedActions = actions.filter((action) => !action.allowed);

    if (!deniedActions.length) {
        return;
    }

    const normalizeLabel = (value) => String(value || '')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();

    const getControlLabel = (control) => {
        if (control instanceof HTMLInputElement) {
            return normalizeLabel(control.value);
        }

        return normalizeLabel(control.textContent);
    };

    const disableControl = (control, action) => {
        if (!(control instanceof HTMLElement)) {
            return;
        }

        control.dataset.modlusPermissionDisabled = '1';
        control.dataset.modlusActionKey = action.key;
        control.setAttribute('aria-disabled', 'true');
        control.setAttribute('title', 'You do not have permission for ' + action.label);

        if ('disabled' in control) {
            control.disabled = true;
        }

        if (control instanceof HTMLAnchorElement) {
            control.setAttribute('tabindex', '-1');
            control.classList.add('disabled');
        }

        control.removeAttribute('data-bs-toggle');
        control.removeAttribute('data-bs-target');
    };

    const findLabelMatches = (action) => {
        const expectedLabel = normalizeLabel(action.label);
        const controls = document.querySelectorAll(
            'button, a.btn, input[type="button"], input[type="submit"], [role="button"]'
        );

        return Array.from(controls).filter(
            (control) => getControlLabel(control) === expectedLabel
        );
    };

    const applyPermissions = () => {
        deniedActions.forEach((action) => {
            let controls = [];

            if (action.selector) {
                try {
                    controls = Array.from(document.querySelectorAll(action.selector));
                } catch (error) {
                    console.warn(
                        'Invalid button selector for permission action:',
                        action.key,
                        action.selector
                    );
                }
            } else {
                controls = findLabelMatches(action);
            }

            controls.forEach((control) => disableControl(control, action));
        });
    };

    let applyTimer = null;
    const scheduleApply = () => {
        window.clearTimeout(applyTimer);
        applyTimer = window.setTimeout(applyPermissions, 50);
    };

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const disabledControl = event.target.closest(
            '[data-modlus-permission-disabled="1"]'
        );

        if (!disabledControl) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        if (typeof window.showToast === 'function') {
            window.showToast('warning', disabledControl.getAttribute('title'));
        }
    }, true);

    const start = () => {
        applyPermissions();

        const observer = new MutationObserver(scheduleApply);
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
</script>
