<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permissoes.php';

exigirPermissao(
    $pdo,
    'FATURAMENTO_PROCEDIMENTOS'
);

header('Content-Type: application/json; charset=utf-8');

function responderPreco(
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

function receberJsonPreco(): array
{
    $conteudo = file_get_contents('php://input');
    $dados = json_decode($conteudo ?: '', true);

    if (!is_array($dados)) {
        responderPreco(
            false,
            'Dados inválidos.',
            [],
            400
        );
    }

    return $dados;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderPreco(
        false,
        'Método não permitido.',
        [],
        405
    );
}

$dados = receberJsonPreco();

$convenioId = filter_var(
    $dados['convenio_id'] ?? null,
    FILTER_VALIDATE_INT
);

$tipoItem = strtoupper(
    trim(
        (string) ($dados['tipo_item'] ?? '')
    )
);

$itens = $dados['itens'] ?? [];

$tipos = [
    'MEDICAMENTO' => 'medicamentos',
    'EXAME_LABORATORIAL' => 'exames_laboratoriais',
    'EXAME_IMAGEM' => 'exames_imagem',
    'PROCEDIMENTO' => 'procedimentos'
];

if (!$convenioId || $convenioId <= 0) {
    responderPreco(
        false,
        'Convênio inválido.',
        [],
        422
    );
}

if (!isset($tipos[$tipoItem])) {
    responderPreco(
        false,
        'Tipo de item inválido.',
        [],
        422
    );
}

if (!is_array($itens) || !$itens) {
    responderPreco(
        false,
        'Nenhum preço foi enviado.',
        [],
        422
    );
}

$stmtConvenio = $pdo->prepare("
    SELECT id, nome
    FROM convenios
    WHERE id = :id
      AND ativo = 1
    LIMIT 1
");
$stmtConvenio->execute([
    ':id' => $convenioId
]);

$convenio = $stmtConvenio->fetch(PDO::FETCH_ASSOC);

if (!$convenio) {
    responderPreco(
        false,
        'Convênio não encontrado ou inativo.',
        [],
        404
    );
}

$tabelaCatalogo = $tipos[$tipoItem];

$stmtExisteItem = $pdo->prepare("
    SELECT id
    FROM {$tabelaCatalogo}
    WHERE id = :id
      AND ativo = 1
    LIMIT 1
");

$stmtSalvar = $pdo->prepare("
    INSERT INTO precos_convenio (
        convenio_id,
        tipo_item,
        item_id,
        valor,
        exige_autorizacao,
        ativo,
        criado_em,
        atualizado_em
    )
    VALUES (
        :convenio_id,
        :tipo_item,
        :item_id,
        :valor,
        :exige_autorizacao,
        1,
        NOW(),
        NOW()
    )
    ON DUPLICATE KEY UPDATE
        valor = VALUES(valor),
        exige_autorizacao = VALUES(exige_autorizacao),
        ativo = 1,
        atualizado_em = NOW()
");

$stmtRemover = $pdo->prepare("
    DELETE FROM precos_convenio
    WHERE convenio_id = :convenio_id
      AND tipo_item = :tipo_item
      AND item_id = :item_id
");

$salvos = 0;
$removidos = 0;

try {
    $pdo->beginTransaction();

    foreach ($itens as $item) {
        if (!is_array($item)) {
            continue;
        }

        $itemId = filter_var(
            $item['item_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$itemId || $itemId <= 0) {
            throw new InvalidArgumentException(
                'Um dos itens enviados é inválido.'
            );
        }

        $stmtExisteItem->execute([
            ':id' => $itemId
        ]);

        if (!$stmtExisteItem->fetchColumn()) {
            throw new RuntimeException(
                "O item {$itemId} não existe no catálogo."
            );
        }

        $valorOriginal = trim(
            (string) ($item['valor'] ?? '')
        );

        /*
         * Campo vazio = ainda sem precificação.
         * Removemos eventual preço antigo daquele convênio.
         */
        if ($valorOriginal === '') {
            $stmtRemover->execute([
                ':convenio_id' => $convenioId,
                ':tipo_item' => $tipoItem,
                ':item_id' => $itemId
            ]);

            $removidos += $stmtRemover->rowCount();
            continue;
        }

        $valorNormalizado = str_replace(
            ',',
            '.',
            $valorOriginal
        );

        if (!is_numeric($valorNormalizado)) {
            throw new InvalidArgumentException(
                'Existe um valor de preço inválido.'
            );
        }

        $valor = round(
            (float) $valorNormalizado,
            2
        );

        if ($valor < 0) {
            throw new InvalidArgumentException(
                'O preço não pode ser negativo.'
            );
        }

        $stmtSalvar->execute([
            ':convenio_id' => $convenioId,
            ':tipo_item' => $tipoItem,
            ':item_id' => $itemId,
            ':valor' => $valor,
            ':exige_autorizacao' =>
                !empty($item['exige_autorizacao']) ? 1 : 0
        ]);

        $salvos++;
    }

    $pdo->commit();

    if (
        file_exists(
            __DIR__ . '/../../../includes/auditoria.php'
        )
    ) {
        require_once __DIR__
            . '/../../../includes/auditoria.php';

        registrarAuditoria(
            $pdo,
            'ATUALIZAR',
            'Tabela de preços por convênio atualizada.',
            'Faturamento',
            'Procedimentos / Tabela de Preços',
            'precos_convenio',
            $convenioId,
            null,
            [
                'convenio' => $convenio['nome'],
                'tipo_item' => $tipoItem,
                'precos_salvos' => $salvos,
                'precos_removidos' => $removidos
            ],
            (int) ($_SESSION['usuario_id'] ?? 0)
        );
    }

    responderPreco(
        true,
        'Tabela de preços salva com sucesso.',
        [
            'salvos' => $salvos,
            'removidos' => $removidos
        ]
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro na tabela de preços por convênio: '
        . $erro->getMessage()
    );

    responderPreco(
        false,
        $erro instanceof InvalidArgumentException
            ? $erro->getMessage()
            : 'Não foi possível salvar a tabela de preços.',
        [],
        500
    );
}
