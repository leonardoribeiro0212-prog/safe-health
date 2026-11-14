<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/database.php';

if(empty($_SESSION['usuario_id'])){http_response_code(401);exit;}
$q=trim((string)($_GET['q']??''));
if(mb_strlen($q)<2){echo json_encode(['sucesso'=>true,'dados'=>[]]);exit;}

$stmt=$pdo->prepare("
 SELECT id,nome,principio_ativo,codigo,apresentacao,concentracao
 FROM medicamentos
 WHERE ativo=1 AND (
   nome LIKE :q OR principio_ativo LIKE :q OR codigo LIKE :q
 )
 ORDER BY nome LIMIT 15
");
$stmt->execute([':q'=>'%'.$q.'%']);
echo json_encode(
 ['sucesso'=>true,'dados'=>$stmt->fetchAll(PDO::FETCH_ASSOC)],
 JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
);
