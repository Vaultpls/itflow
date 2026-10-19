<?php

/*
 * ITFlow - Logout
 */

if (isset($_GET['logout'])) {

    // Logging
    logAudit("Logout", "Success", "$session_name logged out");
    
    mysqli_query($mysqli, "UPDATE users SET user_php_session = '' WHERE user_id = $session_user_id");

    // Revoke this device's remember-me token, and drop the cookie
    if (!empty($_COOKIE['rememberme'])) {
        $logout_remember_raw = escapeSql((string) $_COOKIE['rememberme']);
        $logout_remember_hash = hash('sha256', (string) $_COOKIE['rememberme']);
        mysqli_query($mysqli, "DELETE FROM remember_tokens WHERE remember_token_user_id = $session_user_id AND (remember_token_token = '$logout_remember_hash' OR remember_token_token = '$logout_remember_raw')");
    }
    setcookie("rememberme", '', time() - 3600, "/");
    unset($_COOKIE['rememberme']);

    setcookie("PHPSESSID", '', time() - 3600, "/");
    unset($_COOKIE['PHPSESSID']);

    setcookie("user_encryption_session_key", '', time() - 3600, "/");
    unset($_COOKIE['user_encryption_session_key']);

    setcookie("user_extension_key", '', time() - 3600, "/");
    unset($_COOKIE['user_extension_key']);

    session_unset();
    session_destroy();

    if ($config_login_key_required == 1) {
        header('Location: ../../login.php?key=' . $config_login_key_secret);
    } else {
        header('Location: ../../login.php');
    }
}

?>
