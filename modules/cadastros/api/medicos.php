<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';

function responder(bool $sucesso, string $mensagem, array $dados = [], int $status = 200): never {
    http_response_code($status);
    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'dados' => $dados
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function numeros(string $valor): string {
    return preg_replace('/\D/', '', $valor) ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim((string) ($_GET['acao'] ?? ''));

    if ($acao === 'pesquisar') {
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q === '') {
            responder(false, 'Informe nome, CPF ou CRM para pesquisar.', [], 422);
        }

        $stmt = $pdo->prepare("
            SELECT
                m.id, m.nome, m.cpf, m.crm, m.uf_crm,
                m.especialidade_id, e.nome AS especialidade_nome,
                m.telefone, m.email, m.usuario_id, m.ativo
            FROM medicos m
            INNER JOIN especialidades e ON e.id = m.especialidade_id
            WHERE m.nome LIKE :texto
               OR m.crm LIKE :texto
               OR m.cpf LIKE :cpf
            ORDER BY m.ativo DESC, m.nome
            LIMIT 30
        ");

        $stmt->execute([
            ':texto' => '%' . $q . '%',
            ':cpf' => '%' . numeros($q) . '%'
        ]);

        responder(true, 'Pesquisa concluída.', [
            'medicos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($acao === 'buscar') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            responder(false, 'Médico inválido.', [], 422);
        }

        $stmt = $pdo->prepare("
            SELECT id, nome, cpf, crm, uf_crm, especialidade_id,
                   telefone, email, usuario_id, ativo
            FROM medicos
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        $medico = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$medico) {
            responder(false, 'Médico não encontrado.', [], 404);
        }

        responder(true, 'Médico carregado.', ['medico' => $medico]);
    }

    responder(false, 'Ação inválida.', [], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método não permitido.', [], 405);
}

$entrada = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($entrada)) {
    responder(false, 'Dados inválidos.', [], 400);
}

$medicoId = (int) ($entrada['medico_id'] ?? 0);
$nome = trim((string) ($entrada['nome'] ?? ''));
$cpf = numeros((string) ($entrada['cpf'] ?? ''));
$crm = strtoupper(trim((string) ($entrada['crm'] ?? '')));
$uf = strtoupper(trim((string) ($entrada['uf_crm'] ?? '')));
$especialidadeId = (int) ($entrada['especialidade_id'] ?? 0);
$telefone = trim((string) ($entrada['telefone'] ?? ''));
$email = trim((string) ($entrada['email'] ?? ''));
$usuarioId = (int) ($entrada['usuario_id'] ?? 0);
$ativo = (int) ($entrada['ativo'] ?? 1);

if ($nome === '' || strlen($cpf) !== 11 || $crm === '' || strlen($uf) !== 2 || $especialidadeId <= 0) {
    responder(false, 'Preencha os campos obrigatórios corretamente.', [], 422);
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(false, 'Informe um e-mail válido.', [], 422);
}

try {
    $pdo->beginTransaction();

    $dup = $pdo->prepare("
        SELECT id
        FROM medicos
        WHERE (cpf = :cpf OR (crm = :crm AND uf_crm = :uf))
          AND id <> :id
        LIMIT 1
    ");
    $dup->execute([
        ':cpf' => $cpf,
        ':crm' => $crm,
        ':uf' => $uf,
        ':id' => $medicoId
    ]);

    if ($dup->fetchColumn()) {
        $pdo->rollBack();
        responder(false, 'Já existe médico com esse CPF ou CRM/UF.', [], 409);
    }

    if ($medicoId > 0) {
        $stmt = $pdo->prepare("
            UPDATE medicos
            SET nome=:nome, cpf=:cpf, crm=:crm, uf_crm=:uf,
                especialidade_id=:especialidade_id,
                telefone=:telefone, email=:email,
                usuario_id=:usuario_id, ativo=:ativo
            WHERE id=:id
        ");
        $stmt->execute([
            ':nome'=>$nome, ':cpf'=>$cpf, ':crm'=>$crm, ':uf'=>$uf,
            ':especialidade_id'=>$especialidadeId,
            ':telefone'=>$telefone !== '' ? $telefone : null,
            ':email'=>$email !== '' ? $email : null,
            ':usuario_id'=>$usuarioId > 0 ? $usuarioId : null,
            ':ativo'=>$ativo === 0 ? 0 : 1,
            ':id'=>$medicoId
        ]);

        $pdo->commit();
        responder(true, 'Cadastro do médico atualizado com sucesso.', [
            'medico_id'=>$medicoId, 'modo'=>'ALTERACAO'
        ]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO medicos
        (nome, cpf, crm, uf_crm, especialidade_id, telefone, email, usuario_id, ativo)
        VALUES
        (:nome, :cpf, :crm, :uf, :especialidade_id, :telefone, :email, :usuario_id, :ativo)
    ");
    $stmt->execute([
        ':nome'=>$nome, ':cpf'=>$cpf, ':crm'=>$crm, ':uf'=>$uf,
        ':especialidade_id'=>$especialidadeId,
        ':telefone'=>$telefone !== '' ? $telefone : null,
        ':email'=>$email !== '' ? $email : null,
        ':usuario_id'=>$usuarioId > 0 ? $usuarioId : null,
        ':ativo'=>$ativo === 0 ? 0 : 1
    ]);

    $id = (int) $pdo->lastInsertId();
    $pdo->commit();

    responder(true, 'Médico cadastrado com sucesso.', [
        'medico_id'=>$id, 'modo'=>'CADASTRO'
    ], 201);

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responder(false, 'Não foi possível salvar o médico.', [
        'erro'=>$erro->getMessage()
    ], 500);
}
