<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';

function responder(bool $sucesso, string $mensagem, array $dados = [], int $status = 200): never
{
    http_response_code($status);

    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'dados' => $dados
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

function normalizarData(?string $valor): ?string
{
    $valor = trim((string) $valor);

    if ($valor === '') {
        return null;
    }

    $timestamp = strtotime($valor);

    return $timestamp === false
        ? null
        : date('Y-m-d H:i:s', $timestamp);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim((string) ($_GET['acao'] ?? ''));

    if ($acao === 'listar_abertos') {
        $stmt = $pdo->query("
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
            ORDER BY a.data_entrada ASC, a.id ASC
        ");

        responder(true, 'Atendimentos carregados.', [
            'atendimentos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($acao === 'medicamentos') {
        $stmt = $pdo->query("
            SELECT
                id,
                nome,
                principio_ativo,
                apresentacao,
                concentracao,
                via_administracao
            FROM medicamentos
            WHERE ativo = 1
            ORDER BY nome
        ");

        responder(true, 'Medicamentos carregados.', [
            'medicamentos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($acao === 'buscar_atendimento') {
        $numero = trim((string) ($_GET['numero'] ?? ''));

        if ($numero === '') {
            responder(false, 'Informe o número do atendimento.', [], 422);
        }

        $numeroSemPrefixo = preg_replace('/\D/', '', $numero) ?? '';

        $stmt = $pdo->prepare("
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
        ");

        $stmt->execute([
            ':numero' => $numero,
            ':numero_id' => $numeroSemPrefixo
        ]);

        $atendimento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$atendimento) {
            responder(false, 'Atendimento não encontrado ou já encerrado.', [], 404);
        }

        responder(true, 'Atendimento localizado.', [
            'atendimento' => $atendimento
        ]);
    }

    if ($acao === 'historico') {
        $atendimentoId = filter_var(
            $_GET['atendimento_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$atendimentoId) {
            responder(false, 'Atendimento inválido.', [], 422);
        }

        $stmt = $pdo->prepare("
            SELECT
                am.id,
                am.dose,
                am.via_administracao,
                am.horario_previsto,
                am.data_hora_administracao,
                am.status,
                am.motivo_nao_administracao,
                am.observacoes,
                am.criado_em,
                am.usuario_id,
                m.nome AS medicamento_nome,
                m.concentracao,
                m.apresentacao,
                u.nome AS usuario_nome
            FROM administracoes_medicamentos am
            INNER JOIN medicamentos m
                ON m.id = am.medicamento_id
            LEFT JOIN usuarios u
                ON u.id = am.usuario_id
            WHERE am.atendimento_id = :atendimento_id
            ORDER BY
                COALESCE(am.data_hora_administracao, am.criado_em) DESC,
                am.id DESC
        ");

        $stmt->execute([
            ':atendimento_id' => $atendimentoId
        ]);

        responder(true, 'Histórico carregado.', [
            'administracoes' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    responder(false, 'Ação inválida.', [], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método não permitido.', [], 405);
}

$entrada = json_decode(
    file_get_contents('php://input') ?: '',
    true
);

if (!is_array($entrada)) {
    responder(false, 'Os dados enviados são inválidos.', [], 400);
}

$atendimentoId = (int) ($entrada['atendimento_id'] ?? 0);
$pacienteId = (int) ($entrada['paciente_id'] ?? 0);
$medicamentoId = (int) ($entrada['medicamento_id'] ?? 0);

$dose = trim((string) ($entrada['dose'] ?? ''));
$via = strtoupper(trim((string) ($entrada['via_administracao'] ?? '')));
$horarioPrevisto = normalizarData($entrada['horario_previsto'] ?? null);
$dataHoraAdministracao = normalizarData($entrada['data_hora_administracao'] ?? null);
$status = strtoupper(trim((string) ($entrada['status'] ?? '')));
$motivo = trim((string) ($entrada['motivo_nao_administracao'] ?? ''));
$observacoes = trim((string) ($entrada['observacoes'] ?? ''));

if ($atendimentoId <= 0 || $pacienteId <= 0) {
    responder(false, 'Atendimento ou paciente inválido.', [], 422);
}

if ($medicamentoId <= 0) {
    responder(false, 'Selecione um medicamento.', [], 422);
}

if (!in_array($status, ['ADMINISTRADO', 'NAO_ADMINISTRADO'], true)) {
    responder(false, 'Status inválido.', [], 422);
}

if ($status === 'NAO_ADMINISTRADO' && $motivo === '') {
    responder(false, 'Informe o motivo da não administração.', [], 422);
}

if ($status === 'ADMINISTRADO' && $dataHoraAdministracao === null) {
    $dataHoraAdministracao = date('Y-m-d H:i:s');
}

if ($status === 'NAO_ADMINISTRADO') {
    $dataHoraAdministracao = null;
}

try {
    $stmtAtendimento = $pdo->prepare("
        SELECT id
        FROM atendimentos
        WHERE
            id = :atendimento_id
            AND paciente_id = :paciente_id
            AND status <> 'ALTA'
            AND data_alta IS NULL
        LIMIT 1
    ");

    $stmtAtendimento->execute([
        ':atendimento_id' => $atendimentoId,
        ':paciente_id' => $pacienteId
    ]);

    if (!$stmtAtendimento->fetchColumn()) {
        responder(
            false,
            'O atendimento não está mais aberto para administração de medicamentos.',
            [],
            422
        );
    }

    $stmtMedicamento = $pdo->prepare("
        SELECT id
        FROM medicamentos
        WHERE id = :id
          AND ativo = 1
        LIMIT 1
    ");

    $stmtMedicamento->execute([
        ':id' => $medicamentoId
    ]);

    if (!$stmtMedicamento->fetchColumn()) {
        responder(false, 'Medicamento inválido ou inativo.', [], 422);
    }

    $usuarioId =
        $_SESSION['usuario_id'] ??
        $_SESSION['id_usuario'] ??
        null;

    $stmt = $pdo->prepare("
        INSERT INTO administracoes_medicamentos (
            atendimento_id,
            paciente_id,
            medicamento_id,
            usuario_id,
            dose,
            via_administracao,
            horario_previsto,
            data_hora_administracao,
            status,
            motivo_nao_administracao,
            observacoes
        )
        VALUES (
            :atendimento_id,
            :paciente_id,
            :medicamento_id,
            :usuario_id,
            :dose,
            :via_administracao,
            :horario_previsto,
            :data_hora_administracao,
            :status,
            :motivo_nao_administracao,
            :observacoes
        )
    ");

    $stmt->execute([
        ':atendimento_id' => $atendimentoId,
        ':paciente_id' => $pacienteId,
        ':medicamento_id' => $medicamentoId,
        ':usuario_id' => $usuarioId,
        ':dose' => $dose !== '' ? $dose : null,
        ':via_administracao' => $via !== '' ? $via : null,
        ':horario_previsto' => $horarioPrevisto,
        ':data_hora_administracao' => $dataHoraAdministracao,
        ':status' => $status,
        ':motivo_nao_administracao' => $status === 'NAO_ADMINISTRADO' ? $motivo : null,
        ':observacoes' => $observacoes !== '' ? $observacoes : null
    ]);

    responder(true, 'Administração de medicamento registrada com sucesso.', [
        'administracao_id' => (int) $pdo->lastInsertId()
    ], 201);

} catch (Throwable $erro) {
    error_log(
        'Erro ao salvar administração de medicamento: ' .
        $erro->getMessage()
    );

    responder(false, 'Não foi possível registrar a administração.', [
        'erro' => $erro->getMessage()
    ], 500);
}
