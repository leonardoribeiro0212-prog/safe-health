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

        $stmt = $pdo->prepare(
            "
            SELECT
                id,
                nome,
                codigo,
                categoria,
                unidade_medida,
                fabricante,
                valor_unitario,
                estoque_minimo,
                ativo
            FROM materiais_hospitalares
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
                'materiais' =>
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
                'Material inválido.',
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
                unidade_medida,
                fabricante,
                valor_unitario,
                estoque_minimo,
                ativo
            FROM materiais_hospitalares
            WHERE id = :id
            LIMIT 1
            "
        );

        $stmt->execute([
            ':id' => $id
        ]);

        $material =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$material) {
            responder(
                false,
                'Material não encontrado.',
                [],
                404
            );
        }

        responder(
            true,
            'Material carregado.',
            [
                'material' => $material
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

$materialId = (int) (
    $entrada['material_id'] ?? 0
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

$categoria = trim(
    (string) ($entrada['categoria'] ?? '')
);

$unidadeMedida = strtoupper(
    trim((string) ($entrada['unidade_medida'] ?? ''))
);

$fabricante = trim(
    (string) ($entrada['fabricante'] ?? '')
);

$valorUnitario = str_replace(
    ',',
    '.',
    trim((string) ($entrada['valor_unitario'] ?? '0'))
);

$estoqueMinimo = str_replace(
    ',',
    '.',
    trim((string) ($entrada['estoque_minimo'] ?? '0'))
);

$ativo = (string) (
    $entrada['ativo'] ?? '1'
);

if ($nome === '') {
    responder(
        false,
        'Informe o nome do material.',
        [],
        422
    );
}

if ($codigo === '') {
    responder(
        false,
        'Não foi possível gerar o código do material.',
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

if (
    $estoqueMinimo === '' ||
    !is_numeric($estoqueMinimo) ||
    (float) $estoqueMinimo < 0
) {
    responder(
        false,
        'Informe um estoque mínimo válido.',
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
        FROM materiais_hospitalares
        WHERE codigo = :codigo
          AND id <> :id
        LIMIT 1
        "
    );

    $stmtCodigo->execute([
        ':codigo' => $codigo,
        ':id' => $materialId
    ]);

    if ($stmtCodigo->fetchColumn()) {
        $pdo->rollBack();

        responder(
            false,
            'Já existe um material com esse código.',
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

        ':unidade_medida' =>
            $unidadeMedida !== ''
                ? $unidadeMedida
                : null,

        ':fabricante' =>
            $fabricante !== ''
                ? $fabricante
                : null,

        ':valor_unitario' =>
            round(
                (float) $valorUnitario,
                2
            ),

        ':estoque_minimo' =>
            round(
                (float) $estoqueMinimo,
                3
            ),

        ':ativo' =>
            (int) $ativo
    ];

    if ($materialId > 0) {
        $stmtExistente = $pdo->prepare(
            "
            SELECT id
            FROM materiais_hospitalares
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
            "
        );

        $stmtExistente->execute([
            ':id' => $materialId
        ]);

        if (!$stmtExistente->fetchColumn()) {
            $pdo->rollBack();

            responder(
                false,
                'Material não encontrado.',
                [],
                404
            );
        }

        $stmtAtualizar = $pdo->prepare(
            "
            UPDATE materiais_hospitalares
            SET
                nome = :nome,
                codigo = :codigo,
                categoria = :categoria,
                unidade_medida = :unidade_medida,
                fabricante = :fabricante,
                valor_unitario = :valor_unitario,
                estoque_minimo = :estoque_minimo,
                ativo = :ativo
            WHERE id = :id
            "
        );

        $stmtAtualizar->execute(
            $parametros + [
                ':id' => $materialId
            ]
        );

        $pdo->commit();

        responder(
            true,
            'Material atualizado com sucesso.',
            [
                'material_id' =>
                    $materialId,

                'codigo' =>
                    $codigo,

                'modo' =>
                    'ALTERACAO'
            ]
        );
    }

    $stmtInserir = $pdo->prepare(
        "
        INSERT INTO materiais_hospitalares (
            nome,
            codigo,
            categoria,
            unidade_medida,
            fabricante,
            valor_unitario,
            estoque_minimo,
            ativo
        )
        VALUES (
            :nome,
            :codigo,
            :categoria,
            :unidade_medida,
            :fabricante,
            :valor_unitario,
            :estoque_minimo,
            :ativo
        )
        "
    );

    $stmtInserir->execute(
        $parametros
    );

    $novoId =
        (int) $pdo->lastInsertId();

    $pdo->commit();

    responder(
        true,
        'Material cadastrado com sucesso.',
        [
            'material_id' =>
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
        'Erro no cadastro de materiais hospitalares: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível salvar o material.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
