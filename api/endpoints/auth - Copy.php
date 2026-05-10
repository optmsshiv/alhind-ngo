<?php
// endpoints/auth.php

function handleAuth(): void {
    $body = body();
    $username = sanitize($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if (empty($username) || empty($password)) {
        error('Username and password are required');
    }

    // Check against config (for simple single-admin setup)
    // For multi-user, check admin_users table below
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $token = createToken(['username' => $username, 'role' => 'superadmin']);
        ok(['token' => $token, 'role' => 'superadmin', 'name' => 'Admin'], 'Login successful');
    }

    // ── Multi-user: check database ───────────────────────────
    $db   = getDB();
    $stmt = $db->prepare("SELECT id, name, email, password_hash, role FROM admin_users WHERE email = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        error('Invalid credentials', 401);
    }

    // Update last login
    $db->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?")
       ->execute([$user['id']]);

    $token = createToken(['user_id' => $user['id'], 'role' => $user['role']]);

    ok([
        'token' => $token,
        'role'  => $user['role'],
        'name'  => $user['name'],
        'email' => $user['email'],
    ], 'Login successful');
}