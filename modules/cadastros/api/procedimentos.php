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

function gerarCodigo(string $nome): string
{
    $texto = mb_strtoupper(
        trim($nome),
        'UTF-8'
    );

    $mapa = [
        'Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','Ä'=>'A',
        'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
        'Ó'=>'O','Ò'=>'O','Õ'=>'O','Ô'=>'O','Ö'=>'O',
        'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
        'Ç'=>'C'
    ];

    $texto = strtr($texto, $mapa);

    $texto = preg_replace(
        '/[^A-Z0-9]+/',
        '_',
        $texto
    ) ?? '';

    return trim($texto, '_');
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
                'Informe um termo para pesquisar.',
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
                categoria,
                descricao,
                duracao_estimada_minutos,
                valor_unitario,
                exige_autorizacao,
                ativo
            FROM procedimentos
            WHERE
                nome LIKE :q
                OR codigo LIKE :q
                OR categoria LIKE :q
            ORDER BY
                ativo DESC,
                nome
            LIMIT 40
            "
        );

        $stmt->execute([
            ':q' => '%' . $q . '%'
        ]);

        responder(
            true,
            'Pesquisa concluída.',
            [
                'procedimentos' =>
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
                'Procedimento inválido.',
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
                categoria,
                descricao,
                duracao_estimada_minutos,
                valor_unitario,
                exige_autorizacao,
                ativo
            FROM procedimentos
            WHERE id = :id
            LIMIT 1
            "
        );

        $stmt->execute([
            ':id' => $id
        ]);

        $procedimento =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$procedimento) {
            responder(
                false,
                'Procedimento não encontrado.',
                [],
                404
            );
        }

        responder(
            true,
            'Procedimento carregado.',
            [
                'procedimento' => $procedimento
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

$procedimentoId =
    (int) ($entrada['procedimento_id'] ?? 0);

$nome =
    trim((string) ($entrada['nome'] ?? ''));

$codigoInformado =
    trim((string) ($entrada['codigo'] ?? ''));

$codigo = gerarCodigo(
    $codigoInformado !== ''
        ? $codigoInformado
        : $nome
);

$categoria =
    strtoupper(trim((string) ($entrada['categoria'] ?? '')));

$descricao =
    trim((string) ($entrada['descricao'] ?? ''));

$duracao = filter_var(
    $entrada['duracao_estimada_minutos'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $duracao === false ||
    $duracao === null
) {
    $duracao = null;
}

$valorUnitario = str_replace(
    ',',
    '.',
    trim((string) ($entrada['valor_unitario'] ?? '0'))
);

$exigeAutorizacao =
    (string) ($entrada['exige_autorizacao'] ?? '0');

$ativo =
    (string) ($entrada['ativo'] ?? '1');

if ($nome === '') {
    responder(
        false,
        'Informe o nome do procedimento.',
        [],
        422
    );
}

if ($codigo === '') {
    responder(
        false,
        'Não foi possível gerar o código do procedimento.',
        [],
        422
    );
}

if (
    $duracao !== null &&
    $duracao < 0
) {
    responder(
        false,
        'A duração estimada não pode ser negativa.',
        [],
        422
    );
}

if (
    $valorUnitario === '' ||
    !is_numeric($valorUnitario) ||
    (float) $valorUnitario < 0
) {
    responder(
        false,
        'Informe um valor unitário válido.',
        [],
        422
    );
}

if (!in_array(
    $exigeAutorizacao,
    ['0', '1'],
    true
)) {
    responder(
        false,
        'O campo de autorização é inválido.',
        [],
        422
    );
}

if (!in_array(
    $ativo,
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

    $stmtCodigo = $pdo->prepare(
        "
        SELECT id
        FROM procedimentos
        WHERE codigo = :codigo
          AND id <> :id
        LIMIT 1
        "
    );

    $stmtCodigo->execute([
        ':codigo' => $codigo,
        ':id' => $procedimentoId
    ]);

    if ($stmtCodigo->fetchColumn()) {
        $pdo->rollBack();

        responder(
            false,
            'Já existe um procedimento com esse código.',
            [],
            409
        );
    }

    $parametros = [
        ':nome' => $nome,
        ':codigo' => $codigo,

        ':categoria' =>
            $categoria !== ''
                ? $categoria
                : null,

        ':descricao' =>
            $descricao !== ''
                ? $descricao
                : null,

        ':duracao_estimada_minutos' =>
            $duracao,

        ':valor_unitario' =>
            round(
                (float) $valorUnitario,
                2
            ),

        ':exige_autorizacao' =>
            (int) $exigeAutorizacao,

        ':ativo' =>
            (int) $ativo
    ];

    if ($procedimentoId > 0) {
        $stmtExistente = $pdo->prepare(
            "
            SELECT id
            FROM procedimentos
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
            "
        );

        $stmtExistente->execute([
            ':id' => $procedimentoId
        ]);

        if (!$stmtExistente->fetchColumn()) {
            $pdo->rollBack();

            responder(
                false,
                'Procedimento não encontrado.',
                [],
                404
            );
        }

        $stmtAtualizar = $pdo->prepare(
            "
            UPDATE procedimentos
            SET
                nome = :nome,
                codigo = :codigo,
                categoria = :categoria,
                descricao = :descricao,
                duracao_estimada_minutos = :duracao_estimada_minutos,
                valor_unitario = :valor_unitario,
                exige_autorizacao = :exige_autorizacao,
                ativo = :ativo
            WHERE id = :id
            "
        );

        $stmtAtualizar->execute(
            $parametros + [
                ':id' => $procedimentoId
            ]
        );

        $pdo->commit();

        responder(
            true,
            'Procedimento atualizado com sucesso.',
            [
                'procedimento_id' =>
                    $procedimentoId,

                'codigo' =>
                    $codigo,

                'modo' =>
                    'ALTERACAO'
            ]
        );
    }

    $stmtInserir = $pdo->prepare(
        "
        INSERT INTO procedimentos (
            nome,
            codigo,
            categoria,
            descricao,
            duracao_estimada_minutos,
            valor_unitario,
            exige_autorizacao,
            ativo
        )
        VALUES (
            :nome,
            :codigo,
            :categoria,
            :descricao,
            :duracao_estimada_minutos,
            :valor_unitario,
            :exige_autorizacao,
            :ativo
        )
        "
    );

    $stmtInserir->execute($parametros);

    $novoId =
        (int) $pdo->lastInsertId();

    $pdo->commit();

    responder(
        true,
        'Procedimento cadastrado com sucesso.',
        [
            'procedimento_id' =>
                $novoId,

            'codigo' =>
                $codigo,

            'modo' =>
                'CADASTRO'
        ],
        201
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro no cadastro de procedimentos: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível salvar o procedimento.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
