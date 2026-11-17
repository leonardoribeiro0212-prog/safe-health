<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/seguranca.php';


session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../config/database.php';

if (file_exists(__DIR__ . '/../../../includes/auditoria.php')) {
    require_once __DIR__ . '/../../../includes/auditoria.php';
}

function responderEvo(
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

function contextoEvo(
    PDO $pdo,
    int $atendimentoId,
    int $usuarioId
): array {
    $stmt = $pdo->prepare("
        SELECT
            a.id AS atendimento_id,
            a.paciente_id,
            a.numero_atendimento,
            a.status AS atendimento_status,
            m.id AS medico_id,
            m.nome AS medico_nome,
            m.crm,
            m.uf_crm
        FROM atendimentos a
        INNER JOIN medicos m
            ON m.id = a.profissional_id
        WHERE a.id = :atendimento_id
          AND m.usuario_id = :usuario_id
          AND m.ativo = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':atendimento_id' => $atendimentoId,
        ':usuario_id' => $usuarioId
    ]);

    $contexto =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contexto) {
        responderEvo(
            false,
            'Este atendimento não pertence ao profissional logado.',
            [],
            403
        );
    }

    if (
        in_array(
            $contexto['atendimento_status'],
            ['FINALIZADO', 'CANCELADO', 'ALTA'],
            true
        )
    ) {
        responderEvo(
            false,
            'O atendimento já foi encerrado.',
            [],
            409
        );
    }

    return $contexto;
}

function carregarRascunhoEvo(
    PDO $pdo,
    int $evolucaoId,
    array $contexto
): array {
    if ($evolucaoId <= 0) {
        responderEvo(
            false,
            'Nenhuma Evolução Médica está aberta. Clique em Novo.',
            [],
            422
        );
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            atendimento_id,
            paciente_id,
            medico_id,
            usuario_id,
            status,
            subjetivo,
            objetivo,
            avaliacao,
            plano,
            observacoes
        FROM evolucoes_medicas
        WHERE id = :id
          AND atendimento_id = :atendimento_id
          AND medico_id = :medico_id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $evolucaoId,
        ':atendimento_id' =>
            (int) $contexto['atendimento_id'],
        ':medico_id' =>
            (int) $contexto['medico_id']
    ]);

    $evolucao =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evolucao) {
        responderEvo(
            false,
            'Evolução Médica não encontrada.',
            [],
            404
        );
    }

    if ($evolucao['status'] !== 'RASCUNHO') {
        responderEvo(
            false,
            'Esta Evolução Médica já foi finalizada e não pode ser editada diretamente.',
            [],
            409
        );
    }

    return $evolucao;
}


function validarDiagnosticosEvo(
    PDO $pdo,
    mixed $entrada
): array {
    if (!is_array($entrada)) {
        return [];
    }

    $resultado = [];
    $principalEncontrado = false;
    $idsVistos = [];

    $stmt = $pdo->prepare("
        SELECT id, codigo, descricao
        FROM cid10
        WHERE id = :id
          AND ativo = 1
        LIMIT 1
    ");

    foreach ($entrada as $item) {
        if (!is_array($item)) {
            continue;
        }

        $cidId = (int) ($item['cid10_id'] ?? 0);
        $tipo = strtoupper(
            trim((string) ($item['tipo'] ?? 'SECUNDARIO'))
        );

        if ($cidId <= 0 || isset($idsVistos[$cidId])) {
            continue;
        }

        if (!in_array($tipo, ['PRINCIPAL', 'SECUNDARIO'], true)) {
            $tipo = 'SECUNDARIO';
        }

        if ($tipo === 'PRINCIPAL') {
            if ($principalEncontrado) {
                $tipo = 'SECUNDARIO';
            } else {
                $principalEncontrado = true;
            }
        }

        $stmt->execute([':id' => $cidId]);
        $cid = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cid) {
            responderEvo(
                false,
                'Um dos códigos CID-10 selecionados não existe na base cadastrada.',
                [],
                422
            );
        }

        $idsVistos[$cidId] = true;
        $resultado[] = [
            'cid10_id' => $cidId,
            'tipo' => $tipo,
            'codigo' => $cid['codigo'],
            'descricao' => $cid['descricao']
        ];
    }

    return $resultado;
}

function sincronizarDiagnosticosEvo(
    PDO $pdo,
    int $evolucaoId,
    array $diagnosticos
): void {
    $stmtDelete = $pdo->prepare("
        DELETE FROM evolucao_diagnosticos
        WHERE evolucao_id = :evolucao_id
    ");
    $stmtDelete->execute([
        ':evolucao_id' => $evolucaoId
    ]);

    if (!$diagnosticos) {
        return;
    }

    $stmtInsert = $pdo->prepare("
        INSERT INTO evolucao_diagnosticos (
            evolucao_id,
            cid10_id,
            tipo,
            criado_em
        )
        VALUES (
            :evolucao_id,
            :cid10_id,
            :tipo,
            NOW()
        )
    ");

    foreach ($diagnosticos as $diagnostico) {
        $stmtInsert->execute([
            ':evolucao_id' => $evolucaoId,
            ':cid10_id' => (int) $diagnostico['cid10_id'],
            ':tipo' => $diagnostico['tipo']
        ]);
    }
}

$usuarioId =
    (int) ($_SESSION['usuario_id'] ?? 0);

if ($usuarioId <= 0) {
    responderEvo(
        false,
        'Sessão inválida.',
        [],
        401
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderEvo(
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
    responderEvo(
        false,
        'Dados inválidos.',
        [],
        400
    );
}

$acao =
    trim((string) ($entrada['acao'] ?? ''));

$atendimentoId =
    (int) ($entrada['atendimento_id'] ?? 0);

if ($atendimentoId <= 0) {
    responderEvo(
        false,
        'Atendimento inválido.',
        [],
        422
    );
}

$contexto =
    contextoEvo(
        $pdo,
        $atendimentoId,
        $usuarioId
    );

try {

    if ($acao === 'novo') {

        $stmt = $pdo->prepare("
            INSERT INTO evolucoes_medicas (
                atendimento_id,
                paciente_id,
                medico_id,
                usuario_id,
                status,
                criada_em,
                atualizada_em
            )
            VALUES (
                :atendimento_id,
                :paciente_id,
                :medico_id,
                :usuario_id,
                'RASCUNHO',
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ':atendimento_id' => $atendimentoId,
            ':paciente_id' =>
                (int) $contexto['paciente_id'],
            ':medico_id' =>
                (int) $contexto['medico_id'],
            ':usuario_id' => $usuarioId
        ]);

        $evolucaoId =
            (int) $pdo->lastInsertId();

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'CRIAR',
                'Novo rascunho de Evolução Médica iniciado.',
                'Prontuário',
                'Evolução Médica',
                'evolucoes_medicas',
                $evolucaoId,
                null,
                [
                    'atendimento_id' =>
                        $atendimentoId,
                    'status' => 'RASCUNHO'
                ],
                $usuarioId
            );
        }

        responderEvo(
            true,
            'Nova Evolução Médica criada.',
            [
                'evolucao_id' => $evolucaoId
            ],
            201
        );
    }

    $evolucaoId =
        (int) ($entrada['evolucao_id'] ?? 0);

    $evolucao =
        carregarRascunhoEvo(
            $pdo,
            $evolucaoId,
            $contexto
        );

    if ($acao === 'salvar') {

        $subjetivo =
            trim((string) ($entrada['subjetivo'] ?? ''));

        $objetivo =
            trim((string) ($entrada['objetivo'] ?? ''));

        $avaliacao =
            trim((string) ($entrada['avaliacao'] ?? ''));

        $plano =
            trim((string) ($entrada['plano'] ?? ''));

        $observacoes =
            trim((string) ($entrada['observacoes'] ?? ''));

        $diagnosticos = validarDiagnosticosEvo(
            $pdo,
            $entrada['diagnosticos'] ?? []
        );

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE evolucoes_medicas
            SET
                subjetivo = :subjetivo,
                objetivo = :objetivo,
                avaliacao = :avaliacao,
                plano = :plano,
                observacoes = :observacoes,
                atualizada_em = NOW()
            WHERE id = :id
              AND status = 'RASCUNHO'
        ");

        $stmt->execute([
            ':subjetivo' =>
                $subjetivo !== '' ? $subjetivo : null,
            ':objetivo' =>
                $objetivo !== '' ? $objetivo : null,
            ':avaliacao' =>
                $avaliacao !== '' ? $avaliacao : null,
            ':plano' =>
                $plano !== '' ? $plano : null,
            ':observacoes' =>
                $observacoes !== ''
                    ? $observacoes
                    : null,
            ':id' => $evolucaoId
        ]);

        sincronizarDiagnosticosEvo(
            $pdo,
            $evolucaoId,
            $diagnosticos
        );

        $pdo->commit();

        responderEvo(
            true,
            'Evolução Médica salva como rascunho.'
        );
    }

    if ($acao === 'excluir') {

        $stmt = $pdo->prepare("
            DELETE FROM evolucoes_medicas
            WHERE id = :id
              AND status = 'RASCUNHO'
        ");

        $stmt->execute([
            ':id' => $evolucaoId
        ]);

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'EXCLUIR',
                'Rascunho de Evolução Médica excluído.',
                'Prontuário',
                'Evolução Médica',
                'evolucoes_medicas',
                $evolucaoId,
                $evolucao,
                null,
                $usuarioId
            );
        }

        responderEvo(
            true,
            'Rascunho de Evolução Médica excluído.'
        );
    }

    if ($acao === 'finalizar') {
        // Confere a permissão e exige confirmação da senha antes de finalizar.
        shExigirAcao($pdo, 'PRONTUARIO_FINALIZAR');
        shExigirReautenticacao($pdo);


        $subjetivo =
            trim((string) ($entrada['subjetivo'] ?? ''));

        $objetivo =
            trim((string) ($entrada['objetivo'] ?? ''));

        $avaliacao =
            trim((string) ($entrada['avaliacao'] ?? ''));

        $plano =
            trim((string) ($entrada['plano'] ?? ''));

        $observacoes =
            trim((string) ($entrada['observacoes'] ?? ''));

        $diagnosticos = validarDiagnosticosEvo(
            $pdo,
            $entrada['diagnosticos'] ?? []
        );

        if (
            $subjetivo === ''
            && $objetivo === ''
            && $avaliacao === ''
            && $plano === ''
        ) {
            responderEvo(
                false,
                'Preencha pelo menos uma informação clínica antes de fechar e assinar.',
                [],
                422
            );
        }

        $conteudoHash = json_encode(
            [
                'evolucao_id' => $evolucaoId,
                'atendimento_id' => $atendimentoId,
                'paciente_id' =>
                    (int) $contexto['paciente_id'],
                'medico_id' =>
                    (int) $contexto['medico_id'],
                'usuario_id' => $usuarioId,
                'crm' => $contexto['crm'],
                'uf_crm' => $contexto['uf_crm'],
                'subjetivo' => $subjetivo,
                'objetivo' => $objetivo,
                'avaliacao' => $avaliacao,
                'plano' => $plano,
                'observacoes' => $observacoes,
                'diagnosticos' => $diagnosticos
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        $hash =
            hash(
                'sha256',
                (string) $conteudoHash
            );

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE evolucoes_medicas
            SET
                subjetivo = :subjetivo,
                objetivo = :objetivo,
                avaliacao = :avaliacao,
                plano = :plano,
                observacoes = :observacoes,
                status = 'FINALIZADA',
                finalizada_em = NOW(),
                atualizada_em = NOW(),
                hash_finalizacao = :hash
            WHERE id = :id
              AND status = 'RASCUNHO'
        ");

        $stmt->execute([
            ':subjetivo' =>
                $subjetivo !== '' ? $subjetivo : null,
            ':objetivo' =>
                $objetivo !== '' ? $objetivo : null,
            ':avaliacao' =>
                $avaliacao !== '' ? $avaliacao : null,
            ':plano' =>
                $plano !== '' ? $plano : null,
            ':observacoes' =>
                $observacoes !== ''
                    ? $observacoes
                    : null,
            ':hash' => $hash,
            ':id' => $evolucaoId
        ]);

        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();

            responderEvo(
                false,
                'O documento já foi alterado por outra operação.',
                [],
                409
            );
        }

        sincronizarDiagnosticosEvo(
            $pdo,
            $evolucaoId,
            $diagnosticos
        );

        $pdo->commit();

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'FINALIZAR',
                'Evolução Médica fechada e assinada.',
                'Prontuário',
                'Evolução Médica',
                'evolucoes_medicas',
                $evolucaoId,
                ['status' => 'RASCUNHO'],
                [
                    'status' => 'FINALIZADA',
                    'hash_finalizacao' => $hash,
                    'atendimento_id' =>
                        $atendimentoId
                ],
                $usuarioId
            );
        }

        responderEvo(
            true,
            'Evolução Médica fechada e assinada.',
            [
                'hash' => $hash
            ]
        );
    }

    responderEvo(
        false,
        'Ação inválida.',
        [],
        400
    );

} catch (Throwable $erro) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro na API de Evolução Médica: '
        . $erro->getMessage()
    );

    responderEvo(
        false,
        'Não foi possível concluir a operação da Evolução Médica.',
        [],
        500
    );
}
