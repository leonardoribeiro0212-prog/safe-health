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

function responderRx(
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

function contextoRx(PDO $pdo, int $atendimentoId, int $usuarioId): array
{
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

    $contexto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contexto) {
        responderRx(
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
        responderRx(
            false,
            'O atendimento já foi encerrado.',
            [],
            409
        );
    }

    return $contexto;
}

function buscarOuCriarRascunho(
    PDO $pdo,
    array $contexto,
    int $usuarioId
): int {
    $stmt = $pdo->prepare("
        SELECT id
        FROM prescricoes_medicas
        WHERE atendimento_id = :atendimento_id
          AND status = 'RASCUNHO'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':atendimento_id' => (int) $contexto['atendimento_id']
    ]);

    $id = (int) ($stmt->fetchColumn() ?: 0);

    if ($id > 0) {
        return $id;
    }

    $stmt = $pdo->prepare("
        INSERT INTO prescricoes_medicas (
            atendimento_id,
            paciente_id,
            medico_id,
            usuario_id,
            status,
            observacoes,
            data_prescricao
        )
        VALUES (
            :atendimento_id,
            :paciente_id,
            :medico_id,
            :usuario_id,
            'RASCUNHO',
            NULL,
            NOW()
        )
    ");

    $stmt->execute([
        ':atendimento_id' => (int) $contexto['atendimento_id'],
        ':paciente_id' => (int) $contexto['paciente_id'],
        ':medico_id' => (int) $contexto['medico_id'],
        ':usuario_id' => $usuarioId
    ]);

    return (int) $pdo->lastInsertId();
}


function rascunhoRx(
    PDO $pdo,
    int $prescricaoId,
    array $contexto
): array {
    if ($prescricaoId <= 0) {
        responderRx(
            false,
            'Nenhuma prescrição foi aberta. Clique em Novo.',
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
            observacoes
        FROM prescricoes_medicas
        WHERE id = :id
          AND atendimento_id = :atendimento_id
          AND medico_id = :medico_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $prescricaoId,
        ':atendimento_id' => (int) $contexto['atendimento_id'],
        ':medico_id' => (int) $contexto['medico_id']
    ]);

    $prescricao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prescricao) {
        responderRx(false, 'Prescrição não encontrada.', [], 404);
    }

    if ($prescricao['status'] !== 'RASCUNHO') {
        responderRx(
            false,
            'Esta prescrição já foi finalizada e não pode ser editada.',
            [],
            409
        );
    }

    return $prescricao;
}


/*
|--------------------------------------------------------------------------
| Integração automática com o Faturamento
|--------------------------------------------------------------------------
| Ao fechar e assinar a prescrição, medicamentos e exames são lançados
| automaticamente na conta hospitalar do atendimento.
|
| A operação ocorre na mesma transação da finalização da prescrição:
| se o faturamento falhar, a prescrição NÃO é finalizada pela metade.
|--------------------------------------------------------------------------
*/

function buscarContaHospitalarPrescricao(
    PDO $pdo,
    int $atendimentoId
): array {
    $stmt = $pdo->prepare("
        SELECT
            ch.id,
            ch.atendimento_id,
            ch.paciente_id,
            ch.convenio_id,
            ch.status,
            ch.valor_bruto,
            ch.valor_desconto,
            ch.valor_coparticipacao,
            ch.valor_liquido,
            a.convenio AS atendimento_convenio
        FROM contas_hospitalares ch
        INNER JOIN atendimentos a
            ON a.id = ch.atendimento_id
        WHERE ch.atendimento_id = :atendimento_id
        ORDER BY ch.id DESC
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':atendimento_id' => $atendimentoId
    ]);

    $conta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conta) {
        throw new RuntimeException(
            'A conta hospitalar deste atendimento não foi encontrada.'
        );
    }

    if (
        in_array(
            strtoupper((string) $conta['status']),
            ['FECHADA', 'FATURADA', 'CANCELADA'],
            true
        )
    ) {
        throw new DomainException(
            'A conta hospitalar já está fechada e não aceita novos lançamentos.'
        );
    }

    /*
     * Contas antigas podem estar sem convenio_id.
     * Nesse caso, resolvemos pelo nome/código gravado no atendimento.
     */
    if (empty($conta['convenio_id'])) {
        $nomeConvenio = trim(
            (string) ($conta['atendimento_convenio'] ?? '')
        );

        if ($nomeConvenio !== '') {
            $stmtConvenio = $pdo->prepare("
                SELECT id
                FROM convenios
                WHERE ativo = 1
                  AND (
                      LOWER(nome) = LOWER(:nome)
                      OR LOWER(codigo) = LOWER(:codigo)
                  )
                LIMIT 1
            ");

            $stmtConvenio->execute([
                ':nome' => $nomeConvenio,
                ':codigo' => $nomeConvenio
            ]);

            $convenioId = (int) (
                $stmtConvenio->fetchColumn() ?: 0
            );

            if ($convenioId > 0) {
                $stmtAtualizarConta = $pdo->prepare("
                    UPDATE contas_hospitalares
                    SET convenio_id = :convenio_id
                    WHERE id = :id
                ");

                $stmtAtualizarConta->execute([
                    ':convenio_id' => $convenioId,
                    ':id' => (int) $conta['id']
                ]);

                $conta['convenio_id'] = $convenioId;
            }
        }
    }

    return $conta;
}

function buscarPrecoConvenioPrescricao(
    PDO $pdo,
    ?int $convenioId,
    string $tipoItem,
    int $itemId
): ?array {
    if (!$convenioId || $convenioId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT
            valor,
            exige_autorizacao
        FROM precos_convenio
        WHERE convenio_id = :convenio_id
          AND tipo_item = :tipo_item
          AND item_id = :item_id
          AND ativo = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':convenio_id' => $convenioId,
        ':tipo_item' => $tipoItem,
        ':item_id' => $itemId
    ]);

    $preco = $stmt->fetch(PDO::FETCH_ASSOC);

    return $preco ?: null;
}

function recalcularContaHospitalarPrescricao(
    PDO $pdo,
    int $contaId
): array {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(valor_total), 0) AS valor_bruto,
            COALESCE(
                SUM(
                    CASE
                        WHEN status_item = 'GLOSADO'
                        THEN valor_total
                        ELSE 0
                    END
                ),
                0
            ) AS valor_desconto
        FROM itens_conta_hospitalar
        WHERE conta_id = :conta_id
    ");

    $stmt->execute([
        ':conta_id' => $contaId
    ]);

    $totaisItens = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtConta = $pdo->prepare("
        SELECT valor_coparticipacao
        FROM contas_hospitalares
        WHERE id = :conta_id
        LIMIT 1
    ");

    $stmtConta->execute([
        ':conta_id' => $contaId
    ]);

    $coparticipacao = round(
        (float) ($stmtConta->fetchColumn() ?: 0),
        2
    );

    $bruto = round(
        (float) ($totaisItens['valor_bruto'] ?? 0),
        2
    );

    $desconto = round(
        (float) ($totaisItens['valor_desconto'] ?? 0),
        2
    );

    $liquido = max(
        0,
        round(
            $bruto - $desconto - $coparticipacao,
            2
        )
    );

    $stmtUpdate = $pdo->prepare("
        UPDATE contas_hospitalares
        SET
            valor_bruto = :valor_bruto,
            valor_desconto = :valor_desconto,
            valor_liquido = :valor_liquido
        WHERE id = :conta_id
    ");

    $stmtUpdate->execute([
        ':valor_bruto' => $bruto,
        ':valor_desconto' => $desconto,
        ':valor_liquido' => $liquido,
        ':conta_id' => $contaId
    ]);

    return [
        'valor_bruto' => $bruto,
        'valor_desconto' => $desconto,
        'valor_coparticipacao' => $coparticipacao,
        'valor_liquido' => $liquido
    ];
}

function lancarPrescricaoNaContaHospitalar(
    PDO $pdo,
    int $contaId,
    ?int $convenioId,
    array $medicamentos,
    array $solicitacoes
): array {
    $quantidadeLancada = 0;
    $pendentesPreco = 0;

    $stmtMedicamento = $pdo->prepare("
        INSERT IGNORE INTO itens_conta_hospitalar (
            conta_id,
            tipo_item,
            item_id,
            descricao,
            quantidade,
            valor_unitario,
            valor_total,
            status_item,
            criado_em,
            origem_modulo,
            origem_id
        )
        VALUES (
            :conta_id,
            'MEDICAMENTO',
            :item_id,
            :descricao,
            :quantidade,
            :valor_unitario,
            :valor_total,
            :status_item,
            NOW(),
            'PRESCRICAO_MEDICAMENTO',
            :origem_id
        )
    ");

    foreach ($medicamentos as $item) {
        $quantidade = max(
            0.01,
            (float) ($item['quantidade'] ?? 1)
        );

        $preco = buscarPrecoConvenioPrescricao(
            $pdo,
            $convenioId,
            'MEDICAMENTO',
            (int) $item['medicamento_id']
        );

        $valorUnitario = $preco
            ? round((float) $preco['valor'], 2)
            : 0.00;

        $statusItem = $preco
            ? 'PENDENTE'
            : 'PENDENTE_PRECO';

        if (!$preco) {
            $pendentesPreco++;
        }

        $stmtMedicamento->execute([
            ':conta_id' => $contaId,
            ':item_id' => (int) $item['medicamento_id'],
            ':descricao' => mb_substr(
                trim((string) ($item['nome'] ?? 'Medicamento')),
                0,
                255
            ),
            ':quantidade' => $quantidade,
            ':valor_unitario' => $valorUnitario,
            ':valor_total' => round(
                $quantidade * $valorUnitario,
                2
            ),
            ':status_item' => $statusItem,
            ':origem_id' => (int) $item['id']
        ]);

        $quantidadeLancada +=
            $stmtMedicamento->rowCount();
    }

    $stmtExame = $pdo->prepare("
        INSERT IGNORE INTO itens_conta_hospitalar (
            conta_id,
            tipo_item,
            item_id,
            descricao,
            quantidade,
            valor_unitario,
            valor_total,
            status_item,
            criado_em,
            origem_modulo,
            origem_id
        )
        VALUES (
            :conta_id,
            :tipo_item,
            :item_id,
            :descricao,
            1,
            :valor_unitario,
            :valor_unitario,
            :status_item,
            NOW(),
            :origem_modulo,
            :origem_id
        )
    ");

    foreach ($solicitacoes as $item) {
        $tipo = strtoupper(
            (string) ($item['tipo_item'] ?? '')
        );

        if (
            !in_array(
                $tipo,
                ['EXAME_LABORATORIAL', 'EXAME_IMAGEM'],
                true
            )
        ) {
            continue;
        }

        $preco = buscarPrecoConvenioPrescricao(
            $pdo,
            $convenioId,
            $tipo,
            (int) $item['item_id']
        );

        $valorUnitario = $preco
            ? round((float) $preco['valor'], 2)
            : 0.00;

        $statusItem = $preco
            ? 'PENDENTE'
            : 'PENDENTE_PRECO';

        if (!$preco) {
            $pendentesPreco++;
        }

        $origemModulo = $tipo === 'EXAME_IMAGEM'
            ? 'PRESCRICAO_EXAME_IMAGEM'
            : 'PRESCRICAO_EXAME_LAB';

        $stmtExame->execute([
            ':conta_id' => $contaId,
            ':tipo_item' => $tipo,
            ':item_id' => (int) $item['item_id'],
            ':descricao' => mb_substr(
                (string) $item['descricao'],
                0,
                255
            ),
            ':valor_unitario' => $valorUnitario,
            ':status_item' => $statusItem,
            ':origem_modulo' => $origemModulo,
            ':origem_id' => (int) $item['id']
        ]);

        $quantidadeLancada +=
            $stmtExame->rowCount();
    }

    return [
        'itens_lancados' => $quantidadeLancada,
        'pendentes_preco' => $pendentesPreco
    ];
}


$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);

if ($usuarioId <= 0) {
    responderRx(
        false,
        'Sessão inválida.',
        [],
        401
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim((string) ($_GET['acao'] ?? ''));

    if ($acao !== 'pesquisar_medicamentos') {
        responderRx(false, 'Ação inválida.', [], 400);
    }

    $atendimentoId = (int) ($_GET['atendimento_id'] ?? 0);
    $q = trim((string) ($_GET['q'] ?? ''));

    if ($atendimentoId <= 0 || $q === '') {
        responderRx(
            false,
            'Informe o atendimento e um termo de pesquisa.',
            [],
            422
        );
    }

    $contexto = contextoRx($pdo, $atendimentoId, $usuarioId);

    $like = '%' . $q . '%';

    $stmtMed = $pdo->prepare("
        SELECT
            id,
            nome,
            principio_ativo,
            codigo,
            apresentacao,
            concentracao,
            unidade_medida,
            via_administracao,
            dose_padrao,
            frequencia_padrao,
            quantidade_padrao,
            controlado
        FROM medicamentos
        WHERE ativo = 1
          AND (
              nome LIKE :q
              OR principio_ativo LIKE :q
              OR codigo LIKE :q
          )
        ORDER BY nome
        LIMIT 20
    ");
    $stmtMed->execute([':q' => $like]);
    $medicamentos = $stmtMed->fetchAll(PDO::FETCH_ASSOC);

    $frequenciasPorMedicamento = [];

    if (count($medicamentos) > 0) {
        $ids = array_map(
            static fn (array $item): int => (int) $item['id'],
            $medicamentos
        );

        $placeholders = implode(
            ',',
            array_fill(0, count($ids), '?')
        );

        $stmtFreq = $pdo->prepare("
            SELECT
                mf.medicamento_id,
                f.codigo,
                f.descricao
            FROM medicamento_frequencias mf
            INNER JOIN frequencias_prescricao f
                ON f.id = mf.frequencia_id
            WHERE mf.medicamento_id IN ($placeholders)
              AND f.ativo = 1
            ORDER BY mf.medicamento_id, f.ordem, f.id
        ");
        $stmtFreq->execute($ids);

        foreach ($stmtFreq->fetchAll(PDO::FETCH_ASSOC) as $freq) {
            $mid = (int) $freq['medicamento_id'];

            if (!isset($frequenciasPorMedicamento[$mid])) {
                $frequenciasPorMedicamento[$mid] = [];
            }

            $frequenciasPorMedicamento[$mid][] = [
                'codigo' => $freq['codigo'],
                'descricao' => $freq['descricao']
            ];
        }
    }

    $stmtLab = $pdo->prepare("
        SELECT
            id,
            nome,
            codigo,
            categoria,
            material_biologico,
            preparo,
            prazo_resultado_horas
        FROM exames_laboratoriais
        WHERE ativo = 1
          AND (
              nome LIKE :q
              OR codigo LIKE :q
              OR categoria LIKE :q
          )
        ORDER BY nome
        LIMIT 20
    ");
    $stmtLab->execute([':q' => $like]);
    $laboratoriais = $stmtLab->fetchAll(PDO::FETCH_ASSOC);

    $stmtImg = $pdo->prepare("
        SELECT
            id,
            nome,
            codigo,
            modalidade,
            regiao_anatomica,
            preparo,
            usa_contraste,
            prazo_resultado_horas
        FROM exames_imagem
        WHERE ativo = 1
          AND (
              nome LIKE :q
              OR codigo LIKE :q
              OR modalidade LIKE :q
              OR regiao_anatomica LIKE :q
          )
        ORDER BY nome
        LIMIT 20
    ");
    $stmtImg->execute([':q' => $like]);
    $imagem = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

    $stmtAlergias = $pdo->prepare("
        SELECT substancia, gravidade
        FROM alergias_paciente
        WHERE paciente_id = :paciente_id
          AND status = 'ATIVA'
    ");
    $stmtAlergias->execute([
        ':paciente_id' => (int) $contexto['paciente_id']
    ]);
    $alergias = $stmtAlergias->fetchAll(PDO::FETCH_ASSOC);

    $itens = [];

    foreach ($medicamentos as $medicamento) {
        $alertas = [];

        $textoMedicamento = mb_strtolower(
            trim(
                ($medicamento['nome'] ?? '') . ' '
                . ($medicamento['principio_ativo'] ?? '')
            )
        );

        foreach ($alergias as $alergia) {
            $substancia = mb_strtolower(
                trim((string) $alergia['substancia'])
            );

            if (
                $substancia !== ''
                && (
                    str_contains($textoMedicamento, $substancia)
                    || str_contains($substancia, $textoMedicamento)
                )
            ) {
                $alertas[] = [
                    'substancia' => $alergia['substancia'],
                    'gravidade' => $alergia['gravidade'],
                    'tipo_alerta' => 'CORRESPONDENCIA_TEXTUAL'
                ];
            }
        }

        $itens[] = [
            'tipo' => 'MEDICAMENTO',
            'id' => (int) $medicamento['id'],
            'nome' => $medicamento['nome'],
            'codigo' => $medicamento['codigo'],
            'subtitulo' => trim(
                ($medicamento['principio_ativo'] ?: '')
                . ' • '
                . ($medicamento['concentracao'] ?: '')
                . ' • '
                . ($medicamento['apresentacao'] ?: ''),
                " •"
            ),
            'via_administracao' => $medicamento['via_administracao'],
            'dose_padrao' => $medicamento['dose_padrao'],
            'frequencias' =>
                $frequenciasPorMedicamento[(int) $medicamento['id']] ?? [],
            'quantidade_padrao' => (int) ($medicamento['quantidade_padrao'] ?: 1),
            'controlado' => (int) $medicamento['controlado'],
            'alertas_alergia' => $alertas
        ];
    }

    foreach ($laboratoriais as $exame) {
        $itens[] = [
            'tipo' => 'EXAME_LABORATORIAL',
            'id' => (int) $exame['id'],
            'nome' => $exame['nome'],
            'codigo' => $exame['codigo'],
            'subtitulo' => trim(
                'Laboratorial'
                . ($exame['categoria'] ? ' • ' . $exame['categoria'] : '')
                . ($exame['material_biologico'] ? ' • ' . $exame['material_biologico'] : '')
            )
        ];
    }

    foreach ($imagem as $exame) {
        $itens[] = [
            'tipo' => 'EXAME_IMAGEM',
            'id' => (int) $exame['id'],
            'nome' => $exame['nome'],
            'codigo' => $exame['codigo'],
            'subtitulo' => trim(
                'Imagem'
                . ($exame['modalidade'] ? ' • ' . $exame['modalidade'] : '')
                . ($exame['regiao_anatomica'] ? ' • ' . $exame['regiao_anatomica'] : '')
            )
        ];
    }

    usort(
        $itens,
        static fn (array $a, array $b): int =>
            strcasecmp($a['nome'], $b['nome'])
    );

    responderRx(
        true,
        'Itens carregados.',
        ['itens' => $itens]
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderRx(false, 'Método não permitido.', [], 405);
}

$entrada = json_decode(
    file_get_contents('php://input') ?: '',
    true
);

if (!is_array($entrada)) {
    responderRx(false, 'Dados inválidos.', [], 400);
}

$acao = trim((string) ($entrada['acao'] ?? ''));
$atendimentoId = (int) ($entrada['atendimento_id'] ?? 0);

if ($atendimentoId <= 0) {
    responderRx(false, 'Atendimento inválido.', [], 422);
}

$contexto = contextoRx($pdo, $atendimentoId, $usuarioId);

try {
    if ($acao === 'excluir_rascunho') {
        $prescricaoId = (int) ($entrada['prescricao_id'] ?? 0);

        if ($prescricaoId <= 0) {
            responderRx(false, 'Prescrição inválida.', [], 422);
        }

        $stmt = $pdo->prepare("
            SELECT id, status
            FROM prescricoes_medicas
            WHERE id = :id
              AND atendimento_id = :atendimento_id
              AND medico_id = :medico_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $prescricaoId,
            ':atendimento_id' => $atendimentoId,
            ':medico_id' => (int) $contexto['medico_id']
        ]);
        $prescricao = $stmt->fetch();

        if (!$prescricao) {
            responderRx(false, 'Prescrição não encontrada.', [], 404);
        }

        if (($prescricao['status'] ?? '') !== 'RASCUNHO') {
            responderRx(
                false,
                'Somente prescrições em rascunho podem ser excluídas. Documentos assinados permanecem no histórico.',
                [],
                409
            );
        }

        $pdo->beginTransaction();

        $stmtSolicitacoes = $pdo->prepare("
            DELETE FROM prescricao_solicitacoes
            WHERE prescricao_id = :prescricao_id
        ");
        $stmtSolicitacoes->execute([
            ':prescricao_id' => $prescricaoId
        ]);

        $stmtItens = $pdo->prepare("
            DELETE FROM prescricao_medicamentos
            WHERE prescricao_id = :prescricao_id
        ");
        $stmtItens->execute([':prescricao_id' => $prescricaoId]);

        $stmtExcluir = $pdo->prepare("
            DELETE FROM prescricoes_medicas
            WHERE id = :id
              AND status = 'RASCUNHO'
        ");
        $stmtExcluir->execute([':id' => $prescricaoId]);

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'EXCLUIR',
                'Prescrição médica em rascunho excluída.',
                'Prontuário',
                'Prescrição Médica',
                'prescricoes_medicas',
                $prescricaoId,
                ['status' => 'RASCUNHO'],
                null,
                $usuarioId
            );
        }

        $pdo->commit();

        responderRx(true, 'Prescrição em rascunho excluída com sucesso.');
    }

    if ($acao === 'novo_rascunho') {
        $stmt = $pdo->prepare("
            INSERT INTO prescricoes_medicas (
                atendimento_id,
                paciente_id,
                medico_id,
                usuario_id,
                status,
                observacoes,
                data_prescricao
            )
            VALUES (
                :atendimento_id,
                :paciente_id,
                :medico_id,
                :usuario_id,
                'RASCUNHO',
                NULL,
                NOW()
            )
        ");
        $stmt->execute([
            ':atendimento_id' => $atendimentoId,
            ':paciente_id' => (int) $contexto['paciente_id'],
            ':medico_id' => (int) $contexto['medico_id'],
            ':usuario_id' => $usuarioId
        ]);

        $prescricaoId = (int) $pdo->lastInsertId();

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'CRIAR',
                'Novo documento de prescrição médica iniciado.',
                'Prontuário',
                'Prescrição Médica',
                'prescricoes_medicas',
                $prescricaoId,
                null,
                [
                    'atendimento_id' => $atendimentoId,
                    'status' => 'RASCUNHO'
                ],
                $usuarioId
            );
        }

        responderRx(
            true,
            'Nova prescrição criada.',
            ['prescricao_id' => $prescricaoId],
            201
        );
    }

    if ($acao === 'adicionar_item') {
        $prescricaoId = (int) ($entrada['prescricao_id'] ?? 0);
        $prescricao = rascunhoRx($pdo, $prescricaoId, $contexto);

        $medicamentoId = (int) ($entrada['medicamento_id'] ?? 0);
        $frequenciaSelecionada = strtoupper(
            trim((string) ($entrada['frequencia'] ?? ''))
        );
        $instrucoes = trim((string) ($entrada['instrucoes'] ?? ''));

        if ($medicamentoId <= 0) {
            responderRx(
                false,
                'Selecione um medicamento cadastrado.',
                [],
                422
            );
        }

        $stmtMed = $pdo->prepare("
            SELECT
                id,
                nome,
                principio_ativo,
                dose_padrao,
                via_administracao,
                quantidade_padrao
            FROM medicamentos
            WHERE id = :id
              AND ativo = 1
            LIMIT 1
        ");
        $stmtMed->execute([':id' => $medicamentoId]);
        $medicamento = $stmtMed->fetch(PDO::FETCH_ASSOC);

        if (!$medicamento) {
            responderRx(false, 'Medicamento não encontrado.', [], 404);
        }

        $dose = trim((string) ($medicamento['dose_padrao'] ?? ''));
        $via = trim((string) ($medicamento['via_administracao'] ?? ''));
        $quantidade = (int) ($medicamento['quantidade_padrao'] ?? 0);

        if (
            $dose === ''
            || $via === ''
            || $quantidade <= 0
        ) {
            responderRx(
                false,
                'O medicamento selecionado não possui dose, via e quantidade completas no Cadastro de Medicamentos.',
                [],
                409
            );
        }

        if ($frequenciaSelecionada === '') {
            responderRx(
                false,
                'Selecione a frequência do medicamento.',
                [],
                422
            );
        }

        $stmtFreqPermitida = $pdo->prepare("
            SELECT f.codigo
            FROM medicamento_frequencias mf
            INNER JOIN frequencias_prescricao f
                ON f.id = mf.frequencia_id
            WHERE mf.medicamento_id = :medicamento_id
              AND f.codigo = :codigo
              AND f.ativo = 1
            LIMIT 1
        ");
        $stmtFreqPermitida->execute([
            ':medicamento_id' => $medicamentoId,
            ':codigo' => $frequenciaSelecionada
        ]);

        $frequencia = (string) (
            $stmtFreqPermitida->fetchColumn() ?: ''
        );

        if ($frequencia === '') {
            responderRx(
                false,
                'A frequência selecionada não está vinculada a este medicamento.',
                [],
                409
            );
        }

        $pdo->beginTransaction();

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM prescricao_medicamentos
            WHERE prescricao_id = :prescricao_id
              AND medicamento_id = :medicamento_id
              AND status_farmacia <> 'CANCELADO'
            LIMIT 1
        ");
        $stmtDup->execute([
            ':prescricao_id' => $prescricaoId,
            ':medicamento_id' => $medicamentoId
        ]);

        if ($stmtDup->fetchColumn()) {
            $pdo->rollBack();
            responderRx(
                false,
                'Este medicamento já está no rascunho atual.',
                [],
                409
            );
        }

        $stmtItem = $pdo->prepare("
            INSERT INTO prescricao_medicamentos (
                prescricao_id,
                medicamento_id,
                dose,
                via_administracao,
                frequencia,
                quantidade,
                instrucoes,
                status_farmacia
            )
            VALUES (
                :prescricao_id,
                :medicamento_id,
                :dose,
                :via,
                :frequencia,
                :quantidade,
                :instrucoes,
                'PENDENTE'
            )
        ");
        $stmtItem->execute([
            ':prescricao_id' => $prescricaoId,
            ':medicamento_id' => $medicamentoId,
            ':dose' => $dose,
            ':via' => $via,
            ':frequencia' => $frequencia,
            ':quantidade' => $quantidade,
            ':instrucoes' => $instrucoes !== '' ? $instrucoes : null
        ]);

        $itemId = (int) $pdo->lastInsertId();
        $pdo->commit();

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'CRIAR',
                'Medicamento adicionado ao rascunho da prescrição.',
                'Prontuário',
                'Prescrição Médica',
                'prescricao_medicamentos',
                $itemId,
                null,
                [
                    'prescricao_id' => $prescricaoId,
                    'atendimento_id' => $atendimentoId,
                    'medicamento_id' => $medicamentoId,
                    'medicamento' => $medicamento['nome']
                ],
                $usuarioId
            );
        }

        responderRx(
            true,
            'Medicamento adicionado à prescrição.',
            ['prescricao_id' => $prescricaoId],
            201
        );
    }

    if ($acao === 'adicionar_solicitacao') {
        $tipoItem = strtoupper(
            trim((string) ($entrada['tipo_item'] ?? ''))
        );
        $itemId = (int) ($entrada['item_id'] ?? 0);
        $detalhes = trim((string) ($entrada['detalhes'] ?? ''));

        if (
            !in_array(
                $tipoItem,
                ['EXAME_LABORATORIAL', 'EXAME_IMAGEM'],
                true
            )
            || $itemId <= 0
        ) {
            responderRx(
                false,
                'Exame inválido.',
                [],
                422
            );
        }

        if ($tipoItem === 'EXAME_LABORATORIAL') {
            $stmtItem = $pdo->prepare("
                SELECT id, nome, codigo
                FROM exames_laboratoriais
                WHERE id = :id
                  AND ativo = 1
                LIMIT 1
            ");
        } else {
            $stmtItem = $pdo->prepare("
                SELECT id, nome, codigo
                FROM exames_imagem
                WHERE id = :id
                  AND ativo = 1
                LIMIT 1
            ");
        }

        $stmtItem->execute([':id' => $itemId]);
        $catalogo = $stmtItem->fetch(PDO::FETCH_ASSOC);

        if (!$catalogo) {
            responderRx(
                false,
                'O exame selecionado não foi encontrado no cadastro.',
                [],
                404
            );
        }

        $prescricaoId = (int) ($entrada['prescricao_id'] ?? 0);
        $prescricao = rascunhoRx($pdo, $prescricaoId, $contexto);

        $pdo->beginTransaction();

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM prescricao_solicitacoes
            WHERE prescricao_id = :prescricao_id
              AND tipo_item = :tipo_item
              AND item_id = :item_id
              AND status <> 'CANCELADO'
            LIMIT 1
        ");
        $stmtDup->execute([
            ':prescricao_id' => $prescricaoId,
            ':tipo_item' => $tipoItem,
            ':item_id' => $itemId
        ]);

        if ($stmtDup->fetchColumn()) {
            $pdo->rollBack();
            responderRx(
                false,
                'Este exame já está no rascunho atual.',
                [],
                409
            );
        }

        $stmtInsert = $pdo->prepare("
            INSERT INTO prescricao_solicitacoes (
                prescricao_id,
                tipo_item,
                item_id,
                descricao,
                codigo_item,
                detalhes,
                status,
                criado_em
            )
            VALUES (
                :prescricao_id,
                :tipo_item,
                :item_id,
                :descricao,
                :codigo_item,
                :detalhes,
                'PENDENTE',
                NOW()
            )
        ");
        $stmtInsert->execute([
            ':prescricao_id' => $prescricaoId,
            ':tipo_item' => $tipoItem,
            ':item_id' => $itemId,
            ':descricao' => $catalogo['nome'],
            ':codigo_item' => $catalogo['codigo'],
            ':detalhes' => $detalhes !== '' ? $detalhes : null
        ]);

        $solicitacaoId = (int) $pdo->lastInsertId();
        $pdo->commit();

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'CRIAR',
                'Exame adicionado ao rascunho da prescrição.',
                'Prontuário',
                'Prescrição Médica',
                'prescricao_solicitacoes',
                $solicitacaoId,
                null,
                [
                    'prescricao_id' => $prescricaoId,
                    'atendimento_id' => $atendimentoId,
                    'tipo_item' => $tipoItem,
                    'item_id' => $itemId,
                    'descricao' => $catalogo['nome']
                ],
                $usuarioId
            );
        }

        responderRx(
            true,
            'Exame adicionado à prescrição.',
            ['prescricao_id' => $prescricaoId],
            201
        );
    }

    if ($acao === 'remover_solicitacao') {
        $solicitacaoId =
            (int) ($entrada['solicitacao_id'] ?? 0);

        $stmt = $pdo->prepare("
            SELECT
                ps.id,
                ps.prescricao_id,
                ps.status AS item_status,
                pm.status AS prescricao_status
            FROM prescricao_solicitacoes ps
            INNER JOIN prescricoes_medicas pm
                ON pm.id = ps.prescricao_id
            WHERE ps.id = :id
              AND pm.atendimento_id = :atendimento_id
              AND pm.medico_id = :medico_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $solicitacaoId,
            ':atendimento_id' => $atendimentoId,
            ':medico_id' => (int) $contexto['medico_id']
        ]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$item
            || $item['prescricao_status'] !== 'RASCUNHO'
        ) {
            responderRx(
                false,
                'A solicitação não pode mais ser removida.',
                [],
                409
            );
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE prescricao_solicitacoes
            SET status = 'CANCELADO'
            WHERE id = :id
        ");
        $stmtUpdate->execute([':id' => $solicitacaoId]);

        responderRx(true, 'Exame removido do rascunho.');
    }

    if ($acao === 'remover_item') {
        $itemId = (int) ($entrada['item_id'] ?? 0);

        $stmt = $pdo->prepare("
            SELECT
                pmi.id,
                pmi.prescricao_id,
                pm.status,
                pm.atendimento_id
            FROM prescricao_medicamentos pmi
            INNER JOIN prescricoes_medicas pm
                ON pm.id = pmi.prescricao_id
            WHERE pmi.id = :item_id
              AND pm.atendimento_id = :atendimento_id
              AND pm.medico_id = :medico_id
            LIMIT 1
        ");
        $stmt->execute([
            ':item_id' => $itemId,
            ':atendimento_id' => $atendimentoId,
            ':medico_id' => (int) $contexto['medico_id']
        ]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item || $item['status'] !== 'RASCUNHO') {
            responderRx(
                false,
                'O item não pode mais ser removido.',
                [],
                409
            );
        }

        $stmtDel = $pdo->prepare("
            UPDATE prescricao_medicamentos
            SET status_farmacia = 'CANCELADO'
            WHERE id = :id
        ");
        $stmtDel->execute([':id' => $itemId]);

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'CANCELAR',
                'Item removido do rascunho da prescrição.',
                'Prontuário',
                'Prescrição Médica',
                'prescricao_medicamentos',
                $itemId,
                $item,
                ['status_farmacia' => 'CANCELADO'],
                $usuarioId
            );
        }

        responderRx(true, 'Item removido.');
    }

    if ($acao === 'salvar_observacoes') {
        $observacoes = trim((string) ($entrada['observacoes'] ?? ''));
        $prescricaoId = (int) ($entrada['prescricao_id'] ?? 0);
        $prescricao = rascunhoRx($pdo, $prescricaoId, $contexto);

        $stmt = $pdo->prepare("
            UPDATE prescricoes_medicas
            SET observacoes = :observacoes
            WHERE id = :id
              AND status = 'RASCUNHO'
        ");
        $stmt->execute([
            ':observacoes' => $observacoes !== '' ? $observacoes : null,
            ':id' => $prescricaoId
        ]);

        responderRx(
            true,
            'Observações salvas.',
            ['prescricao_id' => $prescricaoId]
        );
    }

    if ($acao === 'finalizar') {
        // Confere a permissão e exige confirmação da senha antes de finalizar.
        shExigirAcao($pdo, 'PRONTUARIO_FINALIZAR');
        shExigirReautenticacao($pdo);

        $prescricaoId = (int) ($entrada['prescricao_id'] ?? 0);
        $prescricao = rascunhoRx($pdo, $prescricaoId, $contexto);

        $stmtItens = $pdo->prepare("
            SELECT
                pmi.id,
                pmi.medicamento_id,
                pmi.dose,
                pmi.via_administracao,
                pmi.frequencia,
                pmi.quantidade,
                pmi.instrucoes,
                med.nome,
                med.principio_ativo,
                med.valor_unitario
            FROM prescricao_medicamentos pmi
            INNER JOIN medicamentos med
                ON med.id = pmi.medicamento_id
            WHERE pmi.prescricao_id = :prescricao_id
              AND pmi.status_farmacia <> 'CANCELADO'
            ORDER BY pmi.id
        ");
        $stmtItens->execute([
            ':prescricao_id' => $prescricaoId
        ]);
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        $stmtSolicitacoes = $pdo->prepare("
            SELECT
                ps.id,
                ps.tipo_item,
                ps.item_id,
                ps.descricao,
                ps.codigo_item,
                ps.detalhes,
                CASE
                    WHEN ps.tipo_item = 'EXAME_LABORATORIAL'
                        THEN COALESCE(el.valor_unitario, 0)
                    WHEN ps.tipo_item = 'EXAME_IMAGEM'
                        THEN COALESCE(ei.valor_unitario, 0)
                    ELSE 0
                END AS valor_unitario
            FROM prescricao_solicitacoes ps
            LEFT JOIN exames_laboratoriais el
                ON el.id = ps.item_id
               AND ps.tipo_item = 'EXAME_LABORATORIAL'
            LEFT JOIN exames_imagem ei
                ON ei.id = ps.item_id
               AND ps.tipo_item = 'EXAME_IMAGEM'
            WHERE ps.prescricao_id = :prescricao_id
              AND ps.status <> 'CANCELADO'
            ORDER BY ps.id
        ");
        $stmtSolicitacoes->execute([
            ':prescricao_id' => $prescricaoId
        ]);
        $solicitacoes =
            $stmtSolicitacoes->fetchAll(PDO::FETCH_ASSOC);

        if (
            count($itens) === 0
            && count($solicitacoes) === 0
        ) {
            responderRx(
                false,
                'Adicione pelo menos um medicamento ou exame antes de finalizar.',
                [],
                422
            );
        }

        $conteudoHash = json_encode(
            [
                'prescricao_id' => $prescricaoId,
                'atendimento_id' => $atendimentoId,
                'paciente_id' => (int) $contexto['paciente_id'],
                'medico_id' => (int) $contexto['medico_id'],
                'usuario_id' => $usuarioId,
                'observacoes' => $prescricao['observacoes'],
                'itens' => $itens,
                'solicitacoes' => $solicitacoes
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        $hash = hash('sha256', (string) $conteudoHash);

        $pdo->beginTransaction();

        /*
         * A conta é bloqueada antes dos lançamentos para garantir que
         * prescrição e faturamento sejam confirmados juntos.
         */
        $contaHospitalar = buscarContaHospitalarPrescricao(
            $pdo,
            $atendimentoId
        );

        $resultadoFaturamento = lancarPrescricaoNaContaHospitalar(
            $pdo,
            (int) $contaHospitalar['id'],
            !empty($contaHospitalar['convenio_id'])
                ? (int) $contaHospitalar['convenio_id']
                : null,
            $itens,
            $solicitacoes
        );

        $itensLancadosConta =
            (int) $resultadoFaturamento['itens_lancados'];

        $itensPendentesPreco =
            (int) $resultadoFaturamento['pendentes_preco'];

        $totaisConta = recalcularContaHospitalarPrescricao(
            $pdo,
            (int) $contaHospitalar['id']
        );

        $stmtUpdate = $pdo->prepare("
            UPDATE prescricoes_medicas
            SET
                status = 'LIBERADA_FARMACIA',
                data_liberacao_farmacia = NOW(),
                finalizada_em = NOW(),
                hash_finalizacao = :hash
            WHERE id = :id
              AND status = 'RASCUNHO'
        ");
        $stmtUpdate->execute([
            ':hash' => $hash,
            ':id' => $prescricaoId
        ]);

        if ($stmtUpdate->rowCount() !== 1) {
            $pdo->rollBack();
            responderRx(
                false,
                'A prescrição já foi alterada por outra operação.',
                [],
                409
            );
        }

        $pdo->commit();

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria(
                $pdo,
                'FINALIZAR',
                'Prescrição médica assinada e liberada para a Farmácia.',
                'Prontuário',
                'Prescrição Médica',
                'prescricoes_medicas',
                $prescricaoId,
                ['status' => 'RASCUNHO'],
                [
                    'status' => 'LIBERADA_FARMACIA',
                    'hash_finalizacao' => $hash,
                    'atendimento_id' => $atendimentoId,
                    'conta_hospitalar_id' => (int) $contaHospitalar['id'],
                    'itens_lancados_conta' => $itensLancadosConta,
                    'itens_pendentes_preco' => $itensPendentesPreco,
                    'convenio_id' => $contaHospitalar['convenio_id'] ?? null,
                    'valor_liquido_conta' => $totaisConta['valor_liquido']
                ],
                $usuarioId
            );
        }

        responderRx(
            true,
            $itensPendentesPreco > 0
                ? 'Prescrição assinada e lançada na conta hospitalar. Existem itens pendentes de precificação para este convênio.'
                : 'Prescrição assinada, liberada para a Farmácia e lançada na conta hospitalar com a tabela do convênio.',
            [
                'prescricao_id' => $prescricaoId,
                'hash' => $hash,
                'conta_hospitalar_id' => (int) $contaHospitalar['id'],
                'convenio_id' => $contaHospitalar['convenio_id'] ?? null,
                'itens_lancados_conta' => $itensLancadosConta,
                'itens_pendentes_preco' => $itensPendentesPreco,
                'totais_conta' => $totaisConta
            ]
        );
    }

    responderRx(false, 'Ação inválida.', [], 400);

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro na API de prescrição do prontuário: '
        . $erro->getMessage()
    );

    responderRx(
        false,
        'Não foi possível concluir a operação da prescrição.',
        [],
        500
    );
}
