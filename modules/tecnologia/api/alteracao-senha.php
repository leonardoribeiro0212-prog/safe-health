<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';

if (file_exists(__DIR__ . '/../../../includes/auditoria.php')) {
    require_once __DIR__ . '/../../../includes/auditoria.php';
}

function responder(
    bool $sucesso,
    string $mensagem,
    array $dados = [],
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        [
            'sucesso' => $sucesso,
            'mensagem' => $mensagem,
            'dados' => $dados
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim((string) ($_GET['acao'] ?? ''));

    if ($acao !== 'pesquisar') {
        responder(false, 'Ação inválida.', [], 400);
    }

    $q = trim((string) ($_GET['q'] ?? ''));

    if ($q === '') {
        responder(true, 'Pesquisa vazia.', ['usuarios' => []]);
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            nome,
            email,
            ativo
        FROM usuarios
        WHERE
            nome LIKE :q
            OR email LIKE :q
        ORDER BY nome
        LIMIT 30
    ");

    $stmt->execute([
        ':q' => '%' . $q . '%'
    ]);

    responder(
        true,
        'Usuários encontrados.',
        [
            'usuarios' =>
                $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método não permitido.', [], 405);
}

$entrada = json_decode(
    file_get_contents('php://input') ?: '',
    true
);

if (!is_array($entrada)) {
    responder(false, 'Dados inválidos.', [], 400);
}

$usuarioId =
    (int) ($entrada['usuario_id'] ?? 0);

$novaSenha =
    (string) ($entrada['nova_senha'] ?? '');

$confirmarSenha =
    (string) ($entrada['confirmar_senha'] ?? '');

if ($usuarioId <= 0) {
    responder(false, 'Selecione um usuário.', [], 422);
}

if ($novaSenha === '') {
    responder(false, 'Informe a nova senha.', [], 422);
}

if ($novaSenha !== $confirmarSenha) {
    responder(false, 'A confirmação da nova senha não confere.', [], 422);
}

if (strlen($novaSenha) < 8) {
    responder(false, 'A nova senha deve ter pelo menos 8 caracteres.', [], 422);
}

if (!preg_match('/[A-Z]/', $novaSenha)) {
    responder(false, 'A nova senha deve conter pelo menos uma letra maiúscula.', [], 422);
}

if (!preg_match('/[a-z]/', $novaSenha)) {
    responder(false, 'A nova senha deve conter pelo menos uma letra minúscula.', [], 422);
}

if (!preg_match('/[0-9]/', $novaSenha)) {
    responder(false, 'A nova senha deve conter pelo menos um número.', [], 422);
}

try {
    $stmtUsuario = $pdo->prepare("
        SELECT
            id,
            nome,
            email,
            ativo,
            senha
        FROM usuarios
        WHERE id = :id
        LIMIT 1
    ");

    $stmtUsuario->execute([
        ':id' => $usuarioId
    ]);

    $usuario =
        $stmtUsuario->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        responder(false, 'Usuário não encontrado.', [], 404);
    }

    /*
     * Se a senha atual já usa password_hash(), impede reutilização.
     * Se for um registro legado em texto puro, a redefinição também
     * serve para migrá-lo para hash seguro.
     */
    $infoHash = password_get_info((string) $usuario['senha']);

    if (
        !empty($infoHash['algo']) &&
        password_verify(
            $novaSenha,
            (string) $usuario['senha']
        )
    ) {
        responder(
            false,
            'A nova senha deve ser diferente da senha atual.',
            [],
            422
        );
    }

    $novoHash = password_hash(
        $novaSenha,
        PASSWORD_DEFAULT
    );

    if ($novoHash === false) {
        responder(
            false,
            'Não foi possível processar a nova senha.',
            [],
            500
        );
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE usuarios
        SET senha = :senha
        WHERE id = :id
    ");

    $stmtUpdate->execute([
        ':senha' => $novoHash,
        ':id' => $usuarioId
    ]);

    if (function_exists('registrarAuditoria')) {
        registrarAuditoria(
            $pdo,
            'ALTERACAO',
            'A senha do usuário "' . $usuario['nome'] . '" foi redefinida.',
            'Tecnologia',
            'Alteração de Senha',
            'usuarios',
            $usuarioId,
            null,
            [
                'senha_redefinida' => true
            ]
        );
    }

    responder(
        true,
        'Senha do usuário alterada com sucesso.'
    );

} catch (Throwable $erro) {
    error_log(
        'Erro ao redefinir senha: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível alterar a senha.',
        [],
        500
    );
}
