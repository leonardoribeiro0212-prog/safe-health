<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

/*
|--------------------------------------------------------------------------
| Funções auxiliares
|--------------------------------------------------------------------------
*/

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

function receberJson(): array
{
    $conteudo = file_get_contents('php://input');

    $dados = json_decode(
        $conteudo ?: '',
        true
    );

    if (!is_array($dados)) {
        responderJson(
            false,
            'Os dados enviados são inválidos.',
            [],
            400
        );
    }

    return $dados;
}

function textoObrigatorio(
    mixed $valor,
    string $campo,
    int $limite
): string {
    $texto = trim((string) $valor);

    if ($texto === '') {
        responderJson(
            false,
            "O campo {$campo} é obrigatório.",
            [],
            422
        );
    }

    return mb_substr(
        $texto,
        0,
        $limite
    );
}

function textoOpcional(
    mixed $valor,
    int $limite
): ?string {
    if ($valor === null) {
        return null;
    }

    $texto = trim((string) $valor);

    if ($texto === '') {
        return null;
    }

    return mb_substr(
        $texto,
        0,
        $limite
    );
}

function inteiroPositivo(
    mixed $valor,
    string $campo
): int {
    $numero = filter_var(
        $valor,
        FILTER_VALIDATE_INT
    );

    if (
        $numero === false ||
        $numero <= 0
    ) {
        responderJson(
            false,
            "O campo {$campo} é inválido.",
            [],
            422
        );
    }

    return $numero;
}

function decimalNaoNegativo(
    mixed $valor,
    string $campo
): float {
    $valorNormalizado = str_replace(
        ',',
        '.',
        trim((string) $valor)
    );

    if (
        $valorNormalizado === '' ||
        !is_numeric($valorNormalizado)
    ) {
        responderJson(
            false,
            "O campo {$campo} é inválido.",
            [],
            422
        );
    }

    $numero = round(
        (float) $valorNormalizado,
        2
    );

    if ($numero < 0) {
        responderJson(
            false,
            "O campo {$campo} não pode ser negativo.",
            [],
            422
        );
    }

    return $numero;
}

function decimalPositivo(
    mixed $valor,
    string $campo
): float {
    $numero = decimalNaoNegativo(
        $valor,
        $campo
    );

    if ($numero <= 0) {
        responderJson(
            false,
            "O campo {$campo} deve ser maior que zero.",
            [],
            422
        );
    }

    return $numero;
}

function formatarDinheiro(
    float|string|null $valor
): string {
    return 'R$ ' . number_format(
        (float) $valor,
        2,
        ',',
        '.'
    );
}

/*
|--------------------------------------------------------------------------
| Bloqueio e recálculo da conta
|--------------------------------------------------------------------------
*/

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
            convenio_id,
            status,
            valor_bruto,
            valor_desconto,
            valor_liquido,
            data_abertura,
            data_fechamento

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
    /*
     * Total bruto:
     * soma de todos os itens.
     *
     * Desconto/glosa:
     * soma dos itens marcados como GLOSADO.
     *
     * Total líquido:
     * bruto menos glosas.
     */
    $stmtTotais = $pdo->prepare(
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
            ) AS valor_desconto,

            COALESCE(
                SUM(
                    CASE
                        WHEN status_item <> 'GLOSADO'
                        THEN valor_total
                        ELSE 0
                    END
                ),
                0
            ) AS valor_liquido

        FROM itens_conta_hospitalar

        WHERE conta_id = :conta_id
        "
    );

    $stmtTotais->execute([
        ':conta_id' => $contaId
    ]);

    $totais = $stmtTotais->fetch(
        PDO::FETCH_ASSOC
    );

    $valorBruto = round(
        (float) ($totais['valor_bruto'] ?? 0),
        2
    );

    $valorDesconto = round(
        (float) ($totais['valor_desconto'] ?? 0),
        2
    );

    $valorLiquido = round(
        (float) ($totais['valor_liquido'] ?? 0),
        2
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
        'valor_liquido' => $valorLiquido
    ];
}

/*
|--------------------------------------------------------------------------
| Processamento AJAX
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = receberJson();

    $acao = trim(
        (string) ($dados['acao'] ?? '')
    );

    $contaId = inteiroPositivo(
        $dados['conta_id'] ?? null,
        'conta'
    );

    /*
    |--------------------------------------------------------------------------
    | Adicionar item
    |--------------------------------------------------------------------------
    */

    if ($acao === 'adicionar_item') {
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
                'O tipo do item é inválido.',
                [],
                422
            );
        }

        $descricao = textoObrigatorio(
            $dados['descricao'] ?? null,
            'descrição',
            255
        );

        $itemId = filter_var(
            $dados['item_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $itemId === false ||
            $itemId === 0
        ) {
            $itemId = null;
        }

        $quantidade = decimalPositivo(
            $dados['quantidade'] ?? null,
            'quantidade'
        );

        $valorUnitario = decimalNaoNegativo(
            $dados['valor_unitario'] ?? null,
            'valor unitário'
        );

        $valorTotal = round(
            $quantidade * $valorUnitario,
            2
        );

        try {
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
                $pdo->rollBack();

                responderJson(
                    false,
                    'Não é possível adicionar itens a uma conta fechada ou faturada.',
                    [
                        'status_atual' => $conta['status']
                    ],
                    409
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

            $itemContaId = (int) $pdo->lastInsertId();

            $totais = recalcularConta(
                $pdo,
                $contaId
            );

            $pdo->commit();

            responderJson(
                true,
                'Item adicionado à conta hospitalar.',
                [
                    'item_conta_id' => $itemContaId,
                    'valor_total_item' => $valorTotal,
                    'totais' => $totais
                ],
                201
            );
        } catch (Throwable $erro) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erro ao adicionar item à conta: ' .
                $erro->getMessage()
            );

            responderJson(
                false,
                'Não foi possível adicionar o item.',
                [],
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Alterar status de item
    |--------------------------------------------------------------------------
    */

    if ($acao === 'alterar_status_item') {
        $itemContaId = inteiroPositivo(
            $dados['item_conta_id'] ?? null,
            'item'
        );

        $novoStatus = strtoupper(
            trim((string) ($dados['status_item'] ?? ''))
        );

        $statusPermitidos = [
            'PENDENTE',
            'AUDITADO',
            'GLOSADO'
        ];

        if (!in_array(
            $novoStatus,
            $statusPermitidos,
            true
        )) {
            responderJson(
                false,
                'O status informado é inválido.',
                [],
                422
            );
        }

        try {
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
                $pdo->rollBack();

                responderJson(
                    false,
                    'Não é possível alterar itens de uma conta fechada ou faturada.',
                    [],
                    409
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

            if (!$stmtItem->fetch()) {
                $pdo->rollBack();

                responderJson(
                    false,
                    'Item não encontrado nesta conta.',
                    [],
                    404
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
                ':status_item' => $novoStatus,
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
                    'status_item' => $novoStatus,
                    'totais' => $totais
                ]
            );
        } catch (Throwable $erro) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erro ao atualizar item da conta: ' .
                $erro->getMessage()
            );

            responderJson(
                false,
                'Não foi possível atualizar o item.',
                [],
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Encaminhar conta para auditoria
    |--------------------------------------------------------------------------
    */

    if ($acao === 'encaminhar_auditoria') {
        try {
            $pdo->beginTransaction();

            $conta = bloquearConta(
                $pdo,
                $contaId
            );

            if ($conta['status'] !== 'ABERTA') {
                $pdo->rollBack();

                responderJson(
                    false,
                    'Somente contas abertas podem ser encaminhadas para auditoria.',
                    [
                        'status_atual' => $conta['status']
                    ],
                    409
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

            $quantidadeItens =
                (int) $stmtQuantidade->fetchColumn();

            if ($quantidadeItens === 0) {
                $pdo->rollBack();

                responderJson(
                    false,
                    'A conta não possui itens para auditoria.',
                    [],
                    422
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
                    'status_atual' => 'EM_AUDITORIA'
                ]
            );
        } catch (Throwable $erro) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erro ao encaminhar conta para auditoria: ' .
                $erro->getMessage()
            );

            responderJson(
                false,
                'Não foi possível encaminhar a conta para auditoria.',
                [],
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Fechar conta hospitalar
    |--------------------------------------------------------------------------
    */

    if ($acao === 'fechar_conta') {
        try {
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
                $pdo->rollBack();

                responderJson(
                    false,
                    'Esta conta já está fechada ou faturada.',
                    [
                        'status_atual' => $conta['status']
                    ],
                    409
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

            $itensPendentes =
                (int) $stmtPendentes->fetchColumn();

            if ($itensPendentes > 0) {
                $pdo->rollBack();

                responderJson(
                    false,
                    'Existem itens pendentes de auditoria.',
                    [
                        'itens_pendentes' => $itensPendentes
                    ],
                    409
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
                    'status_atual' => 'FECHADA',
                    'totais' => $totais
                ]
            );
        } catch (Throwable $erro) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erro ao fechar conta hospitalar: ' .
                $erro->getMessage()
            );

            responderJson(
                false,
                'Não foi possível fechar a conta hospitalar.',
                [],
                500
            );
        }
    }

    responderJson(
        false,
        'Ação inválida.',
        [],
        400
    );
}

/*
|--------------------------------------------------------------------------
| Carregamento da tela
|--------------------------------------------------------------------------
*/

$contaId = filter_input(
    INPUT_GET,
    'conta_id',
    FILTER_VALIDATE_INT
);

if (
    $contaId === false ||
    $contaId === null ||
    $contaId <= 0
) {
    /*
    |--------------------------------------------------------------------------
    | Lista de contas hospitalares
    |--------------------------------------------------------------------------
    |
    | Quando o usuário acessa a opção pelo menu do ERP, ainda não existe uma
    | conta específica selecionada. Nesse caso, mostramos a lista de contas e
    | cada botão monta a URL correta com o respectivo conta_id.
    |
    */

    $pesquisa = trim(
        (string) ($_GET['q'] ?? '')
    );

    $sqlListaContas = "
        SELECT
            ch.id,
            ch.atendimento_id,
            ch.status,
            ch.valor_bruto,
            ch.valor_liquido,
            ch.data_abertura,
            p.nome AS paciente_nome,
            a.numero_atendimento,
            a.convenio
        FROM contas_hospitalares ch
        INNER JOIN pacientes p
            ON p.id = ch.paciente_id
        INNER JOIN atendimentos a
            ON a.id = ch.atendimento_id
    ";

    $parametrosLista = [];

    if ($pesquisa !== '') {
        $pesquisaSemHash = ltrim(
            $pesquisa,
            '#'
        );

        $sqlListaContas .= "
            WHERE
                CAST(ch.id AS CHAR) LIKE :conta
                OR a.numero_atendimento LIKE :atendimento
        ";

        $parametrosLista = [
            ':conta' => '%' . $pesquisaSemHash . '%',
            ':atendimento' => '%' . $pesquisa . '%'
        ];
    }

    $sqlListaContas .= "
        ORDER BY ch.id DESC
    ";

    $stmtListaContas = $pdo->prepare(
        $sqlListaContas
    );

    $stmtListaContas->execute(
        $parametrosLista
    );

    $contasHospitalares = $stmtListaContas->fetchAll(
        PDO::FETCH_ASSOC
    );

    $tituloPagina = 'Contas Hospitalares';

    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../includes/sidebar.php';
    ?>

    <div class="app-content">
        <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

        <main class="main-content">
            <section style="
                padding: 24px;
                border: 1px solid #dfe7eb;
                border-radius: 16px;
                background: #ffffff;
                box-shadow: 0 8px 24px rgba(22,70,85,.06);
            ">
                <div style="
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:16px;
                    margin-bottom:20px;
                ">
                    <div>
                        <h2 style="margin:0 0 6px;">Contas Hospitalares</h2>
                        <p style="margin:0;color:#64748b;">
                            Selecione uma conta para visualizar seus dados e lançamentos.
                        </p>
                    </div>
                </div>

                <form
                    method="GET"
                    action="/safe-health/modules/faturamento/conta-hospitalar.php"
                    style="
                        display:flex;
                        align-items:end;
                        gap:12px;
                        flex-wrap:wrap;
                        margin-bottom:20px;
                    "
                >
                    <div style="
                        flex:1;
                        min-width:280px;
                    ">
                        <label
                            for="pesquisaConta"
                            style="
                                display:block;
                                margin-bottom:7px;
                                color:#334155;
                                font-size:14px;
                                font-weight:700;
                            "
                        >
                            Pesquisar conta
                        </label>

                        <div style="position:relative;">
                            <i
                                class="fa-solid fa-magnifying-glass"
                                style="
                                    position:absolute;
                                    left:14px;
                                    top:50%;
                                    transform:translateY(-50%);
                                    color:#7b8c98;
                                "
                            ></i>

                            <input
                                type="text"
                                id="pesquisaConta"
                                name="q"
                                value="<?= htmlspecialchars(
                                    $pesquisa,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="Digite o nº da conta ou do atendimento"
                                autocomplete="off"
                                style="
                                    width:100%;
                                    min-height:46px;
                                    padding:0 14px 0 42px;
                                    border:1px solid #cfdbe3;
                                    border-radius:10px;
                                    background:#ffffff;
                                    color:#172033;
                                    font:inherit;
                                    outline:none;
                                "
                            >
                        </div>
                    </div>

                    <button
                        type="submit"
                        style="
                            min-height:46px;
                            padding:0 18px;
                            border:0;
                            border-radius:10px;
                            background:#176b87;
                            color:#ffffff;
                            font-weight:700;
                            cursor:pointer;
                        "
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Pesquisar
                    </button>

                    <?php if ($pesquisa !== ''): ?>
                        <a
                            href="/safe-health/modules/faturamento/conta-hospitalar.php"
                            style="
                                display:inline-flex;
                                min-height:46px;
                                align-items:center;
                                justify-content:center;
                                gap:8px;
                                padding:0 18px;
                                border:1px solid #cfdbe3;
                                border-radius:10px;
                                background:#ffffff;
                                color:#334155;
                                text-decoration:none;
                                font-weight:700;
                            "
                        >
                            <i class="fa-solid fa-xmark"></i>
                            Limpar
                        </a>
                    <?php endif; ?>
                </form>

                <?php if ($pesquisa !== ''): ?>
                    <div style="
                        margin:-4px 0 16px;
                        color:#64748b;
                        font-size:14px;
                    ">
                        Resultado da pesquisa por:
                        <strong>
                            <?= htmlspecialchars(
                                $pesquisa,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>
                    </div>
                <?php endif; ?>

                <div style="overflow-x:auto;">
                    <table style="
                        width:100%;
                        border-collapse:collapse;
                        min-width:900px;
                    ">
                        <thead>
                            <tr style="background:#f5f8fa;text-align:left;">
                                <th style="padding:12px;border-bottom:1px solid #dfe7eb;">Conta</th>
                                <th style="padding:12px;border-bottom:1px solid #dfe7eb;">Atendimento</th>
                                <th style="padding:12px;border-bottom:1px solid #dfe7eb;">Paciente</th>
                                <th style="padding:12px;border-bottom:1px solid #dfe7eb;">Convênio</th>
                                <th style="padding:12px;border-bottom:1px solid #dfe7eb;">Status</th>
                                <th style="padding:12px;border-bottom:1px solid #dfe7eb;">Valor líquido</th>
                                <th style="padding:12px;border-bottom:1px solid #dfe7eb;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$contasHospitalares): ?>
                                <tr>
                                    <td colspan="7" style="padding:24px;text-align:center;color:#64748b;">
                                        <?= $pesquisa !== ''
                                            ? 'Nenhuma conta corresponde à pesquisa informada.'
                                            : 'Nenhuma Conta Hospitalar foi encontrada.' ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($contasHospitalares as $contaLista): ?>
                                    <tr>
                                        <td style="padding:12px;border-bottom:1px solid #edf1f3;">
                                            #<?= (int) $contaLista['id'] ?>
                                        </td>
                                        <td style="padding:12px;border-bottom:1px solid #edf1f3;">
                                            <?= htmlspecialchars((string) $contaLista['numero_atendimento'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td style="padding:12px;border-bottom:1px solid #edf1f3;">
                                            <?= htmlspecialchars((string) $contaLista['paciente_nome'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td style="padding:12px;border-bottom:1px solid #edf1f3;">
                                            <?= htmlspecialchars((string) ($contaLista['convenio'] ?: 'Particular'), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td style="padding:12px;border-bottom:1px solid #edf1f3;">
                                            <?= htmlspecialchars((string) $contaLista['status'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td style="padding:12px;border-bottom:1px solid #edf1f3;">
                                            R$ <?= number_format((float) $contaLista['valor_liquido'], 2, ',', '.') ?>
                                        </td>
                                        <td style="padding:12px;border-bottom:1px solid #edf1f3;">
                                            <a
                                                href="/safe-health/modules/faturamento/conta-hospitalar.php?conta_id=<?= (int) $contaLista['id'] ?>"
                                                style="
                                                    display:inline-flex;
                                                    align-items:center;
                                                    gap:8px;
                                                    padding:9px 14px;
                                                    border-radius:8px;
                                                    background:#176b87;
                                                    color:#fff;
                                                    text-decoration:none;
                                                    font-weight:700;
                                                "
                                            >
                                                <i class="fa-solid fa-folder-open"></i>
                                                Abrir conta
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

    <?php
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

/*
 * Ajuste os nomes dos campos abaixo caso sua estrutura
 * de pacientes, atendimentos ou convênios seja diferente.
 */
$stmtConta = $pdo->prepare("
SELECT
    ch.id,
    ch.atendimento_id,
    ch.paciente_id,
    ch.convenio_id,
    ch.data_abertura,
    ch.data_fechamento,
    ch.valor_bruto,
    ch.valor_desconto,
    ch.valor_coparticipacao,
    ch.valor_liquido,
    ch.status,

    p.nome AS paciente_nome,
    p.cpf,

    a.numero_atendimento,
    a.convenio

FROM contas_hospitalares ch

INNER JOIN pacientes p
    ON p.id = ch.paciente_id

INNER JOIN atendimentos a
    ON a.id = ch.atendimento_id

WHERE ch.id = :conta_id
LIMIT 1
");

$stmtConta->execute([
    ':conta_id' => $contaId
]);

$conta = $stmtConta->fetch(
    PDO::FETCH_ASSOC
);

if (!$conta) {
    http_response_code(404);

    exit('Conta hospitalar não encontrada.');
}

$stmtItens = $pdo->prepare(
    "
    SELECT
        id,
        conta_id,
        tipo_item,
        item_id,
        descricao,
        quantidade,
        valor_unitario,
        valor_total,
        status_item,
        criado_em

    FROM itens_conta_hospitalar

    WHERE conta_id = :conta_id

    ORDER BY
        FIELD(
            tipo_item,
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
        ),
        criado_em ASC,
        id ASC
    "
);

$stmtItens->execute([
    ':conta_id' => $contaId
]);

$itens = $stmtItens->fetchAll(
    PDO::FETCH_ASSOC
);

$itensAgrupados = [];

foreach ($itens as $item) {
    $tipo = $item['tipo_item'];

    $itensAgrupados[$tipo][] = $item;
}

$nomesTipos = [
    'DIARIA' => 'Diárias',
    'TAXA' => 'Taxas hospitalares',
    'PROCEDIMENTO' => 'Procedimentos',
    'CIRURGIA' => 'Cirurgias',
    'MEDICAMENTO' => 'Medicamentos',
    'MATERIAL' => 'Materiais',
    'EXAME_LABORATORIAL' => 'Exames laboratoriais',
    'EXAME_IMAGEM' => 'Exames de imagem',
    'HONORARIO' => 'Honorários',
    'OUTRO' => 'Outros lançamentos'
];

$classesStatusConta = [
    'ABERTA' => 'bg-success',
    'EM_AUDITORIA' => 'bg-warning text-dark',
    'FECHADA' => 'bg-primary',
    'FATURADA' => 'bg-dark'
];

$classesStatusItem = [
    'PENDENTE' => 'bg-warning text-dark',
    'AUDITADO' => 'bg-success',
    'GLOSADO' => 'bg-danger'
];

$tituloPagina = 'Conta Hospitalar';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';

?>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<style>
    .conta-page {
        padding: 28px;
        background: #f4f7fa;
        min-height: calc(100vh - 80px);
    }

    .conta-card {
        border: 1px solid #dce5ec;
        border-radius: 20px;
        background: #ffffff;
        box-shadow: 0 15px 40px rgba(23, 48, 66, 0.06);
    }

    .conta-header {
        padding: 24px;
    }

    .conta-title {
        color: #163448;
        font-weight: 800;
    }

    .info-label {
        color: #718492;
        font-size: 0.82rem;
        margin-bottom: 4px;
    }

    .info-value {
        color: #132d40;
        font-weight: 700;
    }

    .category-title {
        color: #087f74;
        font-weight: 800;
        font-size: 0.95rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .table thead th {
        background: #edf4f7;
        color: #284454;
        border-bottom: 0;
        font-size: 0.82rem;
        text-transform: uppercase;
    }

    .financial-summary {
        position: sticky;
        top: 20px;
    }

    .financial-line {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        padding: 13px 0;
        border-bottom: 1px solid #e3ebf0;
    }

    .financial-total {
        font-size: 1.3rem;
        color: #087f74;
        font-weight: 800;
    }

    .btn-safe {
        background: #087f9b;
        border-color: #087f9b;
        color: #ffffff;
        font-weight: 700;
    }

    .btn-safe:hover {
        background: #066d87;
        border-color: #066d87;
        color: #ffffff;
    }

    .empty-items {
        padding: 50px 20px;
        text-align: center;
        color: #738590;
    }

    #toastConta {
        position: fixed;
        right: 25px;
        bottom: 25px;
        z-index: 9999;
        min-width: 320px;
        display: none;
    }

    @media (max-width: 991px) {
        .financial-summary {
            position: static;
        }

        .conta-page {
            padding: 15px;
        }
    }
</style>

<main class="conta-page">
    <div class="container-fluid">

        <div class="conta-card conta-header mb-4">
            <div
                class="d-flex flex-wrap justify-content-between align-items-start gap-3"
            >
                <div>
                    <div class="text-uppercase text-success fw-bold small">
                        Faturamento hospitalar
                    </div>

                    <h1 class="conta-title h3 mb-2">
                        Conta Hospitalar
                        #<?= str_pad(
                            (string) $conta['id'],
                            6,
                            '0',
                            STR_PAD_LEFT
                        ) ?>
                    </h1>

                    <span
                        class="badge rounded-pill <?= htmlspecialchars(
                            $classesStatusConta[$conta['status']] ??
                            'bg-secondary'
                        ) ?>"
                    >
                        <?= htmlspecialchars(
                            str_replace(
                                '_',
                                ' ',
                                $conta['status']
                            )
                        ) ?>
                    </span>
                </div>

                <?php if (!in_array(
                    $conta['status'],
                    ['FECHADA', 'FATURADA'],
                    true
                )): ?>
                    <button
                        type="button"
                        class="btn btn-safe"
                        data-bs-toggle="modal"
                        data-bs-target="#modalNovoItem"
                    >
                        + Adicionar item
                    </button>
                <?php endif; ?>
            </div>

            <hr class="my-4">

            <div class="row g-4">
                <div class="col-md-6 col-xl-3">
                    <div class="info-label">
                        Paciente
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars(
                            $conta['paciente_nome']
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6 col-xl-2">
                    <div class="info-label">
                        CPF
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars(
                            $conta['paciente_cpf'] ??
                            'Não informado'
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6 col-xl-2">
                    <div class="info-label">
                        Atendimento
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars(
                            $conta['numero_atendimento'] ??
                            (string) $conta['atendimento_id']
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="info-label">
                        Convênio
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars(
                            $conta['convenio_nome'] ??
                            'Particular'
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6 col-xl-2">
                    <div class="info-label">
                        Data de abertura
                    </div>

                    <div class="info-value">
                        <?= date(
                            'd/m/Y H:i',
                            strtotime($conta['data_abertura'])
                        ) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-9">
                <div class="conta-card p-4">

                    <div
                        class="d-flex justify-content-between align-items-center mb-4"
                    >
                        <div>
                            <div class="category-title">
                                Lançamentos
                            </div>

                            <h2 class="h4 conta-title mb-0">
                                Itens da conta
                            </h2>
                        </div>

                        <span class="text-secondary">
                            <?= count($itens) ?>
                            item(ns)
                        </span>
                    </div>

                    <?php if (!$itens): ?>
                        <div class="empty-items">
                            Nenhum item foi lançado nesta conta.
                        </div>
                    <?php else: ?>

                        <?php foreach (
                            $itensAgrupados as
                            $tipo => $itensCategoria
                        ): ?>
                            <div class="mb-5">
                                <div class="category-title mb-3">
                                    <?= htmlspecialchars(
                                        $nomesTipos[$tipo] ??
                                        $tipo
                                    ) ?>
                                </div>

                                <div class="table-responsive">
                                    <table
                                        class="table table-hover align-middle"
                                    >
                                        <thead>
                                            <tr>
                                                <th>Descrição</th>
                                                <th class="text-end">
                                                    Quantidade
                                                </th>
                                                <th class="text-end">
                                                    Valor unitário
                                                </th>
                                                <th class="text-end">
                                                    Total
                                                </th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php foreach (
                                                $itensCategoria as $item
                                            ): ?>
                                                <tr>
                                                    <td>
                                                        <strong>
                                                            <?= htmlspecialchars(
                                                                $item['descricao']
                                                            ) ?>
                                                        </strong>

                                                        <div class="small text-secondary">
                                                            <?= date(
                                                                'd/m/Y H:i',
                                                                strtotime(
                                                                    $item['criado_em']
                                                                )
                                                            ) ?>
                                                        </div>
                                                    </td>

                                                    <td class="text-end">
                                                        <?= number_format(
                                                            (float) $item['quantidade'],
                                                            2,
                                                            ',',
                                                            '.'
                                                        ) ?>
                                                    </td>

                                                    <td class="text-end">
                                                        <?= formatarDinheiro(
                                                            $item['valor_unitario']
                                                        ) ?>
                                                    </td>

                                                    <td class="text-end fw-bold">
                                                        <?= formatarDinheiro(
                                                            $item['valor_total']
                                                        ) ?>
                                                    </td>

                                                    <td>
                                                        <span
                                                            class="badge <?= htmlspecialchars(
                                                                $classesStatusItem[
                                                                    $item['status_item']
                                                                ] ?? 'bg-secondary'
                                                            ) ?>"
                                                        >
                                                            <?= htmlspecialchars(
                                                                $item['status_item']
                                                            ) ?>
                                                        </span>
                                                    </td>

                                                    <td>
                                                        <?php if (!in_array(
                                                            $conta['status'],
                                                            ['FECHADA', 'FATURADA'],
                                                            true
                                                        )): ?>
                                                            <div class="dropdown">
                                                                <button
                                                                    class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                                    data-bs-toggle="dropdown"
                                                                    type="button"
                                                                >
                                                                    Alterar
                                                                </button>

                                                                <ul class="dropdown-menu">
                                                                    <li>
                                                                        <button
                                                                            class="dropdown-item alterar-status"
                                                                            data-item-id="<?= (int) $item['id'] ?>"
                                                                            data-status="PENDENTE"
                                                                        >
                                                                            Pendente
                                                                        </button>
                                                                    </li>

                                                                    <li>
                                                                        <button
                                                                            class="dropdown-item alterar-status"
                                                                            data-item-id="<?= (int) $item['id'] ?>"
                                                                            data-status="AUDITADO"
                                                                        >
                                                                            Auditado
                                                                        </button>
                                                                    </li>

                                                                    <li>
                                                                        <button
                                                                            class="dropdown-item alterar-status text-danger"
                                                                            data-item-id="<?= (int) $item['id'] ?>"
                                                                            data-status="GLOSADO"
                                                                        >
                                                                            Glosado
                                                                        </button>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>
            </div>

            <div class="col-xl-3">
                <div class="conta-card p-4 financial-summary">
                    <div class="category-title">
                        Resumo financeiro
                    </div>

                    <h2 class="h5 conta-title mb-4">
                        Total da conta
                    </h2>

                    <div class="financial-line">
                        <span>Total bruto</span>

                        <strong>
                            <?= formatarDinheiro(
                                $conta['valor_bruto']
                            ) ?>
                        </strong>
                    </div>

                    <div class="financial-line">
                        <span>Descontos / Glosas</span>

                        <strong class="text-danger">
                            <?= formatarDinheiro(
                                $conta['valor_desconto']
                            ) ?>
                        </strong>
                    </div>

                    <div class="financial-line">
                        <span>Coparticipação</span>

                        <strong>
                            R$ 0,00
                        </strong>
                    </div>

                    <div class="financial-line border-0">
                        <span>Total líquido</span>

                        <span class="financial-total">
                            <?= formatarDinheiro(
                                $conta['valor_liquido']
                            ) ?>
                        </span>
                    </div>

                    <?php if ($conta['status'] === 'ABERTA'): ?>
                        <button
                            type="button"
                            id="botaoAuditoria"
                            class="btn btn-warning w-100 mt-4 fw-bold"
                        >
                            Encaminhar para auditoria
                        </button>
                    <?php endif; ?>

                    <?php if (in_array(
                        $conta['status'],
                        ['ABERTA', 'EM_AUDITORIA'],
                        true
                    )): ?>
                        <button
                            type="button"
                            id="botaoFecharConta"
                            class="btn btn-dark w-100 mt-3 fw-bold"
                        >
                            Fechar conta hospitalar
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</main>

<div
    class="modal fade"
    id="modalNovoItem"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form id="formNovoItem">
                <div class="modal-header">
                    <div>
                        <div class="category-title">
                            Novo lançamento
                        </div>

                        <h2 class="modal-title h5 conta-title">
                            Adicionar item à conta
                        </h2>
                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                Tipo do item
                            </label>

                            <select
                                name="tipo_item"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    Selecione
                                </option>

                                <option value="DIARIA">
                                    Diária
                                </option>

                                <option value="TAXA">
                                    Taxa
                                </option>

                                <option value="PROCEDIMENTO">
                                    Procedimento
                                </option>

                                <option value="CIRURGIA">
                                    Cirurgia
                                </option>

                                <option value="MEDICAMENTO">
                                    Medicamento
                                </option>

                                <option value="MATERIAL">
                                    Material
                                </option>

                                <option value="EXAME_LABORATORIAL">
                                    Exame laboratorial
                                </option>

                                <option value="EXAME_IMAGEM">
                                    Exame de imagem
                                </option>

                                <option value="HONORARIO">
                                    Honorário
                                </option>

                                <option value="OUTRO">
                                    Outro
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                Código do item
                            </label>

                            <input
                                type="number"
                                name="item_id"
                                class="form-control"
                                min="1"
                            >
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">
                                Descrição
                            </label>

                            <input
                                type="text"
                                name="descricao"
                                class="form-control"
                                maxlength="255"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                Quantidade
                            </label>

                            <input
                                type="number"
                                name="quantidade"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                value="1"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                Valor unitário
                            </label>

                            <input
                                type="number"
                                name="valor_unitario"
                                class="form-control"
                                min="0"
                                step="0.01"
                                required
                            >
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        id="botaoSalvarItem"
                        class="btn btn-safe"
                    >
                        Salvar item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div
    id="toastConta"
    class="alert shadow"
    role="alert"
></div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

<script>
    const contaId = <?= (int) $contaId ?>;

    const toastConta =
        document.getElementById('toastConta');

    function mostrarMensagem(
        mensagem,
        tipo = 'success'
    ) {
        toastConta.textContent = mensagem;

        toastConta.className =
            `alert shadow alert-${tipo}`;

        toastConta.style.display = 'block';

        window.setTimeout(() => {
            toastConta.style.display = 'none';
        }, 4000);
    }

    async function executarAcao(dados) {
        const resposta = await fetch(
            window.location.pathname,
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json'
                },

                body: JSON.stringify({
                    ...dados,
                    conta_id: contaId
                })
            }
        );

        const textoResposta =
            await resposta.text();

        let resultado;

        try {
            resultado = JSON.parse(textoResposta);
        } catch (erro) {
            console.error(
                'Resposta inválida:',
                textoResposta
            );

            throw new Error(
                'A API retornou uma resposta inválida.'
            );
        }

        if (
            !resposta.ok ||
            !resultado.sucesso
        ) {
            throw new Error(
                resultado.mensagem ||
                'Não foi possível concluir a operação.'
            );
        }

        return resultado;
    }

    const formNovoItem =
        document.getElementById('formNovoItem');

    if (formNovoItem) {
        formNovoItem.addEventListener(
            'submit',
            async (evento) => {
                evento.preventDefault();

                const botao =
                    document.getElementById(
                        'botaoSalvarItem'
                    );

                botao.disabled = true;

                try {
                    const formulario =
                        new FormData(formNovoItem);

                    const dados =
                        Object.fromEntries(
                            formulario.entries()
                        );

                    const resultado =
                        await executarAcao({
                            acao: 'adicionar_item',
                            ...dados
                        });

                    mostrarMensagem(
                        resultado.mensagem
                    );

                    window.setTimeout(() => {
                        window.location.reload();
                    }, 800);
                } catch (erro) {
                    console.error(erro);

                    mostrarMensagem(
                        erro.message,
                        'danger'
                    );
                } finally {
                    botao.disabled = false;
                }
            }
        );
    }

    document
        .querySelectorAll('.alterar-status')
        .forEach((botao) => {
            botao.addEventListener(
                'click',
                async () => {
                    const itemContaId =
                        Number(botao.dataset.itemId);

                    const statusItem =
                        botao.dataset.status;

                    try {
                        const resultado =
                            await executarAcao({
                                acao: 'alterar_status_item',
                                item_conta_id: itemContaId,
                                status_item: statusItem
                            });

                        mostrarMensagem(
                            resultado.mensagem
                        );

                        window.setTimeout(() => {
                            window.location.reload();
                        }, 700);
                    } catch (erro) {
                        mostrarMensagem(
                            erro.message,
                            'danger'
                        );
                    }
                }
            );
        });

    const botaoAuditoria =
        document.getElementById('botaoAuditoria');

    if (botaoAuditoria) {
        botaoAuditoria.addEventListener(
            'click',
            async () => {
                if (
                    !window.confirm(
                        'Deseja encaminhar esta conta para auditoria?'
                    )
                ) {
                    return;
                }

                botaoAuditoria.disabled = true;

                try {
                    const resultado =
                        await executarAcao({
                            acao: 'encaminhar_auditoria'
                        });

                    mostrarMensagem(
                        resultado.mensagem
                    );

                    window.setTimeout(() => {
                        window.location.reload();
                    }, 700);
                } catch (erro) {
                    mostrarMensagem(
                        erro.message,
                        'danger'
                    );

                    botaoAuditoria.disabled = false;
                }
            }
        );
    }

    const botaoFecharConta =
        document.getElementById(
            'botaoFecharConta'
        );

    if (botaoFecharConta) {
        botaoFecharConta.addEventListener(
            'click',
            async () => {
                if (
                    !window.confirm(
                        'Confirma o fechamento da conta? Após o fechamento, novos lançamentos serão bloqueados.'
                    )
                ) {
                    return;
                }

                botaoFecharConta.disabled = true;

                try {
                    const resultado =
                        await executarAcao({
                            acao: 'fechar_conta'
                        });

                    mostrarMensagem(
                        resultado.mensagem
                    );

                    window.setTimeout(() => {
                        window.location.reload();
                    }, 700);
                } catch (erro) {
                    mostrarMensagem(
                        erro.message,
                        'danger'
                    );

                    botaoFecharConta.disabled = false;
                }
            }
        );
    }
</script>

<?php

require_once __DIR__ . '/../../includes/footer.php';