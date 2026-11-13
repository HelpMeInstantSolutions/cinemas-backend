<?php
// backend/api/auth.php - Authentication API (Email / 10-Digit Mobile + Password)
require_once __DIR__ . '/config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? '');

switch ($action) {
    case 'register':
        $name = trim($input['name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $phone = preg_replace('/[^0-9]/', '', trim($input['phone'] ?? ''));
        $password = trim($input['password'] ?? '');

        if (empty($name)) {
            sendResponse(false, "Full name is required", null, 400);
        }

        if (empty($phone) || strlen($phone) !== 10) {
            sendResponse(false, "Please enter a valid 10-digit mobile number", null, 400);
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(false, "Please enter a valid email address", null, 400);
        }

        if (empty($password) || strlen($password) < 6) {
            sendResponse(false, "Password must be at least 6 characters long", null, 400);
        }

        // Check if email already registered
        $chkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $chkEmail->execute([$email]);
        if ($chkEmail->fetch()) {
            sendResponse(false, "This email is already registered. Please sign in.", null, 409);
        }

        // Check if phone already registered
        $chkPhone = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $chkPhone->execute([$phone]);
        if ($chkPhone->fetch()) {
            sendResponse(false, "This phone number is already registered. Please sign in.", null, 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, plain_password, status, otp_verified, email_verified) VALUES (?, ?, ?, ?, ?, 1, 0, 0)");
        $stmt->execute([$name, $email, $phone, $hash, $password]);
        $newId = (int)$pdo->lastInsertId();

        sendResponse(true, "Registration successful! Welcome to Cinemas.", [
            'id' => $newId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'otp_verified' => 0,
            'email_verified' => 0
        ]);
        break;

    case 'login':
        $identifier = trim($input['email'] ?? ($input['username'] ?? ($input['phone'] ?? '')));
        $password = trim($input['password'] ?? '');

        if (empty($identifier) || empty($password)) {
            sendResponse(false, "Email/Mobile and password are required", null, 400);
        }

        // Search by email OR 10-digit phone
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([strtolower($identifier), preg_replace('/[^0-9]/', '', $identifier)]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse(false, "Account not found with this email or mobile", null, 404);
        }

        if ((int)$user['status'] === 0) {
            sendResponse(false, "Your account has been deactivated. Please contact support.", null, 403);
        }

        $valid = password_verify($password, $user['password']) || (!empty($user['plain_password']) && $password === $user['plain_password']);
        if (!$valid) {
            sendResponse(false, "Incorrect password. Please try again.", null, 401);
        }

        sendResponse(true, "Login successful! Welcome back.", [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? '',
            'otp_verified' => (int)($user['otp_verified'] ?? 0),
            'email_verified' => (int)($user['email_verified'] ?? 0)
        ]);
        break;

    default:
        sendResponse(false, "Invalid action specified", null, 400);
        break;
}
