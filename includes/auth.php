<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/seguranca.php';

$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);

if ($usuarioId <= 0) {
    header('Location: /safe-health/login.php?erro=acesso');
    exit;
}

$agora = time();
$timeout = max(60, (int)shConfig($pdo, 'TIMEOUT_SESSAO_SEGUNDOS', '1200'));
$ultima = (int)($_SESSION['ultima_atividade'] ?? $agora);

if (($agora - $ultima) > $timeout) {
    if (!empty($_SESSION['security_session_token'])) {
        $hash = hash('sha256', (string)$_SESSION['security_session_token']);
        $stmt = $pdo->prepare("
            UPDATE sessoes_usuario
            SET encerrada_em=NOW(), motivo_encerramento='TIMEOUT'
            WHERE token_hash=:token AND encerrada_em IS NULL
        ");
        $stmt->execute([':token'=>$hash]);
    }

    shRegistrarAuditoria($pdo,'SESSAO_EXPIRADA','Sessão encerrada por inatividade.',$usuarioId,'Autenticação','Sessão');
    $_SESSION = [];
    session_destroy();
    header('Location: /safe-health/login.php?erro=expirada');
    exit;
}

$token = (string)($_SESSION['security_session_token'] ?? '');
if ($token === '') {
    $_SESSION = [];
    session_destroy();
    header('Location: /safe-health/login.php?erro=acesso');
    exit;
}

$hash = hash('sha256', $token);
$stmtSessao = $pdo->prepare("
    SELECT id,encerrada_em
    FROM sessoes_usuario
    WHERE usuario_id=:usuario_id AND token_hash=:token
    LIMIT 1
");
$stmtSessao->execute([':usuario_id'=>$usuarioId, ':token'=>$hash]);
$sessaoDb = $stmtSessao->fetch(PDO::FETCH_ASSOC);

if (!$sessaoDb || $sessaoDb['encerrada_em'] !== null) {
    shRegistrarAuditoria($pdo,'SESSAO_INVALIDADA','Tentativa de uso de sessão encerrada ou substituída.',$usuarioId,'Autenticação','Sessão');
    $_SESSION = [];
    session_destroy();
    header('Location: /safe-health/login.php?erro=sessao');
    exit;
}

$_SESSION['ultima_atividade'] = $agora;

$stmtTouch = $pdo->prepare("
    UPDATE sessoes_usuario
    SET ultima_atividade_em=NOW()
    WHERE id=:id
");
$stmtTouch->execute([':id'=>(int)$sessaoDb['id']]);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$naTelaTrocaSenha = str_ends_with($uri, '/trocar-senha.php');

if (!empty($_SESSION['troca_senha_obrigatoria']) && !$naTelaTrocaSenha) {
    header('Location: /safe-health/trocar-senha.php?primeiro_acesso=1');
    exit;
}
