<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'E-posta ve parola gereklidir.';
        header('Location: ../index.php');
        exit;
    }
    
    $result = authenticateUser($email, $password);
    
    if ($result['success']) {
        $user = $result['user'];
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['location_id'] = $user['location_id'];
        $_SESSION['company_name'] = $user['company_name'];
        $_SESSION['location_name'] = $user['location_name'];
        
        switch ($user['role']) {
            case 'admin':
                header('Location: ../admin/dashboard.php');
                break;
            case 'hr':
                header('Location: ../hr/dashboard.php');
                break;
            case 'employee':
                header('Location: ../employee/dashboard.php');
                break;
            default:
                header('Location: ../index.php');
        }
        exit;
    } else {
        $_SESSION['error'] = $result['message'];
        header('Location: ../index.php');
        exit;
    }
} else {
    header('Location: ../index.php');
    exit;
}
?>
