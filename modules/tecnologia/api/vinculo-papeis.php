<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';

if (file_exists(__DIR__ . '/../../../includes/auditoria.php')) {
    require_once __DIR__ . '/../../../includes/auditoria.php';
}

function resposta(bool $ok, string $mensagem, array $dados = [], int $status = 200): never {
    http_response_code($status);
    echo json_encode(
        ['sucesso'=>$ok, 'mensagem'=>$mensagem, 'dados'=>$dados],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $usuarios = $pdo->query("
        SELECT id, nome, email, ativo
        FROM usuarios
        ORDER BY nome
    ")->fetchAll(PDO::FETCH_ASSOC);

    $papeis = $pdo->query("
        SELECT id, nome, descricao, ativo
        FROM papeis
        WHERE ativo = 1
        ORDER BY id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $vinculos = $pdo->query("
        SELECT usuario_id, papel_id
        FROM usuario_papel
        ORDER BY usuario_id, papel_id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $geral = $pdo->query("
        SELECT
            u.id,
            u.nome,
            u.email,
            u.ativo,
            GROUP_CONCAT(
                p.nome
                ORDER BY p.id
                SEPARATOR '||'
            ) AS papeis
        FROM usuarios u
        LEFT JOIN usuario_papel up ON up.usuario_id = u.id
        LEFT JOIN papeis p ON p.id = up.papel_id
        GROUP BY u.id, u.nome, u.email, u.ativo
        ORDER BY u.nome
    ")->fetchAll(PDO::FETCH_ASSOC);

    resposta(true, 'Dados carregados.', [
        'usuarios'=>$usuarios,
        'papeis'=>$papeis,
        'vinculos'=>$vinculos,
        'geral'=>$geral
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resposta(false, 'Método não permitido.', [], 405);
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    resposta(false, 'Dados inválidos.', [], 400);
}

$usuarioId = filter_var($body['usuario_id'] ?? null, FILTER_VALIDATE_INT);
$papeisRecebidos = $body['papeis'] ?? [];

if (!$usuarioId || !is_array($papeisRecebidos)) {
    resposta(false, 'Usuário ou papéis inválidos.', [], 422);
}

$stmtUsuario = $pdo->prepare("SELECT id, nome FROM usuarios WHERE id = :id LIMIT 1");
$stmtUsuario->execute([':id'=>$usuarioId]);
$usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    resposta(false, 'Usuário não encontrado.', [], 404);
}

$papeisIds = [];
foreach ($papeisRecebidos as $id) {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    if ($id) $papeisIds[] = (int)$id;
}
$papeisIds = array_values(array_unique($papeisIds));

if ($papeisIds) {
    $marks = implode(',', array_fill(0, count($papeisIds), '?'));
    $stmtValidar = $pdo->prepare("SELECT id FROM papeis WHERE ativo = 1 AND id IN ($marks)");
    $stmtValidar->execute($papeisIds);
    $validos = array_map('intval', $stmtValidar->fetchAll(PDO::FETCH_COLUMN));
    sort($validos);
    $comparar = $papeisIds;
    sort($comparar);

    if ($validos !== $comparar) {
        resposta(false, 'Existe um papel inválido ou inativo na seleção.', [], 422);
    }
}

$stmtAntigos = $pdo->prepare("
    SELECT up.papel_id, p.nome
    FROM usuario_papel up
    JOIN papeis p ON p.id = up.papel_id
    WHERE up.usuario_id = :usuario_id
    ORDER BY p.id
");
$stmtAntigos->execute([':usuario_id'=>$usuarioId]);
$antigos = $stmtAntigos->fetchAll(PDO::FETCH_ASSOC);

try {
    $pdo->beginTransaction();

    $del = $pdo->prepare("DELETE FROM usuario_papel WHERE usuario_id = :usuario_id");
    $del->execute([':usuario_id'=>$usuarioId]);

    if ($papeisIds) {
        $ins = $pdo->prepare("
            INSERT INTO usuario_papel (usuario_id, papel_id)
            VALUES (:usuario_id, :papel_id)
        ");
        foreach ($papeisIds as $papelId) {
            $ins->execute([
                ':usuario_id'=>$usuarioId,
                ':papel_id'=>$papelId
            ]);
        }
    }

    $pdo->commit();

    $novos = [];
    if ($papeisIds) {
        $marks = implode(',', array_fill(0, count($papeisIds), '?'));
        $stmtNovos = $pdo->prepare("SELECT id, nome FROM papeis WHERE id IN ($marks) ORDER BY id");
        $stmtNovos->execute($papeisIds);
        $novos = $stmtNovos->fetchAll(PDO::FETCH_ASSOC);
    }

    if (function_exists('registrarAuditoria')) {
        registrarAuditoria(
            $pdo,
            'ALTERACAO',
            'Vínculos de papéis do usuário "' . $usuario['nome'] . '" foram atualizados.',
            'Tecnologia',
            'Vínculo de Papéis',
            'usuario_papel',
            $usuarioId,
            ['papeis'=>$antigos],
            ['papeis'=>$novos]
        );
    }

    resposta(true, 'Vínculos salvos com sucesso.');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Erro vínculo de papéis: '.$e->getMessage());
    resposta(false, 'Não foi possível salvar os vínculos.', ['erro'=>$e->getMessage()], 500);
}
