<?php
/**
 * 晚风影视 - 登录接口
 */

function handleLogin($params) {
    $username = trim($params['username'] ?? '');
    $password = $params['password'] ?? '';

    if (empty($username) || empty($password)) {
        jsonResponse(400, '请输入用户名和密码');
    }

    $user = dbQueryOne("SELECT id, username, password, nickname, avatar, status FROM wf_users WHERE username = ?", [$username]);

    if (!$user) {
        jsonResponse(401, '用户名或密码错误');
    }

    if ($user['status'] != 1) {
        jsonResponse(403, '账号已被禁用，请联系管理员');
    }

    if (!passwordVerify($password, $user['password'])) {
        jsonResponse(401, '用户名或密码错误');
    }

    // 更新最后登录信息
    $ip = getClientIp();
    dbExecute("UPDATE wf_users SET last_login = NOW(), login_ip = ? WHERE id = ?", [$ip, $user['id']]);

    // 生成Token
    $token = generateToken($user['id']);

    jsonResponse(200, '登录成功', [
        'token' => $token,
        'user_id' => $user['id'],
        'username' => $user['username'],
        'nickname' => $user['nickname'] ?: $user['username'],
        'avatar' => $user['avatar']
    ]);
}
