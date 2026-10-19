<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';

// Admin modal endpoints are directly reachable; gate them here so a logged-in
// agent cannot read payment provider keys, AI keys, mail bodies, etc. The two
// category/tag pickers are also opened from agent pages and stay available.
$modal_script = $_SERVER['SCRIPT_NAME'] ?? '';
if (str_contains($modal_script, '/admin/modals/')
    && !preg_match('#/admin/modals/(category/category_add|tag/tag_add)\.php$#', $modal_script)) {
    enforceAdminPermission();
}

header('Content-Type: application/json');

// Check for the 'id' parameter
//if (!isset($_GET['id'])) {
//    echo json_encode(['error' => 'ID missing.']);
//    exit;
//}
