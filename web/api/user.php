<?php
/**
 * 晚风影视 - 用户接口
 */

function handleUser($method, $segments, $params) {
    $userId = getCurrentUserId();
    if (!$userId) {
        jsonResponse(401, '请先登录');
    }

    $action = $segments[1] ?? 'profile';

    switch ($action) {
        case 'profile':
            if ($method === 'GET') {
                $user = dbQueryOne(
                    "SELECT id, username, nickname, avatar, last_login, created_at FROM wf_users WHERE id = ?",
                    [$userId]
                );
                if (!$user) {
                    jsonResponse(404, '用户不存在');
                }
                jsonResponse(200, '获取成功', $user);
            } elseif ($method === 'PUT' || $method === 'POST') {
                $nickname = trim($params['nickname'] ?? '');
                $avatar = trim($params['avatar'] ?? '');

                if ($nickname) {
                    dbExecute("UPDATE wf_users SET nickname = ? WHERE id = ?", [$nickname, $userId]);
                }
                if ($avatar) {
                    dbExecute("UPDATE wf_users SET avatar = ? WHERE id = ?", [$avatar, $userId]);
                }

                $user = dbQueryOne(
                    "SELECT id, username, nickname, avatar FROM wf_users WHERE id = ?",
                    [$userId]
                );
                jsonResponse(200, '更新成功', $user);
            }
            break;

        case 'change-password':
            if ($method !== 'POST') {
                jsonResponse(405, '方法不允许');
            }
            $oldPass = $params['old_password'] ?? '';
            $newPass = $params['new_password'] ?? '';

            if (empty($oldPass) || empty($newPass)) {
                jsonResponse(400, '请输入旧密码和新密码');
            }
            if (strlen($newPass) < 6) {
                jsonResponse(400, '新密码长度不能少于6位');
            }

            $user = dbQueryOne("SELECT password FROM wf_users WHERE id = ?", [$userId]);
            if (!passwordVerify($oldPass, $user['password'])) {
                jsonResponse(400, '旧密码错误');
            }

            dbExecute("UPDATE wf_users SET password = ? WHERE id = ?", [passwordHash($newPass), $userId]);
            jsonResponse(200, '密码修改成功');
            break;

        default:
            jsonResponse(404, '接口不存在');
    }
}
