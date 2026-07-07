<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'me' && $method === 'GET') {
    json_response(['user' => current_user()]);
}

if ($action === 'logout' && $method === 'POST') {
    $_SESSION = [];
    clear_app_session_cookie();
    session_destroy();
    json_response(['ok' => true]);
}

$data = read_json();

if ($action === 'register' && $method === 'POST') {
    $name = trim((string) ($data['name'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        json_response(['error' => 'Name, email and password are required.'], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['error' => 'Please enter a valid email address.'], 422);
    }

    if (strlen($password) < 8) {
        json_response(['error' => 'Password must be at least 8 characters.'], 422);
    }

    validate_length($name, 'Name', 100);
    validate_length($email, 'Email', 190);

    try {
        $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) db()->lastInsertId();

        $projectStmt = db()->prepare('INSERT INTO projects (user_id, name, color) VALUES (?, ?, ?)');
        foreach ([
            ['Client Work', '#2563eb'],
            ['Internal Admin', '#16a34a'],
            ['Learning', '#f97316'],
        ] as [$projectName, $projectColor]) {
            $projectStmt->execute([$_SESSION['user_id'], $projectName, $projectColor]);
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            json_response(['error' => 'An account with this email already exists.'], 409);
        }
        throw $e;
    }

    json_response(['user' => current_user()], 201);
}

if ($action === 'login' && $method === 'POST') {
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_response(['error' => 'Invalid email or password.'], 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    json_response(['user' => current_user()]);
}

json_response(['error' => 'Unknown auth action.'], 404);
