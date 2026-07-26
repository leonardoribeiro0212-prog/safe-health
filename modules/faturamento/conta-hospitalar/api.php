<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permissoes.php';

/*
|--------------------------------------------------------------------------
| Controle de acesso da API
|--------------------------------------------------------------------------
|
| A proteção da interface não é suficiente.
| A API também precisa verificar a permissão do usuário.
|
*/

exigirPermissao(
    $pdo,
    'FATURAMENTO_CONTAS_HOSPITALARES'
);

function responderJson(
    bool $sucesso,
    string $mensagem,
    array $dados = [],
    int $statusHttp = 200
): never {
    http_response_code($statusHttp);

    header('Content-Type: application/json; charset=utf-8');

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

function lerJson(): array
{
    $conteudo = file_get_contents('php://input');

    if (!$conteudo) {
        responderJson(
            false,
            'Nenhum dado foi enviado.',
            [],
            400
        );
    }

    $dados = json_decode(
        $conteudo,
        true
    );

    if (!is_array($dados)) {
        responderJson(
            false,
            'JSON inválido.',
            [],
            400
        );
    }

    return $dados;
}

function validarId(
    mixed $valor,
    string $nomeCampo
): int {
    $id = filter_var(
        $valor,
        FILTER_VALIDATE_INT
    );

    if (!$id || $id <= 0) {
        responderJson(
            false,
            "O campo {$nomeCampo} é inválido.",
            [],
            422
        );
    }

    return $id;
}

function validarDecimalPositivo(
    mixed $valor,
    string $nomeCampo
): float {
    $valor = str_replace(
        ',',
        '.',
        trim((string) $valor)
    );

    if (!is_numeric($valor)) {
        responderJson(
            false,
            "O campo {$nomeCampo} é inválido.",
            [],
            422
        );
    }

    $numero = round(
        (float) $valor,
        2
    );

    if ($numero <= 0) {
        responderJson(
            false,
            "O campo {$nomeCampo} deve ser maior que zero.",
            [],
            422
        );
    }

    return $numero;
}

function validarDecimalNaoNegativo(
    mixed $valor,
    string $nomeCampo
): float {
    $valor = str_replace(
        ',',
        '.',
        trim((string) $valor)
    );

    if (!is_numeric($valor)) {
        responderJson(
            false,
            "O campo {$nomeCampo} é inválido.",
            [],
            422
        );
    }

    $numero = round(
        (float) $valor,
        2
    );

    if ($numero < 0) {
        responderJson(
            false,
            "O campo {$nomeCampo} não pode ser negativo.",
            [],
            422
        );
    }

    return $numero;
}

function bloquearConta(
    PDO $pdo,
    int $contaId
): array {
    $stmt = $pdo->prepare(
        "
        SELECT
            id,
            atendimento_id,
            paciente_id,
            valor_bruto,
            valor_desconto,
            valor_coparticipacao,
            valor_liquido,
            status

        FROM contas_hospitalares

        WHERE id = :conta_id

        LIMIT 1

        FOR UPDATE
        "
    );

    $stmt->execute([
        ':conta_id' => $contaId
    ]);

    $conta = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$conta) {
        throw new RuntimeException(
            'Conta hospitalar não encontrada.'
        );
    }

    return $conta;
}

function recalcularConta(
    PDO $pdo,
    int $contaId
): array {
    $stmt = $pdo->prepare(
        "
        SELECT
            COALESCE(
                SUM(valor_total),
                0
            ) AS valor_bruto,

            COALESCE(
                SUM(
                    CASE
                        WHEN status_item = 'GLOSADO'
                        THEN valor_total
                        ELSE 0
                    END
                ),
                0
            ) AS valor_desconto

        FROM itens_conta_hospitalar

        WHERE conta_id = :conta_id
        "
    );

    $stmt->execute([
        ':conta_id' => $contaId
    ]);

    $resultado = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    $stmtCoparticipacao = $pdo->prepare(
        "
        SELECT
            valor_coparticipacao

        FROM contas_hospitalares

        WHERE id = :conta_id
        "
    );

    $stmtCoparticipacao->execute([
        ':conta_id' => $contaId
    ]);

    $valorCoparticipacao = round(
        (float) $stmtCoparticipacao->fetchColumn(),
        2
    );

    $valorBruto = round(
        (float) ($resultado['valor_bruto'] ?? 0),
        2
    );

    $valorDesconto = round(
        (float) ($resultado['valor_desconto'] ?? 0),
        2
    );

    $valorLiquido = max(
        0,
        round(
            $valorBruto -
            $valorDesconto -
            $valorCoparticipacao,
            2
        )
    );

    $stmtAtualizar = $pdo->prepare(
        "
        UPDATE contas_hospitalares

        SET
            valor_bruto = :valor_bruto,
            valor_desconto = :valor_desconto,
            valor_liquido = :valor_liquido

        WHERE id = :conta_id
        "
    );

    $stmtAtualizar->execute([
        ':valor_bruto' => $valorBruto,
        ':valor_desconto' => $valorDesconto,
        ':valor_liquido' => $valorLiquido,
        ':conta_id' => $contaId
    ]);

    return [
        'valor_bruto' => $valorBruto,
        'valor_desconto' => $valorDesconto,
        'valor_coparticipacao' => $valorCoparticipacao,
        'valor_liquido' => $valorLiquido
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(
        false,
        'Método não permitido.',
        [],
        405
    );
}

$dados = lerJson();

$acao = trim(
    (string) ($dados['acao'] ?? '')
);

$contaId = validarId(
    $dados['conta_id'] ?? null,
    'conta'
);

try {
    switch ($acao) {
        case 'adicionar_item':

            $tiposPermitidos = [
                'DIARIA',
                'TAXA',
                'PROCEDIMENTO',
                'CIRURGIA',
                'MEDICAMENTO',
                'MATERIAL',
                'EXAME_LABORATORIAL',
                'EXAME_IMAGEM',
                'HONORARIO',
                'OUTRO'
            ];

            $tipoItem = strtoupper(
                trim((string) ($dados['tipo_item'] ?? ''))
            );

            if (!in_array(
                $tipoItem,
                $tiposPermitidos,
                true
            )) {
                responderJson(
                    false,
                    'Categoria inválida.',
                    [],
                    422
                );
            }

            $descricao = trim(
                (string) ($dados['descricao'] ?? '')
            );

            if ($descricao === '') {
                responderJson(
                    false,
                    'A descrição é obrigatória.',
                    [],
                    422
                );
            }

            if (mb_strlen($descricao) > 255) {
                responderJson(
                    false,
                    'A descrição possui mais de 255 caracteres.',
                    [],
                    422
                );
            }

            $itemId = null;

            if (
                isset($dados['item_id']) &&
                $dados['item_id'] !== ''
            ) {
                $itemId = validarId(
                    $dados['item_id'],
                    'código do item'
                );
            }

            $quantidade = validarDecimalPositivo(
                $dados['quantidade'] ?? null,
                'quantidade'
            );

            $valorUnitario = validarDecimalNaoNegativo(
                $dados['valor_unitario'] ?? null,
                'valor unitário'
            );

            $valorTotal = round(
                $quantidade * $valorUnitario,
                2
            );

            $pdo->beginTransaction();

            $conta = bloquearConta(
                $pdo,
                $contaId
            );

            if (in_array(
                $conta['status'],
                ['FECHADA', 'FATURADA'],
                true
            )) {
                throw new DomainException(
                    'A conta está fechada e não aceita novos lançamentos.'
                );
            }

            $stmtInserir = $pdo->prepare(
                "
                INSERT INTO itens_conta_hospitalar (
                    conta_id,
                    tipo_item,
                    item_id,
                    descricao,
                    quantidade,
                    valor_unitario,
                    valor_total,
                    status_item,
                    criado_em
                )
                VALUES (
                    :conta_id,
                    :tipo_item,
                    :item_id,
                    :descricao,
                    :quantidade,
                    :valor_unitario,
                    :valor_total,
                    'PENDENTE',
                    NOW()
                )
                "
            );

            $stmtInserir->execute([
                ':conta_id' => $contaId,
                ':tipo_item' => $tipoItem,
                ':item_id' => $itemId,
                ':descricao' => $descricao,
                ':quantidade' => $quantidade,
                ':valor_unitario' => $valorUnitario,
                ':valor_total' => $valorTotal
            ]);

            $totais = recalcularConta(
                $pdo,
                $contaId
            );

            $pdo->commit();

            responderJson(
                true,
                'Item adicionado com sucesso.',
                [
                    'item_id' => (int) $pdo->lastInsertId(),
                    'totais' => $totais
                ],
                201
            );

        case 'alterar_status_item':

            $itemContaId = validarId(
                $dados['item_conta_id'] ?? null,
                'item'
            );

            $statusItem = strtoupper(
                trim((string) ($dados['status_item'] ?? ''))
            );

            if (!in_array(
                $statusItem,
                ['PENDENTE', 'AUDITADO', 'GLOSADO'],
                true
            )) {
                responderJson(
                    false,
                    'Status do item inválido.',
                    [],
                    422
                );
            }

            $pdo->beginTransaction();

            $conta = bloquearConta(
                $pdo,
                $contaId
            );

            if (in_array(
                $conta['status'],
                ['FECHADA', 'FATURADA'],
                true
            )) {
                throw new DomainException(
                    'Não é possível alterar itens de uma conta fechada.'
                );
            }

            $stmtItem = $pdo->prepare(
                "
                SELECT id

                FROM itens_conta_hospitalar

                WHERE id = :item_id
                  AND conta_id = :conta_id

                LIMIT 1

                FOR UPDATE
                "
            );

            $stmtItem->execute([
                ':item_id' => $itemContaId,
                ':conta_id' => $contaId
            ]);

            if (!$stmtItem->fetchColumn()) {
                throw new RuntimeException(
                    'Item não encontrado nesta conta.'
                );
            }

            $stmtAtualizar = $pdo->prepare(
                "
                UPDATE itens_conta_hospitalar

                SET status_item = :status_item

                WHERE id = :item_id
                  AND conta_id = :conta_id
                "
            );

            $stmtAtualizar->execute([
                ':status_item' => $statusItem,
                ':item_id' => $itemContaId,
                ':conta_id' => $contaId
            ]);

            $totais = recalcularConta(
                $pdo,
                $contaId
            );

            $pdo->commit();

            responderJson(
                true,
                'Status do item atualizado.',
                [
                    'status_item' => $statusItem,
                    'totais' => $totais
                ]
            );

        case 'encaminhar_auditoria':

            $pdo->beginTransaction();

            $conta = bloquearConta(
                $pdo,
                $contaId
            );

            if ($conta['status'] !== 'ABERTA') {
                throw new DomainException(
                    'Somente contas abertas podem ser encaminhadas para auditoria.'
                );
            }

            $stmtItens = $pdo->prepare(
                "
                SELECT COUNT(*)

                FROM itens_conta_hospitalar

                WHERE conta_id = :conta_id
                "
            );

            $stmtItens->execute([
                ':conta_id' => $contaId
            ]);

            if ((int) $stmtItens->fetchColumn() === 0) {
                throw new DomainException(
                    'A conta não possui itens para auditoria.'
                );
            }

            recalcularConta(
                $pdo,
                $contaId
            );

            $stmtAtualizar = $pdo->prepare(
                "
                UPDATE contas_hospitalares

                SET status = 'EM_AUDITORIA'

                WHERE id = :conta_id
                  AND status = 'ABERTA'
                "
            );

            $stmtAtualizar->execute([
                ':conta_id' => $contaId
            ]);

            if ($stmtAtualizar->rowCount() !== 1) {
                throw new RuntimeException(
                    'A conta não pôde ser encaminhada para auditoria.'
                );
            }

            $pdo->commit();

            responderJson(
                true,
                'Conta encaminhada para auditoria.',
                [
                    'status' => 'EM_AUDITORIA'
                ]
            );

        case 'fechar_conta':

            $pdo->beginTransaction();

            $conta = bloquearConta(
                $pdo,
                $contaId
            );

            if (in_array(
                $conta['status'],
                ['FECHADA', 'FATURADA'],
                true
            )) {
                throw new DomainException(
                    'Esta conta já está fechada ou faturada.'
                );
            }

            $stmtQuantidade = $pdo->prepare(
                "
                SELECT COUNT(*)

                FROM itens_conta_hospitalar

                WHERE conta_id = :conta_id
                "
            );

            $stmtQuantidade->execute([
                ':conta_id' => $contaId
            ]);

            if ((int) $stmtQuantidade->fetchColumn() === 0) {
                throw new DomainException(
                    'Não é possível fechar uma conta sem itens.'
                );
            }

            $stmtPendentes = $pdo->prepare(
                "
                SELECT COUNT(*)

                FROM itens_conta_hospitalar

                WHERE conta_id = :conta_id
                  AND status_item = 'PENDENTE'
                "
            );

            $stmtPendentes->execute([
                ':conta_id' => $contaId
            ]);

            $quantidadePendentes =
                (int) $stmtPendentes->fetchColumn();

            if ($quantidadePendentes > 0) {
                throw new DomainException(
                    "Existem {$quantidadePendentes} item(ns) pendente(s) de auditoria."
                );
            }

            $totais = recalcularConta(
                $pdo,
                $contaId
            );

            $stmtFechar = $pdo->prepare(
                "
                UPDATE contas_hospitalares

                SET
                    status = 'FECHADA',
                    data_fechamento = NOW()

                WHERE id = :conta_id
                  AND status IN (
                      'ABERTA',
                      'EM_AUDITORIA'
                  )
                "
            );

            $stmtFechar->execute([
                ':conta_id' => $contaId
            ]);

            if ($stmtFechar->rowCount() !== 1) {
                throw new RuntimeException(
                    'A conta não pôde ser fechada.'
                );
            }

            $pdo->commit();

            responderJson(
                true,
                'Conta hospitalar fechada com sucesso.',
                [
                    'status' => 'FECHADA',
                    'totais' => $totais
                ]
            );

        default:
            responderJson(
                false,
                'Ação inválida.',
                [],
                400
            );
    }
} catch (DomainException $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responderJson(
        false,
        $erro->getMessage(),
        [],
        409
    );
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        sprintf(
            '[Conta Hospitalar] %s em %s:%d',
            $erro->getMessage(),
            $erro->getFile(),
            $erro->getLine()
        )
    );

    responderJson(
        false,
        'Ocorreu um erro ao processar a conta hospitalar.',
        [],
        500
    );
}