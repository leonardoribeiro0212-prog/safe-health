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

    $texto = str_replace(
        ',',
        '.',
        trim((string) $valor)
    );

    return is_numeric($texto)
        ? (float) $texto
        : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim(
        (string) ($_GET['acao'] ?? '')
    );

    if ($acao === 'estoque') {
        $q = trim(
            (string) ($_GET['q'] ?? '')
        );

        $sql = "
            SELECT
                ea.id AS estoque_id,
                ea.material_id,
                ea.lote,
                ea.validade,
                ea.quantidade_atual,
                ea.quantidade_minima,
                ea.localizacao,

                mh.nome AS material_nome,
                mh.codigo,
                mh.categoria,
                mh.unidade_medida

            FROM estoque_almoxarifado ea

            INNER JOIN materiais_hospitalares mh
                ON mh.id = ea.material_id

            WHERE ea.ativo = 1
        ";

        $params = [];

        if ($q !== '') {
            $sql .= "
                AND (
                    mh.nome LIKE :q
                    OR mh.codigo LIKE :q
                    OR mh.categoria LIKE :q
                    OR ea.lote LIKE :q
                    OR ea.localizacao LIKE :q
                )
            ";

            $params[':q'] =
                '%' . $q . '%';
        }

        $sql .= "
            ORDER BY
                mh.nome,
                ea.lote
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

    if ($acao === 'historico') {
        $stmt = $pdo->query(
            "
            SELECT
                i.id,
                i.status,
                i.observacoes,
                i.criado_em,
                i.finalizado_em,

                u.nome AS usuario_nome,

                COUNT(ii.id) AS total_itens,

                SUM(
                    CASE
                        WHEN ABS(ii.diferenca) > 0.000001
                        THEN 1
                        ELSE 0
                    END
                ) AS itens_divergentes

            FROM inventarios i

            LEFT JOIN inventario_itens ii
                ON ii.inventario_id = i.id

            LEFT JOIN usuarios u
                ON u.id = i.usuario_id

            GROUP BY
                i.id,
                i.status,
                i.observacoes,
                i.criado_em,
                i.finalizado_em,
                u.nome

            ORDER BY
                i.criado_em DESC,
                i.id DESC

            LIMIT 50
            "
        );

        responder(
            true,
            'Histórico carregado.',
            [
                'inventarios' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'detalhes') {
        $id = filter_var(
            $_GET['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id) {
            responder(
                false,
                'Inventário inválido.',
                [],
                422
            );
        }

        $stmt = $pdo->prepare(
            "
            SELECT
                i.id,
                i.status,
                i.observacoes,
                i.criado_em,
                i.finalizado_em,
                u.nome AS usuario_nome

            FROM inventarios i

            LEFT JOIN usuarios u
                ON u.id = i.usuario_id

            WHERE i.id = :id
            LIMIT 1
            "
        );

        $stmt->execute([
            ':id' => $id
        ]);

        $inventario =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inventario) {
            responder(
                false,
                'Inventário não encontrado.',
                [],
                404
            );
        }

        $stmtItens = $pdo->prepare(
            "
            SELECT
                ii.id,
                ii.estoque_id,
                ii.material_id,
                ii.lote,
                ii.quantidade_sistema,
                ii.quantidade_contada,
                ii.diferenca,
                ii.justificativa,

                mh.nome AS material_nome,
                mh.codigo,
                mh.unidade_medida

            FROM inventario_itens ii

            INNER JOIN materiais_hospitalares mh
                ON mh.id = ii.material_id

            WHERE ii.inventario_id = :inventario_id

            ORDER BY
                mh.nome,
                ii.lote
            "
        );

        $stmtItens->execute([
            ':inventario_id' => $id
        ]);

        responder(
            true,
            'Inventário carregado.',
            [
                'inventario' => $inventario,
                'itens' =>
                    $stmtItens->fetchAll(PDO::FETCH_ASSOC)
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

$observacoes =
    trim((string) ($entrada['observacoes'] ?? ''));

$itens =
    $entrada['itens'] ?? [];

if (
    !is_array($itens) ||
    count($itens) === 0
) {
    responder(
        false,
        'O inventário precisa ter pelo menos um item.',
        [],
        422
    );
}

$itensValidados = [];

foreach ($itens as $item) {
    if (!is_array($item)) {
        continue;
    }

    $estoqueId =
        (int) ($item['estoque_id'] ?? 0);

    $quantidadeContada =
        decimalOuNull(
            $item['quantidade_contada'] ?? null
        );

    $justificativa =
        trim((string) ($item['justificativa'] ?? ''));

    if ($estoqueId <= 0) {
        responder(
            false,
            'Existe um item de estoque inválido.',
            [],
            422
        );
    }

    if (
        $quantidadeContada === null ||
        $quantidadeContada < 0
    ) {
        responder(
            false,
            'Informe uma quantidade contada válida para todos os itens.',
            [],
            422
        );
    }

    $itensValidados[] = [
        'estoque_id' =>
            $estoqueId,

        'quantidade_contada' =>
            round(
                $quantidadeContada,
                3
            ),

        'justificativa' =>
            $justificativa !== ''
                ? $justificativa
                : null
    ];
}

try {
    $pdo->beginTransaction();

    $usuarioId =
        $_SESSION['usuario_id'] ??
        $_SESSION['id_usuario'] ??
        null;

    $stmtInventario = $pdo->prepare(
        "
        INSERT INTO inventarios (
            usuario_id,
            status,
            observacoes,
            finalizado_em
        )
        VALUES (
            :usuario_id,
            'FINALIZADO',
            :observacoes,
            NOW()
        )
        "
    );

    $stmtInventario->execute([
        ':usuario_id' =>
            $usuarioId,

        ':observacoes' =>
            $observacoes !== ''
                ? $observacoes
                : null
    ]);

    $inventarioId =
        (int) $pdo->lastInsertId();

    $stmtEstoque = $pdo->prepare(
        "
        SELECT
            id,
            material_id,
            lote,
            quantidade_atual

        FROM estoque_almoxarifado

        WHERE id = :id

        LIMIT 1
        FOR UPDATE
        "
    );

    $stmtInsertItem = $pdo->prepare(
        "
        INSERT INTO inventario_itens (
            inventario_id,
            estoque_id,
            material_id,
            lote,
            quantidade_sistema,
            quantidade_contada,
            diferenca,
            justificativa
        )
        VALUES (
            :inventario_id,
            :estoque_id,
            :material_id,
            :lote,
            :quantidade_sistema,
            :quantidade_contada,
            :diferenca,
            :justificativa
        )
        "
    );

    $stmtUpdateEstoque = $pdo->prepare(
        "
        UPDATE estoque_almoxarifado
        SET quantidade_atual = :quantidade_atual
        WHERE id = :id
        "
    );

    foreach ($itensValidados as $item) {
        $stmtEstoque->execute([
            ':id' =>
                $item['estoque_id']
        ]);

        $estoque =
            $stmtEstoque->fetch(PDO::FETCH_ASSOC);

        if (!$estoque) {
            $pdo->rollBack();

            responder(
                false,
                'Um dos lotes do inventário não existe mais.',
                [],
                422
            );
        }

        $quantidadeSistema =
            (float) $estoque['quantidade_atual'];

        $quantidadeContada =
            (float) $item['quantidade_contada'];

        $diferenca =
            $quantidadeContada -
            $quantidadeSistema;

        if (
            abs($diferenca) > 0.000001 &&
            $item['justificativa'] === null
        ) {
            $pdo->rollBack();

            responder(
                false,
                'Informe a justificativa para todos os itens com divergência.',
                [],
                422
            );
        }

        $stmtInsertItem->execute([
            ':inventario_id' =>
                $inventarioId,

            ':estoque_id' =>
                $estoque['id'],

            ':material_id' =>
                $estoque['material_id'],

            ':lote' =>
                $estoque['lote'],

            ':quantidade_sistema' =>
                $quantidadeSistema,

            ':quantidade_contada' =>
                $quantidadeContada,

            ':diferenca' =>
                $diferenca,

            ':justificativa' =>
                $item['justificativa']
        ]);

        if (abs($diferenca) > 0.000001) {
            $stmtUpdateEstoque->execute([
                ':quantidade_atual' =>
                    $quantidadeContada,

                ':id' =>
                    $estoque['id']
            ]);
        }
    }

    $pdo->commit();

    responder(
        true,
        'Inventário finalizado e estoque ajustado com sucesso.',
        [
            'inventario_id' =>
                $inventarioId
        ],
        201
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro no inventário: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível finalizar o inventário.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
