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

function decimalOuNull(mixed $valor): ?float
{
    if ($valor === null || $valor === '') {
        return null;
    }

    $valor = str_replace(
        ',',
        '.',
        trim((string) $valor)
    );

    return is_numeric($valor)
        ? (float) $valor
        : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim(
        (string) ($_GET['acao'] ?? '')
    );

    if ($acao === 'medicamentos') {
        $stmt = $pdo->query(
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
        );

        responder(
            true,
            'Medicamentos carregados.',
            [
                'medicamentos' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'listar') {
        $q = trim(
            (string) ($_GET['q'] ?? '')
        );

        $sql = "
            SELECT
                ef.id,
                ef.medicamento_id,
                ef.lote,
                ef.validade,
                ef.quantidade_atual,
                ef.quantidade_minima,
                ef.localizacao,
                ef.ativo,

                m.nome AS medicamento_nome,
                m.concentracao,
                m.apresentacao,
                m.unidade_medida,

                CASE
                    WHEN ef.quantidade_atual <= 0
                        THEN 'SEM_SALDO'

                    WHEN ef.quantidade_atual <= ef.quantidade_minima
                        THEN 'ESTOQUE_BAIXO'

                    WHEN ef.validade IS NOT NULL
                         AND ef.validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                        THEN 'PROXIMO_VENCIMENTO'

                    ELSE 'NORMAL'
                END AS situacao

            FROM estoque_farmacia ef

            INNER JOIN medicamentos m
                ON m.id = ef.medicamento_id

            WHERE ef.ativo = 1
        ";

        $params = [];

        if ($q !== '') {
            $sql .= "
                AND (
                    m.nome LIKE :q
                    OR ef.lote LIKE :q
                    OR ef.localizacao LIKE :q
                )
            ";

            $params[':q'] =
                '%' . $q . '%';
        }

        $sql .= "
            ORDER BY
                m.nome,
                ef.validade,
                ef.lote
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        responder(
            true,
            'Estoque carregado.',
            [
                'estoque' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'resumo') {
        $stmt = $pdo->query(
            "
            SELECT
                COUNT(*) AS itens,

                SUM(
                    CASE
                        WHEN quantidade_atual <= quantidade_minima
                             AND quantidade_atual > 0
                        THEN 1
                        ELSE 0
                    END
                ) AS baixo,

                SUM(
                    CASE
                        WHEN validade IS NOT NULL
                             AND validade >= CURDATE()
                             AND validade <= DATE_ADD(
                                CURDATE(),
                                INTERVAL 30 DAY
                             )
                        THEN 1
                        ELSE 0
                    END
                ) AS vencimento,

                SUM(
                    CASE
                        WHEN quantidade_atual <= 0
                        THEN 1
                        ELSE 0
                    END
                ) AS sem_saldo

            FROM estoque_farmacia
            WHERE ativo = 1
            "
        );

        responder(
            true,
            'Resumo carregado.',
            [
                'resumo' =>
                    $stmt->fetch(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'movimentacoes') {
        $stmt = $pdo->query(
            "
            SELECT
                mef.id,
                mef.tipo,
                mef.quantidade,
                mef.saldo_anterior,
                mef.saldo_posterior,
                mef.referencia,
                mef.observacoes,
                mef.criado_em,

                ef.lote,

                m.nome AS medicamento_nome,

                u.nome AS usuario_nome

            FROM movimentacoes_estoque_farmacia mef

            INNER JOIN estoque_farmacia ef
                ON ef.id = mef.estoque_id

            INNER JOIN medicamentos m
                ON m.id = mef.medicamento_id

            LEFT JOIN usuarios u
                ON u.id = mef.usuario_id

            ORDER BY
                mef.criado_em DESC,
                mef.id DESC

            LIMIT 50
            "
        );

        responder(
            true,
            'Movimentações carregadas.',
            [
                'movimentacoes' =>
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

$estoqueId =
    (int) ($entrada['estoque_id'] ?? 0);

$medicamentoId =
    (int) ($entrada['medicamento_id'] ?? 0);

$lote =
    trim((string) ($entrada['lote'] ?? ''));

$validade =
    trim((string) ($entrada['validade'] ?? ''));

$tipo =
    strtoupper(trim(
        (string) ($entrada['tipo'] ?? '')
    ));

$quantidade =
    decimalOuNull(
        $entrada['quantidade'] ?? null
    );

$quantidadeMinima =
    decimalOuNull(
        $entrada['quantidade_minima'] ?? 0
    );

$localizacao =
    trim((string) ($entrada['localizacao'] ?? ''));

$referencia =
    trim((string) ($entrada['referencia'] ?? ''));

$observacoes =
    trim((string) ($entrada['observacoes'] ?? ''));

if ($medicamentoId <= 0) {
    responder(
        false,
        'Selecione o medicamento.',
        [],
        422
    );
}

if ($lote === '') {
    responder(
        false,
        'Informe o lote.',
        [],
        422
    );
}

if (
    $validade !== '' &&
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $validade
    )
) {
    responder(
        false,
        'Validade inválida.',
        [],
        422
    );
}

if (!in_array(
    $tipo,
    [
        'ENTRADA',
        'AJUSTE_POSITIVO',
        'AJUSTE_NEGATIVO'
    ],
    true
)) {
    responder(
        false,
        'Tipo de movimentação inválido.',
        [],
        422
    );
}

if (
    $quantidade === null ||
    $quantidade <= 0
) {
    responder(
        false,
        'Informe uma quantidade válida.',
        [],
        422
    );
}

if (
    $quantidadeMinima === null ||
    $quantidadeMinima < 0
) {
    responder(
        false,
        'Estoque mínimo inválido.',
        [],
        422
    );
}

try {
    $pdo->beginTransaction();

    $stmtMedicamento = $pdo->prepare(
        "
        SELECT id
        FROM medicamentos
        WHERE id = :id
          AND ativo = 1
        LIMIT 1
        "
    );

    $stmtMedicamento->execute([
        ':id' => $medicamentoId
    ]);

    if (!$stmtMedicamento->fetchColumn()) {
        $pdo->rollBack();

        responder(
            false,
            'Medicamento inválido ou inativo.',
            [],
            422
        );
    }

    $stmtEstoque = $pdo->prepare(
        "
        SELECT
            id,
            quantidade_atual
        FROM estoque_farmacia
        WHERE
            medicamento_id = :medicamento_id
            AND lote = :lote
        LIMIT 1
        FOR UPDATE
        "
    );

    $stmtEstoque->execute([
        ':medicamento_id' => $medicamentoId,
        ':lote' => $lote
    ]);

    $estoque =
        $stmtEstoque->fetch(PDO::FETCH_ASSOC);

    if (!$estoque) {
        if ($tipo === 'AJUSTE_NEGATIVO') {
            $pdo->rollBack();

            responder(
                false,
                'Não é possível realizar ajuste negativo em um lote sem estoque.',
                [],
                422
            );
        }

        $saldoAnterior = 0.0;
        $saldoPosterior =
            $quantidade;

        $stmtInsertEstoque = $pdo->prepare(
            "
            INSERT INTO estoque_farmacia (
                medicamento_id,
                lote,
                validade,
                quantidade_atual,
                quantidade_minima,
                localizacao,
                ativo
            )
            VALUES (
                :medicamento_id,
                :lote,
                :validade,
                :quantidade_atual,
                :quantidade_minima,
                :localizacao,
                1
            )
            "
        );

        $stmtInsertEstoque->execute([
            ':medicamento_id' =>
                $medicamentoId,

            ':lote' =>
                $lote,

            ':validade' =>
                $validade !== ''
                    ? $validade
                    : null,

            ':quantidade_atual' =>
                $saldoPosterior,

            ':quantidade_minima' =>
                $quantidadeMinima,

            ':localizacao' =>
                $localizacao !== ''
                    ? $localizacao
                    : null
        ]);

        $estoqueId =
            (int) $pdo->lastInsertId();

    } else {
        $estoqueId =
            (int) $estoque['id'];

        $saldoAnterior =
            (float) $estoque['quantidade_atual'];

        if ($tipo === 'AJUSTE_NEGATIVO') {
            if ($quantidade > $saldoAnterior) {
                $pdo->rollBack();

                responder(
                    false,
                    'A quantidade do ajuste é maior que o saldo disponível.',
                    [],
                    422
                );
            }

            $saldoPosterior =
                $saldoAnterior -
                $quantidade;

        } else {
            $saldoPosterior =
                $saldoAnterior +
                $quantidade;
        }

        $stmtUpdateEstoque = $pdo->prepare(
            "
            UPDATE estoque_farmacia
            SET
                validade = :validade,
                quantidade_atual = :quantidade_atual,
                quantidade_minima = :quantidade_minima,
                localizacao = :localizacao,
                ativo = 1
            WHERE id = :id
            "
        );

        $stmtUpdateEstoque->execute([
            ':validade' =>
                $validade !== ''
                    ? $validade
                    : null,

            ':quantidade_atual' =>
                $saldoPosterior,

            ':quantidade_minima' =>
                $quantidadeMinima,

            ':localizacao' =>
                $localizacao !== ''
                    ? $localizacao
                    : null,

            ':id' =>
                $estoqueId
        ]);
    }

    $usuarioId =
        $_SESSION['usuario_id'] ??
        $_SESSION['id_usuario'] ??
        null;

    $stmtMov = $pdo->prepare(
        "
        INSERT INTO movimentacoes_estoque_farmacia (
            estoque_id,
            medicamento_id,
            usuario_id,
            tipo,
            quantidade,
            saldo_anterior,
            saldo_posterior,
            referencia,
            observacoes
        )
        VALUES (
            :estoque_id,
            :medicamento_id,
            :usuario_id,
            :tipo,
            :quantidade,
            :saldo_anterior,
            :saldo_posterior,
            :referencia,
            :observacoes
        )
        "
    );

    $stmtMov->execute([
        ':estoque_id' =>
            $estoqueId,

        ':medicamento_id' =>
            $medicamentoId,

        ':usuario_id' =>
            $usuarioId,

        ':tipo' =>
            $tipo,

        ':quantidade' =>
            $quantidade,

        ':saldo_anterior' =>
            $saldoAnterior,

        ':saldo_posterior' =>
            $saldoPosterior,

        ':referencia' =>
            $referencia !== ''
                ? $referencia
                : null,

        ':observacoes' =>
            $observacoes !== ''
                ? $observacoes
                : null
    ]);

    $pdo->commit();

    responder(
        true,
        'Movimentação registrada com sucesso.',
        [
            'estoque_id' =>
                $estoqueId,

            'saldo_posterior' =>
                $saldoPosterior
        ],
        201
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro no estoque da Farmácia: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível registrar a movimentação.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
