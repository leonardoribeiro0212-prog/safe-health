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

function somenteNumeros(string $valor): string
{
    return preg_replace(
        '/\D/',
        '',
        $valor
    ) ?? '';
}

function gerarCodigo(string $texto): string
{
    $texto = mb_strtoupper(
        trim($texto),
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
                'Informe um termo para pesquisar.',
                [],
                422
            );
        }

        $qNumerico =
            somenteNumeros($q);

        $stmt = $pdo->prepare(
            "
            SELECT
                id,
                nome,
                codigo,
                cnpj,
                registro_ans,
                telefone,
                email,
                ativo
            FROM convenios
            WHERE
                nome LIKE :q
                OR codigo LIKE :q
                OR registro_ans LIKE :q
                OR cnpj LIKE :cnpj
            ORDER BY
                ativo DESC,
                nome
            LIMIT 30
            "
        );

        $stmt->execute([
            ':q' => '%' . $q . '%',
            ':cnpj' => '%' . $qNumerico . '%'
        ]);

        responder(
            true,
            'Pesquisa concluída.',
            [
                'convenios' =>
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
                'Convênio inválido.',
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
                cnpj,
                registro_ans,
                telefone,
                email,
                ativo
            FROM convenios
            WHERE id = :id
            LIMIT 1
            "
        );

        $stmt->execute([
            ':id' => $id
        ]);

        $convenio =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$convenio) {
            responder(
                false,
                'Convênio não encontrado.',
                [],
                404
            );
        }

        responder(
            true,
            'Convênio carregado.',
            [
                'convenio' => $convenio
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

$convenioId = (int) (
    $entrada['convenio_id'] ?? 0
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

$cnpj = somenteNumeros(
    (string) ($entrada['cnpj'] ?? '')
);

$registroAns = trim(
    (string) ($entrada['registro_ans'] ?? '')
);

$telefone = trim(
    (string) ($entrada['telefone'] ?? '')
);

$email = trim(
    (string) ($entrada['email'] ?? '')
);

$ativo = (string) (
    $entrada['ativo'] ?? '1'
);

if ($nome === '') {
    responder(
        false,
        'Informe o nome do convênio.',
        [],
        422
    );
}

if ($codigo === '') {
    responder(
        false,
        'Não foi possível gerar o código do convênio.',
        [],
        422
    );
}

if (
    $cnpj !== '' &&
    strlen($cnpj) !== 14
) {
    responder(
        false,
        'Informe um CNPJ com 14 números.',
        [],
        422
    );
}

if (
    $email !== '' &&
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {
    responder(
        false,
        'Informe um e-mail válido.',
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

    $stmtDuplicidade = $pdo->prepare(
        "
        SELECT
            id,
            CASE
                WHEN nome = :nome
                    THEN 'NOME'
                WHEN codigo = :codigo
                    THEN 'CODIGO'
                WHEN :cnpj_verifica <> ''
                     AND cnpj = :cnpj_compara
                    THEN 'CNPJ'
                WHEN :ans_verifica <> ''
                     AND registro_ans = :ans_compara
                    THEN 'ANS'
                ELSE 'DADO'
            END AS conflito

        FROM convenios

        WHERE id <> :id
          AND (
                nome = :nome
                OR codigo = :codigo
                OR (
                    :cnpj_where <> ''
                    AND cnpj = :cnpj_where_compara
                )
                OR (
                    :ans_where <> ''
                    AND registro_ans = :ans_where_compara
                )
          )

        LIMIT 1
        "
    );

    $stmtDuplicidade->execute([
        ':nome' => $nome,
        ':codigo' => $codigo,

        ':cnpj_verifica' => $cnpj,
        ':cnpj_compara' => $cnpj,

        ':ans_verifica' => $registroAns,
        ':ans_compara' => $registroAns,

        ':id' => $convenioId,

        ':cnpj_where' => $cnpj,
        ':cnpj_where_compara' => $cnpj,

        ':ans_where' => $registroAns,
        ':ans_where_compara' => $registroAns
    ]);

    $duplicidade =
        $stmtDuplicidade->fetch(PDO::FETCH_ASSOC);

    if ($duplicidade) {
        $pdo->rollBack();

        $mensagem = match (
            $duplicidade['conflito']
        ) {
            'NOME' =>
                'Já existe um convênio com esse nome.',

            'CODIGO' =>
                'Já existe um convênio com esse código.',

            'CNPJ' =>
                'Já existe um convênio com esse CNPJ.',

            'ANS' =>
                'Já existe um convênio com esse registro ANS.',

            default =>
                'Já existe um convênio com esses dados.'
        };

        responder(
            false,
            $mensagem,
            [],
            409
        );
    }

    if ($convenioId > 0) {
        $stmtExistente = $pdo->prepare(
            "
            SELECT id
            FROM convenios
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
            "
        );

        $stmtExistente->execute([
            ':id' => $convenioId
        ]);

        if (!$stmtExistente->fetchColumn()) {
            $pdo->rollBack();

            responder(
                false,
                'Convênio não encontrado.',
                [],
                404
            );
        }

        $stmtAtualizar = $pdo->prepare(
            "
            UPDATE convenios
            SET
                nome = :nome,
                codigo = :codigo,
                cnpj = :cnpj,
                registro_ans = :registro_ans,
                telefone = :telefone,
                email = :email,
                ativo = :ativo
            WHERE id = :id
            "
        );

        $stmtAtualizar->execute([
            ':nome' => $nome,
            ':codigo' => $codigo,
            ':cnpj' =>
                $cnpj !== ''
                    ? $cnpj
                    : null,

            ':registro_ans' =>
                $registroAns !== ''
                    ? $registroAns
                    : null,

            ':telefone' =>
                $telefone !== ''
                    ? $telefone
                    : null,

            ':email' =>
                $email !== ''
                    ? $email
                    : null,

            ':ativo' =>
                (int) $ativo,

            ':id' =>
                $convenioId
        ]);

        $pdo->commit();

        responder(
            true,
            'Convênio atualizado com sucesso.',
            [
                'convenio_id' =>
                    $convenioId,

                'codigo' =>
                    $codigo,

                'modo' =>
                    'ALTERACAO'
            ]
        );
    }

    $stmtInserir = $pdo->prepare(
        "
        INSERT INTO convenios (
            nome,
            codigo,
            cnpj,
            registro_ans,
            telefone,
            email,
            ativo
        )
        VALUES (
            :nome,
            :codigo,
            :cnpj,
            :registro_ans,
            :telefone,
            :email,
            :ativo
        )
        "
    );

    $stmtInserir->execute([
        ':nome' => $nome,
        ':codigo' => $codigo,

        ':cnpj' =>
            $cnpj !== ''
                ? $cnpj
                : null,

        ':registro_ans' =>
            $registroAns !== ''
                ? $registroAns
                : null,

        ':telefone' =>
            $telefone !== ''
                ? $telefone
                : null,

        ':email' =>
            $email !== ''
                ? $email
                : null,

        ':ativo' =>
            (int) $ativo
    ]);

    $novoId = (int) $pdo->lastInsertId();

    $pdo->commit();

    responder(
        true,
        'Convênio cadastrado com sucesso.',
        [
            'convenio_id' =>
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
        'Erro no cadastro de convênios: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível salvar o convênio.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
