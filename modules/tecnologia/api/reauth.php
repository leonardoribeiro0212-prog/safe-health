<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/seguranca.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD']!=='POST') {
    http_response_code(405);
    echo json_encode(['sucesso'=>false,'mensagem'=>'Método não permitido.']);
    exit;
}

$input=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$senha=(string)($input['senha'] ?? '');
$usuarioId=(int)$_SESSION['usuario_id'];

$stmt=$pdo->prepare("SELECT senha FROM usuarios WHERE id=:id AND ativo=1 LIMIT 1");
$stmt->execute([':id'=>$usuarioId]);
$hash=$stmt->fetchColumn();

if (!$hash || !password_verify($senha,(string)$hash)) {
    shRegistrarAuditoria($pdo,'REAUTENTICACAO_FALHA','Senha inválida em confirmação de ação crítica.',$usuarioId,'Segurança','Reautenticação');
    http_response_code(401);
    echo json_encode(['sucesso'=>false,'mensagem'=>'Senha inválida.'],JSON_UNESCAPED_UNICODE);
    exit;
}

$segundos=max(60,(int)shConfig($pdo,'REAUTENTICACAO_SEGUNDOS','300'));
$_SESSION['reauth_ate']=time()+$segundos;

shRegistrarAuditoria($pdo,'REAUTENTICACAO','Identidade confirmada para ação crítica.',$usuarioId,'Segurança','Reautenticação');

echo json_encode([
    'sucesso'=>true,
    'mensagem'=>'Identidade confirmada.',
    'valido_por_segundos'=>$segundos
],JSON_UNESCAPED_UNICODE);
