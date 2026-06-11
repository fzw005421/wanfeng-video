<?php
/**
 * 晚风影视 - 注册接口
 */

function handleRegister($params) {
    $username = trim($params['username'] ?? '');
    $password = $params['password'] ?? '';
    $nickname = trim($params['nickname'] ?? '');

    if (empty($username) || empty($password)) {
        jsonResponse(400, '请输入用户名和密码');
    }

    if (strlen($username) < 3 || strlen($username) > 50) {
        jsonResponse(400, '用户名长度需在3-50个字符之间');
    }

    if (strlen($password) < 6) {
        jsonResponse(400, '密码长度不能少于6位');
    }

    if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $username)) {
        jsonResponse(400, '用户名只能包含字母、数字、下划线和中文');
    }

    // 检查用户名是否已存在
    $exists = dbQueryOne("SELECT id FROM wf_users WHERE username = ?", [$username]);
    if ($exists) {
        jsonResponse(400, '该用户名已被注册');
    }

    $hash = passwordHash($password);
    $displayName = $nickname ?: $username;
    $ip = getClientIp();

    dbExecute(
        "INSERT INTO wf_users (username, password, nickname, status, last_login, login_ip) VALUES (?, ?, ?, 1, NOW(), ?)",
        [$username, $hash, $displayName, $ip]
    );

    $userId = dbLastInsertId();
    $token = generateToken($userId);

    jsonResponse(200, '注册成功', [
        'token' => $token,
        'user_id' => $userId,
        'username' => $username,
        'nickname' => $displayName,
        'avatar' => ''
    ]);
}
