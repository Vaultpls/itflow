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

// Standalone agent modals are directly reachable too: map their directory to the
// same module the matching API resource uses. New directories fail closed to support.
if (preg_match('#/agent/modals/([a-z_]+)/#', $modal_script, $modal_match)) {
    $modal_modules = [
        'account' => 'module_financial', 'asset' => 'module_support', 'calendar' => 'module_support',
        'certificate' => 'module_support', 'client' => 'module_client', 'contact' => 'module_client',
        'credential' => 'module_credential', 'document' => 'module_support', 'domain' => 'module_support',
        'expense' => 'module_financial', 'file' => 'module_support', 'folder' => 'module_support',
        'income' => 'module_financial', 'invoice' => 'module_sales', 'location' => 'module_client',
        'network' => 'module_support', 'payment' => 'module_financial', 'product' => 'module_sales',
        'project' => 'module_support', 'quote' => 'module_sales', 'rack' => 'module_support',
        'recurring_expense' => 'module_financial', 'recurring_invoice' => 'module_sales',
        'recurring_ticket' => 'module_support', 'revenue' => 'module_financial', 'service' => 'module_client',
        'software' => 'module_support', 'ticket' => 'module_support', 'transaction' => 'module_financial',
        'transfer' => 'module_financial', 'trip' => 'module_financial', 'vendor' => 'module_client',
    ];
    enforceUserPermission($modal_modules[$modal_match[1]] ?? 'module_support');
}

header('Content-Type: application/json');

// Check for the 'id' parameter
//if (!isset($_GET['id'])) {
//    echo json_encode(['error' => 'ID missing.']);
//    exit;
//}
