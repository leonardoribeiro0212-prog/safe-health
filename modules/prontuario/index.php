<?php
session_start();

require_once __DIR__ . '/../../config/database.php';

if (file_exists(__DIR__ . '/../../includes/auth.php')) {
    require_once __DIR__ . '/../../includes/auth.php';
}

$atendimentoId = isset($_GET['atendimento_id'])
    ? (int) $_GET['atendimento_id']
    : 0;

$aba = $_GET['aba'] ?? 'atendimento-atual';

$abasPermitidas = [
    'atendimento-atual',
    'alergias',
    'prescricao',
    'evolucao',
    'documentos',
    'alta',
    'historico'
];

if (!in_array($aba, $abasPermitidas, true)) {
    $aba = 'atendimento-atual';
}

$nomeUsuario = $_SESSION['usuario_nome']
    ?? $_SESSION['nome']
    ?? 'Usuário';

$iniciais = '';
foreach (preg_split('/\s+/', trim($nomeUsuario)) as $parte) {
    if ($parte !== '') {
        $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
    }

    if (mb_strlen($iniciais) >= 2) {
        break;
    }
}

if ($iniciais === '') {
    $iniciais = 'SH';
}

function urlProntuario(string $aba, int $atendimentoId): string
{
    $url = '/safe-health/modules/prontuario/index.php?aba='
        . urlencode($aba);

    if ($atendimentoId > 0) {
        $url .= '&atendimento_id=' . $atendimentoId;
    }

    return $url;
}

function abaAtiva(string $abaAtual, string $aba): string
{
    return $abaAtual === $aba ? 'active' : '';
}

$titulos = [
    'atendimento-atual' => 'Atendimento Atual',
    'alergias' => 'Alergias',
    'prescricao' => 'Prescrição Médica',
    'evolucao' => 'Evolução Médica',
    'documentos' => 'Documentos / Receituário',
    'alta' => 'Alta Médica',
    'historico' => 'Histórico do Paciente'
];

$tituloPagina = $titulos[$aba] ?? 'Prontuário Eletrônico';

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
$medicoLogado = null;
$atendimento = null;
$erroAtendimento = null;
$alergiasPaciente = [];
$erroAlergias = null;
$prescricaoAtual = null;
$prescricaoSolicitadaId = (int) ($_GET['prescricao_id'] ?? 0);
$itensPrescricao = [];
$prescricoesAnteriores = [];
$solicitacoesPrescricao = [];
$alergiasAtivasPrescricao = [];
$erroPrescricao = null;

$evolucaoAtual = null;
$evolucaoSolicitadaId = (int) ($_GET['evolucao_id'] ?? 0);
$evolucoesAnteriores = [];
$erroEvolucao = null;

$documentoAtual = null;
$documentosAnteriores = [];
$documentoSolicitadoId = (int) ($_GET['documento_id'] ?? 0);
$tipoDocumento = strtoupper(trim((string) ($_GET['tipo_documento'] ?? 'RECEITUARIO')));
$tiposDocumentoPermitidos = [
    'RECEITUARIO',
    'ATESTADO',
    'DECLARACAO',
    'RELATORIO',
    'ENCAMINHAMENTO'
];
if (!in_array($tipoDocumento, $tiposDocumentoPermitidos, true)) {
    $tipoDocumento = 'RECEITUARIO';
}
$erroDocumentos = null;

$altaAtual = null;
$altasAnteriores = [];
$altaSolicitadaId = (int) ($_GET['alta_id'] ?? 0);
$erroAlta = null;

$historicoPaciente = [
    'atendimentos' => [],
    'alergias' => [],
    'evolucoes' => [],
    'prescricoes' => [],
    'documentos' => [],
    'altas' => []
];
$erroHistorico = null;
$especialidadesAtivas = [];

function formatarCpfPep(?string $cpf): string
{
    $cpf = preg_replace('/\D/', '', (string) $cpf);

    if (strlen($cpf) !== 11) {
        return $cpf ?: 'Não informado';
    }

    return substr($cpf, 0, 3) . '.'
        . substr($cpf, 3, 3) . '.'
        . substr($cpf, 6, 3) . '-'
        . substr($cpf, 9, 2);
}

function calcularIdadePep(?string $dataNascimento): string
{
    if (!$dataNascimento) {
        return 'Não informada';
    }

    try {
        $nascimento = new DateTime($dataNascimento);
        return $nascimento->diff(new DateTime())->y . ' anos';
    } catch (Throwable $erro) {
        return 'Não informada';
    }
}

function formatarDataHoraPep(?string $data): string
{
    if (!$data) {
        return 'Não informado';
    }

    try {
        return (new DateTime($data))->format('d/m/Y H:i');
    } catch (Throwable $erro) {
        return $data;
    }
}

function valorClinicoPep(mixed $valor, string $sufixo = ''): string
{
    if ($valor === null || $valor === '') {
        return 'Não informado';
    }

    return (string) $valor . $sufixo;
}

if ($usuarioId > 0) {
    $stmtMedico = $pdo->prepare("
        SELECT
            m.id,
            m.nome,
            m.crm,
            m.uf_crm,
            e.nome AS especialidade
        FROM medicos m
        INNER JOIN especialidades e
            ON e.id = m.especialidade_id
        WHERE m.usuario_id = :usuario_id
          AND m.ativo = 1
        LIMIT 1
    ");
    $stmtMedico->execute([
        ':usuario_id' => $usuarioId
    ]);
    $medicoLogado = $stmtMedico->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($atendimentoId > 0) {
    try {
        $sqlAtendimento = "
            SELECT
                a.id,
                a.numero_atendimento,
                a.paciente_id,
                a.senha_id,
                a.origem,
                a.tipo_atendimento,
                a.convenio,
                a.especialidade,
                a.profissional_id,
                a.profissional_responsavel,
                a.status,
                a.data_entrada,

                p.nome AS paciente_nome,
                p.cpf,
                p.data_nascimento,
                p.sexo,
                p.telefone,
                p.cartao_sus,

                t.id AS triagem_id,
                t.queixa_principal,
                t.pressao_arterial,
                t.frequencia_cardiaca,
                t.frequencia_respiratoria,
                t.temperatura,
                t.saturacao,
                t.peso,
                t.escala_dor,
                t.classificacao_risco,
                t.observacoes AS triagem_observacoes,
                t.realizada_em AS triagem_realizada_em

            FROM atendimentos a

            INNER JOIN pacientes p
                ON p.id = a.paciente_id

            LEFT JOIN triagens t
                ON t.senha_id = a.senha_id

            WHERE a.id = :atendimento_id
        ";

        $parametros = [
            ':atendimento_id' => $atendimentoId
        ];

        /*
         * Se a conta estiver vinculada a um médico, ela só pode abrir
         * atendimentos atribuídos ao próprio profissional.
         */
        if ($medicoLogado) {
            $sqlAtendimento .= "
                AND a.profissional_id = :medico_id
            ";
            $parametros[':medico_id'] = (int) $medicoLogado['id'];
        }

        $sqlAtendimento .= " LIMIT 1";

        $stmtAtendimento = $pdo->prepare($sqlAtendimento);
        $stmtAtendimento->execute($parametros);
        $atendimento = $stmtAtendimento->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$atendimento) {
            $erroAtendimento =
                'O atendimento não foi encontrado ou não pertence ao profissional logado.';
        } else {
            /*
             * Ao abrir o prontuário pela primeira vez, o paciente deixa
             * AGUARDANDO_MEDICO e passa a EM_ATENDIMENTO.
             */
            if ($atendimento['status'] === 'AGUARDANDO_MEDICO') {
                $stmtStatus = $pdo->prepare("
                    UPDATE atendimentos
                    SET status = 'EM_ATENDIMENTO'
                    WHERE id = :id
                      AND status = 'AGUARDANDO_MEDICO'
                ");
                $stmtStatus->execute([
                    ':id' => $atendimentoId
                ]);

                $atendimento['status'] = 'EM_ATENDIMENTO';
            }

            if (file_exists(__DIR__ . '/../../includes/auditoria.php')) {
                require_once __DIR__ . '/../../includes/auditoria.php';

                registrarAuditoria(
                    $pdo,
                    'VISUALIZAR',
                    'Prontuário eletrônico acessado.',
                    'Prontuário',
                    'Atendimento Atual',
                    'atendimentos',
                    $atendimentoId,
                    null,
                    [
                        'numero_atendimento' =>
                            $atendimento['numero_atendimento'],
                        'paciente_id' =>
                            (int) $atendimento['paciente_id']
                    ],
                    $usuarioId
                );
            }
        }
        unset($evolucao);
    } catch (Throwable $erro) {
        $erroAtendimento =
            'Não foi possível carregar os dados clínicos do atendimento.';

        error_log(
            'Erro no Atendimento Atual do prontuário: '
            . $erro->getMessage()
        );
    }
}


if ($atendimento && $aba === 'alergias') {
    try {
        $stmtAlergias = $pdo->prepare("
            SELECT
                ap.id,
                ap.substancia,
                ap.tipo,
                ap.reacao,
                ap.gravidade,
                ap.observacoes,
                ap.status,
                ap.criado_em,
                ap.atualizado_em,
                ap.inativado_em,
                u.nome AS registrado_por
            FROM alergias_paciente ap
            LEFT JOIN usuarios u
                ON u.id = ap.registrado_por_usuario_id
            WHERE ap.paciente_id = :paciente_id
            ORDER BY
                CASE WHEN ap.status = 'ATIVA' THEN 0 ELSE 1 END,
                ap.criado_em DESC,
                ap.id DESC
        ");

        $stmtAlergias->execute([
            ':paciente_id' => (int) $atendimento['paciente_id']
        ]);

        $alergiasPaciente =
            $stmtAlergias->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $erro) {
        $erroAlergias =
            'Não foi possível carregar as alergias do paciente. '
            . 'Verifique se a migração da tela de Alergias foi executada.';

        error_log(
            'Erro ao carregar alergias no prontuário: '
            . $erro->getMessage()
        );
    }
}


if ($atendimento && $aba === 'prescricao') {
    try {
        $stmtAlergiasPrescricao = $pdo->prepare("
            SELECT
                id,
                substancia,
                tipo,
                reacao,
                gravidade
            FROM alergias_paciente
            WHERE paciente_id = :paciente_id
              AND status = 'ATIVA'
            ORDER BY substancia
        ");
        $stmtAlergiasPrescricao->execute([
            ':paciente_id' => (int) $atendimento['paciente_id']
        ]);
        $alergiasAtivasPrescricao =
            $stmtAlergiasPrescricao->fetchAll(PDO::FETCH_ASSOC);

        $stmtPrescricao = $pdo->prepare("
            SELECT
                pm.id,
                pm.status,
                pm.observacoes,
                pm.data_prescricao,
                pm.data_liberacao_farmacia,
                pm.hash_finalizacao,
                pm.finalizada_em,
                u.nome AS usuario_nome,
                m.nome AS medico_nome,
                m.crm,
                m.uf_crm
            FROM prescricoes_medicas pm
            LEFT JOIN usuarios u
                ON u.id = pm.usuario_id
            LEFT JOIN medicos m
                ON m.id = pm.medico_id
            WHERE pm.atendimento_id = :atendimento_id
            ORDER BY pm.id DESC
        ");
        $stmtPrescricao->execute([
            ':atendimento_id' => $atendimentoId
        ]);
        $todasPrescricoes =
            $stmtPrescricao->fetchAll(PDO::FETCH_ASSOC);

        foreach ($todasPrescricoes as $prescricao) {
            if (
                $prescricaoSolicitadaId > 0
                && (int) $prescricao['id'] === $prescricaoSolicitadaId
                && $prescricao['status'] === 'RASCUNHO'
            ) {
                $prescricaoAtual = $prescricao;
            } else {
                $prescricoesAnteriores[] = $prescricao;
            }
        }

        if ($prescricaoAtual) {
            $stmtItens = $pdo->prepare("
                SELECT
                    pmi.id,
                    pmi.medicamento_id,
                    pmi.dose,
                    pmi.via_administracao,
                    pmi.frequencia,
                    pmi.quantidade,
                    pmi.instrucoes,
                    pmi.status_farmacia,
                    med.nome AS medicamento_nome,
                    med.principio_ativo,
                    med.apresentacao,
                    med.concentracao,
                    med.unidade_medida,
                    med.controlado
                FROM prescricao_medicamentos pmi
                INNER JOIN medicamentos med
                    ON med.id = pmi.medicamento_id
                WHERE pmi.prescricao_id = :prescricao_id
                  AND pmi.status_farmacia <> 'CANCELADO'
                ORDER BY pmi.id
            ");
            $stmtItens->execute([
                ':prescricao_id' => (int) $prescricaoAtual['id']
            ]);
            $itensPrescricao =
                $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            $stmtSolicitacoes = $pdo->prepare("
                SELECT
                    id,
                    tipo_item,
                    item_id,
                    descricao,
                    codigo_item,
                    detalhes,
                    status
                FROM prescricao_solicitacoes
                WHERE prescricao_id = :prescricao_id
                  AND status <> 'CANCELADO'
                ORDER BY id
            ");
            $stmtSolicitacoes->execute([
                ':prescricao_id' => (int) $prescricaoAtual['id']
            ]);
            $solicitacoesPrescricao =
                $stmtSolicitacoes->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $erro) {
        $erroPrescricao =
            'Não foi possível carregar a prescrição médica. '
            . 'Verifique se a migração da Sprint 5 foi executada.';

        error_log(
            'Erro ao carregar prescrição no prontuário: '
            . $erro->getMessage()
        );
    }
}


if ($atendimento && $aba === 'evolucao') {
    try {
        $stmtEvolucoes = $pdo->prepare("
            SELECT
                em.id,
                em.atendimento_id,
                em.paciente_id,
                em.medico_id,
                em.usuario_id,
                em.status,
                em.subjetivo,
                em.objetivo,
                em.avaliacao,
                em.plano,
                em.observacoes,
                em.criada_em,
                em.atualizada_em,
                em.finalizada_em,
                em.hash_finalizacao,
                m.nome AS medico_nome,
                m.crm,
                m.uf_crm,
                u.nome AS usuario_nome
            FROM evolucoes_medicas em
            LEFT JOIN medicos m
                ON m.id = em.medico_id
            LEFT JOIN usuarios u
                ON u.id = em.usuario_id
            WHERE em.atendimento_id = :atendimento_id
            ORDER BY em.id DESC
        ");

        $stmtEvolucoes->execute([
            ':atendimento_id' => $atendimentoId
        ]);

        $todasEvolucoes =
            $stmtEvolucoes->fetchAll(PDO::FETCH_ASSOC);

        foreach ($todasEvolucoes as &$evolucao) {
            $stmtDiagnosticos = $pdo->prepare("
                SELECT
                    ed.id,
                    ed.cid10_id,
                    ed.tipo,
                    c.codigo,
                    c.descricao
                FROM evolucao_diagnosticos ed
                INNER JOIN cid10 c
                    ON c.id = ed.cid10_id
                WHERE ed.evolucao_id = :evolucao_id
                ORDER BY
                    CASE WHEN ed.tipo = 'PRINCIPAL' THEN 0 ELSE 1 END,
                    ed.id
            ");
            $stmtDiagnosticos->execute([
                ':evolucao_id' => (int) $evolucao['id']
            ]);
            $evolucao['diagnosticos'] =
                $stmtDiagnosticos->fetchAll(PDO::FETCH_ASSOC);

            if (
                $evolucaoSolicitadaId > 0
                && (int) $evolucao['id'] === $evolucaoSolicitadaId
                && $evolucao['status'] === 'RASCUNHO'
            ) {
                $evolucaoAtual = $evolucao;
            } else {
                $evolucoesAnteriores[] = $evolucao;
            }
        }
    } catch (Throwable $erro) {
        $erroEvolucao =
            'Não foi possível carregar a Evolução Médica. '
            . 'Verifique se a migração da Sprint 6 foi executada.';

        error_log(
            'Erro ao carregar Evolução Médica: '
            . $erro->getMessage()
        );
    }
}


if (
    $atendimento
    && $aba === 'documentos'
    && $tipoDocumento === 'ENCAMINHAMENTO'
) {
    try {
        $stmtEspecialidades = $pdo->query("
            SELECT id, nome
            FROM especialidades
            WHERE ativa = 1
            ORDER BY nome
        ");
        $especialidadesAtivas =
            $stmtEspecialidades->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $erro) {
        error_log(
            'Erro ao carregar especialidades no encaminhamento: '
            . $erro->getMessage()
        );
    }
}

if ($atendimento && $aba === 'documentos') {
    try {
        $stmtDocs = $pdo->prepare("
            SELECT
                dm.id,
                dm.atendimento_id,
                dm.paciente_id,
                dm.medico_id,
                dm.usuario_id,
                dm.tipo,
                dm.status,
                dm.conteudo_json,
                dm.criado_em,
                dm.atualizado_em,
                dm.finalizado_em,
                dm.hash_finalizacao,
                m.nome AS medico_nome,
                m.crm,
                m.uf_crm
            FROM documentos_medicos dm
            LEFT JOIN medicos m ON m.id = dm.medico_id
            WHERE dm.atendimento_id = :atendimento_id
              AND dm.tipo = :tipo
            ORDER BY dm.id DESC
        ");
        $stmtDocs->execute([
            ':atendimento_id' => $atendimentoId,
            ':tipo' => $tipoDocumento
        ]);
        $docs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

        foreach ($docs as $doc) {
            $doc['conteudo'] = json_decode(
                (string) ($doc['conteudo_json'] ?? '{}'),
                true
            ) ?: [];

            if (
                $documentoSolicitadoId > 0
                && (int) $doc['id'] === $documentoSolicitadoId
                && $doc['status'] === 'RASCUNHO'
            ) {
                $documentoAtual = $doc;
            } else {
                $documentosAnteriores[] = $doc;
            }
        }
    } catch (Throwable $erro) {
        $erroDocumentos =
            'Não foi possível carregar os documentos médicos. '
            . 'Verifique se a migração da Sprint 7 foi executada.';
        error_log(
            'Erro Documentos/Receituário: ' . $erro->getMessage()
        );
    }
}


if ($atendimento && $aba === 'alta') {
    try {
        $stmtAltas = $pdo->prepare("
            SELECT
                am.id,
                am.atendimento_id,
                am.paciente_id,
                am.medico_id,
                am.usuario_id,
                am.status,
                am.resumo_clinico,
                am.condicao_alta,
                am.destino_paciente,
                am.orientacoes,
                am.retorno_recomendado,
                am.observacoes,
                am.criada_em,
                am.atualizada_em,
                am.finalizada_em,
                am.hash_finalizacao,
                m.nome AS medico_nome,
                m.crm,
                m.uf_crm
            FROM altas_medicas am
            LEFT JOIN medicos m
                ON m.id = am.medico_id
            WHERE am.atendimento_id = :atendimento_id
            ORDER BY am.id DESC
        ");

        $stmtAltas->execute([
            ':atendimento_id' => $atendimentoId
        ]);

        $todasAltas =
            $stmtAltas->fetchAll(PDO::FETCH_ASSOC);

        /*
         * A Alta Médica herda o diagnóstico da Evolução Médica.
         * Preferimos a evolução mais recente que possua CID registrado.
         * O CID não é digitado novamente na Alta.
         */
        $diagnosticosDaEvolucao = [];

        $stmtEvolucaoCid = $pdo->prepare("
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

        $stmtEvolucaoCid->execute([
            ':atendimento_id' => $atendimentoId,
            ':atendimento_id_2' => $atendimentoId
        ]);

        $diagnosticosDaEvolucao =
            $stmtEvolucaoCid->fetchAll(PDO::FETCH_ASSOC);

        foreach ($todasAltas as $alta) {
            $stmtDiagnosticosAlta = $pdo->prepare("
                SELECT
                    ad.id,
                    ad.cid10_id,
                    ad.tipo,
                    c.codigo,
                    c.descricao
                FROM alta_diagnosticos ad
                INNER JOIN cid10 c
                    ON c.id = ad.cid10_id
                WHERE ad.alta_id = :alta_id
                ORDER BY
                    CASE
                        WHEN ad.tipo = 'PRINCIPAL' THEN 0
                        ELSE 1
                    END,
                    ad.id
            ");

            $stmtDiagnosticosAlta->execute([
                ':alta_id' => (int) $alta['id']
            ]);

            $alta['diagnosticos'] =
                $diagnosticosDaEvolucao;

            if (
                $altaSolicitadaId > 0
                && (int) $alta['id'] === $altaSolicitadaId
                && $alta['status'] === 'RASCUNHO'
            ) {
                $altaAtual = $alta;
            } else {
                $altasAnteriores[] = $alta;
            }
        }
    } catch (Throwable $erro) {
        $erroAlta =
            'Não foi possível carregar a Alta Médica. '
            . 'Verifique se a migração da Sprint 8 foi executada.';

        error_log(
            'Erro ao carregar Alta Médica: '
            . $erro->getMessage()
        );
    }
}


if ($atendimento && $aba === 'historico') {
    $pacienteHistoricoId = (int) $atendimento['paciente_id'];
    $errosHistorico = [];

    /*
     * Cada seção é carregada isoladamente.
     * Se uma tabela/coluna opcional tiver diferença de versão,
     * as demais seções continuam funcionando.
     */
    try {
        $stmtHistAt = $pdo->prepare("
            SELECT
                a.id,
                a.numero_atendimento,
                a.status,
                a.criado_em,
                m.nome AS medico_nome,
                m.crm,
                m.uf_crm,
                e.nome AS especialidade_nome
            FROM atendimentos a
            LEFT JOIN medicos m
                ON m.id = a.profissional_id
            LEFT JOIN especialidades e
                ON e.id = m.especialidade_id
            WHERE a.paciente_id = :paciente_id
            ORDER BY a.id DESC
        ");
        $stmtHistAt->execute([
            ':paciente_id' => $pacienteHistoricoId
        ]);
        $historicoPaciente['atendimentos'] =
            $stmtHistAt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $erro) {
        $errosHistorico[] = 'atendimentos';
        error_log(
            'Histórico/atendimentos: ' . $erro->getMessage()
        );
    }

    try {
        $stmtHistAlg = $pdo->prepare("
            SELECT *
            FROM alergias_paciente
            WHERE paciente_id = :paciente_id
            ORDER BY ativa DESC, id DESC
        ");
        $stmtHistAlg->execute([
            ':paciente_id' => $pacienteHistoricoId
        ]);
        $historicoPaciente['alergias'] =
            $stmtHistAlg->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $erro) {
        $errosHistorico[] = 'alergias';
        error_log(
            'Histórico/alergias: ' . $erro->getMessage()
        );
    }

    try {
        $stmtHistEvo = $pdo->prepare("
            SELECT
                em.*,
                a.numero_atendimento,
                m.nome AS medico_nome,
                m.crm,
                m.uf_crm
            FROM evolucoes_medicas em
            INNER JOIN atendimentos a
                ON a.id = em.atendimento_id
            LEFT JOIN medicos m
                ON m.id = em.medico_id
            WHERE em.paciente_id = :paciente_id
            ORDER BY em.id DESC
        ");
        $stmtHistEvo->execute([
            ':paciente_id' => $pacienteHistoricoId
        ]);
        $historicoPaciente['evolucoes'] =
            $stmtHistEvo->fetchAll(PDO::FETCH_ASSOC);

        foreach ($historicoPaciente['evolucoes'] as &$evoHist) {
            $evoHist['diagnosticos'] = [];

            try {
                $stmtHistCid = $pdo->prepare("
                    SELECT
                        c.codigo,
                        c.descricao,
                        ed.tipo
                    FROM evolucao_diagnosticos ed
                    INNER JOIN cid10 c
                        ON c.id = ed.cid10_id
                    WHERE ed.evolucao_id = :id
                    ORDER BY
                        CASE
                            WHEN ed.tipo = 'PRINCIPAL' THEN 0
                            ELSE 1
                        END,
                        ed.id
                ");
                $stmtHistCid->execute([
                    ':id' => (int) $evoHist['id']
                ]);
                $evoHist['diagnosticos'] =
                    $stmtHistCid->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $erro) {
                error_log(
                    'Histórico/CID evolução: '
                    . $erro->getMessage()
                );
            }
        }
        unset($evoHist);
    } catch (Throwable $erro) {
        $errosHistorico[] = 'evoluções';
        error_log(
            'Histórico/evoluções: ' . $erro->getMessage()
        );
    }

    try {
        $stmtHistRx = $pdo->prepare("
            SELECT
                pm.*,
                a.numero_atendimento,
                m.nome AS medico_nome,
                m.crm,
                m.uf_crm
            FROM prescricoes_medicas pm
            INNER JOIN atendimentos a
                ON a.id = pm.atendimento_id
            LEFT JOIN medicos m
                ON m.id = pm.medico_id
            WHERE pm.paciente_id = :paciente_id
            ORDER BY pm.id DESC
        ");
        $stmtHistRx->execute([
            ':paciente_id' => $pacienteHistoricoId
        ]);
        $historicoPaciente['prescricoes'] =
            $stmtHistRx->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $erro) {
        $errosHistorico[] = 'prescrições';
        error_log(
            'Histórico/prescrições: ' . $erro->getMessage()
        );
    }

    try {
        $stmtHistDoc = $pdo->prepare("
            SELECT
                dm.*,
                a.numero_atendimento,
                m.nome AS medico_nome,
                m.crm,
                m.uf_crm
            FROM documentos_medicos dm
            INNER JOIN atendimentos a
                ON a.id = dm.atendimento_id
            LEFT JOIN medicos m
                ON m.id = dm.medico_id
            WHERE dm.paciente_id = :paciente_id
            ORDER BY dm.id DESC
        ");
        $stmtHistDoc->execute([
            ':paciente_id' => $pacienteHistoricoId
        ]);
        $historicoPaciente['documentos'] =
            $stmtHistDoc->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $erro) {
        $errosHistorico[] = 'documentos';
        error_log(
            'Histórico/documentos: ' . $erro->getMessage()
        );
    }

    try {
        $stmtHistAlta = $pdo->prepare("
            SELECT
                am.*,
                a.numero_atendimento,
                m.nome AS medico_nome,
                m.crm,
                m.uf_crm
            FROM altas_medicas am
            INNER JOIN atendimentos a
                ON a.id = am.atendimento_id
            LEFT JOIN medicos m
                ON m.id = am.medico_id
            WHERE am.paciente_id = :paciente_id
            ORDER BY am.id DESC
        ");
        $stmtHistAlta->execute([
            ':paciente_id' => $pacienteHistoricoId
        ]);
        $historicoPaciente['altas'] =
            $stmtHistAlta->fetchAll(PDO::FETCH_ASSOC);

        foreach ($historicoPaciente['altas'] as &$altaHist) {
            $altaHist['diagnosticos'] = [];

            try {
                $stmtAltaCid = $pdo->prepare("
                    SELECT
                        c.codigo,
                        c.descricao,
                        ad.tipo
                    FROM alta_diagnosticos ad
                    INNER JOIN cid10 c
                        ON c.id = ad.cid10_id
                    WHERE ad.alta_id = :id
                    ORDER BY
                        CASE
                            WHEN ad.tipo = 'PRINCIPAL' THEN 0
                            ELSE 1
                        END,
                        ad.id
                ");
                $stmtAltaCid->execute([
                    ':id' => (int) $altaHist['id']
                ]);
                $altaHist['diagnosticos'] =
                    $stmtAltaCid->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $erro) {
                error_log(
                    'Histórico/CID alta: '
                    . $erro->getMessage()
                );
            }
        }
        unset($altaHist);
    } catch (Throwable $erro) {
        $errosHistorico[] = 'altas';
        error_log(
            'Histórico/altas: ' . $erro->getMessage()
        );
    }

    if ($errosHistorico) {
        $erroHistorico =
            'Algumas áreas do histórico não puderam ser carregadas: '
            . implode(', ', array_unique($errosHistorico))
            . '. As demais informações continuam disponíveis.';
    }
}

$statusRotulo = [
    'AGUARDANDO_MEDICO' => 'Aguardando médico',
    'EM_ATENDIMENTO' => 'Em atendimento',
    'FINALIZADO' => 'Finalizado'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($tituloPagina) ?> | Safe Health</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="/safe-health/assets/css/prontuario.css?v=21"
    >
</head>

<body data-prontuario-aba="<?= htmlspecialchars($aba) ?>" data-atendimento-id="<?= (int) $atendimentoId ?>">

<div class="pep-shell">

    <aside class="pep-sidebar">

        <div class="pep-brand">
            <div class="pep-brand-icon">
                <i class="fa-solid fa-notes-medical"></i>
            </div>

            <div>
                <strong>Safe Health</strong>
                <span>Prontuário Eletrônico</span>
            </div>
        </div>

        <nav class="pep-nav">

            <a
                class="pep-nav-item <?= abaAtiva($aba, 'atendimento-atual') ?>"
                href="<?= htmlspecialchars(urlProntuario('atendimento-atual', $atendimentoId)) ?>"
            >
                <span class="pep-nav-number">0</span>
                <i class="fa-solid fa-bed-pulse"></i>
                <span>Atendimento Atual</span>
            </a>

            <a
                class="pep-nav-item <?= abaAtiva($aba, 'alergias') ?>"
                href="<?= htmlspecialchars(urlProntuario('alergias', $atendimentoId)) ?>"
            >
                <span class="pep-nav-number">1</span>
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Alergias</span>
            </a>

            <a
                class="pep-nav-item <?= abaAtiva($aba, 'prescricao') ?>"
                href="<?= htmlspecialchars(urlProntuario('prescricao', $atendimentoId)) ?>"
            >
                <span class="pep-nav-number">2</span>
                <i class="fa-solid fa-prescription"></i>
                <span>Prescrição Médica</span>
            </a>

            <a
                class="pep-nav-item <?= abaAtiva($aba, 'evolucao') ?>"
                href="<?= htmlspecialchars(urlProntuario('evolucao', $atendimentoId)) ?>"
            >
                <span class="pep-nav-number">3</span>
                <i class="fa-solid fa-file-waveform"></i>
                <span>Evolução Médica</span>
            </a>

            <a
                class="pep-nav-item <?= abaAtiva($aba, 'documentos') ?>"
                href="<?= htmlspecialchars(urlProntuario('documentos', $atendimentoId)) ?>"
            >
                <span class="pep-nav-number">4</span>
                <i class="fa-solid fa-file-signature"></i>
                <span>Documentos / Receituário</span>
            </a>

            <a
                class="pep-nav-item <?= abaAtiva($aba, 'alta') ?>"
                href="<?= htmlspecialchars(urlProntuario('alta', $atendimentoId)) ?>"
            >
                <span class="pep-nav-number">5</span>
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Alta Médica</span>
            </a>

            <a
                class="pep-nav-item <?= abaAtiva($aba, 'historico') ?>"
                href="<?= htmlspecialchars(urlProntuario('historico', $atendimentoId)) ?>"
            >
                <span class="pep-nav-number">6</span>
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Histórico do Paciente</span>
            </a>

        </nav>

        <div class="pep-sidebar-footer">
            <a href="/safe-health/">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Voltar ao Safe Health</span>
            </a>
        </div>

    </aside>

    <section class="pep-workspace">

        <header class="pep-topbar">

            <div class="pep-topbar-left">
                <button
                    type="button"
                    class="pep-menu-toggle"
                    id="pepMenuToggle"
                    aria-label="Abrir menu"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>
                    <span class="pep-eyebrow">Prontuário Eletrônico</span>
                    <h1><?= htmlspecialchars($tituloPagina) ?></h1>
                </div>
            </div>

            <div class="pep-topbar-right">

                <a
                    href="/safe-health/modules/prontuario/atendimentos.php"
                    class="pep-back-patients"
                    title="Voltar para a lista de pacientes"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Lista de pacientes</span>
                </a>

                <div class="pep-user">
                    <div class="pep-user-text">
                    <strong><?= htmlspecialchars($nomeUsuario) ?></strong>
                    <span>Ambiente clínico</span>
                </div>

                    <div class="pep-avatar">
                        <?= htmlspecialchars($iniciais) ?>
                    </div>
                </div>
            </div>

        </header>

        <section class="pep-patientbar">

            <?php if ($atendimentoId > 0): ?>

                <?php if ($atendimento): ?>
                    <div class="patient-id-box">
                        <span>Atendimento</span>
                        <strong>
                            <?= htmlspecialchars(
                                $atendimento['numero_atendimento']
                            ) ?>
                        </strong>
                    </div>

                    <div class="patient-summary">
                        <strong>
                            <?= htmlspecialchars(
                                $atendimento['paciente_nome']
                            ) ?>
                        </strong>
                        <span>
                            <?= htmlspecialchars(
                                calcularIdadePep(
                                    $atendimento['data_nascimento']
                                )
                            ) ?>
                            •
                            <?= htmlspecialchars(
                                $atendimento['sexo']
                                    ?: 'Sexo não informado'
                            ) ?>
                            •
                            CPF
                            <?= htmlspecialchars(
                                formatarCpfPep($atendimento['cpf'])
                            ) ?>
                        </span>
                    </div>

                    <div class="patient-status">
                        <span class="status-dot"></span>
                        <?= htmlspecialchars(
                            $statusRotulo[$atendimento['status']]
                                ?? $atendimento['status']
                                ?? 'Em atendimento'
                        ) ?>
                    </div>
                <?php else: ?>
                    <div class="patient-summary empty">
                        <div class="patient-empty-icon">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <strong>Atendimento indisponível</strong>
                            <span>
                                <?= htmlspecialchars(
                                    $erroAtendimento
                                        ?: 'Não foi possível carregar o atendimento.'
                                ) ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>

                <div class="patient-summary empty">
                    <div class="patient-empty-icon">
                        <i class="fa-solid fa-user-injured"></i>
                    </div>

                    <div>
                        <strong>Nenhum atendimento selecionado</strong>
                        <span>
                            Acesse o prontuário a partir de um atendimento
                            para visualizar os dados do paciente.
                        </span>
                    </div>
                </div>

            <?php endif; ?>

        </section>

        <main class="pep-content">

            <?php if ($aba === 'atendimento-atual'): ?>

                <div class="content-grid">

                    <section class="pep-card card-main">
                        <div class="pep-card-header">
                            <div>
                                <span class="section-kicker">
                                    Visão clínica
                                </span>
                                <h2>Atendimento Atual</h2>
                            </div>

                            <span class="card-badge">
                                <i class="fa-solid fa-stethoscope"></i>
                                PEP
                            </span>
                        </div>

                        <?php if ($atendimento): ?>

                            <div class="clinical-sections">

                                <section class="clinical-section">
                                    <div class="clinical-section-title">
                                        <div>
                                            <span class="section-kicker">
                                                Identificação
                                            </span>
                                            <h3>Paciente</h3>
                                        </div>
                                        <i class="fa-solid fa-user-injured"></i>
                                    </div>

                                    <div class="clinical-data-grid">
                                        <div class="clinical-data">
                                            <span>Nome completo</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $atendimento['paciente_nome']
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="clinical-data">
                                            <span>CPF</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    formatarCpfPep(
                                                        $atendimento['cpf']
                                                    )
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="clinical-data">
                                            <span>Idade</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    calcularIdadePep(
                                                        $atendimento['data_nascimento']
                                                    )
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="clinical-data">
                                            <span>Sexo</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $atendimento['sexo']
                                                        ?: 'Não informado'
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="clinical-data">
                                            <span>Telefone</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $atendimento['telefone']
                                                        ?: 'Não informado'
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="clinical-data">
                                            <span>Cartão SUS</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $atendimento['cartao_sus']
                                                        ?: 'Não informado'
                                                ) ?>
                                            </strong>
                                        </div>
                                    </div>
                                </section>

                                <section class="clinical-section">
                                    <div class="clinical-section-title">
                                        <div>
                                            <span class="section-kicker">
                                                Atendimento
                                            </span>
                                            <h3>Dados assistenciais</h3>
                                        </div>
                                        <i class="fa-solid fa-hospital"></i>
                                    </div>

                                    <div class="clinical-data-grid">
                                        <div class="clinical-data">
                                            <span>Número</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $atendimento['numero_atendimento']
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="clinical-data">
                                            <span>Entrada</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    formatarDataHoraPep(
                                                        $atendimento['data_entrada']
                                                    )
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="clinical-data">
                                            <span>Tipo</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $atendimento['tipo_atendimento']
                                                        ?: 'Não informado'
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="clinical-data">
                                            <span>Origem</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $atendimento['origem']
                                                        ?: 'Não informada'
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="clinical-data">
                                            <span>Convênio</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $atendimento['convenio']
                                                        ?: 'Não informado'
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="clinical-data">
                                            <span>Especialidade</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $atendimento['especialidade']
                                                        ?: 'Não informada'
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="clinical-data clinical-data-wide">
                                            <span>Médico responsável</span>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $atendimento['profissional_responsavel']
                                                        ?: 'Não informado'
                                                ) ?>
                                            </strong>
                                        </div>
                                    </div>
                                </section>

                                <section class="clinical-section">
                                    <div class="clinical-section-title">
                                        <div>
                                            <span class="section-kicker">
                                                Enfermagem
                                            </span>
                                            <h3>Triagem e sinais vitais</h3>
                                        </div>

                                        <?php if (
                                            !empty(
                                                $atendimento['classificacao_risco']
                                            )
                                        ): ?>
                                            <span class="risk-badge risk-<?=
                                                strtolower(
                                                    htmlspecialchars(
                                                        $atendimento[
                                                            'classificacao_risco'
                                                        ]
                                                    )
                                                )
                                            ?>">
                                                <?= htmlspecialchars(
                                                    $atendimento[
                                                        'classificacao_risco'
                                                    ]
                                                ) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($atendimento['triagem_id']): ?>
                                        <div class="complaint-box">
                                            <span>Queixa principal</span>
                                            <strong>
                                                <?= nl2br(
                                                    htmlspecialchars(
                                                        $atendimento[
                                                            'queixa_principal'
                                                        ]
                                                            ?: 'Não informada'
                                                    )
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="vital-grid">
                                            <div class="vital-card">
                                                <i class="fa-solid fa-heart-pulse"></i>
                                                <span>Pressão arterial</span>
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        valorClinicoPep(
                                                            $atendimento[
                                                                'pressao_arterial'
                                                            ],
                                                            ' mmHg'
                                                        )
                                                    ) ?>
                                                </strong>
                                            </div>

                                            <div class="vital-card">
                                                <i class="fa-solid fa-heart"></i>
                                                <span>Freq. cardíaca</span>
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        valorClinicoPep(
                                                            $atendimento[
                                                                'frequencia_cardiaca'
                                                            ],
                                                            ' bpm'
                                                        )
                                                    ) ?>
                                                </strong>
                                            </div>

                                            <div class="vital-card">
                                                <i class="fa-solid fa-lungs"></i>
                                                <span>Freq. respiratória</span>
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        valorClinicoPep(
                                                            $atendimento[
                                                                'frequencia_respiratoria'
                                                            ],
                                                            ' irpm'
                                                        )
                                                    ) ?>
                                                </strong>
                                            </div>

                                            <div class="vital-card">
                                                <i class="fa-solid fa-temperature-half"></i>
                                                <span>Temperatura</span>
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        valorClinicoPep(
                                                            $atendimento[
                                                                'temperatura'
                                                            ],
                                                            ' °C'
                                                        )
                                                    ) ?>
                                                </strong>
                                            </div>

                                            <div class="vital-card">
                                                <i class="fa-solid fa-droplet"></i>
                                                <span>Saturação</span>
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        valorClinicoPep(
                                                            $atendimento[
                                                                'saturacao'
                                                            ],
                                                            '%'
                                                        )
                                                    ) ?>
                                                </strong>
                                            </div>

                                            <div class="vital-card">
                                                <i class="fa-solid fa-weight-scale"></i>
                                                <span>Peso</span>
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        valorClinicoPep(
                                                            $atendimento[
                                                                'peso'
                                                            ],
                                                            ' kg'
                                                        )
                                                    ) ?>
                                                </strong>
                                            </div>

                                            <div class="vital-card">
                                                <i class="fa-solid fa-bolt"></i>
                                                <span>Escala de dor</span>
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        valorClinicoPep(
                                                            $atendimento[
                                                                'escala_dor'
                                                            ],
                                                            '/10'
                                                        )
                                                    ) ?>
                                                </strong>
                                            </div>
                                        </div>

                                        <?php if (
                                            !empty(
                                                $atendimento[
                                                    'triagem_observacoes'
                                                ]
                                            )
                                        ): ?>
                                            <div class="clinical-note">
                                                <span>Observações da triagem</span>
                                                <p>
                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            $atendimento[
                                                                'triagem_observacoes'
                                                            ]
                                                        )
                                                    ) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="clinical-meta">
                                            Triagem realizada em
                                            <?= htmlspecialchars(
                                                formatarDataHoraPep(
                                                    $atendimento[
                                                        'triagem_realizada_em'
                                                    ]
                                                )
                                            ) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="clinical-empty">
                                            <i class="fa-solid fa-circle-info"></i>
                                            <div>
                                                <strong>
                                                    Triagem não registrada
                                                </strong>
                                                <span>
                                                    Em atendimentos ambulatoriais,
                                                    a triagem pode não ser aplicável.
                                                </span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </section>

                            </div>

                        <?php elseif ($atendimentoId > 0): ?>

                            <div class="clinical-placeholder">
                                <div class="placeholder-icon">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </div>

                                <div>
                                    <h3>Atendimento indisponível</h3>
                                    <p>
                                        <?= htmlspecialchars(
                                            $erroAtendimento
                                                ?: 'Não foi possível carregar os dados.'
                                        ) ?>
                                    </p>
                                </div>
                            </div>

                        <?php else: ?>

                            <div class="clinical-placeholder">
                                <div class="placeholder-icon">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </div>

                                <div>
                                    <h3>Selecione um atendimento</h3>

                                    <p>
                                        O prontuário eletrônico será aberto
                                        sempre associado a um atendimento
                                        hospitalar, mantendo a rastreabilidade
                                        das informações clínicas.
                                    </p>
                                </div>
                            </div>

                        <?php endif; ?>

                    </section>

                    <section class="pep-card">
                        <div class="pep-card-header compact">
                            <div>
                                <span class="section-kicker">
                                    Navegação
                                </span>
                                <h2>Acesso rápido</h2>
                            </div>
                        </div>

                        <div class="quick-actions">

                            <a href="<?= htmlspecialchars(urlProntuario('alergias', $atendimentoId)) ?>">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <span>Alergias</span>
                            </a>

                            <a href="<?= htmlspecialchars(urlProntuario('prescricao', $atendimentoId)) ?>">
                                <i class="fa-solid fa-prescription"></i>
                                <span>Prescrever</span>
                            </a>

                            <a href="<?= htmlspecialchars(urlProntuario('evolucao', $atendimentoId)) ?>">
                                <i class="fa-solid fa-file-waveform"></i>
                                <span>Evolução</span>
                            </a>

                            <a href="<?= htmlspecialchars(urlProntuario('alta', $atendimentoId)) ?>">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                <span>Alta</span>
                            </a>

                        </div>
                    </section>

                </div>

            <?php elseif ($aba === 'alergias'): ?>

                <div
                    class="allergy-page"
                    data-atendimento-id="<?= (int) $atendimentoId ?>"
                >
                    <section class="pep-card allergy-main-card">
                        <div class="pep-card-header allergy-header">
                            <div>
                                <span class="section-kicker">
                                    Segurança clínica
                                </span>
                                <h2>Alergias do paciente</h2>
                                <p class="allergy-subtitle">
                                    O histórico de alergias é longitudinal:
                                    os registros acompanham o paciente em
                                    atendimentos futuros.
                                </p>
                            </div>

                            <?php
                            $ativas = array_values(
                                array_filter(
                                    $alergiasPaciente,
                                    static fn ($item) =>
                                        ($item['status'] ?? '') === 'ATIVA'
                                )
                            );
                            ?>

                            <div class="allergy-counter <?= count($ativas) > 0 ? 'has-allergy' : '' ?>">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <strong><?= count($ativas) ?></strong>
                                <span>
                                    <?= count($ativas) === 1
                                        ? 'alergia ativa'
                                        : 'alergias ativas' ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!$atendimento): ?>
                            <div class="clinical-placeholder">
                                <div class="placeholder-icon">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </div>
                                <div>
                                    <h3>Atendimento indisponível</h3>
                                    <p>
                                        <?= htmlspecialchars(
                                            $erroAtendimento
                                                ?: 'Não foi possível carregar o atendimento.'
                                        ) ?>
                                    </p>
                                </div>
                            </div>

                        <?php elseif ($erroAlergias): ?>
                            <div class="pep-alert pep-alert-error">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <span>
                                    <?= htmlspecialchars($erroAlergias) ?>
                                </span>
                            </div>

                        <?php else: ?>
                            <div class="allergy-patient-strip">
                                <div>
                                    <span>Paciente</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $atendimento['paciente_nome']
                                        ) ?>
                                    </strong>
                                </div>
                                <div>
                                    <span>Atendimento</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $atendimento['numero_atendimento']
                                        ) ?>
                                    </strong>
                                </div>
                                <div>
                                    <span>CPF</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            formatarCpfPep(
                                                $atendimento['cpf']
                                            )
                                        ) ?>
                                    </strong>
                                </div>
                            </div>

                            <div class="allergy-layout">
                                <section class="allergy-list-panel">
                                    <div class="allergy-section-heading">
                                        <div>
                                            <span class="section-kicker">
                                                Histórico
                                            </span>
                                            <h3>Registros de alergia</h3>
                                        </div>

                                        <button
                                            type="button"
                                            class="btn-primary-clinical"
                                            id="btnNovaAlergia"
                                        >
                                            <i class="fa-solid fa-plus"></i>
                                            Registrar alergia
                                        </button>
                                    </div>

                                    <?php if (count($alergiasPaciente) === 0): ?>
                                        <div class="clinical-empty allergy-empty">
                                            <i class="fa-solid fa-shield-heart"></i>
                                            <div>
                                                <strong>
                                                    Nenhuma alergia registrada
                                                </strong>
                                                <span>
                                                    Isso significa apenas que
                                                    não há registro cadastrado
                                                    no Safe Health. Não equivale
                                                    a uma confirmação clínica de
                                                    ausência de alergias.
                                                </span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="allergy-list">
                                            <?php foreach ($alergiasPaciente as $alergia): ?>
                                                <?php
                                                $statusAtivo =
                                                    $alergia['status'] === 'ATIVA';

                                                $gravidadeClasse =
                                                    strtolower(
                                                        str_replace(
                                                            '_',
                                                            '-',
                                                            $alergia['gravidade']
                                                        )
                                                    );
                                                ?>
                                                <article class="allergy-item <?= $statusAtivo ? 'is-active' : 'is-inactive' ?>">
                                                    <div class="allergy-item-icon">
                                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                                    </div>

                                                    <div class="allergy-item-body">
                                                        <div class="allergy-item-top">
                                                            <div>
                                                                <h4>
                                                                    <?= htmlspecialchars(
                                                                        $alergia['substancia']
                                                                    ) ?>
                                                                </h4>
                                                                <span class="allergy-type">
                                                                    <?= htmlspecialchars(
                                                                        ucfirst(
                                                                            strtolower(
                                                                                $alergia['tipo']
                                                                            )
                                                                        )
                                                                    ) ?>
                                                                </span>
                                                            </div>

                                                            <div class="allergy-badges">
                                                                <span class="severity severity-<?= htmlspecialchars($gravidadeClasse) ?>">
                                                                    <?= htmlspecialchars(
                                                                        str_replace(
                                                                            '_',
                                                                            ' ',
                                                                            $alergia['gravidade']
                                                                        )
                                                                    ) ?>
                                                                </span>

                                                                <span class="allergy-status <?= $statusAtivo ? 'active' : 'inactive' ?>">
                                                                    <?= htmlspecialchars(
                                                                        $alergia['status']
                                                                    ) ?>
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div class="allergy-detail-grid">
                                                            <div>
                                                                <span>Reação</span>
                                                                <strong>
                                                                    <?= htmlspecialchars(
                                                                        $alergia['reacao']
                                                                            ?: 'Não informada'
                                                                    ) ?>
                                                                </strong>
                                                            </div>

                                                            <div>
                                                                <span>Registrada em</span>
                                                                <strong>
                                                                    <?= htmlspecialchars(
                                                                        formatarDataHoraPep(
                                                                            $alergia['criado_em']
                                                                        )
                                                                    ) ?>
                                                                </strong>
                                                            </div>

                                                            <div>
                                                                <span>Registrada por</span>
                                                                <strong>
                                                                    <?= htmlspecialchars(
                                                                        $alergia['registrado_por']
                                                                            ?: 'Usuário não identificado'
                                                                    ) ?>
                                                                </strong>
                                                            </div>
                                                        </div>

                                                        <?php if (!empty($alergia['observacoes'])): ?>
                                                            <div class="allergy-observation">
                                                                <?= nl2br(
                                                                    htmlspecialchars(
                                                                        $alergia['observacoes']
                                                                    )
                                                                ) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if ($statusAtivo): ?>
                                                        <div class="allergy-item-actions">
                                                            <button
                                                                type="button"
                                                                class="btn-inactivate-allergy"
                                                                data-allergy-id="<?= (int) $alergia['id'] ?>"
                                                                data-allergy-name="<?= htmlspecialchars(
                                                                    $alergia['substancia'],
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>"
                                                            >
                                                                <i class="fa-solid fa-ban"></i>
                                                                Inativar
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>

                                <aside class="allergy-safety-panel">
                                    <div class="allergy-safety-icon">
                                        <i class="fa-solid fa-shield-halved"></i>
                                    </div>
                                    <h3>Alerta de segurança</h3>
                                    <p>
                                        Alergias ativas devem permanecer
                                        visíveis durante todo o atendimento e
                                        serão usadas nas próximas etapas para
                                        apoiar a conferência da prescrição
                                        médica.
                                    </p>

                                    <?php if (count($ativas) > 0): ?>
                                        <div class="active-allergy-summary">
                                            <span>Alergias ativas</span>
                                            <?php foreach ($ativas as $alergia): ?>
                                                <strong>
                                                    <i class="fa-solid fa-circle-exclamation"></i>
                                                    <?= htmlspecialchars(
                                                        $alergia['substancia']
                                                    ) ?>
                                                </strong>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-active-allergy">
                                            <i class="fa-solid fa-circle-info"></i>
                                            <span>
                                                Nenhuma alergia ativa está
                                                cadastrada neste momento.
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </aside>
                            </div>
                        <?php endif; ?>
                    </section>

                    <?php if ($atendimento && !$erroAlergias): ?>
                        <div
                            class="allergy-modal"
                            id="allergyModal"
                            aria-hidden="true"
                        >
                            <div class="allergy-modal-backdrop" data-close-allergy-modal></div>

                            <section
                                class="allergy-modal-dialog"
                                role="dialog"
                                aria-modal="true"
                                aria-labelledby="allergyModalTitle"
                            >
                                <div class="allergy-modal-header">
                                    <div>
                                        <span class="section-kicker">
                                            Registro clínico
                                        </span>
                                        <h3 id="allergyModalTitle">
                                            Registrar nova alergia
                                        </h3>
                                    </div>

                                    <button
                                        type="button"
                                        class="allergy-modal-close"
                                        data-close-allergy-modal
                                        aria-label="Fechar"
                                    >
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>

                                <form id="formAlergia">
                                    <input
                                        type="hidden"
                                        name="atendimento_id"
                                        value="<?= (int) $atendimentoId ?>"
                                    >

                                    <div class="allergy-form-grid">
                                        <div class="field allergy-field-wide">
                                            <label for="substancia">
                                                Substância / agente
                                                <span>*</span>
                                            </label>
                                            <input
                                                type="text"
                                                id="substancia"
                                                name="substancia"
                                                maxlength="150"
                                                placeholder="Ex.: Dipirona, amendoim, látex"
                                                required
                                            >
                                        </div>

                                        <div class="field">
                                            <label for="tipo">
                                                Tipo
                                                <span>*</span>
                                            </label>
                                            <select
                                                id="tipo"
                                                name="tipo"
                                                required
                                            >
                                                <option value="MEDICAMENTO">
                                                    Medicamento
                                                </option>
                                                <option value="ALIMENTO">
                                                    Alimento
                                                </option>
                                                <option value="AMBIENTAL">
                                                    Ambiental
                                                </option>
                                                <option value="MATERIAL">
                                                    Material
                                                </option>
                                                <option value="OUTRO">
                                                    Outro
                                                </option>
                                            </select>
                                        </div>

                                        <div class="field">
                                            <label for="gravidade">
                                                Gravidade
                                                <span>*</span>
                                            </label>
                                            <select
                                                id="gravidade"
                                                name="gravidade"
                                                required
                                            >
                                                <option value="NAO_INFORMADA">
                                                    Não informada
                                                </option>
                                                <option value="LEVE">
                                                    Leve
                                                </option>
                                                <option value="MODERADA">
                                                    Moderada
                                                </option>
                                                <option value="GRAVE">
                                                    Grave
                                                </option>
                                            </select>
                                        </div>

                                        <div class="field allergy-field-wide">
                                            <label for="reacao">
                                                Reação apresentada
                                            </label>
                                            <input
                                                type="text"
                                                id="reacao"
                                                name="reacao"
                                                maxlength="255"
                                                placeholder="Ex.: urticária, dispneia, edema"
                                            >
                                        </div>

                                        <div class="field allergy-field-wide">
                                            <label for="observacoes">
                                                Observações
                                            </label>
                                            <textarea
                                                id="observacoes"
                                                name="observacoes"
                                                rows="4"
                                                maxlength="1000"
                                                placeholder="Informações clínicas adicionais"
                                            ></textarea>
                                        </div>
                                    </div>

                                    <div
                                        class="allergy-form-feedback"
                                        id="allergyFormFeedback"
                                        hidden
                                    ></div>

                                    <div class="allergy-modal-footer">
                                        <button
                                            type="button"
                                            class="btn-secondary-clinical"
                                            data-close-allergy-modal
                                        >
                                            Cancelar
                                        </button>

                                        <button
                                            type="submit"
                                            class="btn-primary-clinical"
                                            id="btnSalvarAlergia"
                                        >
                                            <i class="fa-solid fa-floppy-disk"></i>
                                            Salvar alergia
                                        </button>
                                    </div>
                                </form>
                            </section>
                        </div>
                    <?php endif; ?>
                </div>

                <script src="/safe-health/assets/js/prontuario-alergias.js?v=1"></script>

            <?php elseif ($aba === 'prescricao'): ?>

                <div
                    class="rx-page"
                    data-atendimento-id="<?= (int) $atendimentoId ?>"
                    data-prescricao-id="<?= (int) ($prescricaoAtual['id'] ?? 0) ?>"
                >
                    <section class="pep-card rx-main-card">
                        <div class="pep-card-header rx-header">
                            <div>
                                <span class="section-kicker">
                                    Terapêutica
                                </span>
                                <h2>Prescrição Médica</h2>
                                <p class="rx-subtitle">
                                    Elabore a prescrição do atendimento e
                                    libere-a para a Farmácia somente após a
                                    conferência dos itens.
                                </p>
                            </div>

                            <?php if ($prescricaoAtual): ?>
                                <div class="rx-draft-badge">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    <div>
                                        <strong>
                                            Prescrição #<?= (int) $prescricaoAtual['id'] ?>
                                        </strong>
                                        <span>Rascunho</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$atendimento): ?>
                            <div class="clinical-placeholder">
                                <div class="placeholder-icon">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </div>
                                <div>
                                    <h3>Atendimento indisponível</h3>
                                    <p>
                                        <?= htmlspecialchars(
                                            $erroAtendimento
                                                ?: 'Não foi possível carregar o atendimento.'
                                        ) ?>
                                    </p>
                                </div>
                            </div>

                        <?php elseif ($erroPrescricao): ?>
                            <div class="pep-alert pep-alert-error">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <span>
                                    <?= htmlspecialchars($erroPrescricao) ?>
                                </span>
                            </div>

                        <?php else: ?>

                            <div class="rx-patient-strip">
                                <div>
                                    <span>Paciente</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $atendimento['paciente_nome']
                                        ) ?>
                                    </strong>
                                </div>
                                <div>
                                    <span>Atendimento</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $atendimento['numero_atendimento']
                                        ) ?>
                                    </strong>
                                </div>
                                <div>
                                    <span>Especialidade</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $atendimento['especialidade']
                                                ?: 'Não informada'
                                        ) ?>
                                    </strong>
                                </div>
                            </div>

                            <?php if (count($alergiasAtivasPrescricao) > 0): ?>
                                <section class="rx-allergy-alert">
                                    <div class="rx-allergy-alert-icon">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                    </div>
                                    <div class="rx-allergy-alert-content">
                                        <span>ALERGIAS ATIVAS</span>
                                        <strong>
                                            Conferir antes de prescrever
                                        </strong>
                                        <div class="rx-allergy-tags">
                                            <?php foreach ($alergiasAtivasPrescricao as $alergia): ?>
                                                <span>
                                                    <?= htmlspecialchars(
                                                        $alergia['substancia']
                                                    ) ?>
                                                    <?php if (
                                                        $alergia['gravidade']
                                                        !== 'NAO_INFORMADA'
                                                    ): ?>
                                                        •
                                                        <?= htmlspecialchars(
                                                            $alergia['gravidade']
                                                        ) ?>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            <?php endif; ?>

                            <?php if (!$prescricaoAtual): ?>
                                <section class="rx-closed-state">
                                    <div class="rx-closed-icon">
                                        <i class="fa-solid fa-file-circle-plus"></i>
                                    </div>
                                    <div>
                                        <span class="section-kicker">
                                            Documento fechado
                                        </span>
                                        <h3>Nenhuma prescrição aberta</h3>
                                        <p>
                                            Clique em <strong>Novo</strong> para iniciar
                                            uma nova prescrição. Entrar neste módulo não
                                            cria nem abre documentos automaticamente.
                                        </p>
                                    </div>

                                    <div class="rx-document-actions rx-document-actions-closed">
                                        <button
                                            type="button"
                                            class="pep-action-new"
                                            data-clinical-new
                                        >
                                            <i class="fa-solid fa-file-circle-plus"></i>
                                            <span>Novo</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="pep-action-save"
                                            disabled
                                        >
                                            <i class="fa-solid fa-floppy-disk"></i>
                                            <span>Salvar</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="pep-action-sign"
                                            disabled
                                        >
                                            <i class="fa-solid fa-file-signature"></i>
                                            <span>Fechar e assinar</span>
                                        </button>
                                    </div>
                                </section>
                            <?php else: ?>

                            <div class="rx-layout">
                                <section class="rx-editor">
                                    <div class="rx-section-heading">
                                        <div>
                                            <span class="section-kicker">
                                                Prescrição atual
                                            </span>
                                            <h3>Itens da prescrição</h3>
                                        </div>

                                        <button
                                            type="button"
                                            class="btn-primary-clinical"
                                            id="btnAdicionarMedicamento"
                                        >
                                            <i class="fa-solid fa-plus"></i>
                                            Adicionar item
                                        </button>
                                    </div>

                                    <?php if (count($itensPrescricao) === 0): ?>
                                        <div class="clinical-empty rx-empty" id="rxEmpty">
                                            <i class="fa-solid fa-prescription-bottle-medical"></i>
                                            <div>
                                                <strong>
                                                    Nenhum item adicionado
                                                </strong>
                                                <span>
                                                    Use “Adicionar item”
                                                    para iniciar a prescrição.
                                                </span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="rx-items">
                                            <?php foreach ($itensPrescricao as $item): ?>
                                                <article class="rx-item">
                                                    <div class="rx-item-icon">
                                                        <i class="fa-solid fa-capsules"></i>
                                                    </div>

                                                    <div class="rx-item-body">
                                                        <div class="rx-item-title">
                                                            <div>
                                                                <h4>
                                                                    <?= htmlspecialchars(
                                                                        $item['medicamento_nome']
                                                                    ) ?>
                                                                </h4>
                                                                <span>
                                                                    <?= htmlspecialchars(
                                                                        trim(
                                                                            ($item['principio_ativo'] ?: '')
                                                                            . ' '
                                                                            . ($item['concentracao'] ?: '')
                                                                            . ' '
                                                                            . ($item['apresentacao'] ?: '')
                                                                        )
                                                                    ) ?>
                                                                </span>
                                                            </div>

                                                            <?php if ((int) $item['controlado'] === 1): ?>
                                                                <span class="rx-controlled">
                                                                    Controlado
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <div class="rx-item-grid">
                                                            <div>
                                                                <span>Dose</span>
                                                                <strong>
                                                                    <?= htmlspecialchars($item['dose']) ?>
                                                                </strong>
                                                            </div>
                                                            <div>
                                                                <span>Via</span>
                                                                <strong>
                                                                    <?= htmlspecialchars($item['via_administracao']) ?>
                                                                </strong>
                                                            </div>
                                                            <div>
                                                                <span>Frequência</span>
                                                                <strong>
                                                                    <?= htmlspecialchars($item['frequencia']) ?>
                                                                </strong>
                                                            </div>
                                                            <div>
                                                                <span>Quantidade</span>
                                                                <strong>
                                                                    <?= htmlspecialchars((string) $item['quantidade']) ?>
                                                                </strong>
                                                            </div>
                                                        </div>

                                                        <?php if (!empty($item['instrucoes'])): ?>
                                                            <div class="rx-instructions">
                                                                <?= nl2br(
                                                                    htmlspecialchars(
                                                                        $item['instrucoes']
                                                                    )
                                                                ) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        class="rx-remove-item"
                                                        title="Remover do rascunho"
                                                        data-item-id="<?= (int) $item['id'] ?>"
                                                        data-item-name="<?= htmlspecialchars(
                                                            $item['medicamento_nome'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                    >
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </article>
                                            <?php endforeach; ?>

                                            <?php foreach ($solicitacoesPrescricao as $solicitacao): ?>
                                                <article class="rx-item rx-order-item">
                                                    <div class="rx-item-icon">
                                                        <i class="fa-solid <?= $solicitacao['tipo_item'] === 'EXAME_IMAGEM' ? 'fa-x-ray' : 'fa-vials' ?>"></i>
                                                    </div>

                                                    <div class="rx-item-body">
                                                        <div class="rx-item-title">
                                                            <div>
                                                                <h4><?= htmlspecialchars($solicitacao['descricao']) ?></h4>
                                                                <span>
                                                                    <?= $solicitacao['tipo_item'] === 'EXAME_IMAGEM'
                                                                        ? 'Exame de imagem'
                                                                        : 'Exame laboratorial' ?>
                                                                    <?php if (!empty($solicitacao['codigo_item'])): ?>
                                                                        • <?= htmlspecialchars($solicitacao['codigo_item']) ?>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <?php if (!empty($solicitacao['detalhes'])): ?>
                                                            <div class="rx-instructions">
                                                                <?= nl2br(htmlspecialchars($solicitacao['detalhes'])) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        class="rx-remove-request"
                                                        title="Remover do rascunho"
                                                        data-request-id="<?= (int) $solicitacao['id'] ?>"
                                                        data-request-name="<?= htmlspecialchars($solicitacao['descricao'], ENT_QUOTES, 'UTF-8') ?>"
                                                    >
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (count($itensPrescricao) === 0 && count($solicitacoesPrescricao) > 0): ?>
                                        <div class="rx-items">
                                            <?php foreach ($solicitacoesPrescricao as $solicitacao): ?>
                                                <article class="rx-item rx-order-item">
                                                    <div class="rx-item-icon">
                                                        <i class="fa-solid <?= $solicitacao['tipo_item'] === 'EXAME_IMAGEM' ? 'fa-x-ray' : 'fa-vials' ?>"></i>
                                                    </div>
                                                    <div class="rx-item-body">
                                                        <div class="rx-item-title">
                                                            <div>
                                                                <h4><?= htmlspecialchars($solicitacao['descricao']) ?></h4>
                                                                <span>
                                                                    <?= $solicitacao['tipo_item'] === 'EXAME_IMAGEM'
                                                                        ? 'Exame de imagem'
                                                                        : 'Exame laboratorial' ?>
                                                                    <?php if (!empty($solicitacao['codigo_item'])): ?>
                                                                        • <?= htmlspecialchars($solicitacao['codigo_item']) ?>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <?php if (!empty($solicitacao['detalhes'])): ?>
                                                            <div class="rx-instructions">
                                                                <?= nl2br(htmlspecialchars($solicitacao['detalhes'])) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="rx-remove-request"
                                                        title="Remover do rascunho"
                                                        data-request-id="<?= (int) $solicitacao['id'] ?>"
                                                        data-request-name="<?= htmlspecialchars($solicitacao['descricao'], ENT_QUOTES, 'UTF-8') ?>"
                                                    >
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="rx-observation-box">
                                        <label for="rxObservacoes">
                                            Observações gerais da prescrição
                                        </label>
                                        <textarea
                                            id="rxObservacoes"
                                            rows="3"
                                            maxlength="2000"
                                            placeholder="Orientações gerais para a Farmácia ou equipe assistencial"
                                        ><?= htmlspecialchars(
                                            $prescricaoAtual['observacoes'] ?? ''
                                        ) ?></textarea>
                                        <span data-prescricao-id="<?= (int) ($prescricaoAtual['id'] ?? 0) ?>" hidden></span>
                                    <div class="rx-document-actions">
                                        <button type="button" class="pep-action-delete" id="btnExcluirPrescricao" title="Excluir esta prescrição em rascunho">
                                            <i class="fa-solid fa-trash-can"></i>
                                            <span>Excluir</span>
                                        </button>

                                        <button type="button" class="pep-action-new" data-clinical-new title="Criar uma nova prescrição">
                                            <i class="fa-solid fa-file-circle-plus"></i>
                                            <span>Novo</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="btn-secondary-clinical"
                                            id="btnSalvarObservacoes"
                                        >
                                            <i class="fa-solid fa-floppy-disk"></i>
                                            Salvar observações
                                        </button>

                                        <button type="button" class="pep-action-save" data-clinical-save title="Salvar a prescrição como rascunho">
                                            <i class="fa-solid fa-floppy-disk"></i>
                                            <span>Salvar</span>
                                        </button>

                                        <button type="button" class="pep-action-sign" data-clinical-sign title="Fechar e assinar a prescrição">
                                            <i class="fa-solid fa-file-signature"></i>
                                            <span>Fechar e assinar</span>
                                        </button>
                                    </div>
                                    </div>
                                </section>

                                
                            </div>

                            </div>
                            <?php endif; ?>

                            <?php if (count($prescricoesAnteriores) > 0): ?>
                                <section class="rx-history">
                                    <div class="rx-section-heading">
                                        <div>
                                            <span class="section-kicker">
                                                Histórico do atendimento
                                            </span>
                                            <h3>Prescrições finalizadas</h3>
                                        </div>
                                    </div>

                                    <div class="rx-history-list">
                                        <?php foreach ($prescricoesAnteriores as $historica): ?>
                                            <article>
                                                <div>
                                                    <strong>
                                                        Prescrição #<?= (int) $historica['id'] ?>
                                                    </strong>
                                                    <span>
                                                        <?= htmlspecialchars(
                                                            $historica['status']
                                                        ) ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <span>Prescrita em</span>
                                                    <strong>
                                                        <?= htmlspecialchars(
                                                            formatarDataHoraPep(
                                                                $historica['data_prescricao']
                                                            )
                                                        ) ?>
                                                    </strong>
                                                </div>
                                                <div>
                                                    <span>Finalizada em</span>
                                                    <strong>
                                                        <?= htmlspecialchars(
                                                            formatarDataHoraPep(
                                                                $historica['finalizada_em']
                                                                ?: $historica['data_liberacao_farmacia']
                                                            )
                                                        ) ?>
                                                    </strong>
                                                </div>

                                                <?php if ($historica['status'] === 'RASCUNHO'): ?>
                                                    <a
                                                        class="rx-open-draft"
                                                        href="/safe-health/modules/prontuario/index.php?aba=prescricao&atendimento_id=<?= (int) $atendimentoId ?>&prescricao_id=<?= (int) $historica['id'] ?>"
                                                    >
                                                        <i class="fa-solid fa-folder-open"></i>
                                                        Abrir rascunho
                                                    </a>
                                                <?php endif; ?>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endif; ?>

                        <?php endif; ?>
                    </section>

                    <?php if ($atendimento && !$erroPrescricao): ?>
                        <div class="rx-modal" id="rxModal" aria-hidden="true">
                            <div class="rx-modal-backdrop" data-close-rx-modal></div>

                            <section class="rx-modal-dialog" role="dialog" aria-modal="true">
                                <div class="rx-modal-header">
                                    <div>
                                        <span class="section-kicker">
                                            Prescrição Médica
                                        </span>
                                        <h3>Adicionar item à prescrição</h3>
                                    </div>
                                    <button
                                        type="button"
                                        class="rx-modal-close"
                                        data-close-rx-modal
                                    >
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>

                                <div class="rx-search-box">
                                    <label for="rxBuscaMedicamento">
                                        Pesquisar item cadastrado
                                    </label>
                                    <div class="rx-search-row">
                                        <input
                                            type="text"
                                            id="rxBuscaMedicamento"
                                            placeholder="Digite o nome do medicamento ou exame..."
                                            autocomplete="off"
                                        >
                                        <button
                                            type="button"
                                            class="btn-secondary-clinical"
                                            id="btnPesquisarMedicamento"
                                        >
                                            <i class="fa-solid fa-magnifying-glass"></i>
                                            Pesquisar
                                        </button>
                                    </div>
                                </div>

                                <div id="rxSearchResults" class="rx-search-results"></div>

                                <form id="rxItemForm" hidden>
                                    <input type="hidden" id="rxItemId">
                                    <input type="hidden" id="rxItemTipo">

                                    <div class="rx-selected-med" id="rxSelectedMed"></div>

                                    <div class="rx-med-parameters" id="rxMedicationFields">
                                        <div class="rx-param-card">
                                            <span>Dose</span>
                                            <strong id="rxDoseValor">—</strong>
                                        </div>

                                        <div class="rx-param-card">
                                            <span>Via</span>
                                            <strong id="rxViaValor">—</strong>
                                        </div>

                                        <div class="rx-param-card rx-param-select-card">
                                            <label for="rxFrequencia">Frequência</label>
                                            <select id="rxFrequencia" required>
                                                <option value="">Selecione</option>
                                            </select>
                                        </div>

                                        <div class="rx-param-card">
                                            <span>Quantidade</span>
                                            <strong id="rxQuantidadeValor">—</strong>
                                        </div>

                                        
                                    </div>

                                    <div class="rx-exam-fields" id="rxExamFields" hidden>
                                        <div class="field">
                                            <label for="rxDetalhesExame">
                                                Observação / indicação clínica
                                            </label>
                                            <textarea
                                                id="rxDetalhesExame"
                                                rows="3"
                                                maxlength="1000"
                                                placeholder="Ex.: hipótese diagnóstica, região anatômica, observações"
                                            ></textarea>
                                        </div>
                                    </div>

                                    <div id="rxFormFeedback" class="allergy-form-feedback" hidden></div>

                                    <div class="rx-modal-footer">
                                        <button
                                            type="button"
                                            class="btn-secondary-clinical"
                                            id="btnTrocarMedicamento"
                                        >
                                            Trocar medicamento
                                        </button>

                                        <button
                                            type="submit"
                                            class="btn-primary-clinical"
                                            id="btnSalvarItem"
                                        >
                                            <i class="fa-solid fa-plus"></i>
                                            Adicionar à prescrição
                                        </button>
                                    </div>
                                </form>
                            </section>
                        </div>
                    <?php endif; ?>
                </div>

                <script src="/safe-health/assets/js/prontuario-prescricao.js?v=7"></script>

            <?php elseif ($aba === 'evolucao'): ?>

                <div
                    class="evo-page"
                    data-atendimento-id="<?= (int) $atendimentoId ?>"
                    data-evolucao-id="<?= (int) ($evolucaoAtual['id'] ?? 0) ?>"
                >
                    <section class="pep-card evo-main-card">
                        <div class="pep-card-header evo-header">
                            <div>
                                <span class="section-kicker">
                                    Registro clínico
                                </span>
                                <h2>Evolução Médica</h2>
                                <p class="evo-subtitle">
                                    Registre a evolução clínica do atendimento.
                                    O documento só é aberto quando o médico
                                    selecionar “Novo”.
                                </p>
                            </div>

                            <?php if ($evolucaoAtual): ?>
                                <div class="rx-draft-badge">
                                    <i class="fa-solid fa-notes-medical"></i>
                                    <div>
                                        <strong>
                                            Evolução #<?= (int) $evolucaoAtual['id'] ?>
                                        </strong>
                                        <span>Rascunho</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$atendimento): ?>

                            <div class="clinical-placeholder">
                                <div class="placeholder-icon">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </div>
                                <div>
                                    <h3>Atendimento indisponível</h3>
                                    <p>
                                        <?= htmlspecialchars(
                                            $erroAtendimento
                                                ?: 'Não foi possível carregar o atendimento.'
                                        ) ?>
                                    </p>
                                </div>
                            </div>

                        <?php elseif ($erroEvolucao): ?>

                            <div class="pep-alert pep-alert-error">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <span>
                                    <?= htmlspecialchars($erroEvolucao) ?>
                                </span>
                            </div>

                        <?php else: ?>

                            <div class="rx-patient-strip">
                                <div>
                                    <span>Paciente</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $atendimento['paciente_nome']
                                        ) ?>
                                    </strong>
                                </div>
                                <div>
                                    <span>Atendimento</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $atendimento['numero_atendimento']
                                        ) ?>
                                    </strong>
                                </div>
                                <div>
                                    <span>Especialidade</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $atendimento['especialidade']
                                                ?: 'Não informada'
                                        ) ?>
                                    </strong>
                                </div>
                            </div>

                            <?php if (!$evolucaoAtual): ?>

                                <section class="rx-closed-state">
                                    <div class="rx-closed-icon">
                                        <i class="fa-solid fa-file-circle-plus"></i>
                                    </div>

                                    <div>
                                        <span class="section-kicker">
                                            Documento fechado
                                        </span>
                                        <h3>Nenhuma evolução aberta</h3>
                                        <p>
                                            Clique em <strong>Novo</strong>
                                            para iniciar uma nova Evolução
                                            Médica. Entrar neste módulo não
                                            cria documentos automaticamente.
                                        </p>
                                    </div>

                                    <div class="rx-document-actions rx-document-actions-closed">
                                        <button
                                            type="button"
                                            class="pep-action-new"
                                            id="btnNovaEvolucao"
                                        >
                                            <i class="fa-solid fa-file-circle-plus"></i>
                                            <span>Novo</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="pep-action-save"
                                            disabled
                                        >
                                            <i class="fa-solid fa-floppy-disk"></i>
                                            <span>Salvar</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="pep-action-sign"
                                            disabled
                                        >
                                            <i class="fa-solid fa-file-signature"></i>
                                            <span>Fechar e assinar</span>
                                        </button>
                                    </div>
                                </section>

                            <?php else: ?>

                                <section class="evo-editor">

                                    <div class="evo-info-bar">
                                        <div>
                                            <i class="fa-solid fa-user-doctor"></i>
                                            <span>
                                                Documento em elaboração
                                            </span>
                                        </div>

                                        <small>
                                            Criado em
                                            <?= htmlspecialchars(
                                                formatarDataHoraPep(
                                                    $evolucaoAtual['criada_em']
                                                )
                                            ) ?>
                                        </small>
                                    </div>

                                    <form id="formEvolucao">

                                        <div class="evo-grid">

                                            <div class="evo-field evo-field-wide">
                                                <label for="evoSubjetivo">
                                                    Subjetivo
                                                </label>
                                                <small>
                                                    Relato do paciente, sintomas,
                                                    queixas e evolução percebida.
                                                </small>
                                                <textarea
                                                    id="evoSubjetivo"
                                                    rows="5"
                                                    maxlength="5000"
                                                    placeholder="Descreva o relato clínico do paciente..."
                                                ><?= htmlspecialchars(
                                                    $evolucaoAtual['subjetivo']
                                                        ?? ''
                                                ) ?></textarea>
                                            </div>

                                            <div class="evo-field evo-field-wide">
                                                <label for="evoObjetivo">
                                                    Objetivo
                                                </label>
                                                <small>
                                                    Achados do exame físico,
                                                    sinais clínicos e dados
                                                    objetivos relevantes.
                                                </small>
                                                <textarea
                                                    id="evoObjetivo"
                                                    rows="5"
                                                    maxlength="5000"
                                                    placeholder="Registre os achados objetivos..."
                                                ><?= htmlspecialchars(
                                                    $evolucaoAtual['objetivo']
                                                        ?? ''
                                                ) ?></textarea>
                                            </div>

                                            <div class="evo-field evo-field-wide">
                                                <label>
                                                    Diagnóstico — CID-10
                                                </label>
                                                <small>
                                                    Pesquise pelo código ou pela
                                                    descrição e selecione um item
                                                    da base cadastrada.
                                                </small>

                                                <div class="cid-search-box">
                                                    <div class="cid-search-row">
                                                        <div class="cid-autocomplete">
                                                            <i class="fa-solid fa-magnifying-glass"></i>
                                                            <input
                                                                type="search"
                                                                id="cidBusca"
                                                                autocomplete="off"
                                                                placeholder="Ex.: J18 ou pneumonia"
                                                            >
                                                            <div
                                                                id="cidResultados"
                                                                class="cid-results"
                                                                hidden
                                                            ></div>
                                                        </div>

                                                        <select id="cidTipo">
                                                            <option value="SECUNDARIO">
                                                                Secundário
                                                            </option>
                                                            <option value="PRINCIPAL">
                                                                Principal
                                                            </option>
                                                        </select>

                                                        <button
                                                            type="button"
                                                            id="btnAdicionarCid"
                                                            class="cid-add-button"
                                                            disabled
                                                        >
                                                            <i class="fa-solid fa-plus"></i>
                                                            Adicionar
                                                        </button>
                                                    </div>

                                                    <div
                                                        id="cidSelecionados"
                                                        class="cid-selected-list"
                                                    >
                                                        <?php foreach (($evolucaoAtual['diagnosticos'] ?? []) as $diagnostico): ?>
                                                            <div
                                                                class="cid-selected-item"
                                                                data-cid-id="<?= (int) $diagnostico['cid10_id'] ?>"
                                                                data-tipo="<?= htmlspecialchars($diagnostico['tipo']) ?>"
                                                            >
                                                                <div>
                                                                    <strong>
                                                                        <?= htmlspecialchars($diagnostico['codigo']) ?>
                                                                    </strong>
                                                                    <span>
                                                                        <?= htmlspecialchars($diagnostico['descricao']) ?>
                                                                    </span>
                                                                </div>
                                                                <div class="cid-selected-actions">
                                                                    <span class="cid-type-badge <?= strtolower($diagnostico['tipo']) ?>">
                                                                        <?= $diagnostico['tipo'] === 'PRINCIPAL' ? 'Principal' : 'Secundário' ?>
                                                                    </span>
                                                                    <button
                                                                        type="button"
                                                                        class="cid-remove-button"
                                                                        title="Remover CID"
                                                                    >
                                                                        <i class="fa-solid fa-xmark"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="evo-field evo-field-wide">
                                                <label for="evoAvaliacao">
                                                    Avaliação
                                                </label>
                                                <small>
                                                    Impressão clínica,
                                                    hipóteses diagnósticas e
                                                    interpretação do quadro.
                                                </small>
                                                <textarea
                                                    id="evoAvaliacao"
                                                    rows="5"
                                                    maxlength="5000"
                                                    placeholder="Registre a avaliação médica..."
                                                ><?= htmlspecialchars(
                                                    $evolucaoAtual['avaliacao']
                                                        ?? ''
                                                ) ?></textarea>
                                            </div>

                                            <div class="evo-field evo-field-wide">
                                                <label for="evoPlano">
                                                    Plano / Conduta
                                                </label>
                                                <small>
                                                    Conduta assistencial,
                                                    orientações e próximos passos.
                                                </small>
                                                <textarea
                                                    id="evoPlano"
                                                    rows="5"
                                                    maxlength="5000"
                                                    placeholder="Registre a conduta e o plano assistencial..."
                                                ><?= htmlspecialchars(
                                                    $evolucaoAtual['plano']
                                                        ?? ''
                                                ) ?></textarea>
                                            </div>

                                            <div class="evo-field evo-field-wide">
                                                <label for="evoObservacoes">
                                                    Observações adicionais
                                                </label>
                                                <textarea
                                                    id="evoObservacoes"
                                                    rows="3"
                                                    maxlength="2000"
                                                    placeholder="Observações complementares, se necessário"
                                                ><?= htmlspecialchars(
                                                    $evolucaoAtual['observacoes']
                                                        ?? ''
                                                ) ?></textarea>
                                            </div>

                                        </div>

                                        <div
                                            class="allergy-form-feedback"
                                            id="evoFeedback"
                                            hidden
                                        ></div>

                                        <div class="evo-document-actions">

                                            <button
                                                type="button"
                                                class="pep-action-delete"
                                                id="btnExcluirEvolucao"
                                            >
                                                <i class="fa-solid fa-trash-can"></i>
                                                <span>Excluir</span>
                                            </button>

                                            <button
                                                type="button"
                                                class="pep-action-new"
                                                id="btnNovaEvolucao"
                                            >
                                                <i class="fa-solid fa-file-circle-plus"></i>
                                                <span>Novo</span>
                                            </button>

                                            <button
                                                type="button"
                                                class="pep-action-save"
                                                id="btnSalvarEvolucao"
                                            >
                                                <i class="fa-solid fa-floppy-disk"></i>
                                                <span>Salvar</span>
                                            </button>

                                            <button
                                                type="button"
                                                class="pep-action-sign"
                                                id="btnAssinarEvolucao"
                                            >
                                                <i class="fa-solid fa-file-signature"></i>
                                                <span>Fechar e assinar</span>
                                            </button>

                                        </div>

                                    </form>

                                </section>

                            <?php endif; ?>

                            <?php if (count($evolucoesAnteriores) > 0): ?>

                                <section class="evo-history">
                                    <div class="rx-section-heading">
                                        <div>
                                            <span class="section-kicker">
                                                Histórico do atendimento
                                            </span>
                                            <h3>Evoluções registradas</h3>
                                        </div>
                                    </div>

                                    <div class="evo-history-list">

                                        <?php foreach ($evolucoesAnteriores as $historica): ?>

                                            <article class="evo-history-item">

                                                <div class="evo-history-main">
                                                    <strong>
                                                        Evolução #<?= (int) $historica['id'] ?>
                                                    </strong>

                                                    <span class="evo-status <?= strtolower($historica['status']) ?>">
                                                        <?= htmlspecialchars(
                                                            $historica['status']
                                                        ) ?>
                                                    </span>
                                                </div>

                                                <div>
                                                    <span>Criada em</span>
                                                    <strong>
                                                        <?= htmlspecialchars(
                                                            formatarDataHoraPep(
                                                                $historica['criada_em']
                                                            )
                                                        ) ?>
                                                    </strong>
                                                </div>

                                                <div>
                                                    <span>Finalizada em</span>
                                                    <strong>
                                                        <?= htmlspecialchars(
                                                            formatarDataHoraPep(
                                                                $historica['finalizada_em']
                                                            )
                                                        ) ?>
                                                    </strong>
                                                </div>

                                                <?php if ($historica['status'] === 'RASCUNHO'): ?>
                                                    <a
                                                        class="rx-open-draft"
                                                        href="/safe-health/modules/prontuario/index.php?aba=evolucao&atendimento_id=<?= (int) $atendimentoId ?>&evolucao_id=<?= (int) $historica['id'] ?>"
                                                    >
                                                        <i class="fa-solid fa-folder-open"></i>
                                                        Abrir rascunho
                                                    </a>
                                                <?php endif; ?>

                                            </article>

                                        <?php endforeach; ?>

                                    </div>
                                </section>

                            <?php endif; ?>

                        <?php endif; ?>

                    </section>
                </div>

                <script src="/safe-health/assets/js/prontuario-evolucao.js?v=2"></script>

            <?php elseif ($aba === 'documentos'): ?>

                <?php
                    $rotulosDocs = [
                        'RECEITUARIO' => 'Receituário',
                        'ATESTADO' => 'Atestado Médico',
                        'DECLARACAO' => 'Declaração',
                        'RELATORIO' => 'Relatório Médico',
                        'ENCAMINHAMENTO' => 'Encaminhamento'
                    ];
                    $conteudoDoc = $documentoAtual['conteudo'] ?? [];
                ?>

                <div
                    class="docs-page"
                    data-atendimento-id="<?= (int) $atendimentoId ?>"
                    data-documento-id="<?= (int) ($documentoAtual['id'] ?? 0) ?>"
                    data-tipo-documento="<?= htmlspecialchars($tipoDocumento) ?>"
                >
                    <section class="pep-card docs-main-card">
                        <div class="pep-card-header">
                            <div>
                                <span class="section-kicker">Prontuário Eletrônico</span>
                                <h2>Documentos / Receituário</h2>
                            </div>
                        </div>

                        <nav class="docs-tabs" aria-label="Tipos de documentos">
                            <?php foreach ($rotulosDocs as $codigoTipo => $rotuloTipo): ?>
                                <a
                                    class="docs-tab <?= $tipoDocumento === $codigoTipo ? 'active' : '' ?>"
                                    href="/safe-health/modules/prontuario/index.php?aba=documentos&atendimento_id=<?= (int) $atendimentoId ?>&tipo_documento=<?= urlencode($codigoTipo) ?>"
                                >
                                    <?= htmlspecialchars($rotuloTipo) ?>
                                </a>
                            <?php endforeach; ?>
                        </nav>

                        <?php if ($erroDocumentos): ?>
                            <div class="pep-alert pep-alert-error">
                                <?= htmlspecialchars($erroDocumentos) ?>
                            </div>
                        <?php elseif (!$documentoAtual): ?>
                            <section class="docs-empty">
                                <div class="rx-closed-icon">
                                    <i class="fa-solid fa-file-circle-plus"></i>
                                </div>
                                <div>
                                    <span class="section-kicker">
                                        <?= htmlspecialchars($rotulosDocs[$tipoDocumento]) ?>
                                    </span>
                                    <h3>Nenhum documento aberto</h3>
                                    <p>
                                        Clique em <strong>Novo</strong> para iniciar.
                                        Entrar nesta aba não cria documentos automaticamente.
                                    </p>
                                </div>
                                <button type="button" id="btnNovoDocumento" class="pep-action-new">
                                    <i class="fa-solid fa-file-circle-plus"></i>
                                    Novo
                                </button>
                            </section>
                        <?php else: ?>

                            <div class="docs-editor-head">
                                <div>
                                    <span>Documento</span>
                                    <strong>
                                        <?= htmlspecialchars($rotulosDocs[$tipoDocumento]) ?>
                                        #<?= (int) $documentoAtual['id'] ?>
                                    </strong>
                                </div>
                                <span class="rx-draft-badge">Rascunho</span>
                            </div>

                            <form id="formDocumentoMedico" class="docs-form">

                                <?php if ($tipoDocumento === 'RECEITUARIO'): ?>
                                    <div class="docs-field docs-wide">
                                        <label>Medicamentos para uso domiciliar</label>
                                        <small>
                                            Pesquise no Cadastro de Medicamentos e adicione
                                            a orientação de uso para o paciente.
                                        </small>

                                        <div class="docs-med-search">
                                            <div class="docs-med-autocomplete">
                                                <input
                                                    type="search"
                                                    id="docMedBusca"
                                                    autocomplete="off"
                                                    placeholder="Digite o nome, princípio ativo ou código"
                                                >
                                                <div id="docMedResultados" class="docs-med-results" hidden></div>
                                            </div>
                                        </div>

                                        <div id="docMedicamentos" class="docs-med-list">
                                            <?php foreach (($conteudoDoc['medicamentos'] ?? []) as $med): ?>
                                                <div
                                                    class="docs-med-item"
                                                    data-medicamento-id="<?= (int) ($med['medicamento_id'] ?? 0) ?>"
                                                    data-nome="<?= htmlspecialchars($med['nome'] ?? '') ?>"
                                                >
                                                    <div class="docs-med-title">
                                                        <strong><?= htmlspecialchars($med['nome'] ?? '') ?></strong>
                                                        <button type="button" class="docs-med-remove" title="Remover">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    </div>
                                                    <div class="docs-med-fields">
                                                        <label>
                                                            Posologia
                                                            <input
                                                                type="text"
                                                                class="doc-med-posologia"
                                                                maxlength="500"
                                                                value="<?= htmlspecialchars($med['posologia'] ?? '') ?>"
                                                                placeholder="Ex.: Tomar 1 comprimido a cada 8 horas por 5 dias"
                                                            >
                                                        </label>
                                                        <label>
                                                            Orientação
                                                            <input
                                                                type="text"
                                                                class="doc-med-orientacao"
                                                                maxlength="500"
                                                                value="<?= htmlspecialchars($med['orientacao'] ?? '') ?>"
                                                                placeholder="Ex.: Tomar após as refeições"
                                                            >
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="docs-field docs-wide">
                                        <label for="docOrientacoesGerais">Orientações gerais</label>
                                        <textarea id="docOrientacoesGerais" rows="4" maxlength="3000"><?= htmlspecialchars($conteudoDoc['orientacoes_gerais'] ?? '') ?></textarea>
                                    </div>

                                <?php elseif ($tipoDocumento === 'ATESTADO'): ?>
                                    <div class="docs-grid">
                                        <div class="docs-field">
                                            <label for="docDiasAfastamento">Dias de afastamento</label>
                                            <input id="docDiasAfastamento" type="number" min="1" max="365" value="<?= htmlspecialchars((string) ($conteudoDoc['dias_afastamento'] ?? '')) ?>">
                                        </div>
                                        <div class="docs-field">
                                            <label for="docDataInicio">A partir de</label>
                                            <input id="docDataInicio" type="date" value="<?= htmlspecialchars($conteudoDoc['data_inicio'] ?? date('Y-m-d')) ?>">
                                        </div>
                                    </div>
                                    <div class="docs-field docs-wide">
                                        <label for="docTextoAtestado">Observações do atestado</label>
                                        <textarea id="docTextoAtestado" rows="6" maxlength="4000" placeholder="Informações complementares do afastamento..."><?= htmlspecialchars($conteudoDoc['texto'] ?? '') ?></textarea>
                                    </div>

                                <?php elseif ($tipoDocumento === 'DECLARACAO'): ?>
                                    <div class="docs-grid">
                                        <div class="docs-field">
                                            <label for="docHoraEntrada">Horário de entrada</label>
                                            <input id="docHoraEntrada" type="time" value="<?= htmlspecialchars($conteudoDoc['hora_entrada'] ?? '') ?>">
                                        </div>
                                        <div class="docs-field">
                                            <label for="docHoraSaida">Horário de saída</label>
                                            <input id="docHoraSaida" type="time" value="<?= htmlspecialchars($conteudoDoc['hora_saida'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="docs-field docs-wide">
                                        <label for="docTextoDeclaracao">Observações</label>
                                        <textarea id="docTextoDeclaracao" rows="5" maxlength="3000"><?= htmlspecialchars($conteudoDoc['texto'] ?? '') ?></textarea>
                                    </div>

                                <?php elseif ($tipoDocumento === 'RELATORIO'): ?>
                                    <div class="docs-field docs-wide">
                                        <label for="docTituloRelatorio">Título</label>
                                        <input id="docTituloRelatorio" type="text" maxlength="180" value="<?= htmlspecialchars($conteudoDoc['titulo'] ?? 'Relatório Médico') ?>">
                                    </div>
                                    <div class="docs-field docs-wide">
                                        <label for="docTextoRelatorio">Relatório médico</label>
                                        <textarea id="docTextoRelatorio" rows="14" maxlength="12000" placeholder="Descreva o relatório clínico..."><?= htmlspecialchars($conteudoDoc['texto'] ?? '') ?></textarea>
                                    </div>

                                <?php elseif ($tipoDocumento === 'ENCAMINHAMENTO'): ?>
                                    <div class="docs-grid">
                                        <div class="docs-field">
                                            <label for="docEspecialidadeDestino">
                                                Especialidade / destino
                                            </label>
                                            <select id="docEspecialidadeDestino">
                                                <option value="">
                                                    Selecione a especialidade
                                                </option>
                                                <?php foreach ($especialidadesAtivas as $especialidadeDestino): ?>
                                                    <option
                                                        value="<?= (int) $especialidadeDestino['id'] ?>"
                                                        data-nome="<?= htmlspecialchars($especialidadeDestino['nome']) ?>"
                                                        <?= (string) ($conteudoDoc['especialidade_id'] ?? '') === (string) $especialidadeDestino['id'] ? 'selected' : '' ?>
                                                    >
                                                        <?= htmlspecialchars($especialidadeDestino['nome']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small>
                                                Lista carregada do módulo Cadastros → Especialidades.
                                            </small>
                                        </div>
                                        <div class="docs-field">
                                            <label for="docPrioridade">Prioridade</label>
                                            <select id="docPrioridade">
                                                <?php foreach (['ROTINA' => 'Rotina', 'PRIORITARIO' => 'Prioritário', 'URGENTE' => 'Urgente'] as $v => $r): ?>
                                                    <option value="<?= $v ?>" <?= ($conteudoDoc['prioridade'] ?? 'ROTINA') === $v ? 'selected' : '' ?>>
                                                        <?= $r ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="docs-field docs-wide">
                                        <label for="docJustificativa">Justificativa clínica</label>
                                        <textarea id="docJustificativa" rows="8" maxlength="6000"><?= htmlspecialchars($conteudoDoc['justificativa'] ?? '') ?></textarea>
                                    </div>
                                <?php endif; ?>

                                <div id="docFeedback" class="allergy-form-feedback" hidden></div>

                                <div class="docs-actions">
                                    <button type="button" id="btnExcluirDocumento" class="pep-action-delete">
                                        <i class="fa-solid fa-trash-can"></i> Excluir
                                    </button>
                                    <button type="button" id="btnNovoDocumento" class="pep-action-new">
                                        <i class="fa-solid fa-file-circle-plus"></i> Novo
                                    </button>
                                    <button type="button" id="btnSalvarDocumento" class="pep-action-save">
                                        <i class="fa-solid fa-floppy-disk"></i> Salvar
                                    </button>
                                    <button type="button" id="btnAssinarDocumento" class="pep-action-sign">
                                        <i class="fa-solid fa-file-signature"></i> Fechar e assinar
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <?php if ($documentosAnteriores): ?>
                            <section class="docs-history">
                                <div class="rx-section-heading">
                                    <div>
                                        <span class="section-kicker">Histórico</span>
                                        <h3><?= htmlspecialchars($rotulosDocs[$tipoDocumento]) ?> anteriores</h3>
                                    </div>
                                </div>
                                <div class="evo-history-list">
                                    <?php foreach ($documentosAnteriores as $doc): ?>
                                        <article class="evo-history-item">
                                            <div class="evo-history-main">
                                                <strong>#<?= (int) $doc['id'] ?></strong>
                                                <span class="evo-status <?= strtolower($doc['status']) ?>">
                                                    <?= htmlspecialchars($doc['status']) ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span>Criado em</span>
                                                <strong><?= htmlspecialchars(formatarDataHoraPep($doc['criado_em'])) ?></strong>
                                            </div>
                                            <div>
                                                <span>Finalizado em</span>
                                                <strong><?= htmlspecialchars(formatarDataHoraPep($doc['finalizado_em'])) ?></strong>
                                            </div>
                                            <?php if ($doc['status'] === 'RASCUNHO'): ?>
                                                <a class="rx-open-draft" href="/safe-health/modules/prontuario/index.php?aba=documentos&atendimento_id=<?= (int) $atendimentoId ?>&tipo_documento=<?= urlencode($tipoDocumento) ?>&documento_id=<?= (int) $doc['id'] ?>">
                                                    <i class="fa-solid fa-folder-open"></i>
                                                    Abrir rascunho
                                                </a>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                    </section>
                </div>

                <script src="/safe-health/assets/js/prontuario-documentos.js?v=2"></script>

            <?php elseif ($aba === 'alta'): ?>

                <div
                    class="alta-page"
                    data-atendimento-id="<?= (int) $atendimentoId ?>"
                    data-alta-id="<?= (int) ($altaAtual['id'] ?? 0) ?>"
                >
                    <section class="pep-card alta-main-card">

                        <div class="pep-card-header">
                            <div>
                                <span class="section-kicker">
                                    Encerramento assistencial
                                </span>
                                <h2>Alta Médica</h2>
                                <p class="alta-subtitle">
                                    Registre o diagnóstico de alta, condição do
                                    paciente e orientações para continuidade do cuidado.
                                </p>
                            </div>

                            <?php if ($altaAtual): ?>
                                <div class="rx-draft-badge">
                                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                    <div>
                                        <strong>
                                            Alta #<?= (int) $altaAtual['id'] ?>
                                        </strong>
                                        <span>Rascunho</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$atendimento): ?>

                            <div class="clinical-placeholder">
                                <div class="placeholder-icon">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </div>
                                <div>
                                    <h3>Atendimento indisponível</h3>
                                    <p>
                                        <?= htmlspecialchars(
                                            $erroAtendimento
                                                ?: 'Não foi possível carregar o atendimento.'
                                        ) ?>
                                    </p>
                                </div>
                            </div>

                        <?php elseif ($erroAlta): ?>

                            <div class="pep-alert pep-alert-error">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <span>
                                    <?= htmlspecialchars($erroAlta) ?>
                                </span>
                            </div>

                        <?php else: ?>

                            <div class="rx-patient-strip">
                                <div>
                                    <span>Paciente</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $atendimento['paciente_nome']
                                        ) ?>
                                    </strong>
                                </div>
                                <div>
                                    <span>Atendimento</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $atendimento['numero_atendimento']
                                        ) ?>
                                    </strong>
                                </div>
                                <div>
                                    <span>Médico responsável</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $medicoLogado['nome']
                                                ?? 'Não informado'
                                        ) ?>
                                    </strong>
                                </div>
                            </div>

                            <?php if (!$altaAtual): ?>

                                <section class="rx-closed-state">
                                    <div class="rx-closed-icon">
                                        <i class="fa-solid fa-file-circle-plus"></i>
                                    </div>

                                    <div>
                                        <span class="section-kicker">
                                            Documento fechado
                                        </span>
                                        <h3>Nenhuma Alta Médica aberta</h3>
                                        <p>
                                            Clique em <strong>Novo</strong>
                                            para iniciar a alta. O atendimento
                                            somente será encerrado quando o médico
                                            selecionar <strong>Finalizar atendimento</strong>.
                                        </p>
                                    </div>

                                    <div class="rx-document-actions rx-document-actions-closed">
                                        <button
                                            type="button"
                                            class="pep-action-new"
                                            id="btnNovaAlta"
                                        >
                                            <i class="fa-solid fa-file-circle-plus"></i>
                                            <span>Novo</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="pep-action-save"
                                            disabled
                                        >
                                            <i class="fa-solid fa-floppy-disk"></i>
                                            <span>Salvar</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="pep-action-sign"
                                            disabled
                                        >
                                            <i class="fa-solid fa-circle-check"></i>
                                            <span>Finalizar atendimento</span>
                                        </button>
                                    </div>
                                </section>

                            <?php else: ?>

                                <form id="formAltaMedica" class="alta-form">

                                    <div class="alta-section">
                                        <div class="alta-section-heading">
                                            <span class="alta-step">1</span>
                                            <div>
                                                <h3>Diagnóstico / CID-10</h3>
                                                <p>
                                                    Os diagnósticos são carregados da
                                                    Evolução Médica deste atendimento.
                                                    Para alterar o CID, edite a Evolução
                                                    antes de concluir a alta.
                                                </p>
                                            </div>
                                        </div>

                                        <div id="altaCidSelecionados" class="cid-selected-list">
                                            <?php if (!empty($altaAtual['diagnosticos'])): ?>
                                                <?php foreach ($altaAtual['diagnosticos'] as $diagnostico): ?>
                                                    <div
                                                        class="cid-selected-item alta-cid-readonly"
                                                        data-cid-id="<?= (int) $diagnostico['cid10_id'] ?>"
                                                        data-tipo="<?= htmlspecialchars($diagnostico['tipo']) ?>"
                                                    >
                                                        <div>
                                                            <strong><?= htmlspecialchars($diagnostico['codigo']) ?></strong>
                                                            <span><?= htmlspecialchars($diagnostico['descricao']) ?></span>
                                                        </div>
                                                        <div class="cid-selected-actions">
                                                            <span class="cid-type-badge <?= strtolower($diagnostico['tipo']) ?>">
                                                                <?= $diagnostico['tipo'] === 'PRINCIPAL'
                                                                    ? 'Principal'
                                                                    : 'Secundário' ?>
                                                            </span>
                                                            <span class="alta-cid-origin">
                                                                <i class="fa-solid fa-notes-medical"></i>
                                                                Evolução Médica
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="alta-cid-empty">
                                                    <i class="fa-solid fa-circle-info"></i>
                                                    <div>
                                                        <strong>Nenhum CID informado na Evolução Médica.</strong>
                                                        <span>
                                                            Registre o diagnóstico na Evolução Médica
                                                            antes de finalizar o atendimento.
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="alta-section">
                                        <div class="alta-section-heading">
                                            <span class="alta-step">2</span>
                                            <div>
                                                <h3>Resumo e condição clínica</h3>
                                                <p>
                                                    Registre de forma objetiva o
                                                    encerramento deste atendimento.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="docs-field docs-wide">
                                            <label for="altaResumoClinico">
                                                Resumo clínico
                                            </label>
                                            <textarea
                                                id="altaResumoClinico"
                                                rows="7"
                                                maxlength="8000"
                                                placeholder="Resuma a evolução do quadro, condutas realizadas e situação no momento da alta..."
                                            ><?= htmlspecialchars(
                                                $altaAtual['resumo_clinico'] ?? ''
                                            ) ?></textarea>
                                        </div>

                                        <div class="docs-grid">
                                            <div class="docs-field">
                                                <label for="altaCondicao">
                                                    Condição na alta
                                                </label>
                                                <select id="altaCondicao">
                                                    <?php
                                                        $condicoesAlta = [
                                                            'MELHORADO' => 'Melhorado',
                                                            'ESTAVEL' => 'Estável',
                                                            'CURADO' => 'Curado',
                                                            'SEM_ALTERACAO' => 'Sem alteração clínica relevante',
                                                            'OUTRO' => 'Outra condição'
                                                        ];
                                                    ?>
                                                    <option value="">
                                                        Selecione
                                                    </option>
                                                    <?php foreach ($condicoesAlta as $valorCondicao => $rotuloCondicao): ?>
                                                        <option
                                                            value="<?= $valorCondicao ?>"
                                                            <?= ($altaAtual['condicao_alta'] ?? '') === $valorCondicao ? 'selected' : '' ?>
                                                        >
                                                            <?= htmlspecialchars($rotuloCondicao) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="docs-field">
                                                <label for="altaDestino">
                                                    Destino do paciente
                                                </label>
                                                <select id="altaDestino">
                                                    <?php
                                                        $destinosAlta = [
                                                            'DOMICILIO' => 'Domicílio',
                                                            'INTERNACAO' => 'Internação',
                                                            'TRANSFERENCIA' => 'Transferência',
                                                            'AMBULATORIO' => 'Seguimento ambulatorial',
                                                            'OUTRO' => 'Outro'
                                                        ];
                                                    ?>
                                                    <option value="">
                                                        Selecione
                                                    </option>
                                                    <?php foreach ($destinosAlta as $valorDestino => $rotuloDestino): ?>
                                                        <option
                                                            value="<?= $valorDestino ?>"
                                                            <?= ($altaAtual['destino_paciente'] ?? '') === $valorDestino ? 'selected' : '' ?>
                                                        >
                                                            <?= htmlspecialchars($rotuloDestino) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alta-section">
                                        <div class="alta-section-heading">
                                            <span class="alta-step">3</span>
                                            <div>
                                                <h3>Orientações de alta</h3>
                                                <p>
                                                    Registre cuidados, sinais de
                                                    alerta e acompanhamento recomendado.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="docs-field docs-wide">
                                            <label for="altaOrientacoes">
                                                Orientações ao paciente
                                            </label>
                                            <textarea
                                                id="altaOrientacoes"
                                                rows="7"
                                                maxlength="8000"
                                                placeholder="Cuidados domiciliares, sinais de alerta, uso de medicações, recomendações..."
                                            ><?= htmlspecialchars(
                                                $altaAtual['orientacoes'] ?? ''
                                            ) ?></textarea>
                                        </div>

                                        <div class="docs-field docs-wide">
                                            <label for="altaRetorno">
                                                Retorno / acompanhamento
                                            </label>
                                            <textarea
                                                id="altaRetorno"
                                                rows="4"
                                                maxlength="3000"
                                                placeholder="Ex.: retorno em 7 dias na especialidade..."
                                            ><?= htmlspecialchars(
                                                $altaAtual['retorno_recomendado'] ?? ''
                                            ) ?></textarea>
                                        </div>

                                        <div class="docs-field docs-wide">
                                            <label for="altaObservacoes">
                                                Observações adicionais
                                            </label>
                                            <textarea
                                                id="altaObservacoes"
                                                rows="3"
                                                maxlength="3000"
                                            ><?= htmlspecialchars(
                                                $altaAtual['observacoes'] ?? ''
                                            ) ?></textarea>
                                        </div>
                                    </div>

                                    <div
                                        id="altaFeedback"
                                        class="allergy-form-feedback"
                                        hidden
                                    ></div>

                                    <div class="docs-actions">
                                        <button
                                            type="button"
                                            id="btnExcluirAlta"
                                            class="pep-action-delete"
                                        >
                                            <i class="fa-solid fa-trash-can"></i>
                                            Excluir
                                        </button>

                                        <button
                                            type="button"
                                            id="btnNovaAlta"
                                            class="pep-action-new"
                                        >
                                            <i class="fa-solid fa-file-circle-plus"></i>
                                            Novo
                                        </button>

                                        <button
                                            type="button"
                                            id="btnSalvarAlta"
                                            class="pep-action-save"
                                        >
                                            <i class="fa-solid fa-floppy-disk"></i>
                                            Salvar
                                        </button>

                                        <button
                                            type="button"
                                            id="btnFinalizarAtendimento"
                                            class="pep-action-sign"
                                        >
                                            <i class="fa-solid fa-circle-check"></i>
                                            Finalizar atendimento
                                        </button>
                                    </div>

                                </form>

                            <?php endif; ?>

                            <?php if ($altasAnteriores): ?>

                                <section class="docs-history">
                                    <div class="rx-section-heading">
                                        <div>
                                            <span class="section-kicker">
                                                Histórico
                                            </span>
                                            <h3>Registros de Alta Médica</h3>
                                        </div>
                                    </div>

                                    <div class="evo-history-list">
                                        <?php foreach ($altasAnteriores as $altaHistorica): ?>
                                            <article class="evo-history-item">

                                                <div class="evo-history-main">
                                                    <strong>
                                                        Alta #<?= (int) $altaHistorica['id'] ?>
                                                    </strong>
                                                    <span class="evo-status <?= strtolower($altaHistorica['status']) ?>">
                                                        <?= htmlspecialchars($altaHistorica['status']) ?>
                                                    </span>
                                                </div>

                                                <div>
                                                    <span>Criada em</span>
                                                    <strong>
                                                        <?= htmlspecialchars(
                                                            formatarDataHoraPep(
                                                                $altaHistorica['criada_em']
                                                            )
                                                        ) ?>
                                                    </strong>
                                                </div>

                                                <div>
                                                    <span>Finalizada em</span>
                                                    <strong>
                                                        <?= htmlspecialchars(
                                                            formatarDataHoraPep(
                                                                $altaHistorica['finalizada_em']
                                                            )
                                                        ) ?>
                                                    </strong>
                                                </div>

                                                <?php if ($altaHistorica['status'] === 'RASCUNHO'): ?>
                                                    <a
                                                        class="rx-open-draft"
                                                        href="/safe-health/modules/prontuario/index.php?aba=alta&atendimento_id=<?= (int) $atendimentoId ?>&alta_id=<?= (int) $altaHistorica['id'] ?>"
                                                    >
                                                        <i class="fa-solid fa-folder-open"></i>
                                                        Abrir rascunho
                                                    </a>
                                                <?php endif; ?>

                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </section>

                            <?php endif; ?>

                        <?php endif; ?>

                    </section>
                </div>

                <script src="/safe-health/assets/js/prontuario-alta.js?v=3"></script>

            <?php elseif ($aba === 'historico'): ?>

                <div class="history-page">
                    <section class="pep-card history-main-card">
                        <div class="pep-card-header history-header">
                            <div>
                                <span class="section-kicker">Visão longitudinal</span>
                                <h2>Histórico do Paciente</h2>
                                <p>
                                    Consulte os registros clínicos do paciente em
                                    diferentes atendimentos, em modo somente leitura.
                                </p>
                            </div>
                            <div class="history-count">
                                <strong><?= count($historicoPaciente['atendimentos']) ?></strong>
                                <span>atendimentos</span>
                            </div>
                        </div>

                        <?php if ($erroHistorico): ?>
                            <div class="pep-alert pep-alert-warning history-warning">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <span><?= htmlspecialchars($erroHistorico) ?></span>
                            </div>
                        <?php endif; ?>

                            <div class="history-summary">
                                <div><span>Paciente</span><strong><?= htmlspecialchars($atendimento['paciente_nome']) ?></strong></div>
                                <div><span>CPF</span><strong><?= htmlspecialchars($atendimento['paciente_cpf'] ?? 'Não informado') ?></strong></div>
                                <div><span>Atendimento atual</span><strong><?= htmlspecialchars($atendimento['numero_atendimento']) ?></strong></div>
                                <div><span>Alergias ativas</span><strong><?= count(array_filter($historicoPaciente['alergias'], fn($a)=>(int)$a['ativa']===1)) ?></strong></div>
                            </div>

                            <nav class="history-tabs">
                                <button type="button" class="history-tab active" data-history-target="atendimentos">Atendimentos</button>
                                <button type="button" class="history-tab" data-history-target="alergias">Alergias</button>
                                <button type="button" class="history-tab" data-history-target="evolucoes">Evoluções</button>
                                <button type="button" class="history-tab" data-history-target="prescricoes">Prescrições</button>
                                <button type="button" class="history-tab" data-history-target="documentos">Documentos</button>
                                <button type="button" class="history-tab" data-history-target="altas">Altas</button>
                            </nav>

                            <div class="history-panels">
                                <section class="history-panel active" data-history-panel="atendimentos">
                                    <?php if (!$historicoPaciente['atendimentos']): ?>
                                        <div class="history-empty">Nenhum atendimento encontrado.</div>
                                    <?php else: ?>
                                        <div class="history-list">
                                        <?php foreach ($historicoPaciente['atendimentos'] as $h): ?>
                                            <article class="history-item">
                                                <div class="history-item-title">
                                                    <div><span>Atendimento</span><strong><?= htmlspecialchars($h['numero_atendimento']) ?></strong></div>
                                                    <span class="history-status"><?= htmlspecialchars($h['status'] ?: 'SEM STATUS') ?></span>
                                                </div>
                                                <div class="history-meta">
                                                    <div><span>Data</span><strong><?= htmlspecialchars(formatarDataHoraPep($h['criado_em'])) ?></strong></div>
                                                    <div><span>Médico</span><strong><?= htmlspecialchars($h['medico_nome'] ?: 'Não informado') ?></strong></div>
                                                    <div><span>Especialidade</span><strong><?= htmlspecialchars($h['especialidade_nome'] ?: 'Não informada') ?></strong></div>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>

                                <section class="history-panel" data-history-panel="alergias">
                                    <?php if (!$historicoPaciente['alergias']): ?>
                                        <div class="history-empty">Nenhuma alergia registrada.</div>
                                    <?php else: ?>
                                        <div class="history-list">
                                        <?php foreach ($historicoPaciente['alergias'] as $h): ?>
                                            <article class="history-item">
                                                <div class="history-item-title">
                                                    <strong><?= htmlspecialchars($h['alergeno']) ?></strong>
                                                    <span class="history-status"><?= (int)$h['ativa']===1 ? 'ATIVA' : 'INATIVA' ?></span>
                                                </div>
                                                <div class="history-meta">
                                                    <div><span>Tipo</span><strong><?= htmlspecialchars(($h['tipo'] ?? '') ?: 'Não informado') ?></strong></div>
                                                    <div><span>Gravidade</span><strong><?= htmlspecialchars(($h['gravidade'] ?? '') ?: 'Não informada') ?></strong></div>
                                                    <div><span>Registrada em</span><strong><?= htmlspecialchars(formatarDataHoraPep($h['criado_em'])) ?></strong></div>
                                                </div>
                                                <?php if (!empty($h['observacoes'] ?? '')): ?><p class="history-text"><?= nl2br(htmlspecialchars($h['observacoes'] ?? '')) ?></p><?php endif; ?>
                                            </article>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>

                                <section class="history-panel" data-history-panel="evolucoes">
                                    <?php if (!$historicoPaciente['evolucoes']): ?>
                                        <div class="history-empty">Nenhuma evolução registrada.</div>
                                    <?php else: ?>
                                        <div class="history-list">
                                        <?php foreach ($historicoPaciente['evolucoes'] as $h): ?>
                                            <article class="history-item">
                                                <div class="history-item-title">
                                                    <div><span><?= htmlspecialchars($h['numero_atendimento']) ?></span><strong>Evolução #<?= (int)$h['id'] ?></strong></div>
                                                    <span class="history-status"><?= htmlspecialchars($h['status']) ?></span>
                                                </div>
                                                <?php if ($h['diagnosticos']): ?>
                                                    <div class="history-cids">
                                                    <?php foreach ($h['diagnosticos'] as $cid): ?>
                                                        <span><strong><?= htmlspecialchars($cid['codigo']) ?></strong> <?= htmlspecialchars($cid['descricao']) ?> · <?= $cid['tipo']==='PRINCIPAL'?'Principal':'Secundário' ?></span>
                                                    <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="history-meta">
                                                    <div><span>Médico</span><strong><?= htmlspecialchars($h['medico_nome'] ?: 'Não informado') ?></strong></div>
                                                    <div><span>Criada</span><strong><?= htmlspecialchars(formatarDataHoraPep($h['criada_em'])) ?></strong></div>
                                                    <div><span>Finalizada</span><strong><?= htmlspecialchars(formatarDataHoraPep($h['finalizada_em'] ?? null)) ?></strong></div>
                                                </div>
                                                <details class="history-details">
                                                    <summary>Visualizar conteúdo clínico</summary>
                                                    <?php foreach (['subjetivo'=>'Subjetivo','objetivo'=>'Objetivo','avaliacao'=>'Avaliação','plano'=>'Plano / Conduta','observacoes'=>'Observações'] as $campo=>$rotulo): ?>
                                                        <?php if (!empty($h[$campo])): ?><div><strong><?= $rotulo ?></strong><p><?= nl2br(htmlspecialchars($h[$campo])) ?></p></div><?php endif; ?>
                                                    <?php endforeach; ?>
                                                </details>
                                            </article>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>

                                <section class="history-panel" data-history-panel="prescricoes">
                                    <?php if (!$historicoPaciente['prescricoes']): ?>
                                        <div class="history-empty">Nenhuma prescrição hospitalar registrada.</div>
                                    <?php else: ?>
                                        <div class="history-list">
                                        <?php foreach ($historicoPaciente['prescricoes'] as $h): ?>
                                            <article class="history-item">
                                                <div class="history-item-title">
                                                    <div><span><?= htmlspecialchars($h['numero_atendimento']) ?></span><strong>Prescrição #<?= (int)$h['id'] ?></strong></div>
                                                    <span class="history-status"><?= htmlspecialchars($h['status']) ?></span>
                                                </div>
                                                <div class="history-meta">
                                                    <div><span>Médico</span><strong><?= htmlspecialchars($h['medico_nome'] ?: 'Não informado') ?></strong></div>
                                                    <div><span>Prescrita em</span><strong><?= htmlspecialchars(formatarDataHoraPep($h['data_prescricao'])) ?></strong></div>
                                                    <div><span>Liberada à Farmácia</span><strong><?= htmlspecialchars(formatarDataHoraPep($h['data_liberacao_farmacia'] ?? null)) ?></strong></div>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>

                                <section class="history-panel" data-history-panel="documentos">
                                    <?php if (!$historicoPaciente['documentos']): ?>
                                        <div class="history-empty">Nenhum documento médico registrado.</div>
                                    <?php else: ?>
                                        <div class="history-list">
                                        <?php foreach ($historicoPaciente['documentos'] as $h): ?>
                                            <article class="history-item">
                                                <div class="history-item-title">
                                                    <div><span><?= htmlspecialchars($h['numero_atendimento']) ?></span><strong><?= htmlspecialchars(ucwords(strtolower(str_replace('_',' ',$h['tipo'])))) ?> #<?= (int)$h['id'] ?></strong></div>
                                                    <span class="history-status"><?= htmlspecialchars($h['status']) ?></span>
                                                </div>
                                                <div class="history-meta">
                                                    <div><span>Médico</span><strong><?= htmlspecialchars($h['medico_nome'] ?: 'Não informado') ?></strong></div>
                                                    <div><span>Criado</span><strong><?= htmlspecialchars(formatarDataHoraPep($h['criado_em'])) ?></strong></div>
                                                    <div><span>Finalizado</span><strong><?= htmlspecialchars(formatarDataHoraPep($h['finalizado_em'] ?? null)) ?></strong></div>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>

                                <section class="history-panel" data-history-panel="altas">
                                    <?php if (!$historicoPaciente['altas']): ?>
                                        <div class="history-empty">Nenhuma Alta Médica registrada.</div>
                                    <?php else: ?>
                                        <div class="history-list">
                                        <?php foreach ($historicoPaciente['altas'] as $h): ?>
                                            <article class="history-item">
                                                <div class="history-item-title">
                                                    <div><span><?= htmlspecialchars($h['numero_atendimento']) ?></span><strong>Alta #<?= (int)$h['id'] ?></strong></div>
                                                    <span class="history-status"><?= htmlspecialchars($h['status']) ?></span>
                                                </div>
                                                <?php if ($h['diagnosticos']): ?>
                                                    <div class="history-cids">
                                                    <?php foreach ($h['diagnosticos'] as $cid): ?>
                                                        <span><strong><?= htmlspecialchars($cid['codigo']) ?></strong> <?= htmlspecialchars($cid['descricao']) ?> · <?= $cid['tipo']==='PRINCIPAL'?'Principal':'Secundário' ?></span>
                                                    <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="history-meta">
                                                    <div><span>Médico</span><strong><?= htmlspecialchars($h['medico_nome'] ?: 'Não informado') ?></strong></div>
                                                    <div><span>Condição</span><strong><?= htmlspecialchars(($h['condicao_alta'] ?? '') ?: 'Não informada') ?></strong></div>
                                                    <div><span>Destino</span><strong><?= htmlspecialchars(($h['destino_paciente'] ?? '') ?: 'Não informado') ?></strong></div>
                                                </div>
                                                <details class="history-details">
                                                    <summary>Visualizar resumo e orientações</summary>
                                                    <?php foreach (['resumo_clinico'=>'Resumo clínico','orientacoes'=>'Orientações','retorno_recomendado'=>'Retorno / acompanhamento','observacoes'=>'Observações'] as $campo=>$rotulo): ?>
                                                        <?php if (!empty($h[$campo])): ?><div><strong><?= $rotulo ?></strong><p><?= nl2br(htmlspecialchars($h[$campo])) ?></p></div><?php endif; ?>
                                                    <?php endforeach; ?>
                                                </details>
                                            </article>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>
                            </div>
                    </section>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const tabs=document.querySelectorAll('.history-tab');
                    const panels=document.querySelectorAll('.history-panel');
                    tabs.forEach(function(tab){
                        tab.addEventListener('click',function(){
                            tabs.forEach(t=>t.classList.remove('active'));
                            panels.forEach(p=>p.classList.remove('active'));
                            tab.classList.add('active');
                            document.querySelector('[data-history-panel="'+tab.dataset.historyTarget+'"]')?.classList.add('active');
                        });
                    });
                });
                </script>

            <?php else: ?>

                <section class="pep-card">
                    <div class="pep-card-header">
                        <div>
                            <span class="section-kicker">
                                Prontuário Eletrônico
                            </span>
                            <h2><?= htmlspecialchars($tituloPagina) ?></h2>
                        </div>
                    </div>

                    <div class="module-under-construction">
                        <i class="fa-solid fa-layer-group"></i>

                        <div>
                            <h3>Módulo preparado</h3>

                            <p>
                                Esta área já faz parte da nova interface do
                                prontuário. A funcionalidade clínica será
                                implementada nas próximas etapas sem retornar
                                ao layout principal do Safe Health.
                            </p>
                        </div>
                    </div>
                </section>

            <?php endif; ?>

        </main>

    </section>

</div>


<script>
/*
 * Safe Health - Segurança v2
 * Intercepta somente FINALIZAÇÕES clínicas.
 * Os demais fetches continuam exatamente como já funcionavam.
 */
(function () {
    const originalFetch = window.fetch.bind(window);

    function ehApiClinica(url) {
        const texto = String(url || '');
        return texto.includes('/modules/prontuario/api/prescricao.php')
            || texto.includes('/modules/prontuario/api/evolucao.php')
            || texto.includes('/modules/prontuario/api/documentos.php')
            || texto.includes('/modules/prontuario/api/alta-medica.php');
    }

    function lerAcao(options) {
        try {
            if (!options || !options.body) return '';
            if (typeof options.body === 'string') {
                const json = JSON.parse(options.body);
                return String(json.acao || '').toLowerCase();
            }
        } catch (e) {}
        return '';
    }

    async function confirmarIdentidade() {
        const senha = window.prompt(
            'Ação clínica crítica.\n\nConfirme sua senha do Safe Health para continuar:'
        );

        if (senha === null) {
            throw new Error('REAUTENTICACAO_CANCELADA');
        }

        if (!senha.trim()) {
            window.alert('Informe sua senha para concluir a operação.');
            throw new Error('REAUTENTICACAO_CANCELADA');
        }

        const resposta = await originalFetch(
            '/safe-health/modules/tecnologia/api/reauth.php',
            {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({senha: senha}),
                credentials: 'same-origin'
            }
        );

        const dados = await resposta.json().catch(() => ({}));

        if (!resposta.ok || !dados.sucesso) {
            window.alert(dados.mensagem || 'Não foi possível confirmar sua identidade.');
            throw new Error('REAUTENTICACAO_FALHOU');
        }

        return true;
    }

    window.fetch = async function (input, options) {
        const url = typeof input === 'string' ? input : (input && input.url ? input.url : '');

        if (ehApiClinica(url) && lerAcao(options) === 'finalizar') {
            try {
                await confirmarIdentidade();
            } catch (erro) {
                if (
                    erro &&
                    (erro.message === 'REAUTENTICACAO_CANCELADA'
                        || erro.message === 'REAUTENTICACAO_FALHOU')
                ) {
                    return new Response(
                        JSON.stringify({
                            sucesso: false,
                            mensagem: 'Finalização cancelada: identidade não confirmada.'
                        }),
                        {
                            status: 401,
                            headers: {'Content-Type': 'application/json'}
                        }
                    );
                }
                throw erro;
            }
        }

        return originalFetch(input, options);
    };
})();
</script>

<script src="/safe-health/assets/js/prontuario.js?v=1"></script>

</body>
</html>
