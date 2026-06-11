<?php
/**
 * 晚风影视 - 管理员退出
 */
session_start();
session_destroy();
header('Location: /admin/login.php');
exit;
