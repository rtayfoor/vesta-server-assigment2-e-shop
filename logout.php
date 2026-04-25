<?php
session_start();


$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}


if (isset($_COOKIE['cart_data'])) {
    setcookie('cart_data', '', time() - 3600, "/");
}


session_destroy();

header("Location: index.php?logout=success");
exit();
?>