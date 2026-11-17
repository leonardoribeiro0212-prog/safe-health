<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';

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
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim((string) ($_GET['acao'] ?? ''));

    if ($acao === 'listar') {
        $q = trim((string) ($_GET['q'] ?? ''));

        $sql = "
            SELECT
                u.id,
                u.nome,
                u.nome_usuario,
                u.email,
                u.perfil_id,
                u.ativo,
                u.criado_em,
                u.atualizado_em,
                GROUP_CONCAT(
                    DISTINCT p.nome
                    ORDER BY p.nome
                    SEPARATOR ', '
                ) AS papeis
            FROM usuarios u
            LEFT JOIN usuario_papel up
                ON up.usuario_id = u.id
            LEFT JOIN papeis p
                ON p.id = up.papel_id
            WHERE 1 = 1
        ";

        $params = [];

        if ($q !== '') {
            $sql .= "
                AND (
                    u.nome LIKE :q
                    OR u.nome_usuario LIKE :q
                    OR u.email LIKE :q
                )
            ";

            $params[':q'] = '%' . $q . '%';
        }

        $sql .= "
            GROUP BY
                u.id,
                u.nome,
                u.nome_usuario,
                u.email,
                u.perfil_id,
                u.ativo,
                u.criado_em,
                u.atualizado_em
            ORDER BY u.nome, u.id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        responder(true, 'Usuários carregados.', [
            'usuarios' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($acao === 'detalhes') {
        $id = filter_var(
            $_GET['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id) {
            responder(false, 'Usuário inválido.', [], 422);
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                nome,
                nome_usuario,
                email,
                perfil_id,
                ativo,
                criado_em,
                atualizado_em
            FROM usuarios
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id
        ]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            responder(false, 'Usuário não encontrado.', [], 404);
        }

        $stmtPapeis = $pdo->prepare("
            SELECT
                p.id,
                p.nome
            FROM usuario_papel up
            INNER JOIN papeis p
                ON p.id = up.papel_id
            WHERE up.usuario_id = :usuario_id
            ORDER BY p.nome
        ");

        $stmtPapeis->execute([
            ':usuario_id' => $id
        ]);

        responder(true, 'Usuário carregado.', [
            'usuario' => $usuario,
            'papeis' => $stmtPapeis->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    responder(false, 'Ação inválida.', [], 400);
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

$usuarioId = (int) ($entrada['usuario_id'] ?? 0);
$nome = trim((string) ($entrada['nome'] ?? ''));
$nomeUsuario = strtolower(trim((string) ($entrada['nome_usuario'] ?? '')));
$email = strtolower(trim((string) ($entrada['email'] ?? '')));
$senha = (string) ($entrada['senha'] ?? '');
$ativo = (int) ($entrada['ativo'] ?? 1);

if ($nome === '') {
    responder(false, 'Informe o nome do usuário.', [], 422);
}

if ($nomeUsuario === '' || !preg_match('/^[a-z0-9._-]{3,80}$/', $nomeUsuario)) {
    responder(false, 'Nome de usuário inválido. Use de 3 a 80 caracteres: letras minúsculas, números, ponto, hífen ou sublinhado.', [], 422);
}

if (
    $email === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    responder(false, 'Informe um e-mail válido.', [], 422);
}

if (!in_array($ativo, [0, 1], true)) {
    responder(false, 'Status inválido.', [], 422);
}

if ($usuarioId <= 0 && strlen($senha) < 6) {
    responder(
        false,
        'A senha do novo usuário deve ter pelo menos 6 caracteres.',
        [],
        422
    );
}

if ($usuarioId > 0 && $senha !== '' && strlen($senha) < 6) {
    responder(
        false,
        'A nova senha deve ter pelo menos 6 caracteres.',
        [],
        422
    );
}

try {
    $stmtNomeUsuario = $pdo->prepare("
        SELECT id
        FROM usuarios
        WHERE nome_usuario = :nome_usuario
          AND id <> :id
        LIMIT 1
    ");
    $stmtNomeUsuario->execute([
        ':nome_usuario' => $nomeUsuario,
        ':id' => $usuarioId
    ]);

    if ($stmtNomeUsuario->fetchColumn()) {
        responder(false, 'Este nome de usuário já está em uso.', [], 409);
    }

    $stmtEmail = $pdo->prepare("
        SELECT id
        FROM usuarios
        WHERE email = :email
          AND id <> :id
        LIMIT 1
    ");

    $stmtEmail->execute([
        ':email' => $email,
        ':id' => $usuarioId
    ]);

    if ($stmtEmail->fetchColumn()) {
        responder(
            false,
            'Já existe um usuário cadastrado com este e-mail.',
            [],
            409
        );
    }

    if ($usuarioId > 0) {
        $stmtUsuario = $pdo->prepare("
            SELECT id
            FROM usuarios
            WHERE id = :id
            LIMIT 1
        ");

        $stmtUsuario->execute([
            ':id' => $usuarioId
        ]);

        if (!$stmtUsuario->fetchColumn()) {
            responder(false, 'Usuário não encontrado.', [], 404);
        }

        if ($senha !== '') {
            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET
                    nome = :nome,
                    nome_usuario = :nome_usuario,
                    email = :email,
                    senha = :senha,
                    ativo = :ativo
                WHERE id = :id
            ");

            $stmt->execute([
                ':nome' => $nome,
                ':nome_usuario' => $nomeUsuario,
                ':email' => $email,
                ':senha' => password_hash($senha, PASSWORD_DEFAULT),
                ':ativo' => $ativo,
                ':id' => $usuarioId
            ]);

        } else {
            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET
                    nome = :nome,
                    nome_usuario = :nome_usuario,
                    email = :email,
                    ativo = :ativo
                WHERE id = :id
            ");

            $stmt->execute([
                ':nome' => $nome,
                ':nome_usuario' => $nomeUsuario,
                ':email' => $email,
                ':ativo' => $ativo,
                ':id' => $usuarioId
            ]);
        }

        responder(true, 'Usuário atualizado com sucesso.', [
            'usuario_id' => $usuarioId
        ]);

    } else {
        /*
         * O campo perfil_id ainda é obrigatório na tabela de usuários.
         * O vínculo com papeis é feito por usuario_papel; neste caso,
         * mantemos um perfil existente para atender à restrição do banco.
         */
        $stmtPerfil = $pdo->query("
            SELECT perfil_id
            FROM usuarios
            WHERE perfil_id IS NOT NULL
            ORDER BY id
            LIMIT 1
        ");

        $perfilId = $stmtPerfil->fetchColumn();

        if ($perfilId === false || (int) $perfilId <= 0) {
            $perfilId = 1;
        }

        $stmt = $pdo->prepare("
            INSERT INTO usuarios (
                nome,
                nome_usuario,
                email,
                senha,
                perfil_id,
                ativo
            )
            VALUES (
                :nome,
                :nome_usuario,
                :email,
                :senha,
                :perfil_id,
                :ativo
            )
        ");

        $stmt->execute([
            ':nome' => $nome,
            ':nome_usuario' => $nomeUsuario,
            ':email' => $email,
            ':senha' => password_hash($senha, PASSWORD_DEFAULT),
            ':perfil_id' => (int) $perfilId,
            ':ativo' => $ativo
        ]);

        responder(true, 'Usuário criado com sucesso.', [
            'usuario_id' => (int) $pdo->lastInsertId()
        ], 201);
    }

} catch (Throwable $erro) {
    error_log(
        'Erro na tela de usuários: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível salvar o usuário.',
        [
            'erro' => $erro->getMessage()
        ],
        500
    );
}
