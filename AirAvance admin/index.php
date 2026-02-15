<?php
// Auto-redirect to login page
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['role'])) {
    $role = strtolower($_SESSION['role']);
    
    switch ($role) {
        case 'admin':
            header("Location: admin/admin_dashboard.php");
            break;
        case 'dcoa':
            header("Location: dcoa/dcoa_dashboard.php");
            break;
        case 'dcsp':
            header("Location: dcsp_dashboard.php");
            break;
        case 'dgf':
            header("Location: dgf_dashboard.php");
            break;
        default:
            header("Location: auth/login.html");
    }
    exit();
}

// Redirect to login if not authenticated
header("Location: auth/login.html");
exit();
?>
