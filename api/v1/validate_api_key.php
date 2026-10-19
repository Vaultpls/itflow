<?php

/*
 * API - validate_api_key.php
 * Called by API endpoint to validate API key is valid
 * Allows execution to continue or exits returning errors to the user
 */

// Includes
require_once __DIR__ . '../../../functions.php';
require_once __DIR__ . "../../../config.php";

// JSON header
header('Content-Type: application/json');

// POST data
$_POST = json_decode(file_get_contents('php://input'), true);

// Get IP & UA
$ip = escapeSql(getIP());
$user_agent = escapeSql($_SERVER['HTTP_USER_AGENT']);

// Temp Added this to work with the new logAction function
$session_ip = $ip;
$session_user_agent = $user_agent;

// Setup return array
$return_arr = array();

// Unauthorised wording
DEFINE("WORDING_UNAUTHORIZED", "HTTP/1.1 401 Unauthorized");

/*
 * API Notes:
 *
 * To avoid over-complicating the app by using PUT and DELETE methods, only going to allow the use of GET and POST methods.
 * GET - Retrieving (READ) data
 * POST - Inserting (CREATE), Updating (UPDATE) or Deleting (DELETE) data
 *
 * Data returned as json encoded $return_arr:-
     * Success - True/False
     * Message - Brief info about a request / failure
     * Count - Count of rows affected/returned
     * Data - Requested data
 *
 */

// Decline methods other than GET/POST
if ($_SERVER['REQUEST_METHOD'] !== "GET" && $_SERVER['REQUEST_METHOD'] !== "POST") {
    header("HTTP/1.1 405 Method Not Allowed");
    var_dump($_SERVER['REQUEST_METHOD']);
    exit();
}

// Set API key variable (query/body first, Authorization: Bearer preferred)
$api_key = '';
if (isset($_GET['api_key'])) {
    $api_key = escapeSql($_GET['api_key']);
}
if (isset($_POST['api_key'])) {
    $api_key = escapeSql($_POST['api_key']);
}
$authorization_header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($authorization_header === '' && function_exists('apache_request_headers')) {
    foreach (apache_request_headers() as $header_name => $header_value) {
        if (strcasecmp($header_name, 'Authorization') === 0) {
            $authorization_header = $header_value;
            break;
        }
    }
}
if (preg_match('/^Bearer\s+(.+)$/i', $authorization_header, $bearer_match)) {
    $api_key = escapeSql(trim($bearer_match[1]));
}

// Check API key is provided
if ($api_key === '') {
    header(WORDING_UNAUTHORIZED);
    exit();
}

// Validate API key
if (isset($api_key)) {
    $api_key = escapeSql($api_key);
    $api_key_hash = 'sha256:' . hash('sha256', $api_key);

    // Ten bad keys per IP per minute; each failure still logs, but a flood is cut off
    $api_failures = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(log_id) AS failure_count FROM logs WHERE log_type = 'API' AND log_action = 'Failed' AND log_ip = '$ip' AND log_created_at > (NOW() - INTERVAL 1 MINUTE)"));
    if (intval($api_failures['failure_count'] ?? 0) >= 10) {
        header("HTTP/1.1 429 Too Many Requests");
        $return_arr['success'] = "False";
        $return_arr['message'] = "Too many failed authentication attempts. Try again later.";
        echo json_encode($return_arr);
        exit();
    }

    $sql = mysqli_query($mysqli, "SELECT api_key_id, api_key_secret, api_key_decrypt_hash, api_key_name, api_key_user_id FROM api_keys WHERE (api_key_secret = '$api_key_hash' OR api_key_secret = '$api_key') AND api_key_expire > NOW() LIMIT 1");

    // Failed
    if (mysqli_num_rows($sql) !== 1) {
        // Invalid Key

        $url_path = escapeSql(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'API', log_action = 'Failed', log_description = 'Incorrect or expired key (endpoint: $url_path)', log_ip = '$ip', log_user_agent = '$user_agent'");

        $return_arr['success'] = "False";
        $return_arr['message'] = "Authentication failed. API key is invalid or has expired.";

        header(WORDING_UNAUTHORIZED);
        echo json_encode($return_arr);
        exit();

    } else {

        // SUCCESS

        // Set client ID, company ID & key name
        $row = mysqli_fetch_assoc($sql);
        $api_key_id = intval($row['api_key_id']);

        // Upgrade a legacy plaintext key to its hash on first successful use
        if (hash_equals((string) $row['api_key_secret'], $api_key)) {
            mysqli_query($mysqli, "UPDATE api_keys SET api_key_secret = '$api_key_hash' WHERE api_key_id = $api_key_id");
        }

        $api_key_name = htmlentities($row['api_key_name']);
        $api_key_decrypt_hash = $row['api_key_decrypt_hash']; // No sanitization
        $api_key_user_id = intval($row['api_key_user_id']);

        // Set limit & offset for queries
        if (isset($_GET['limit'])) {
            $limit = intval($_GET['limit']);
        } elseif (isset($_POST['limit'])) {
            $limit = intval($_POST['limit']);
        } else {
            $limit = 50;
        }

        if (isset($_GET['offset'])) {
            $offset = intval($_GET['offset']);
        } elseif (isset($_POST['offset'])) {
            $offset = intval($_POST['offset']);
        } else {
            $offset = 0;
        }

        // When the key is tied to a user, enforce that user's RBAC (module + operation + client scope)
        require __DIR__ . '/enforce_api_rbac.php';

    }
}
