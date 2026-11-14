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
    $acao = trim((string) ($_GET['acao'] ?? ''));

    if ($acao === 'pesquisar') {
        $q = trim((string) ($_GET['q'] ?? ''));

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
                modalidade,
                regiao_anatomica,
                preparo,
                usa_contraste,
                prazo_resultado_horas,
                valor_unitario,
                ativo
            FROM exames_imagem
            WHERE
                nome LIKE :q
                OR codigo LIKE :q
                OR modalidade LIKE :q
                OR regiao_anatomica LIKE :q
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
                'exames' =>
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
                'Exame inválido.',
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
                modalidade,
                regiao_anatomica,
                preparo,
                usa_contraste,
                prazo_resultado_horas,
                valor_unitario,
                ativo
            FROM exames_imagem
            WHERE id = :id
            LIMIT 1
            "
        );

        $stmt->execute([
            ':id' => $id
        ]);

        $exame = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exame) {
            responder(
                false,
                'Exame não encontrado.',
                [],
                404
            );
        }

        responder(
            true,
            'Exame carregado.',
            [
                'exame' => $exame
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

$exameId = (int) ($entrada['exame_id'] ?? 0);

$nome = trim((string) ($entrada['nome'] ?? ''));

$codigoInformado =
    trim((string) ($entrada['codigo'] ?? ''));

$codigo = gerarCodigo(
    $codigoInformado !== ''
        ? $codigoInformado
        : $nome
);

$modalidade =
    strtoupper(trim((string) ($entrada['modalidade'] ?? '')));

$regiaoAnatomica =
    trim((string) ($entrada['regiao_anatomica'] ?? ''));

$preparo =
    trim((string) ($entrada['preparo'] ?? ''));

$usaContraste =
    (string) ($entrada['usa_contraste'] ?? '0');

$prazoResultado = filter_var(
    $entrada['prazo_resultado_horas'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $prazoResultado === false ||
    $prazoResultado === null
) {
    $prazoResultado = null;
}

$valorUnitario = str_replace(
    ',',
    '.',
    trim((string) ($entrada['valor_unitario'] ?? '0'))
);

$ativo =
    (string) ($entrada['ativo'] ?? '1');

if ($nome === '') {
    responder(
        false,
        'Informe o nome do exame.',
        [],
        422
    );
}

if ($codigo === '') {
    responder(
        false,
        'Não foi possível gerar o código do exame.',
        [],
        422
    );
}

if (!in_array(
    $usaContraste,
    ['0', '1'],
    true
)) {
    responder(
        false,
        'O campo de contraste é inválido.',
        [],
        422
    );
}

if (
    $prazoResultado !== null &&
    $prazoResultado < 0
) {
    responder(
        false,
        'O prazo do resultado não pode ser negativo.',
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
    $ativo,
    ['0', '1'],
    true
)) {
    responder(
        false,
        'O status é inválido.',
        [],
        422
    );
}

try {
    $pdo->beginTransaction();

    $stmtCodigo = $pdo->prepare(
        "
        SELECT id
        FROM exames_imagem
        WHERE codigo = :codigo
          AND id <> :id
        LIMIT 1
        "
    );

    $stmtCodigo->execute([
        ':codigo' => $codigo,
        ':id' => $exameId
    ]);

    if ($stmtCodigo->fetchColumn()) {
        $pdo->rollBack();

        responder(
            false,
            'Já existe um exame com esse código.',
            [],
            409
        );
    }

    $parametros = [
        ':nome' => $nome,
        ':codigo' => $codigo,
        ':modalidade' =>
            $modalidade !== ''
                ? $modalidade
                : null,
        ':regiao_anatomica' =>
            $regiaoAnatomica !== ''
                ? $regiaoAnatomica
                : null,
        ':preparo' =>
            $preparo !== ''
                ? $preparo
                : null,
        ':usa_contraste' =>
            (int) $usaContraste,
        ':prazo_resultado_horas' =>
            $prazoResultado,
        ':valor_unitario' =>
            round(
                (float) $valorUnitario,
                2
            ),
        ':ativo' =>
            (int) $ativo
    ];

    if ($exameId > 0) {
        $stmtExistente = $pdo->prepare(
            "
            SELECT id
            FROM exames_imagem
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
            "
        );

        $stmtExistente->execute([
            ':id' => $exameId
        ]);

        if (!$stmtExistente->fetchColumn()) {
            $pdo->rollBack();

            responder(
                false,
                'Exame não encontrado.',
                [],
                404
            );
        }

        $stmtAtualizar = $pdo->prepare(
            "
            UPDATE exames_imagem
            SET
                nome = :nome,
                codigo = :codigo,
                modalidade = :modalidade,
                regiao_anatomica = :regiao_anatomica,
                preparo = :preparo,
                usa_contraste = :usa_contraste,
                prazo_resultado_horas = :prazo_resultado_horas,
                valor_unitario = :valor_unitario,
                ativo = :ativo
            WHERE id = :id
            "
        );

        $stmtAtualizar->execute(
            $parametros + [
                ':id' => $exameId
            ]
        );

        $pdo->commit();

        responder(
            true,
            'Exame de imagem atualizado com sucesso.',
            [
                'exame_id' => $exameId,
                'codigo' => $codigo,
                'modo' => 'ALTERACAO'
            ]
        );
    }

    $stmtInserir = $pdo->prepare(
        "
        INSERT INTO exames_imagem (
            nome,
            codigo,
            modalidade,
            regiao_anatomica,
            preparo,
            usa_contraste,
            prazo_resultado_horas,
            valor_unitario,
            ativo
        )
        VALUES (
            :nome,
            :codigo,
            :modalidade,
            :regiao_anatomica,
            :preparo,
            :usa_contraste,
            :prazo_resultado_horas,
            :valor_unitario,
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
        'Exame de imagem cadastrado com sucesso.',
        [
            'exame_id' => $novoId,
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
        'Erro no cadastro de exames de imagem: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível salvar o exame de imagem.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
