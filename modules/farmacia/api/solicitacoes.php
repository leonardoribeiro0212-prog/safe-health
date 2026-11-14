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

    if ($acao === 'listar_abertos') {
        $stmt = $pdo->query(
            "
            SELECT
                a.id AS atendimento_id,
                a.numero_atendimento,
                a.paciente_id,
                a.tipo_atendimento,
                a.status,
                a.data_entrada,
                a.convenio,
                p.nome AS paciente_nome,
                p.cpf AS paciente_cpf
            FROM atendimentos a
            INNER JOIN pacientes p
                ON p.id = a.paciente_id
            WHERE
                a.status <> 'ALTA'
                AND a.data_alta IS NULL
            ORDER BY
                a.data_entrada ASC,
                a.id ASC
            "
        );

        responder(
            true,
            'Atendimentos carregados.',
            [
                'atendimentos' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'itens') {
        $medicamentos = $pdo->query(
            "
            SELECT
                id,
                nome,
                concentracao,
                apresentacao
            FROM medicamentos
            WHERE ativo = 1
            ORDER BY nome
            "
        )->fetchAll(PDO::FETCH_ASSOC);

        $materiais = $pdo->query(
            "
            SELECT
                id,
                nome,
                unidade_medida,
                categoria
            FROM materiais_hospitalares
            WHERE ativo = 1
            ORDER BY nome
            "
        )->fetchAll(PDO::FETCH_ASSOC);

        responder(
            true,
            'Itens carregados.',
            [
                'medicamentos' => $medicamentos,
                'materiais' => $materiais
            ]
        );
    }

    if ($acao === 'buscar_atendimento') {
        $numero = trim((string) ($_GET['numero'] ?? ''));

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
                a.tipo_atendimento,
                a.status,
                a.data_entrada,
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
                AND a.status <> 'ALTA'
                AND a.data_alta IS NULL
            LIMIT 1
            "
        );

        $stmt->execute([
            ':numero' => $numero,
            ':numero_id' => $numeroSemPrefixo
        ]);

        $atendimento =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$atendimento) {
            responder(
                false,
                'Atendimento não encontrado ou já encerrado.',
                [],
                404
            );
        }

        responder(
            true,
            'Atendimento localizado.',
            [
                'atendimento' => $atendimento
            ]
        );
    }

    if ($acao === 'historico') {
        $atendimentoId = filter_var(
            $_GET['atendimento_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$atendimentoId) {
            responder(
                false,
                'Atendimento inválido.',
                [],
                422
            );
        }

        $stmt = $pdo->prepare(
            "
            SELECT
                sf.id,
                sf.tipo_solicitacao,
                sf.quantidade,
                sf.prioridade,
                sf.observacoes,
                sf.status,
                sf.criado_em,
                sf.usuario_solicitante_id,

                CASE
                    WHEN sf.tipo_solicitacao = 'MEDICAMENTO'
                        THEN m.nome
                    ELSE mh.nome
                END AS item_nome,

                u.nome AS usuario_nome

            FROM solicitacoes_farmacia sf

            LEFT JOIN medicamentos m
                ON m.id = sf.medicamento_id

            LEFT JOIN materiais_hospitalares mh
                ON mh.id = sf.material_id

            LEFT JOIN usuarios u
                ON u.id = sf.usuario_solicitante_id

            WHERE
                sf.atendimento_id = :atendimento_id

            ORDER BY
                sf.criado_em DESC,
                sf.id DESC
            "
        );

        $stmt->execute([
            ':atendimento_id' =>
                $atendimentoId
        ]);

        responder(
            true,
            'Histórico carregado.',
            [
                'solicitacoes' =>
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

$tipo =
    strtoupper(trim(
        (string) ($entrada['tipo_solicitacao'] ?? '')
    ));

$medicamentoId =
    (int) ($entrada['medicamento_id'] ?? 0);

$materialId =
    (int) ($entrada['material_id'] ?? 0);

$quantidade = str_replace(
    ',',
    '.',
    trim((string) ($entrada['quantidade'] ?? '0'))
);

$prioridade =
    strtoupper(trim(
        (string) ($entrada['prioridade'] ?? 'ROTINA')
    ));

$observacoes =
    trim((string) ($entrada['observacoes'] ?? ''));

if (
    $atendimentoId <= 0 ||
    $pacienteId <= 0
) {
    responder(
        false,
        'Atendimento ou paciente inválido.',
        [],
        422
    );
}

if (!in_array(
    $tipo,
    ['MEDICAMENTO', 'MATERIAL'],
    true
)) {
    responder(
        false,
        'Tipo de solicitação inválido.',
        [],
        422
    );
}

if (
    $quantidade === '' ||
    !is_numeric($quantidade) ||
    (float) $quantidade <= 0
) {
    responder(
        false,
        'Informe uma quantidade válida.',
        [],
        422
    );
}

if (!in_array(
    $prioridade,
    ['ROTINA', 'URGENTE'],
    true
)) {
    responder(
        false,
        'Prioridade inválida.',
        [],
        422
    );
}

if (
    $tipo === 'MEDICAMENTO' &&
    $medicamentoId <= 0
) {
    responder(
        false,
        'Selecione o medicamento.',
        [],
        422
    );
}

if (
    $tipo === 'MATERIAL' &&
    $materialId <= 0
) {
    responder(
        false,
        'Selecione o material hospitalar.',
        [],
        422
    );
}

try {
    $stmtAtendimento = $pdo->prepare(
        "
        SELECT id
        FROM atendimentos
        WHERE
            id = :atendimento_id
            AND paciente_id = :paciente_id
            AND status <> 'ALTA'
            AND data_alta IS NULL
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
        responder(
            false,
            'O atendimento não está mais aberto.',
            [],
            422
        );
    }

    if ($tipo === 'MEDICAMENTO') {
        $stmtItem = $pdo->prepare(
            "
            SELECT id
            FROM medicamentos
            WHERE id = :id
              AND ativo = 1
            LIMIT 1
            "
        );

        $stmtItem->execute([
            ':id' => $medicamentoId
        ]);

        if (!$stmtItem->fetchColumn()) {
            responder(
                false,
                'Medicamento inválido ou inativo.',
                [],
                422
            );
        }

        $materialId = 0;

    } else {
        $stmtItem = $pdo->prepare(
            "
            SELECT id
            FROM materiais_hospitalares
            WHERE id = :id
              AND ativo = 1
            LIMIT 1
            "
        );

        $stmtItem->execute([
            ':id' => $materialId
        ]);

        if (!$stmtItem->fetchColumn()) {
            responder(
                false,
                'Material inválido ou inativo.',
                [],
                422
            );
        }

        $medicamentoId = 0;
    }

    $usuarioId =
        $_SESSION['usuario_id'] ??
        $_SESSION['id_usuario'] ??
        null;

    $stmt = $pdo->prepare(
        "
        INSERT INTO solicitacoes_farmacia (
            atendimento_id,
            paciente_id,
            usuario_solicitante_id,
            tipo_solicitacao,
            medicamento_id,
            material_id,
            quantidade,
            prioridade,
            observacoes,
            status
        )
        VALUES (
            :atendimento_id,
            :paciente_id,
            :usuario_solicitante_id,
            :tipo_solicitacao,
            :medicamento_id,
            :material_id,
            :quantidade,
            :prioridade,
            :observacoes,
            'PENDENTE'
        )
        "
    );

    $stmt->execute([
        ':atendimento_id' =>
            $atendimentoId,

        ':paciente_id' =>
            $pacienteId,

        ':usuario_solicitante_id' =>
            $usuarioId,

        ':tipo_solicitacao' =>
            $tipo,

        ':medicamento_id' =>
            $medicamentoId > 0
                ? $medicamentoId
                : null,

        ':material_id' =>
            $materialId > 0
                ? $materialId
                : null,

        ':quantidade' =>
            round(
                (float) $quantidade,
                3
            ),

        ':prioridade' =>
            $prioridade,

        ':observacoes' =>
            $observacoes !== ''
                ? $observacoes
                : null
    ]);

    responder(
        true,
        'Solicitação registrada com sucesso.',
        [
            'solicitacao_id' =>
                (int) $pdo->lastInsertId()
        ],
        201
    );

} catch (Throwable $erro) {
    error_log(
        'Erro ao salvar solicitação da farmácia: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível registrar a solicitação.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
