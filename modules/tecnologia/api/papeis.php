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
                p.id,
                p.nome,
                p.descricao,
                p.ativo,
                p.criado_em,
                p.atualizado_em,

                COUNT(
                    DISTINCT up.usuario_id
                ) AS total_usuarios,

                COUNT(
                    DISTINCT pt.tela_id
                ) AS total_telas

            FROM papeis p

            LEFT JOIN usuario_papel up
                ON up.papel_id = p.id

            LEFT JOIN papel_tela pt
                ON pt.papel_id = p.id

            WHERE 1 = 1
        ";

        $params = [];

        if ($q !== '') {
            $sql .= "
                AND (
                    p.nome LIKE :q
                    OR p.descricao LIKE :q
                )
            ";

            $params[':q'] =
                '%' . $q . '%';
        }

        $sql .= "
            GROUP BY
                p.id,
                p.nome,
                p.descricao,
                p.ativo,
                p.criado_em,
                p.atualizado_em

            ORDER BY
                p.nome,
                p.id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        responder(
            true,
            'Papéis carregados.',
            [
                'papeis' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'detalhes') {
        $id = filter_var(
            $_GET['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id) {
            responder(
                false,
                'Papel inválido.',
                [],
                422
            );
        }

        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.nome,
                p.descricao,
                p.ativo,
                p.criado_em,
                p.atualizado_em,

                (
                    SELECT COUNT(*)
                    FROM usuario_papel up
                    WHERE up.papel_id = p.id
                ) AS total_usuarios,

                (
                    SELECT COUNT(*)
                    FROM papel_tela pt
                    WHERE pt.papel_id = p.id
                ) AS total_telas

            FROM papeis p

            WHERE p.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id
        ]);

        $papel =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$papel) {
            responder(
                false,
                'Papel não encontrado.',
                [],
                404
            );
        }

        responder(
            true,
            'Papel carregado.',
            [
                'papel' => $papel
            ]
        );
    }

    responder(
        false,
        'Ação inválida.',
        [],
        400
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(
        false,
        'Método não permitido.',
        [],
        405
    );
}

$entrada = json_decode(
    file_get_contents('php://input') ?: '',
    true
);

if (!is_array($entrada)) {
    responder(
        false,
        'Dados inválidos.',
        [],
        400
    );
}

$papelId =
    (int) ($entrada['papel_id'] ?? 0);

$nome =
    trim((string) ($entrada['nome'] ?? ''));

$descricao =
    trim((string) ($entrada['descricao'] ?? ''));

$ativo =
    (int) ($entrada['ativo'] ?? 1);

if ($nome === '') {
    responder(
        false,
        'Informe o nome do papel.',
        [],
        422
    );
}

if (mb_strlen($nome) > 100) {
    responder(
        false,
        'O nome do papel deve ter no máximo 100 caracteres.',
        [],
        422
    );
}

if (mb_strlen($descricao) > 255) {
    responder(
        false,
        'A descrição deve ter no máximo 255 caracteres.',
        [],
        422
    );
}

if (!in_array(
    $ativo,
    [0, 1],
    true
)) {
    responder(
        false,
        'Status inválido.',
        [],
        422
    );
}

try {
    $stmtDuplicado = $pdo->prepare("
        SELECT id
        FROM papeis
        WHERE nome = :nome
          AND id <> :id
        LIMIT 1
    ");

    $stmtDuplicado->execute([
        ':nome' => $nome,
        ':id' => $papelId
    ]);

    if ($stmtDuplicado->fetchColumn()) {
        responder(
            false,
            'Já existe um papel cadastrado com este nome.',
            [],
            409
        );
    }

    if ($papelId > 0) {
        $stmtExiste = $pdo->prepare("
            SELECT id
            FROM papeis
            WHERE id = :id
            LIMIT 1
        ");

        $stmtExiste->execute([
            ':id' => $papelId
        ]);

        if (!$stmtExiste->fetchColumn()) {
            responder(
                false,
                'Papel não encontrado.',
                [],
                404
            );
        }

        $stmt = $pdo->prepare("
            UPDATE papeis
            SET
                nome = :nome,
                descricao = :descricao,
                ativo = :ativo
            WHERE id = :id
        ");

        $stmt->execute([
            ':nome' => $nome,
            ':descricao' =>
                $descricao !== ''
                    ? $descricao
                    : null,
            ':ativo' => $ativo,
            ':id' => $papelId
        ]);

        responder(
            true,
            'Papel atualizado com sucesso.',
            [
                'papel_id' =>
                    $papelId
            ]
        );

    } else {
        $stmt = $pdo->prepare("
            INSERT INTO papeis (
                nome,
                descricao,
                ativo
            )
            VALUES (
                :nome,
                :descricao,
                :ativo
            )
        ");

        $stmt->execute([
            ':nome' => $nome,
            ':descricao' =>
                $descricao !== ''
                    ? $descricao
                    : null,
            ':ativo' => $ativo
        ]);

        responder(
            true,
            'Papel criado com sucesso.',
            [
                'papel_id' =>
                    (int) $pdo->lastInsertId()
            ],
            201
        );
    }

} catch (Throwable $erro) {
    error_log(
        'Erro na tela de papéis: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível salvar o papel.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
