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

function inteiroOuNull(mixed $valor): ?int
{
    if ($valor === null || $valor === '') return null;
    $numero = filter_var($valor, FILTER_VALIDATE_INT);
    return $numero === false ? null : $numero;
}

function decimalOuNull(mixed $valor): ?float
{
    if ($valor === null || $valor === '') return null;
    $valor = str_replace(',', '.', (string) $valor);
    return is_numeric($valor) ? (float) $valor : null;
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
            ORDER BY
                a.data_entrada ASC,
                a.id ASC
        ");

        responder(true, 'Atendimentos em aberto carregados.', [
            'atendimentos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
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
                a.origem,
                a.especialidade,
                a.profissional_responsavel,
                a.status,
                a.data_entrada,
                p.nome AS paciente_nome,
                p.cpf AS paciente_cpf,
                p.data_nascimento,
                p.sexo,
                p.telefone,
                p.cartao_sus
            FROM atendimentos a
            INNER JOIN pacientes p
                ON p.id = a.paciente_id
            WHERE
                (a.numero_atendimento = :numero OR CAST(a.id AS CHAR) = :numero_id)
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
                id,
                pressao_sistolica,
                pressao_diastolica,
                frequencia_cardiaca,
                frequencia_respiratoria,
                temperatura,
                saturacao,
                glicemia,
                peso,
                escala_dor,
                observacoes,
                data_hora,
                usuario_id
            FROM afericoes
            WHERE atendimento_id = :atendimento_id
            ORDER BY data_hora DESC, id DESC
        ");

        $stmt->execute([
            ':atendimento_id' => $atendimentoId
        ]);

        responder(true, 'Histórico carregado.', [
            'afericoes' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    responder(false, 'Ação inválida.', [], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método não permitido.', [], 405);
}

$entrada = json_decode(file_get_contents('php://input') ?: '', true);

if (!is_array($entrada)) {
    responder(false, 'Os dados enviados são inválidos.', [], 400);
}

$atendimentoId = (int) ($entrada['atendimento_id'] ?? 0);
$pacienteId = (int) ($entrada['paciente_id'] ?? 0);

if ($atendimentoId <= 0 || $pacienteId <= 0) {
    responder(false, 'Atendimento ou paciente inválido.', [], 422);
}

$pressaoSistolica = inteiroOuNull($entrada['pressao_sistolica'] ?? null);
$pressaoDiastolica = inteiroOuNull($entrada['pressao_diastolica'] ?? null);
$frequenciaCardiaca = inteiroOuNull($entrada['frequencia_cardiaca'] ?? null);
$frequenciaRespiratoria = inteiroOuNull($entrada['frequencia_respiratoria'] ?? null);
$temperatura = decimalOuNull($entrada['temperatura'] ?? null);
$saturacao = inteiroOuNull($entrada['saturacao'] ?? null);
$glicemia = inteiroOuNull($entrada['glicemia'] ?? null);
$peso = decimalOuNull($entrada['peso'] ?? null);
$escalaDor = inteiroOuNull($entrada['escala_dor'] ?? null);
$observacoes = trim((string) ($entrada['observacoes'] ?? ''));

$temAlgumDado =
    $pressaoSistolica !== null ||
    $pressaoDiastolica !== null ||
    $frequenciaCardiaca !== null ||
    $frequenciaRespiratoria !== null ||
    $temperatura !== null ||
    $saturacao !== null ||
    $glicemia !== null ||
    $peso !== null ||
    $escalaDor !== null ||
    $observacoes !== '';

if (!$temAlgumDado) {
    responder(false, 'Informe pelo menos um dado da aferição.', [], 422);
}

if ($escalaDor !== null && ($escalaDor < 0 || $escalaDor > 10)) {
    responder(false, 'A escala de dor deve estar entre 0 e 10.', [], 422);
}

if ($saturacao !== null && ($saturacao < 0 || $saturacao > 100)) {
    responder(false, 'A saturação deve estar entre 0 e 100%.', [], 422);
}

try {
    $stmtAtendimento = $pdo->prepare("
        SELECT id
        FROM atendimentos
        WHERE id = :atendimento_id
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
            'O atendimento não está mais aberto para novas aferições.',
            [],
            422
        );
    }

    $usuarioId =
        $_SESSION['usuario_id'] ??
        $_SESSION['id_usuario'] ??
        null;

    $stmt = $pdo->prepare("
        INSERT INTO afericoes (
            atendimento_id,
            paciente_id,
            usuario_id,
            pressao_sistolica,
            pressao_diastolica,
            frequencia_cardiaca,
            frequencia_respiratoria,
            temperatura,
            saturacao,
            glicemia,
            peso,
            escala_dor,
            observacoes
        )
        VALUES (
            :atendimento_id,
            :paciente_id,
            :usuario_id,
            :pressao_sistolica,
            :pressao_diastolica,
            :frequencia_cardiaca,
            :frequencia_respiratoria,
            :temperatura,
            :saturacao,
            :glicemia,
            :peso,
            :escala_dor,
            :observacoes
        )
    ");

    $stmt->execute([
        ':atendimento_id' => $atendimentoId,
        ':paciente_id' => $pacienteId,
        ':usuario_id' => $usuarioId,
        ':pressao_sistolica' => $pressaoSistolica,
        ':pressao_diastolica' => $pressaoDiastolica,
        ':frequencia_cardiaca' => $frequenciaCardiaca,
        ':frequencia_respiratoria' => $frequenciaRespiratoria,
        ':temperatura' => $temperatura,
        ':saturacao' => $saturacao,
        ':glicemia' => $glicemia,
        ':peso' => $peso,
        ':escala_dor' => $escalaDor,
        ':observacoes' => $observacoes !== '' ? $observacoes : null
    ]);

    responder(true, 'Aferição registrada com sucesso.', [
        'afericao_id' => (int) $pdo->lastInsertId()
    ], 201);

} catch (Throwable $erro) {
    error_log('Erro ao salvar aferição: ' . $erro->getMessage());

    responder(false, 'Não foi possível registrar a aferição.', [
        'erro' => $erro->getMessage()
    ], 500);
}
