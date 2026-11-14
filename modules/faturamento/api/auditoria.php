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

function recalcularConta(PDO $pdo, int $contaId): array
{
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
            ) AS valor_desconto,

            COALESCE(
                SUM(
                    CASE
                        WHEN status_item <> 'GLOSADO'
                        THEN valor_total
                        ELSE 0
                    END
                ),
                0
            ) AS valor_liquido

        FROM itens_conta_hospitalar
        WHERE conta_id = :conta_id
    ");

    $stmt->execute([
        ':conta_id' => $contaId
    ]);

    $totais = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $valorBruto = round((float) ($totais['valor_bruto'] ?? 0), 2);
    $valorDesconto = round((float) ($totais['valor_desconto'] ?? 0), 2);
    $valorLiquido = round((float) ($totais['valor_liquido'] ?? 0), 2);

    $stmtUpdate = $pdo->prepare("
        UPDATE contas_hospitalares
        SET
            valor_bruto = :valor_bruto,
            valor_desconto = :valor_desconto,
            valor_liquido = :valor_liquido
        WHERE id = :conta_id
    ");

    $stmtUpdate->execute([
        ':valor_bruto' => $valorBruto,
        ':valor_desconto' => $valorDesconto,
        ':valor_liquido' => $valorLiquido,
        ':conta_id' => $contaId
    ]);

    return [
        'valor_bruto' => $valorBruto,
        'valor_desconto' => $valorDesconto,
        'valor_liquido' => $valorLiquido
    ];
}

function obterAuditoriaAberta(PDO $pdo, int $contaId, ?int $usuarioId): int
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM auditorias_contas
        WHERE conta_id = :conta_id
          AND status = 'EM_ANDAMENTO'
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':conta_id' => $contaId
    ]);

    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $stmtInsert = $pdo->prepare("
        INSERT INTO auditorias_contas (
            conta_id,
            usuario_id,
            status
        )
        VALUES (
            :conta_id,
            :usuario_id,
            'EM_ANDAMENTO'
        )
    ");

    $stmtInsert->execute([
        ':conta_id' => $contaId,
        ':usuario_id' => $usuarioId
    ]);

    return (int) $pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim((string) ($_GET['acao'] ?? ''));

    if ($acao === 'resumo') {
        $stmt = $pdo->query("
            SELECT
                COUNT(DISTINCT ch.id) AS contas,

                SUM(
                    CASE
                        WHEN ich.status_item = 'PENDENTE'
                        THEN 1
                        ELSE 0
                    END
                ) AS pendentes,

                SUM(
                    CASE
                        WHEN ich.status_item = 'AUDITADO'
                        THEN 1
                        ELSE 0
                    END
                ) AS auditados,

                SUM(
                    CASE
                        WHEN ich.status_item = 'GLOSADO'
                        THEN 1
                        ELSE 0
                    END
                ) AS glosados

            FROM contas_hospitalares ch

            LEFT JOIN itens_conta_hospitalar ich
                ON ich.conta_id = ch.id

            WHERE ch.status = 'EM_AUDITORIA'
        ");

        responder(true, 'Resumo carregado.', [
            'resumo' => $stmt->fetch(PDO::FETCH_ASSOC)
        ]);
    }

    if ($acao === 'listar') {
        $q = trim((string) ($_GET['q'] ?? ''));

        $sql = "
            SELECT
                ch.id,
                ch.atendimento_id,
                ch.paciente_id,
                ch.status,
                ch.valor_bruto,
                ch.valor_desconto,
                ch.valor_liquido,
                ch.data_abertura,

                p.nome AS paciente_nome,
                a.numero_atendimento,
                a.convenio,

                COUNT(ich.id) AS total_itens,

                SUM(
                    CASE
                        WHEN ich.status_item = 'PENDENTE'
                        THEN 1
                        ELSE 0
                    END
                ) AS itens_pendentes,

                SUM(
                    CASE
                        WHEN ich.status_item = 'GLOSADO'
                        THEN 1
                        ELSE 0
                    END
                ) AS itens_glosados

            FROM contas_hospitalares ch

            INNER JOIN pacientes p
                ON p.id = ch.paciente_id

            INNER JOIN atendimentos a
                ON a.id = ch.atendimento_id

            LEFT JOIN itens_conta_hospitalar ich
                ON ich.conta_id = ch.id

            WHERE ch.status = 'EM_AUDITORIA'
        ";

        $params = [];

        if ($q !== '') {
            $qSemHash = ltrim($q, '#');

            $sql .= "
                AND (
                    CAST(ch.id AS CHAR) LIKE :conta
                    OR a.numero_atendimento LIKE :atendimento
                    OR p.nome LIKE :paciente
                )
            ";

            $params = [
                ':conta' => '%' . $qSemHash . '%',
                ':atendimento' => '%' . $q . '%',
                ':paciente' => '%' . $q . '%'
            ];
        }

        $sql .= "
            GROUP BY
                ch.id,
                ch.atendimento_id,
                ch.paciente_id,
                ch.status,
                ch.valor_bruto,
                ch.valor_desconto,
                ch.valor_liquido,
                ch.data_abertura,
                p.nome,
                a.numero_atendimento,
                a.convenio

            ORDER BY ch.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        responder(true, 'Contas carregadas.', [
            'contas' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($acao === 'detalhes') {
        $contaId = filter_var(
            $_GET['conta_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$contaId) {
            responder(false, 'Conta inválida.', [], 422);
        }

        $stmtConta = $pdo->prepare("
            SELECT
                ch.id,
                ch.atendimento_id,
                ch.paciente_id,
                ch.status,
                ch.valor_bruto,
                ch.valor_desconto,
                ch.valor_liquido,
                ch.data_abertura,

                p.nome AS paciente_nome,
                p.cpf,
                a.numero_atendimento,
                a.convenio

            FROM contas_hospitalares ch

            INNER JOIN pacientes p
                ON p.id = ch.paciente_id

            INNER JOIN atendimentos a
                ON a.id = ch.atendimento_id

            WHERE ch.id = :conta_id
              AND ch.status = 'EM_AUDITORIA'

            LIMIT 1
        ");

        $stmtConta->execute([
            ':conta_id' => $contaId
        ]);

        $conta = $stmtConta->fetch(PDO::FETCH_ASSOC);

        if (!$conta) {
            responder(
                false,
                'Conta não encontrada ou não está em auditoria.',
                [],
                404
            );
        }

        $stmtItens = $pdo->prepare("
            SELECT
                id,
                tipo_item,
                item_id,
                descricao,
                quantidade,
                valor_unitario,
                valor_total,
                status_item,
                criado_em

            FROM itens_conta_hospitalar

            WHERE conta_id = :conta_id

            ORDER BY
                FIELD(
                    tipo_item,
                    'DIARIA',
                    'TAXA',
                    'PROCEDIMENTO',
                    'CIRURGIA',
                    'MEDICAMENTO',
                    'MATERIAL',
                    'EXAME_LABORATORIAL',
                    'EXAME_IMAGEM',
                    'HONORARIO',
                    'OUTRO'
                ),
                criado_em,
                id
        ");

        $stmtItens->execute([
            ':conta_id' => $contaId
        ]);

        responder(true, 'Conta carregada.', [
            'conta' => $conta,
            'itens' => $stmtItens->fetchAll(PDO::FETCH_ASSOC)
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
    responder(false, 'Dados inválidos.', [], 400);
}

$acao = trim((string) ($entrada['acao'] ?? ''));
$contaId = (int) ($entrada['conta_id'] ?? 0);

if ($contaId <= 0) {
    responder(false, 'Conta inválida.', [], 422);
}

$usuarioId =
    isset($_SESSION['usuario_id'])
        ? (int) $_SESSION['usuario_id']
        : (
            isset($_SESSION['id_usuario'])
                ? (int) $_SESSION['id_usuario']
                : null
        );

if ($acao === 'alterar_status_item') {
    $itemId = (int) ($entrada['item_id'] ?? 0);
    $statusItem = strtoupper(trim((string) ($entrada['status_item'] ?? '')));
    $observacao = trim((string) ($entrada['observacao'] ?? ''));

    if ($itemId <= 0) {
        responder(false, 'Item inválido.', [], 422);
    }

    if (!in_array(
        $statusItem,
        ['PENDENTE', 'AUDITADO', 'GLOSADO'],
        true
    )) {
        responder(false, 'Status do item inválido.', [], 422);
    }

    if ($statusItem === 'GLOSADO' && $observacao === '') {
        responder(
            false,
            'Informe o motivo da glosa.',
            [],
            422
        );
    }

    try {
        $pdo->beginTransaction();

        $stmtConta = $pdo->prepare("
            SELECT id
            FROM contas_hospitalares
            WHERE id = :conta_id
              AND status = 'EM_AUDITORIA'
            LIMIT 1
            FOR UPDATE
        ");

        $stmtConta->execute([
            ':conta_id' => $contaId
        ]);

        if (!$stmtConta->fetchColumn()) {
            $pdo->rollBack();

            responder(
                false,
                'A conta não está disponível para auditoria.',
                [],
                409
            );
        }

        $stmtItem = $pdo->prepare("
            SELECT id
            FROM itens_conta_hospitalar
            WHERE id = :item_id
              AND conta_id = :conta_id
            LIMIT 1
            FOR UPDATE
        ");

        $stmtItem->execute([
            ':item_id' => $itemId,
            ':conta_id' => $contaId
        ]);

        if (!$stmtItem->fetchColumn()) {
            $pdo->rollBack();

            responder(false, 'Item não encontrado.', [], 404);
        }

        $auditoriaId = obterAuditoriaAberta(
            $pdo,
            $contaId,
            $usuarioId
        );

        $stmtUpdate = $pdo->prepare("
            UPDATE itens_conta_hospitalar
            SET status_item = :status_item
            WHERE id = :item_id
              AND conta_id = :conta_id
        ");

        $stmtUpdate->execute([
            ':status_item' => $statusItem,
            ':item_id' => $itemId,
            ':conta_id' => $contaId
        ]);

        $stmtObs = $pdo->prepare("
            INSERT INTO auditoria_item_observacoes (
                auditoria_id,
                item_conta_id,
                usuario_id,
                status_item,
                observacao
            )
            VALUES (
                :auditoria_id,
                :item_conta_id,
                :usuario_id,
                :status_item,
                :observacao
            )
        ");

        $stmtObs->execute([
            ':auditoria_id' => $auditoriaId,
            ':item_conta_id' => $itemId,
            ':usuario_id' => $usuarioId,
            ':status_item' => $statusItem,
            ':observacao' => $observacao !== '' ? $observacao : null
        ]);

        $totais = recalcularConta($pdo, $contaId);

        $pdo->commit();

        responder(true, 'Item atualizado.', [
            'totais' => $totais
        ]);

    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Erro na auditoria do item: ' . $erro->getMessage());

        responder(false, 'Não foi possível atualizar o item.', [
            'erro' => $erro->getMessage()
        ], 500);
    }
}

if ($acao === 'concluir_auditoria') {
    $observacoes = trim((string) ($entrada['observacoes'] ?? ''));

    try {
        $pdo->beginTransaction();

        $stmtConta = $pdo->prepare("
            SELECT id
            FROM contas_hospitalares
            WHERE id = :conta_id
              AND status = 'EM_AUDITORIA'
            LIMIT 1
            FOR UPDATE
        ");

        $stmtConta->execute([
            ':conta_id' => $contaId
        ]);

        if (!$stmtConta->fetchColumn()) {
            $pdo->rollBack();

            responder(
                false,
                'A conta não está disponível para auditoria.',
                [],
                409
            );
        }

        $stmtPendentes = $pdo->prepare("
            SELECT COUNT(*)
            FROM itens_conta_hospitalar
            WHERE conta_id = :conta_id
              AND status_item = 'PENDENTE'
        ");

        $stmtPendentes->execute([
            ':conta_id' => $contaId
        ]);

        $pendentes = (int) $stmtPendentes->fetchColumn();

        if ($pendentes > 0) {
            $pdo->rollBack();

            responder(
                false,
                'Ainda existem itens pendentes de auditoria.',
                ['itens_pendentes' => $pendentes],
                409
            );
        }

        $auditoriaId = obterAuditoriaAberta(
            $pdo,
            $contaId,
            $usuarioId
        );

        $stmtFinalizar = $pdo->prepare("
            UPDATE auditorias_contas
            SET
                status = 'CONCLUIDA',
                observacoes = :observacoes,
                concluido_em = NOW()
            WHERE id = :id
        ");

        $stmtFinalizar->execute([
            ':observacoes' => $observacoes !== '' ? $observacoes : null,
            ':id' => $auditoriaId
        ]);

        $totais = recalcularConta($pdo, $contaId);

        $pdo->commit();

        responder(
            true,
            'Auditoria concluída. A conta está pronta para o fechamento.',
            [
                'auditoria_id' => $auditoriaId,
                'totais' => $totais
            ]
        );

    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Erro ao concluir auditoria: ' . $erro->getMessage());

        responder(
            false,
            'Não foi possível concluir a auditoria.',
            ['erro' => $erro->getMessage()],
            500
        );
    }
}

responder(false, 'Ação inválida.', [], 400);
