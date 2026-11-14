<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/database.php';

if (file_exists(__DIR__ . '/../../../includes/auth.php')) {
    require_once __DIR__ . '/../../../includes/auth.php';
}

if (file_exists(__DIR__ . '/../../../includes/auditoria.php')) {
    require_once __DIR__ . '/../../../includes/auditoria.php';
}

function responderAlergia(
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderAlergia(
        false,
        'Método não permitido.',
        [],
        405
    );
}

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);

if ($usuarioId <= 0) {
    responderAlergia(
        false,
        'Sessão inválida. Faça login novamente.',
        [],
        401
    );
}

$entrada = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($entrada)) {
    $entrada = $_POST;
}

$acao = trim((string) ($entrada['acao'] ?? 'registrar'));
$atendimentoId =
    (int) ($entrada['atendimento_id'] ?? 0);

if ($atendimentoId <= 0) {
    responderAlergia(
        false,
        'Atendimento inválido.',
        [],
        422
    );
}

try {
    /*
     * Segurança: resolve o médico pela sessão e confirma que
     * o atendimento pertence ao profissional.
     */
    $stmtContexto = $pdo->prepare("
        SELECT
            a.id AS atendimento_id,
            a.paciente_id,
            a.numero_atendimento,
            a.profissional_id,
            a.status AS atendimento_status,
            m.id AS medico_id,
            m.nome AS medico_nome
        FROM atendimentos a
        INNER JOIN medicos m
            ON m.id = a.profissional_id
        WHERE a.id = :atendimento_id
          AND m.usuario_id = :usuario_id
          AND m.ativo = 1
        LIMIT 1
    ");

    $stmtContexto->execute([
        ':atendimento_id' => $atendimentoId,
        ':usuario_id' => $usuarioId
    ]);

    $contexto =
        $stmtContexto->fetch(PDO::FETCH_ASSOC);

    if (!$contexto) {
        responderAlergia(
            false,
            'Este atendimento não pertence ao profissional logado.',
            [],
            403
        );
    }

    if (
        in_array(
            $contexto['atendimento_status'],
            ['FINALIZADO', 'CANCELADO'],
            true
        )
    ) {
        responderAlergia(
            false,
            'O atendimento já foi finalizado e não permite novos registros clínicos.',
            [],
            409
        );
    }

    if ($acao === 'registrar') {
        $substancia =
            trim((string) ($entrada['substancia'] ?? ''));

        $tipo =
            strtoupper(trim((string) ($entrada['tipo'] ?? 'OUTRO')));

        $reacao =
            trim((string) ($entrada['reacao'] ?? ''));

        $gravidade =
            strtoupper(
                trim(
                    (string) (
                        $entrada['gravidade']
                        ?? 'NAO_INFORMADA'
                    )
                )
            );

        $observacoes =
            trim((string) ($entrada['observacoes'] ?? ''));

        $tiposPermitidos = [
            'MEDICAMENTO',
            'ALIMENTO',
            'AMBIENTAL',
            'MATERIAL',
            'OUTRO'
        ];

        $gravidadesPermitidas = [
            'NAO_INFORMADA',
            'LEVE',
            'MODERADA',
            'GRAVE'
        ];

        if (
            $substancia === ''
            || mb_strlen($substancia) > 150
        ) {
            responderAlergia(
                false,
                'Informe a substância ou agente da alergia.',
                [],
                422
            );
        }

        if (!in_array($tipo, $tiposPermitidos, true)) {
            responderAlergia(
                false,
                'Tipo de alergia inválido.',
                [],
                422
            );
        }

        if (!in_array($gravidade, $gravidadesPermitidas, true)) {
            responderAlergia(
                false,
                'Gravidade inválida.',
                [],
                422
            );
        }

        /*
         * Evita duplicar uma alergia ativa com o mesmo nome
         * para o mesmo paciente.
         */
        $stmtDuplicada = $pdo->prepare("
            SELECT id
            FROM alergias_paciente
            WHERE paciente_id = :paciente_id
              AND LOWER(TRIM(substancia))
                  = LOWER(TRIM(:substancia))
              AND status = 'ATIVA'
            LIMIT 1
        ");

        $stmtDuplicada->execute([
            ':paciente_id' =>
                (int) $contexto['paciente_id'],
            ':substancia' => $substancia
        ]);

        if ($stmtDuplicada->fetchColumn()) {
            responderAlergia(
                false,
                'Já existe uma alergia ativa cadastrada para esta substância.',
                [],
                409
            );
        }

        $pdo->beginTransaction();

        $stmtInsert = $pdo->prepare("
            INSERT INTO alergias_paciente (
                paciente_id,
                atendimento_id,
                substancia,
                tipo,
                reacao,
                gravidade,
                observacoes,
                status,
                registrado_por_usuario_id,
                registrado_por_medico_id,
                criado_em,
                atualizado_em
            )
            VALUES (
                :paciente_id,
                :atendimento_id,
                :substancia,
                :tipo,
                :reacao,
                :gravidade,
                :observacoes,
                'ATIVA',
                :usuario_id,
                :medico_id,
                NOW(),
                NOW()
            )
        ");

        $stmtInsert->execute([
            ':paciente_id' =>
                (int) $contexto['paciente_id'],
            ':atendimento_id' => $atendimentoId,
            ':substancia' => $substancia,
            ':tipo' => $tipo,
            ':reacao' => $reacao !== '' ? $reacao : null,
            ':gravidade' => $gravidade,
            ':observacoes' =>
                $observacoes !== ''
                    ? $observacoes
                    : null,
            ':usuario_id' => $usuarioId,
            ':medico_id' =>
                (int) $contexto['medico_id']
        ]);

        $alergiaId = (int) $pdo->lastInsertId();

        $pdo->commit();

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'CRIAR',
                'Alergia registrada no prontuário do paciente.',
                'Prontuário',
                'Alergias',
                'alergias_paciente',
                $alergiaId,
                null,
                [
                    'paciente_id' =>
                        (int) $contexto['paciente_id'],
                    'atendimento_id' =>
                        $atendimentoId,
                    'substancia' =>
                        $substancia,
                    'tipo' => $tipo,
                    'gravidade' =>
                        $gravidade
                ],
                $usuarioId
            );
        }

        responderAlergia(
            true,
            'Alergia registrada com sucesso.',
            ['id' => $alergiaId],
            201
        );
    }

    if ($acao === 'inativar') {
        $alergiaId =
            (int) ($entrada['alergia_id'] ?? 0);

        if ($alergiaId <= 0) {
            responderAlergia(
                false,
                'Alergia inválida.',
                [],
                422
            );
        }

        $stmtAlergia = $pdo->prepare("
            SELECT
                id,
                paciente_id,
                substancia,
                tipo,
                reacao,
                gravidade,
                observacoes,
                status
            FROM alergias_paciente
            WHERE id = :id
              AND paciente_id = :paciente_id
            LIMIT 1
        ");

        $stmtAlergia->execute([
            ':id' => $alergiaId,
            ':paciente_id' =>
                (int) $contexto['paciente_id']
        ]);

        $alergia =
            $stmtAlergia->fetch(PDO::FETCH_ASSOC);

        if (!$alergia) {
            responderAlergia(
                false,
                'Registro de alergia não encontrado.',
                [],
                404
            );
        }

        if ($alergia['status'] !== 'ATIVA') {
            responderAlergia(
                false,
                'Esta alergia já está inativa.',
                [],
                409
            );
        }

        $stmtInativar = $pdo->prepare("
            UPDATE alergias_paciente
            SET
                status = 'INATIVA',
                inativado_em = NOW(),
                atualizado_em = NOW()
            WHERE id = :id
              AND status = 'ATIVA'
        ");

        $stmtInativar->execute([
            ':id' => $alergiaId
        ]);

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'INATIVAR',
                'Alergia inativada no prontuário do paciente.',
                'Prontuário',
                'Alergias',
                'alergias_paciente',
                $alergiaId,
                $alergia,
                [
                    'status' => 'INATIVA',
                    'paciente_id' =>
                        (int) $contexto['paciente_id'],
                    'atendimento_id' =>
                        $atendimentoId
                ],
                $usuarioId
            );
        }

        responderAlergia(
            true,
            'Alergia inativada com sucesso.'
        );
    }

    responderAlergia(
        false,
        'Ação inválida.',
        [],
        422
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro na API de alergias do prontuário: '
        . $erro->getMessage()
    );

    responderAlergia(
        false,
        'Não foi possível concluir a operação de alergia.',
        [],
        500
    );
}
