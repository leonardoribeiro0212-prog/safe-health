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

function gerarCodigo(string $texto): string
{
    $texto = mb_strtoupper(
        trim($texto),
        'UTF-8'
    );

    $mapa = [
        'Á' => 'A',
        'À' => 'A',
        'Ã' => 'A',
        'Â' => 'A',
        'Ä' => 'A',
        'É' => 'E',
        'È' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Í' => 'I',
        'Ì' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ó' => 'O',
        'Ò' => 'O',
        'Õ' => 'O',
        'Ô' => 'O',
        'Ö' => 'O',
        'Ú' => 'U',
        'Ù' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ç' => 'C'
    ];

    $texto = strtr(
        $texto,
        $mapa
    );

    $texto = preg_replace(
        '/[^A-Z0-9]+/',
        '_',
        $texto
    ) ?? '';

    return trim(
        $texto,
        '_'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim(
        (string) ($_GET['acao'] ?? '')
    );

    if ($acao === 'pesquisar') {
        $q = trim(
            (string) ($_GET['q'] ?? '')
        );

        if ($q === '') {
            responder(
                false,
                'Informe o nome ou código para pesquisar.',
                [],
                422
            );
        }

        $stmt = $pdo->prepare(
            "
            SELECT
                id,
                nome,
                codigo,
                ativa,
                criado_em,
                atualizado_em
            FROM especialidades
            WHERE
                nome LIKE :q
                OR codigo LIKE :q
            ORDER BY
                ativa DESC,
                nome
            LIMIT 30
            "
        );

        $stmt->execute([
            ':q' => '%' . $q . '%'
        ]);

        responder(
            true,
            'Pesquisa concluída.',
            [
                'especialidades' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'buscar') {
        $id = filter_var(
            $_GET['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id) {
            responder(
                false,
                'Especialidade inválida.',
                [],
                422
            );
        }

        $stmt = $pdo->prepare(
            "
            SELECT
                id,
                nome,
                codigo,
                ativa
            FROM especialidades
            WHERE id = :id
            LIMIT 1
            "
        );

        $stmt->execute([
            ':id' => $id
        ]);

        $especialidade =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$especialidade) {
            responder(
                false,
                'Especialidade não encontrada.',
                [],
                404
            );
        }

        responder(
            true,
            'Especialidade carregada.',
            [
                'especialidade' =>
                    $especialidade
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
        'Os dados enviados são inválidos.',
        [],
        400
    );
}

$especialidadeId = (int) (
    $entrada['especialidade_id'] ?? 0
);

$nome = trim(
    (string) ($entrada['nome'] ?? '')
);

$codigoInformado = trim(
    (string) ($entrada['codigo'] ?? '')
);

$codigo = gerarCodigo(
    $codigoInformado !== ''
        ? $codigoInformado
        : $nome
);

$ativa = (string) (
    $entrada['ativa'] ?? '1'
);

if ($nome === '') {
    responder(
        false,
        'Informe o nome da especialidade.',
        [],
        422
    );
}

if ($codigo === '') {
    responder(
        false,
        'Não foi possível gerar o código da especialidade.',
        [],
        422
    );
}

if (!in_array(
    $ativa,
    ['0', '1'],
    true
)) {
    responder(
        false,
        'O status informado é inválido.',
        [],
        422
    );
}

try {
    $pdo->beginTransaction();

    $stmtDuplicidade = $pdo->prepare(
        "
        SELECT
            id,
            CASE
                WHEN nome = :nome THEN 'NOME'
                WHEN codigo = :codigo THEN 'CODIGO'
                ELSE 'DADO'
            END AS conflito
        FROM especialidades
        WHERE
            (
                nome = :nome
                OR codigo = :codigo
            )
            AND id <> :id
        LIMIT 1
        "
    );

    $stmtDuplicidade->execute([
        ':nome' => $nome,
        ':codigo' => $codigo,
        ':id' => $especialidadeId
    ]);

    $duplicidade =
        $stmtDuplicidade->fetch(PDO::FETCH_ASSOC);

    if ($duplicidade) {
        $pdo->rollBack();

        responder(
            false,
            $duplicidade['conflito'] === 'NOME'
                ? 'Já existe uma especialidade com esse nome.'
                : 'Já existe uma especialidade com esse código.',
            [],
            409
        );
    }

    if ($especialidadeId > 0) {
        $stmtExistente = $pdo->prepare(
            "
            SELECT id
            FROM especialidades
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
            "
        );

        $stmtExistente->execute([
            ':id' => $especialidadeId
        ]);

        if (!$stmtExistente->fetchColumn()) {
            $pdo->rollBack();

            responder(
                false,
                'Especialidade não encontrada.',
                [],
                404
            );
        }

        $stmtAtualizar = $pdo->prepare(
            "
            UPDATE especialidades
            SET
                nome = :nome,
                codigo = :codigo,
                ativa = :ativa
            WHERE id = :id
            "
        );

        $stmtAtualizar->execute([
            ':nome' => $nome,
            ':codigo' => $codigo,
            ':ativa' => (int) $ativa,
            ':id' => $especialidadeId
        ]);

        $pdo->commit();

        responder(
            true,
            'Especialidade atualizada com sucesso.',
            [
                'especialidade_id' =>
                    $especialidadeId,

                'codigo' => $codigo,

                'modo' => 'ALTERACAO'
            ]
        );
    }

    $stmtInserir = $pdo->prepare(
        "
        INSERT INTO especialidades (
            nome,
            codigo,
            ativa
        )
        VALUES (
            :nome,
            :codigo,
            :ativa
        )
        "
    );

    $stmtInserir->execute([
        ':nome' => $nome,
        ':codigo' => $codigo,
        ':ativa' => (int) $ativa
    ]);

    $novoId = (int) $pdo->lastInsertId();

    $pdo->commit();

    responder(
        true,
        'Especialidade cadastrada com sucesso.',
        [
            'especialidade_id' => $novoId,
            'codigo' => $codigo,
            'modo' => 'CADASTRO'
        ],
        201
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro no cadastro de especialidades: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível salvar a especialidade.',
        [
            'erro' => $erro->getMessage()
        ],
        500
    );
}
