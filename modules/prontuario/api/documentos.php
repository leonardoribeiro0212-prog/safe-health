<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/seguranca.php';

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../config/database.php';
if (file_exists(__DIR__ . '/../../../includes/auditoria.php')) {
    require_once __DIR__ . '/../../../includes/auditoria.php';
}

function outDoc(bool $ok, string $msg, array $data = [], int $code = 200): never {
    http_response_code($code);
    echo json_encode(
        ['sucesso'=>$ok,'mensagem'=>$msg,'dados'=>$data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

$uid=(int)($_SESSION['usuario_id']??0);
if(!$uid) outDoc(false,'Sessão inválida.',[],401);
if($_SERVER['REQUEST_METHOD']!=='POST') outDoc(false,'Método não permitido.',[],405);

$in=json_decode(file_get_contents('php://input')?:'',true);
if(!is_array($in)) outDoc(false,'Dados inválidos.',[],400);

$acao=trim((string)($in['acao']??''));
$aid=(int)($in['atendimento_id']??0);
$tipo=strtoupper(trim((string)($in['tipo']??'')));
$tipos=['RECEITUARIO','ATESTADO','DECLARACAO','RELATORIO','ENCAMINHAMENTO'];
if(!$aid || !in_array($tipo,$tipos,true)) outDoc(false,'Atendimento ou tipo inválido.',[],422);

$stmt=$pdo->prepare("
    SELECT a.id,a.paciente_id,a.status,m.id AS medico_id,m.nome,m.crm,m.uf_crm
    FROM atendimentos a
    INNER JOIN medicos m ON m.id=a.profissional_id
    WHERE a.id=:aid AND m.usuario_id=:uid AND m.ativo=1
    LIMIT 1
");
$stmt->execute([':aid'=>$aid,':uid'=>$uid]);
$ctx=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$ctx) outDoc(false,'Atendimento não pertence ao médico logado.',[],403);

try {
    if($acao==='novo'){
        $stmt=$pdo->prepare("
            INSERT INTO documentos_medicos
            (atendimento_id,paciente_id,medico_id,usuario_id,tipo,status,conteudo_json,criado_em,atualizado_em)
            VALUES (:a,:p,:m,:u,:t,'RASCUNHO','{}',NOW(),NOW())
        ");
        $stmt->execute([
            ':a'=>$aid,':p'=>(int)$ctx['paciente_id'],
            ':m'=>(int)$ctx['medico_id'],':u'=>$uid,':t'=>$tipo
        ]);
        $id=(int)$pdo->lastInsertId();
        outDoc(true,'Novo documento criado.',['documento_id'=>$id],201);
    }

    $id=(int)($in['documento_id']??0);
    $stmt=$pdo->prepare("
        SELECT * FROM documentos_medicos
        WHERE id=:id AND atendimento_id=:a AND medico_id=:m AND tipo=:t
        LIMIT 1
    ");
    $stmt->execute([':id'=>$id,':a'=>$aid,':m'=>(int)$ctx['medico_id'],':t'=>$tipo]);
    $doc=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$doc) outDoc(false,'Documento não encontrado.',[],404);
    if($doc['status']!=='RASCUNHO') outDoc(false,'Documento finalizado não pode ser editado.',[],409);

    if($acao==='excluir'){
        $pdo->prepare("DELETE FROM documentos_medicos WHERE id=:id AND status='RASCUNHO'")
            ->execute([':id'=>$id]);
        outDoc(true,'Rascunho excluído.');
    }

    $conteudo=$in['conteudo']??[];
    if(!is_array($conteudo)) outDoc(false,'Conteúdo inválido.',[],422);

    if($tipo==='ENCAMINHAMENTO'){
        $especialidadeId=(int)($conteudo['especialidade_id']??0);

        if($especialidadeId<=0){
            outDoc(false,'Selecione a especialidade de destino.',[],422);
        }

        $stmtEspecialidade=$pdo->prepare("
            SELECT id,nome
            FROM especialidades
            WHERE id=:id AND ativa=1
            LIMIT 1
        ");
        $stmtEspecialidade->execute([':id'=>$especialidadeId]);
        $especialidade=$stmtEspecialidade->fetch(PDO::FETCH_ASSOC);

        if(!$especialidade){
            outDoc(
                false,
                'A especialidade selecionada não está ativa no cadastro.',
                [],
                422
            );
        }

        $conteudo['especialidade_id']=(int)$especialidade['id'];
        $conteudo['destino']=$especialidade['nome'];
    }

    $json=json_encode($conteudo,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

    if($acao==='salvar'){
        $pdo->prepare("
            UPDATE documentos_medicos
            SET conteudo_json=:c, atualizado_em=NOW()
            WHERE id=:id AND status='RASCUNHO'
        ")->execute([':c'=>$json,':id'=>$id]);
        outDoc(true,'Documento salvo como rascunho.');
    }

    if($acao==='finalizar'){
        if($tipo==='RECEITUARIO' && empty($conteudo['medicamentos'])) {
            outDoc(false,'Adicione pelo menos um medicamento ao receituário.',[],422);
        }
        $payload=json_encode([
            'documento_id'=>$id,'atendimento_id'=>$aid,
            'paciente_id'=>(int)$ctx['paciente_id'],
            'medico_id'=>(int)$ctx['medico_id'],
            'usuario_id'=>$uid,'tipo'=>$tipo,
            'crm'=>$ctx['crm'],'uf_crm'=>$ctx['uf_crm'],
            'conteudo'=>$conteudo
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $hash=hash('sha256',(string)$payload);

        $stmt=$pdo->prepare("
            UPDATE documentos_medicos
            SET conteudo_json=:c,status='FINALIZADO',
                finalizado_em=NOW(),atualizado_em=NOW(),hash_finalizacao=:h
            WHERE id=:id AND status='RASCUNHO'
        ");
        $stmt->execute([':c'=>$json,':h'=>$hash,':id'=>$id]);
        if($stmt->rowCount()!==1) outDoc(false,'O documento já foi alterado.',[],409);
        outDoc(true,'Documento fechado e assinado.',['hash'=>$hash]);
    }

    outDoc(false,'Ação inválida.',[],400);
} catch(Throwable $e){
    error_log('Documentos médicos: '.$e->getMessage());
    outDoc(false,'Não foi possível concluir a operação.',[],500);
}
