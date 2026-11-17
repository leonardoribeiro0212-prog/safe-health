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

function responderAlta(
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

function contextoAlta(
    PDO $pdo,
    int $atendimentoId,
    int $usuarioId
): array {
    $stmt = $pdo->prepare("
        SELECT
            a.id AS atendimento_id,
            a.paciente_id,
            a.status AS atendimento_status,
            a.numero_atendimento,
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
        responderAlta(
            false,
            'Este atendimento não pertence ao médico logado.',
            [],
            403
        );
    }

    return $contexto;
}

function carregarRascunhoAlta(
    PDO $pdo,
    int $altaId,
    array $contexto
): array {
    if ($altaId <= 0) {
        responderAlta(
            false,
            'Nenhuma Alta Médica está aberta. Clique em Novo.',
            [],
            422
        );
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM altas_medicas
        WHERE id = :id
          AND atendimento_id = :atendimento_id
          AND medico_id = :medico_id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $altaId,
        ':atendimento_id' =>
            (int) $contexto['atendimento_id'],
        ':medico_id' =>
            (int) $contexto['medico_id']
    ]);

    $alta =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alta) {
        responderAlta(
            false,
            'Alta Médica não encontrada.',
            [],
            404
        );
    }

    if ($alta['status'] !== 'RASCUNHO') {
        responderAlta(
            false,
            'Esta Alta Médica já foi finalizada.',
            [],
            409
        );
    }

    return $alta;
}

function validarDiagnosticosAlta(
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

        $cidId =
            (int) ($item['cid10_id'] ?? 0);

        $tipo =
            strtoupper(
                trim(
                    (string) (
                        $item['tipo']
                        ?? 'SECUNDARIO'
                    )
                )
            );

        if (
            $cidId <= 0
            || isset($idsVistos[$cidId])
        ) {
            continue;
        }

        if (
            !in_array(
                $tipo,
                ['PRINCIPAL', 'SECUNDARIO'],
                true
            )
        ) {
            $tipo = 'SECUNDARIO';
        }

        if ($tipo === 'PRINCIPAL') {
            if ($principalEncontrado) {
                $tipo = 'SECUNDARIO';
            } else {
                $principalEncontrado = true;
            }
        }

        $stmt->execute([
            ':id' => $cidId
        ]);

        $cid =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cid) {
            responderAlta(
                false,
                'Um dos códigos CID-10 selecionados não existe na base.',
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

function sincronizarDiagnosticosAlta(
    PDO $pdo,
    int $altaId,
    array $diagnosticos
): void {
    $stmtDelete = $pdo->prepare("
        DELETE FROM alta_diagnosticos
        WHERE alta_id = :alta_id
    ");

    $stmtDelete->execute([
        ':alta_id' => $altaId
    ]);

    if (!$diagnosticos) {
        return;
    }

    $stmtInsert = $pdo->prepare("
        INSERT INTO alta_diagnosticos (
            alta_id,
            cid10_id,
            tipo,
            criado_em
        )
        VALUES (
            :alta_id,
            :cid10_id,
            :tipo,
            NOW()
        )
    ");

    foreach ($diagnosticos as $diagnostico) {
        $stmtInsert->execute([
            ':alta_id' => $altaId,
            ':cid10_id' =>
                (int) $diagnostico['cid10_id'],
            ':tipo' =>
                $diagnostico['tipo']
        ]);
    }
}

$usuarioId =
    (int) ($_SESSION['usuario_id'] ?? 0);

if ($usuarioId <= 0) {
    responderAlta(
        false,
        'Sessão inválida.',
        [],
        401
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderAlta(
        false,
        'Método não permitido.',
        [],
        405
    );
}

$entrada =
    json_decode(
        file_get_contents('php://input') ?: '',
        true
    );

if (!is_array($entrada)) {
    responderAlta(
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
    responderAlta(
        false,
        'Atendimento inválido.',
        [],
        422
    );
}

$contexto =
    contextoAlta(
        $pdo,
        $atendimentoId,
        $usuarioId
    );

try {

    if ($acao === 'novo') {

        if (
            in_array(
                $contexto['atendimento_status'],
                ['ALTA', 'FINALIZADO', 'CANCELADO'],
                true
            )
        ) {
            responderAlta(
                false,
                'O atendimento já está encerrado.',
                [],
                409
            );
        }

        $stmt = $pdo->prepare("
            INSERT INTO altas_medicas (
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
            ':atendimento_id' =>
                $atendimentoId,
            ':paciente_id' =>
                (int) $contexto['paciente_id'],
            ':medico_id' =>
                (int) $contexto['medico_id'],
            ':usuario_id' =>
                $usuarioId
        ]);

        $altaId =
            (int) $pdo->lastInsertId();

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'CRIAR',
                'Novo rascunho de Alta Médica iniciado.',
                'Prontuário',
                'Alta Médica',
                'altas_medicas',
                $altaId,
                null,
                [
                    'atendimento_id' =>
                        $atendimentoId,
                    'status' => 'RASCUNHO'
                ],
                $usuarioId
            );
        }

        responderAlta(
            true,
            'Nova Alta Médica criada.',
            [
                'alta_id' => $altaId
            ],
            201
        );
    }

    $altaId =
        (int) ($entrada['alta_id'] ?? 0);

    $alta =
        carregarRascunhoAlta(
            $pdo,
            $altaId,
            $contexto
        );

    if ($acao === 'excluir') {

        $pdo->beginTransaction();

        $stmtDiag = $pdo->prepare("
            DELETE FROM alta_diagnosticos
            WHERE alta_id = :alta_id
        ");
        $stmtDiag->execute([
            ':alta_id' => $altaId
        ]);

        $stmt = $pdo->prepare("
            DELETE FROM altas_medicas
            WHERE id = :id
              AND status = 'RASCUNHO'
        ");

        $stmt->execute([
            ':id' => $altaId
        ]);

        $pdo->commit();

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'EXCLUIR',
                'Rascunho de Alta Médica excluído.',
                'Prontuário',
                'Alta Médica',
                'altas_medicas',
                $altaId,
                $alta,
                null,
                $usuarioId
            );
        }

        responderAlta(
            true,
            'Rascunho de Alta Médica excluído.'
        );
    }

    $resumoClinico =
        trim(
            (string) (
                $entrada['resumo_clinico']
                ?? ''
            )
        );

    $condicaoAlta =
        strtoupper(
            trim(
                (string) (
                    $entrada['condicao_alta']
                    ?? ''
                )
            )
        );

    $destinoPaciente =
        strtoupper(
            trim(
                (string) (
                    $entrada['destino_paciente']
                    ?? ''
                )
            )
        );

    $orientacoes =
        trim(
            (string) (
                $entrada['orientacoes']
                ?? ''
            )
        );

    $retorno =
        trim(
            (string) (
                $entrada['retorno_recomendado']
                ?? ''
            )
        );

    $observacoes =
        trim(
            (string) (
                $entrada['observacoes']
                ?? ''
            )
        );

    $condicoesPermitidas = [
        'MELHORADO',
        'ESTAVEL',
        'CURADO',
        'SEM_ALTERACAO',
        'OUTRO'
    ];

    $destinosPermitidos = [
        'DOMICILIO',
        'INTERNACAO',
        'TRANSFERENCIA',
        'AMBULATORIO',
        'OUTRO'
    ];

    if (
        $condicaoAlta !== ''
        && !in_array(
            $condicaoAlta,
            $condicoesPermitidas,
            true
        )
    ) {
        responderAlta(
            false,
            'Condição de alta inválida.',
            [],
            422
        );
    }

    if (
        $destinoPaciente !== ''
        && !in_array(
            $destinoPaciente,
            $destinosPermitidos,
            true
        )
    ) {
        responderAlta(
            false,
            'Destino do paciente inválido.',
            [],
            422
        );
    }

    /*
     * Diagnóstico da Alta: herdado da Evolução Médica.
     * Não confiamos em CIDs enviados pelo navegador.
     */
    $stmtDiagnosticosEvolucao = $pdo->prepare("
        SELECT
            ed.cid10_id,
            ed.tipo,
            c.codigo,
            c.descricao
        FROM evolucoes_medicas em
        INNER JOIN evolucao_diagnosticos ed
            ON ed.evolucao_id = em.id
        INNER JOIN cid10 c
            ON c.id = ed.cid10_id
        WHERE em.atendimento_id = :atendimento_id
          AND em.id = (
              SELECT MAX(em2.id)
              FROM evolucoes_medicas em2
              WHERE em2.atendimento_id = :atendimento_id_2
                AND EXISTS (
                    SELECT 1
                    FROM evolucao_diagnosticos ed2
                    WHERE ed2.evolucao_id = em2.id
                )
          )
        ORDER BY
            CASE
                WHEN ed.tipo = 'PRINCIPAL' THEN 0
                ELSE 1
            END,
            ed.id
    ");

    $stmtDiagnosticosEvolucao->execute([
        ':atendimento_id' => $atendimentoId,
        ':atendimento_id_2' => $atendimentoId
    ]);

    $diagnosticos =
        $stmtDiagnosticosEvolucao->fetchAll(PDO::FETCH_ASSOC);

    if ($acao === 'salvar') {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE altas_medicas
            SET
                resumo_clinico = :resumo_clinico,
                condicao_alta = :condicao_alta,
                destino_paciente = :destino_paciente,
                orientacoes = :orientacoes,
                retorno_recomendado = :retorno,
                observacoes = :observacoes,
                atualizada_em = NOW()
            WHERE id = :id
              AND status = 'RASCUNHO'
        ");

        $stmt->execute([
            ':resumo_clinico' =>
                $resumoClinico !== ''
                    ? $resumoClinico
                    : null,
            ':condicao_alta' =>
                $condicaoAlta !== ''
                    ? $condicaoAlta
                    : null,
            ':destino_paciente' =>
                $destinoPaciente !== ''
                    ? $destinoPaciente
                    : null,
            ':orientacoes' =>
                $orientacoes !== ''
                    ? $orientacoes
                    : null,
            ':retorno' =>
                $retorno !== ''
                    ? $retorno
                    : null,
            ':observacoes' =>
                $observacoes !== ''
                    ? $observacoes
                    : null,
            ':id' =>
                $altaId
        ]);

        sincronizarDiagnosticosAlta(
            $pdo,
            $altaId,
            $diagnosticos
        );

        $pdo->commit();

        responderAlta(
            true,
            'Alta Médica salva como rascunho.'
        );
    }

    if ($acao === 'finalizar') {
        // Confere a permissão e exige confirmação da senha antes de finalizar.
        shExigirAcao($pdo, 'PRONTUARIO_ALTA');
        shExigirReautenticacao($pdo);


        $temPrincipal = false;

        foreach ($diagnosticos as $diagnostico) {
            if (
                $diagnostico['tipo']
                === 'PRINCIPAL'
            ) {
                $temPrincipal = true;
                break;
            }
        }

        if (!$temPrincipal) {
            responderAlta(
                false,
                'Selecione um CID-10 principal para concluir a alta.',
                [],
                422
            );
        }

        if ($resumoClinico === '') {
            responderAlta(
                false,
                'Preencha o resumo clínico.',
                [],
                422
            );
        }

        if ($condicaoAlta === '') {
            responderAlta(
                false,
                'Selecione a condição do paciente na alta.',
                [],
                422
            );
        }

        if ($destinoPaciente === '') {
            responderAlta(
                false,
                'Selecione o destino do paciente.',
                [],
                422
            );
        }

        if ($orientacoes === '') {
            responderAlta(
                false,
                'Preencha as orientações de alta.',
                [],
                422
            );
        }

        if (
            in_array(
                $contexto['atendimento_status'],
                ['ALTA', 'FINALIZADO', 'CANCELADO'],
                true
            )
        ) {
            responderAlta(
                false,
                'O atendimento já foi encerrado.',
                [],
                409
            );
        }

        $conteudoHash =
            json_encode(
                [
                    'alta_id' =>
                        $altaId,
                    'atendimento_id' =>
                        $atendimentoId,
                    'paciente_id' =>
                        (int) $contexto['paciente_id'],
                    'medico_id' =>
                        (int) $contexto['medico_id'],
                    'usuario_id' =>
                        $usuarioId,
                    'crm' =>
                        $contexto['crm'],
                    'uf_crm' =>
                        $contexto['uf_crm'],
                    'diagnosticos' =>
                        $diagnosticos,
                    'resumo_clinico' =>
                        $resumoClinico,
                    'condicao_alta' =>
                        $condicaoAlta,
                    'destino_paciente' =>
                        $destinoPaciente,
                    'orientacoes' =>
                        $orientacoes,
                    'retorno_recomendado' =>
                        $retorno,
                    'observacoes' =>
                        $observacoes
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
            UPDATE altas_medicas
            SET
                resumo_clinico = :resumo_clinico,
                condicao_alta = :condicao_alta,
                destino_paciente = :destino_paciente,
                orientacoes = :orientacoes,
                retorno_recomendado = :retorno,
                observacoes = :observacoes,
                status = 'FINALIZADA',
                finalizada_em = NOW(),
                atualizada_em = NOW(),
                hash_finalizacao = :hash
            WHERE id = :id
              AND status = 'RASCUNHO'
        ");

        $stmt->execute([
            ':resumo_clinico' =>
                $resumoClinico,
            ':condicao_alta' =>
                $condicaoAlta,
            ':destino_paciente' =>
                $destinoPaciente,
            ':orientacoes' =>
                $orientacoes,
            ':retorno' =>
                $retorno !== ''
                    ? $retorno
                    : null,
            ':observacoes' =>
                $observacoes !== ''
                    ? $observacoes
                    : null,
            ':hash' =>
                $hash,
            ':id' =>
                $altaId
        ]);

        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();

            responderAlta(
                false,
                'A Alta Médica já foi alterada por outra operação.',
                [],
                409
            );
        }

        sincronizarDiagnosticosAlta(
            $pdo,
            $altaId,
            $diagnosticos
        );

        $stmtAtendimento =
            $pdo->prepare("
                UPDATE atendimentos
                SET
                    status = 'FINALIZADO',
                    data_alta = NOW()
                WHERE id = :id
            ");

        $stmtAtendimento->execute([
            ':id' => $atendimentoId
        ]);

        $pdo->commit();

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'FINALIZAR',
                'Alta Médica finalizada; atendimento finalizado.',
                'Prontuário',
                'Alta Médica',
                'altas_medicas',
                $altaId,
                [
                    'status' => 'RASCUNHO',
                    'atendimento_status' =>
                        $contexto['atendimento_status']
                ],
                [
                    'status' => 'FINALIZADA',
                    'atendimento_status' => 'FINALIZADO',
                    'hash_finalizacao' => $hash,
                    'atendimento_id' =>
                        $atendimentoId
                ],
                $usuarioId
            );
        }

        responderAlta(
            true,
            'Atendimento finalizado com sucesso.',
            [
                'hash' => $hash,
                'atendimento_status' => 'FINALIZADO'
            ]
        );
    }

    responderAlta(
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
        'Erro na API de Alta Médica: '
        . $erro->getMessage()
    );

    responderAlta(
        false,
        'Não foi possível concluir a operação da Alta Médica.',
        [],
        500
    );
}
