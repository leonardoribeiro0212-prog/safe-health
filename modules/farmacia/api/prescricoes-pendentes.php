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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim((string) ($_GET['acao'] ?? ''));

    if ($acao === 'listar') {
        $q = trim((string) ($_GET['q'] ?? ''));

        $sql = "
            SELECT
                pm.id,
                pm.atendimento_id,
                pm.paciente_id,
                pm.status,
                pm.data_prescricao,
                pm.data_liberacao_farmacia,
                p.nome AS paciente_nome,
                p.cpf AS paciente_cpf,
                a.numero_atendimento,
                a.convenio,
                a.tipo_atendimento,
                COUNT(pmi.id) AS total_itens,
                SUM(
                    CASE
                        WHEN pmi.status_farmacia = 'DISPENSADO'
                        THEN 1
                        ELSE 0
                    END
                ) AS itens_dispensados
            FROM prescricoes_medicas pm
            INNER JOIN pacientes p
                ON p.id = pm.paciente_id
            INNER JOIN atendimentos a
                ON a.id = pm.atendimento_id
            LEFT JOIN prescricao_medicamentos pmi
                ON pmi.prescricao_id = pm.id
            WHERE
                pm.status IN (
                    'LIBERADA_FARMACIA',
                    'EM_DISPENSACAO'
                )
                AND a.status <> 'ALTA'
                AND a.data_alta IS NULL
        ";

        $params = [];

        if ($q !== '') {
            $numero = preg_replace('/\D/', '', $q) ?? '';

            $sql .= "
                AND (
                    a.numero_atendimento LIKE :q
                    OR p.nome LIKE :q
                    OR CAST(pm.id AS CHAR) = :id_exato
                )
            ";

            $params[':q'] = '%' . $q . '%';
            $params[':id_exato'] = $numero;
        }

        $sql .= "
            GROUP BY
                pm.id,
                pm.atendimento_id,
                pm.paciente_id,
                pm.status,
                pm.data_prescricao,
                pm.data_liberacao_farmacia,
                p.nome,
                p.cpf,
                a.numero_atendimento,
                a.convenio,
                a.tipo_atendimento

            ORDER BY
                COALESCE(
                    pm.data_liberacao_farmacia,
                    pm.data_prescricao
                ) ASC,
                pm.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        responder(
            true,
            'Prescrições carregadas.',
            [
                'prescricoes' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'detalhes') {
        $id = filter_var(
            $_GET['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id) {
            responder(
                false,
                'Prescrição inválida.',
                [],
                422
            );
        }

        $stmt = $pdo->prepare(
            "
            SELECT
                pm.id,
                pm.atendimento_id,
                pm.paciente_id,
                pm.medico_id,
                pm.usuario_id,
                pm.status,
                pm.observacoes,
                pm.data_prescricao,
                pm.data_liberacao_farmacia,
                p.nome AS paciente_nome,
                p.cpf AS paciente_cpf,
                a.numero_atendimento,
                a.convenio,
                a.tipo_atendimento
            FROM prescricoes_medicas pm
            INNER JOIN pacientes p
                ON p.id = pm.paciente_id
            INNER JOIN atendimentos a
                ON a.id = pm.atendimento_id
            WHERE
                pm.id = :id
                AND pm.status IN (
                    'LIBERADA_FARMACIA',
                    'EM_DISPENSACAO'
                )
            LIMIT 1
            "
        );

        $stmt->execute([
            ':id' => $id
        ]);

        $prescricao =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prescricao) {
            responder(
                false,
                'Prescrição não encontrada ou não está pendente.',
                [],
                404
            );
        }

        $stmtItens = $pdo->prepare(
            "
            SELECT
                pmi.id,
                pmi.medicamento_id,
                pmi.dose,
                pmi.via_administracao,
                pmi.frequencia,
                pmi.quantidade,
                pmi.instrucoes,
                pmi.status_farmacia,
                m.nome AS medicamento_nome,
                m.principio_ativo,
                m.apresentacao,
                m.concentracao,
                m.unidade_medida
            FROM prescricao_medicamentos pmi
            INNER JOIN medicamentos m
                ON m.id = pmi.medicamento_id
            WHERE
                pmi.prescricao_id = :prescricao_id
                AND pmi.status_farmacia <> 'CANCELADO'
            ORDER BY
                pmi.id
            "
        );

        $stmtItens->execute([
            ':prescricao_id' => $id
        ]);

        responder(
            true,
            'Detalhes carregados.',
            [
                'prescricao' => $prescricao,
                'itens' =>
                    $stmtItens->fetchAll(PDO::FETCH_ASSOC)
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

$acao = trim(
    (string) ($entrada['acao'] ?? '')
);

if ($acao !== 'iniciar_dispensacao') {
    responder(
        false,
        'Ação inválida.',
        [],
        400
    );
}

$prescricaoId =
    (int) ($entrada['prescricao_id'] ?? 0);

if ($prescricaoId <= 0) {
    responder(
        false,
        'Prescrição inválida.',
        [],
        422
    );
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "
        SELECT
            pm.id,
            pm.status,
            a.status AS atendimento_status,
            a.data_alta
        FROM prescricoes_medicas pm
        INNER JOIN atendimentos a
            ON a.id = pm.atendimento_id
        WHERE pm.id = :id
        LIMIT 1
        FOR UPDATE
        "
    );

    $stmt->execute([
        ':id' => $prescricaoId
    ]);

    $prescricao =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prescricao) {
        $pdo->rollBack();

        responder(
            false,
            'Prescrição não encontrada.',
            [],
            404
        );
    }

    if (
        $prescricao['atendimento_status'] === 'ALTA' ||
        $prescricao['data_alta'] !== null
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
        $prescricao['status'],
        ['LIBERADA_FARMACIA', 'EM_DISPENSACAO'],
        true
    )) {
        $pdo->rollBack();

        responder(
            false,
            'Esta prescrição não está disponível para dispensação.',
            [],
            422
        );
    }

    if ($prescricao['status'] === 'LIBERADA_FARMACIA') {
        $stmtUpdate = $pdo->prepare(
            "
            UPDATE prescricoes_medicas
            SET status = 'EM_DISPENSACAO'
            WHERE id = :id
            "
        );

        $stmtUpdate->execute([
            ':id' => $prescricaoId
        ]);
    }

    $pdo->commit();

    responder(
        true,
        'Dispensação iniciada.',
        [
            'prescricao_id' => $prescricaoId,
            'url' =>
                '/safe-health/modules/farmacia/dispensacao.php?prescricao_id=' .
                $prescricaoId
        ]
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro em prescrições pendentes: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível iniciar a dispensação.',
        [
            'erro' => $erro->getMessage()
        ],
        500
    );
}
