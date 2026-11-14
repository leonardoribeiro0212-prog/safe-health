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

function decimalValido(
    mixed $valor
): ?float {
    if (
        $valor === null ||
        $valor === ''
    ) {
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

    if ($acao === 'buscar') {
        $termo = trim(
            (string) ($_GET['termo'] ?? '')
        );

        if ($termo === '') {
            responder(
                false,
                'Informe uma prescrição ou atendimento.',
                [],
                422
            );
        }

        $numero =
            preg_replace('/\D/', '', $termo) ?? '';

        $stmt = $pdo->prepare(
            "
            SELECT
                pm.id,
                pm.atendimento_id,
                pm.paciente_id,
                pm.status,
                pm.observacoes,
                pm.data_prescricao,
                pm.data_liberacao_farmacia,
                p.nome AS paciente_nome,
                p.cpf AS paciente_cpf,
                a.numero_atendimento,
                a.convenio,
                a.status AS atendimento_status,
                a.data_alta

            FROM prescricoes_medicas pm

            INNER JOIN pacientes p
                ON p.id = pm.paciente_id

            INNER JOIN atendimentos a
                ON a.id = pm.atendimento_id

            WHERE
                (
                    pm.id = :prescricao_id
                    OR a.numero_atendimento = :numero_atendimento
                )
                AND pm.status IN (
                    'LIBERADA_FARMACIA',
                    'EM_DISPENSACAO',
                    'DISPENSADA'
                )

            ORDER BY
                pm.id DESC

            LIMIT 1
            "
        );

        $stmt->execute([
            ':prescricao_id' =>
                $numero !== ''
                    ? (int) $numero
                    : 0,

            ':numero_atendimento' =>
                $termo
        ]);

        $prescricao =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prescricao) {
            responder(
                false,
                'Prescrição não encontrada.',
                [],
                404
            );
        }

        $stmtItens = $pdo->prepare(
            "
            SELECT
                pmi.id AS prescricao_item_id,
                pmi.medicamento_id,
                pmi.dose,
                pmi.via_administracao,
                pmi.frequencia,
                pmi.quantidade,
                pmi.instrucoes,
                pmi.status_farmacia,

                m.nome AS medicamento_nome,
                m.concentracao,
                m.apresentacao,
                m.unidade_medida,

                COALESCE(
                    SUM(df.quantidade_dispensada),
                    0
                ) AS quantidade_ja_dispensada

            FROM prescricao_medicamentos pmi

            INNER JOIN medicamentos m
                ON m.id = pmi.medicamento_id

            LEFT JOIN dispensacoes_farmacia df
                ON df.prescricao_item_id = pmi.id

            WHERE
                pmi.prescricao_id = :prescricao_id
                AND pmi.status_farmacia <> 'CANCELADO'

            GROUP BY
                pmi.id,
                pmi.medicamento_id,
                pmi.dose,
                pmi.via_administracao,
                pmi.frequencia,
                pmi.quantidade,
                pmi.instrucoes,
                pmi.status_farmacia,
                m.nome,
                m.concentracao,
                m.apresentacao,
                m.unidade_medida

            ORDER BY
                pmi.id
            "
        );

        $stmtItens->execute([
            ':prescricao_id' =>
                $prescricao['id']
        ]);

        responder(
            true,
            'Prescrição carregada.',
            [
                'prescricao' =>
                    $prescricao,

                'itens' =>
                    $stmtItens->fetchAll(
                        PDO::FETCH_ASSOC
                    )
            ]
        );
    }

    if ($acao === 'historico_item') {
        $itemId = filter_var(
            $_GET['item_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$itemId) {
            responder(
                false,
                'Item inválido.',
                [],
                422
            );
        }

        $stmt = $pdo->prepare(
            "
            SELECT
                df.id,
                df.quantidade_solicitada,
                df.quantidade_dispensada,
                df.lote,
                df.validade,
                df.status,
                df.observacoes,
                df.data_dispensacao,
                u.nome AS usuario_nome

            FROM dispensacoes_farmacia df

            LEFT JOIN usuarios u
                ON u.id = df.usuario_id

            WHERE
                df.prescricao_item_id = :item_id

            ORDER BY
                df.data_dispensacao DESC,
                df.id DESC
            "
        );

        $stmt->execute([
            ':item_id' =>
                $itemId
        ]);

        responder(
            true,
            'Histórico carregado.',
            [
                'dispensacoes' =>
                    $stmt->fetchAll(
                        PDO::FETCH_ASSOC
                    )
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

$prescricaoId =
    (int) ($entrada['prescricao_id'] ?? 0);

$itemId =
    (int) ($entrada['prescricao_item_id'] ?? 0);

$quantidadeDispensada =
    decimalValido(
        $entrada['quantidade_dispensada'] ?? null
    );

$lote = trim(
    (string) ($entrada['lote'] ?? '')
);

$validade = trim(
    (string) ($entrada['validade'] ?? '')
);

$observacoes = trim(
    (string) ($entrada['observacoes'] ?? '')
);

if (
    $prescricaoId <= 0 ||
    $itemId <= 0
) {
    responder(
        false,
        'Prescrição ou item inválido.',
        [],
        422
    );
}

if (
    $quantidadeDispensada === null ||
    $quantidadeDispensada <= 0
) {
    responder(
        false,
        'Informe uma quantidade dispensada válida.',
        [],
        422
    );
}

if (
    $validade !== '' &&
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $validade
    )
) {
    responder(
        false,
        'Data de validade inválida.',
        [],
        422
    );
}

try {
    $pdo->beginTransaction();

    $stmtItem = $pdo->prepare(
        "
        SELECT
            pmi.id,
            pmi.prescricao_id,
            pmi.medicamento_id,
            pmi.quantidade,
            pmi.status_farmacia,

            pm.atendimento_id,
            pm.paciente_id,
            pm.status AS prescricao_status,

            a.status AS atendimento_status,
            a.data_alta,

            COALESCE(
                (
                    SELECT SUM(df.quantidade_dispensada)
                    FROM dispensacoes_farmacia df
                    WHERE df.prescricao_item_id = pmi.id
                ),
                0
            ) AS quantidade_ja_dispensada

        FROM prescricao_medicamentos pmi

        INNER JOIN prescricoes_medicas pm
            ON pm.id = pmi.prescricao_id

        INNER JOIN atendimentos a
            ON a.id = pm.atendimento_id

        WHERE
            pmi.id = :item_id
            AND pmi.prescricao_id = :prescricao_id

        LIMIT 1
        FOR UPDATE
        "
    );

    $stmtItem->execute([
        ':item_id' =>
            $itemId,

        ':prescricao_id' =>
            $prescricaoId
    ]);

    $item =
        $stmtItem->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $pdo->rollBack();

        responder(
            false,
            'Item da prescrição não encontrado.',
            [],
            404
        );
    }

    if (
        $item['atendimento_status'] === 'ALTA' ||
        $item['data_alta'] !== null
    ) {
        $pdo->rollBack();

        responder(
            false,
            'O atendimento já foi encerrado.',
            [],
            422
        );
    }

    if (!in_array(
        $item['prescricao_status'],
        [
            'LIBERADA_FARMACIA',
            'EM_DISPENSACAO'
        ],
        true
    )) {
        $pdo->rollBack();

        responder(
            false,
            'A prescrição não está disponível para dispensação.',
            [],
            422
        );
    }

    $quantidadeSolicitada =
        (float) $item['quantidade'];

    $jaDispensado =
        (float) $item['quantidade_ja_dispensada'];

    $restante =
        max(
            0,
            $quantidadeSolicitada -
            $jaDispensado
        );

    if ($restante <= 0) {
        $pdo->rollBack();

        responder(
            false,
            'Este item já foi totalmente dispensado.',
            [],
            422
        );
    }

    if (
        $quantidadeDispensada >
        $restante + 0.000001
    ) {
        $pdo->rollBack();

        responder(
            false,
            'A quantidade informada é maior que a quantidade restante.',
            [],
            422
        );
    }

    $novoTotal =
        $jaDispensado +
        $quantidadeDispensada;

    $statusItem =
        $novoTotal + 0.000001 >=
        $quantidadeSolicitada
            ? 'DISPENSADO'
            : 'PARCIAL';

    $usuarioId =
        $_SESSION['usuario_id'] ??
        $_SESSION['id_usuario'] ??
        null;

    $stmtInsert = $pdo->prepare(
        "
        INSERT INTO dispensacoes_farmacia (
            prescricao_id,
            prescricao_item_id,
            atendimento_id,
            paciente_id,
            medicamento_id,
            usuario_id,
            quantidade_solicitada,
            quantidade_dispensada,
            lote,
            validade,
            status,
            observacoes
        )
        VALUES (
            :prescricao_id,
            :prescricao_item_id,
            :atendimento_id,
            :paciente_id,
            :medicamento_id,
            :usuario_id,
            :quantidade_solicitada,
            :quantidade_dispensada,
            :lote,
            :validade,
            :status,
            :observacoes
        )
        "
    );

    $stmtInsert->execute([
        ':prescricao_id' =>
            $prescricaoId,

        ':prescricao_item_id' =>
            $itemId,

        ':atendimento_id' =>
            $item['atendimento_id'],

        ':paciente_id' =>
            $item['paciente_id'],

        ':medicamento_id' =>
            $item['medicamento_id'],

        ':usuario_id' =>
            $usuarioId,

        ':quantidade_solicitada' =>
            $quantidadeSolicitada,

        ':quantidade_dispensada' =>
            $quantidadeDispensada,

        ':lote' =>
            $lote !== ''
                ? $lote
                : null,

        ':validade' =>
            $validade !== ''
                ? $validade
                : null,

        ':status' =>
            $statusItem,

        ':observacoes' =>
            $observacoes !== ''
                ? $observacoes
                : null
    ]);

    $stmtUpdateItem = $pdo->prepare(
        "
        UPDATE prescricao_medicamentos
        SET
            status_farmacia = :status
        WHERE id = :id
        "
    );

    $stmtUpdateItem->execute([
        ':status' =>
            $statusItem,

        ':id' =>
            $itemId
    ]);

    $stmtPendentes = $pdo->prepare(
        "
        SELECT COUNT(*)
        FROM prescricao_medicamentos
        WHERE
            prescricao_id = :prescricao_id
            AND status_farmacia NOT IN (
                'DISPENSADO',
                'CANCELADO'
            )
        "
    );

    $stmtPendentes->execute([
        ':prescricao_id' =>
            $prescricaoId
    ]);

    $pendentes =
        (int) $stmtPendentes->fetchColumn();

    $novoStatusPrescricao =
        $pendentes === 0
            ? 'DISPENSADA'
            : 'EM_DISPENSACAO';

    $stmtUpdatePrescricao = $pdo->prepare(
        "
        UPDATE prescricoes_medicas
        SET status = :status
        WHERE id = :id
        "
    );

    $stmtUpdatePrescricao->execute([
        ':status' =>
            $novoStatusPrescricao,

        ':id' =>
            $prescricaoId
    ]);

    $pdo->commit();

    responder(
        true,
        $statusItem === 'DISPENSADO'
            ? 'Item dispensado com sucesso.'
            : 'Dispensação parcial registrada com sucesso.',
        [
            'dispensacao_id' =>
                (int) $pdo->lastInsertId(),

            'status_item' =>
                $statusItem,

            'status_prescricao' =>
                $novoStatusPrescricao
        ],
        201
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro na dispensação: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível registrar a dispensação.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
