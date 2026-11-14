<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/seguranca.php';

$usuarioId=(int)($_SESSION['usuario_id'] ?? 0);
$token=(string)($_SESSION['security_session_token'] ?? '');

if ($usuarioId>0) {
    shRegistrarAuditoria($pdo,'LOGOUT','Logout realizado pelo usuário.',$usuarioId,'Autenticação','Logout');
}
if ($token!=='') {
    $stmt=$pdo->prepare("
        UPDATE sessoes_usuario
        SET encerrada_em=NOW(),motivo_encerramento='LOGOUT'
        WHERE token_hash=:token AND encerrada_em IS NULL
    ");
    $stmt->execute([':token'=>hash('sha256',$token)]);
}

$_SESSION=[];
if (ini_get('session.use_cookies')) {
    $p=session_get_cookie_params();
    setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
}
session_destroy();
header('Location: /safe-health/login.php');
exit;
