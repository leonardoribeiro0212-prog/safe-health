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

function decimalOuNull(mixed $valor): ?float
{
    if ($valor === null || $valor === '') {
        return null;
    }

    $texto = str_replace(
        ',',
        '.',
        trim((string) $valor)
    );

    return is_numeric($texto)
        ? (float) $texto
        : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim(
        (string) ($_GET['acao'] ?? '')
    );

    if ($acao === 'estoque') {
        $stmt = $pdo->query(
            "
            SELECT
                ea.id AS estoque_id,
                ea.material_id,
                ea.lote,
                ea.validade,
                ea.quantidade_atual,
                ea.localizacao,

                mh.nome AS material_nome,
                mh.codigo,
                mh.categoria,
                mh.unidade_medida

            FROM estoque_almoxarifado ea

            INNER JOIN materiais_hospitalares mh
                ON mh.id = ea.material_id

            WHERE
                ea.ativo = 1
                AND ea.quantidade_atual > 0
                AND (
                    ea.validade IS NULL
                    OR ea.validade >= CURDATE()
                )

            ORDER BY
                mh.nome,
                ea.validade,
                ea.lote
            "
        );

        responder(
            true,
            'Estoque disponível carregado.',
            [
                'estoque' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'buscar_atendimento') {
        $numero = trim(
            (string) ($_GET['numero'] ?? '')
        );

        if ($numero === '') {
            responder(
                false,
                'Informe o número do atendimento.',
                [],
                422
            );
        }

        $numeroSemPrefixo =
            preg_replace('/\D/', '', $numero) ?? '';

        $stmt = $pdo->prepare(
            "
            SELECT
                a.id AS atendimento_id,
                a.numero_atendimento,
                a.paciente_id,
                a.convenio,
                a.status,
                a.data_alta,
                p.nome AS paciente_nome,
                p.cpf AS paciente_cpf

            FROM atendimentos a

            INNER JOIN pacientes p
                ON p.id = a.paciente_id

            WHERE
                (
                    a.numero_atendimento = :numero
                    OR CAST(a.id AS CHAR) = :numero_id
                )

            LIMIT 1
            "
        );

        $stmt->execute([
            ':numero' =>
                $numero,

            ':numero_id' =>
                $numeroSemPrefixo
        ]);

        $atendimento =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$atendimento) {
            responder(
                false,
                'Atendimento não encontrado.',
                [],
                404
            );
        }

        responder(
            true,
            'Atendimento localizado.',
            [
                'atendimento' =>
                    $atendimento
            ]
        );
    }

    if ($acao === 'historico') {
        $stmt = $pdo->query(
            "
            SELECT
                se.id,
                se.atendimento_id,
                se.paciente_id,
                se.destino,
                se.motivo,
                se.criado_em,

                a.numero_atendimento,
                p.nome AS paciente_nome,
                u.nome AS usuario_nome,

                COUNT(sei.id) AS total_itens,
                COALESCE(
                    SUM(sei.quantidade),
                    0
                ) AS total_quantidade

            FROM saidas_estoque se

            LEFT JOIN saida_estoque_itens sei
                ON sei.saida_id = se.id

            LEFT JOIN atendimentos a
                ON a.id = se.atendimento_id

            LEFT JOIN pacientes p
                ON p.id = se.paciente_id

            LEFT JOIN usuarios u
                ON u.id = se.usuario_id

            GROUP BY
                se.id,
                se.atendimento_id,
                se.paciente_id,
                se.destino,
                se.motivo,
                se.criado_em,
                a.numero_atendimento,
                p.nome,
                u.nome

            ORDER BY
                se.criado_em DESC,
                se.id DESC

            LIMIT 50
            "
        );

        responder(
            true,
            'Histórico carregado.',
            [
                'saidas' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
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

$atendimentoId =
    (int) ($entrada['atendimento_id'] ?? 0);

$pacienteId =
    (int) ($entrada['paciente_id'] ?? 0);

$destino =
    trim((string) ($entrada['destino'] ?? ''));

$motivo =
    trim((string) ($entrada['motivo'] ?? ''));

$observacoes =
    trim((string) ($entrada['observacoes'] ?? ''));

$itens =
    $entrada['itens'] ?? [];

if ($destino === '') {
    responder(
        false,
        'Informe o destino da saída.',
        [],
        422
    );
}

if (
    !is_array($itens) ||
    count($itens) === 0
) {
    responder(
        false,
        'Adicione pelo menos um material à saída.',
        [],
        422
    );
}

$itensValidados = [];

foreach ($itens as $item) {
    if (!is_array($item)) {
        continue;
    }

    $estoqueId =
        (int) ($item['estoque_id'] ?? 0);

    $quantidade =
        decimalOuNull(
            $item['quantidade'] ?? null
        );

    if ($estoqueId <= 0) {
        responder(
            false,
            'Selecione o lote de todos os materiais.',
            [],
            422
        );
    }

    if (
        $quantidade === null ||
        $quantidade <= 0
    ) {
        responder(
            false,
            'Informe uma quantidade válida para todos os materiais.',
            [],
            422
        );
    }

    $itensValidados[] = [
        'estoque_id' =>
            $estoqueId,

        'quantidade' =>
            round($quantidade, 3)
    ];
}

if (
    $atendimentoId > 0 ||
    $pacienteId > 0
) {
    if (
        $atendimentoId <= 0 ||
        $pacienteId <= 0
    ) {
        responder(
            false,
            'Vínculo de atendimento inválido.',
            [],
            422
        );
    }
}

try {
    $pdo->beginTransaction();

    if ($atendimentoId > 0) {
        $stmtAtendimento = $pdo->prepare(
            "
            SELECT id
            FROM atendimentos
            WHERE
                id = :atendimento_id
                AND paciente_id = :paciente_id
            LIMIT 1
            "
        );

        $stmtAtendimento->execute([
            ':atendimento_id' =>
                $atendimentoId,

            ':paciente_id' =>
                $pacienteId
        ]);

        if (!$stmtAtendimento->fetchColumn()) {
            $pdo->rollBack();

            responder(
                false,
                'O atendimento não pertence ao paciente informado.',
                [],
                422
            );
        }
    }

    $usuarioId =
        $_SESSION['usuario_id'] ??
        $_SESSION['id_usuario'] ??
        null;

    $stmtSaida = $pdo->prepare(
        "
        INSERT INTO saidas_estoque (
            atendimento_id,
            paciente_id,
            usuario_id,
            destino,
            motivo,
            observacoes
        )
        VALUES (
            :atendimento_id,
            :paciente_id,
            :usuario_id,
            :destino,
            :motivo,
            :observacoes
        )
        "
    );

    $stmtSaida->execute([
        ':atendimento_id' =>
            $atendimentoId > 0
                ? $atendimentoId
                : null,

        ':paciente_id' =>
            $pacienteId > 0
                ? $pacienteId
                : null,

        ':usuario_id' =>
            $usuarioId,

        ':destino' =>
            $destino,

        ':motivo' =>
            $motivo !== ''
                ? $motivo
                : null,

        ':observacoes' =>
            $observacoes !== ''
                ? $observacoes
                : null
    ]);

    $saidaId =
        (int) $pdo->lastInsertId();

    $stmtEstoque = $pdo->prepare(
        "
        SELECT
            ea.id,
            ea.material_id,
            ea.lote,
            ea.validade,
            ea.quantidade_atual,
            ea.ativo

        FROM estoque_almoxarifado ea

        WHERE ea.id = :id

        LIMIT 1
        FOR UPDATE
        "
    );

    $stmtItem = $pdo->prepare(
        "
        INSERT INTO saida_estoque_itens (
            saida_id,
            estoque_id,
            material_id,
            lote,
            quantidade
        )
        VALUES (
            :saida_id,
            :estoque_id,
            :material_id,
            :lote,
            :quantidade
        )
        "
    );

    $stmtUpdate = $pdo->prepare(
        "
        UPDATE estoque_almoxarifado
        SET quantidade_atual = :quantidade_atual
        WHERE id = :id
        "
    );

    foreach ($itensValidados as $item) {
        $stmtEstoque->execute([
            ':id' =>
                $item['estoque_id']
        ]);

        $estoque =
            $stmtEstoque->fetch(PDO::FETCH_ASSOC);

        if (!$estoque) {
            $pdo->rollBack();

            responder(
                false,
                'Um dos lotes selecionados não existe.',
                [],
                422
            );
        }

        if (
            (int) $estoque['ativo'] !== 1
        ) {
            $pdo->rollBack();

            responder(
                false,
                'Um dos lotes selecionados está inativo.',
                [],
                422
            );
        }

        if (
            $estoque['validade'] !== null &&
            $estoque['validade'] < date('Y-m-d')
        ) {
            $pdo->rollBack();

            responder(
                false,
                'Não é permitido retirar material de lote vencido.',
                [],
                422
            );
        }

        $saldoAtual =
            (float) $estoque['quantidade_atual'];

        if (
            $item['quantidade'] >
            $saldoAtual + 0.000001
        ) {
            $pdo->rollBack();

            responder(
                false,
                'A quantidade solicitada é maior que o saldo disponível de um dos lotes.',
                [],
                422
            );
        }

        $novoSaldo =
            $saldoAtual -
            $item['quantidade'];

        $stmtItem->execute([
            ':saida_id' =>
                $saidaId,

            ':estoque_id' =>
                $estoque['id'],

            ':material_id' =>
                $estoque['material_id'],

            ':lote' =>
                $estoque['lote'],

            ':quantidade' =>
                $item['quantidade']
        ]);

        $stmtUpdate->execute([
            ':quantidade_atual' =>
                $novoSaldo,

            ':id' =>
                $estoque['id']
        ]);
    }

    $pdo->commit();

    responder(
        true,
        'Saída registrada e estoque atualizado com sucesso.',
        [
            'saida_id' =>
                $saidaId
        ],
        201
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro na saída de estoque: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível registrar a saída.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
