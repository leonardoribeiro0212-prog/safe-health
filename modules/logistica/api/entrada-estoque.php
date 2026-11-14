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

function decimalOuNull(mixed $valor): ?float
{
    if ($valor === null || $valor === '') {
        return null;
    }

    $texto = str_replace(',', '.', trim((string) $valor));

    return is_numeric($texto)
        ? (float) $texto
        : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim((string) ($_GET['acao'] ?? ''));

    if ($acao === 'materiais') {
        $stmt = $pdo->query("
            SELECT
                id,
                nome,
                codigo,
                categoria,
                unidade_medida
            FROM materiais_hospitalares
            WHERE ativo = 1
            ORDER BY nome
        ");

        responder(true, 'Materiais carregados.', [
            'materiais' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($acao === 'compras') {
        $q = trim((string) ($_GET['q'] ?? ''));

        $sql = "
            SELECT
                c.id,
                c.fornecedor,
                c.numero_documento,
                c.status,
                c.prioridade,
                c.criado_em,
                COUNT(ci.id) AS total_itens
            FROM compras c
            LEFT JOIN compra_itens ci
                ON ci.compra_id = c.id
            WHERE
                c.status IN (
                    'SOLICITADA',
                    'APROVADA',
                    'EM_COMPRA',
                    'RECEBIDA'
                )
        ";

        $params = [];

        if ($q !== '') {
            $numero = preg_replace('/\D/', '', $q) ?? '';

            $sql .= "
                AND (
                    CAST(c.id AS CHAR) = :id
                    OR c.fornecedor LIKE :q
                    OR c.numero_documento LIKE :q
                )
            ";

            $params[':id'] = $numero;
            $params[':q'] = '%' . $q . '%';
        }

        $sql .= "
            GROUP BY
                c.id,
                c.fornecedor,
                c.numero_documento,
                c.status,
                c.prioridade,
                c.criado_em
            ORDER BY c.id DESC
            LIMIT 30
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        responder(true, 'Compras carregadas.', [
            'compras' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($acao === 'compra_detalhes') {
        $id = filter_var(
            $_GET['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id) {
            responder(false, 'Compra inválida.', [], 422);
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                fornecedor,
                numero_documento,
                status,
                prioridade,
                observacoes
            FROM compras
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id
        ]);

        $compra = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$compra) {
            responder(false, 'Compra não encontrada.', [], 404);
        }

        $stmtItens = $pdo->prepare("
            SELECT
                ci.material_id,
                ci.quantidade,
                mh.nome AS material_nome,
                mh.codigo,
                mh.unidade_medida
            FROM compra_itens ci
            INNER JOIN materiais_hospitalares mh
                ON mh.id = ci.material_id
            WHERE ci.compra_id = :compra_id
            ORDER BY ci.id
        ");

        $stmtItens->execute([
            ':compra_id' => $id
        ]);

        responder(true, 'Compra carregada.', [
            'compra' => $compra,
            'itens' => $stmtItens->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($acao === 'historico') {
        $stmt = $pdo->query("
            SELECT
                ee.id,
                ee.compra_id,
                ee.fornecedor,
                ee.numero_documento,
                ee.criado_em,
                u.nome AS usuario_nome,
                COUNT(eei.id) AS total_itens,
                COALESCE(SUM(eei.quantidade), 0) AS total_quantidade
            FROM entradas_estoque ee
            LEFT JOIN entrada_estoque_itens eei
                ON eei.entrada_id = ee.id
            LEFT JOIN usuarios u
                ON u.id = ee.usuario_id
            GROUP BY
                ee.id,
                ee.compra_id,
                ee.fornecedor,
                ee.numero_documento,
                ee.criado_em,
                u.nome
            ORDER BY
                ee.criado_em DESC,
                ee.id DESC
            LIMIT 50
        ");

        responder(true, 'Histórico carregado.', [
            'entradas' => $stmt->fetchAll(PDO::FETCH_ASSOC)
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

$compraId = (int) ($entrada['compra_id'] ?? 0);
$fornecedor = trim((string) ($entrada['fornecedor'] ?? ''));
$numeroDocumento = trim((string) ($entrada['numero_documento'] ?? ''));
$observacoes = trim((string) ($entrada['observacoes'] ?? ''));
$itens = $entrada['itens'] ?? [];

if (!is_array($itens) || count($itens) === 0) {
    responder(false, 'Adicione pelo menos um material à entrada.', [], 422);
}

$itensValidados = [];

foreach ($itens as $item) {
    if (!is_array($item)) {
        continue;
    }

    $materialId = (int) ($item['material_id'] ?? 0);
    $lote = trim((string) ($item['lote'] ?? ''));
    $validade = trim((string) ($item['validade'] ?? ''));
    $quantidade = decimalOuNull($item['quantidade'] ?? null);
    $quantidadeMinima = decimalOuNull($item['quantidade_minima'] ?? 0);
    $localizacao = trim((string) ($item['localizacao'] ?? ''));

    if ($materialId <= 0) {
        responder(false, 'Selecione o material de todos os itens.', [], 422);
    }

    if ($lote === '') {
        responder(false, 'Informe o lote de todos os itens.', [], 422);
    }

    if (
        $validade !== '' &&
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validade)
    ) {
        responder(false, 'Existe uma validade inválida.', [], 422);
    }

    if ($quantidade === null || $quantidade <= 0) {
        responder(false, 'Informe uma quantidade válida para todos os itens.', [], 422);
    }

    if ($quantidadeMinima === null || $quantidadeMinima < 0) {
        responder(false, 'Existe um estoque mínimo inválido.', [], 422);
    }

    $itensValidados[] = [
        'material_id' => $materialId,
        'lote' => $lote,
        'validade' => $validade !== '' ? $validade : null,
        'quantidade' => round($quantidade, 3),
        'quantidade_minima' => round($quantidadeMinima, 3),
        'localizacao' => $localizacao !== '' ? $localizacao : null
    ];
}

try {
    $pdo->beginTransaction();

    if ($compraId > 0) {
        $stmtCompra = $pdo->prepare("
            SELECT id, status
            FROM compras
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");

        $stmtCompra->execute([
            ':id' => $compraId
        ]);

        $compra = $stmtCompra->fetch(PDO::FETCH_ASSOC);

        if (!$compra) {
            $pdo->rollBack();
            responder(false, 'Compra não encontrada.', [], 404);
        }

        if ($compra['status'] === 'CANCELADA') {
            $pdo->rollBack();
            responder(false, 'Não é possível receber uma compra cancelada.', [], 422);
        }
    }

    foreach ($itensValidados as $item) {
        $stmtMaterial = $pdo->prepare("
            SELECT id
            FROM materiais_hospitalares
            WHERE id = :id
              AND ativo = 1
            LIMIT 1
        ");

        $stmtMaterial->execute([
            ':id' => $item['material_id']
        ]);

        if (!$stmtMaterial->fetchColumn()) {
            $pdo->rollBack();
            responder(false, 'Um dos materiais é inválido ou está inativo.', [], 422);
        }
    }

    $usuarioId =
        $_SESSION['usuario_id'] ??
        $_SESSION['id_usuario'] ??
        null;

    $stmtEntrada = $pdo->prepare("
        INSERT INTO entradas_estoque (
            compra_id,
            usuario_id,
            fornecedor,
            numero_documento,
            observacoes
        )
        VALUES (
            :compra_id,
            :usuario_id,
            :fornecedor,
            :numero_documento,
            :observacoes
        )
    ");

    $stmtEntrada->execute([
        ':compra_id' => $compraId > 0 ? $compraId : null,
        ':usuario_id' => $usuarioId,
        ':fornecedor' => $fornecedor !== '' ? $fornecedor : null,
        ':numero_documento' => $numeroDocumento !== '' ? $numeroDocumento : null,
        ':observacoes' => $observacoes !== '' ? $observacoes : null
    ]);

    $entradaId = (int) $pdo->lastInsertId();

    $stmtEntradaItem = $pdo->prepare("
        INSERT INTO entrada_estoque_itens (
            entrada_id,
            material_id,
            lote,
            validade,
            quantidade,
            quantidade_minima,
            localizacao
        )
        VALUES (
            :entrada_id,
            :material_id,
            :lote,
            :validade,
            :quantidade,
            :quantidade_minima,
            :localizacao
        )
    ");

    foreach ($itensValidados as $item) {
        $stmtEntradaItem->execute([
            ':entrada_id' => $entradaId,
            ':material_id' => $item['material_id'],
            ':lote' => $item['lote'],
            ':validade' => $item['validade'],
            ':quantidade' => $item['quantidade'],
            ':quantidade_minima' => $item['quantidade_minima'],
            ':localizacao' => $item['localizacao']
        ]);

        $stmtEstoque = $pdo->prepare("
            SELECT
                id,
                quantidade_atual
            FROM estoque_almoxarifado
            WHERE
                material_id = :material_id
                AND lote = :lote
            LIMIT 1
            FOR UPDATE
        ");

        $stmtEstoque->execute([
            ':material_id' => $item['material_id'],
            ':lote' => $item['lote']
        ]);

        $estoque = $stmtEstoque->fetch(PDO::FETCH_ASSOC);

        if ($estoque) {
            $novoSaldo =
                (float) $estoque['quantidade_atual'] +
                (float) $item['quantidade'];

            $stmtUpdate = $pdo->prepare("
                UPDATE estoque_almoxarifado
                SET
                    validade = :validade,
                    quantidade_atual = :quantidade_atual,
                    quantidade_minima = :quantidade_minima,
                    localizacao = :localizacao,
                    ativo = 1
                WHERE id = :id
            ");

            $stmtUpdate->execute([
                ':validade' => $item['validade'],
                ':quantidade_atual' => $novoSaldo,
                ':quantidade_minima' => $item['quantidade_minima'],
                ':localizacao' => $item['localizacao'],
                ':id' => $estoque['id']
            ]);

        } else {
            $stmtInsertEstoque = $pdo->prepare("
                INSERT INTO estoque_almoxarifado (
                    material_id,
                    lote,
                    validade,
                    quantidade_atual,
                    quantidade_minima,
                    localizacao,
                    ativo
                )
                VALUES (
                    :material_id,
                    :lote,
                    :validade,
                    :quantidade_atual,
                    :quantidade_minima,
                    :localizacao,
                    1
                )
            ");

            $stmtInsertEstoque->execute([
                ':material_id' => $item['material_id'],
                ':lote' => $item['lote'],
                ':validade' => $item['validade'],
                ':quantidade_atual' => $item['quantidade'],
                ':quantidade_minima' => $item['quantidade_minima'],
                ':localizacao' => $item['localizacao']
            ]);
        }
    }

    if ($compraId > 0) {
        $stmtUpdateCompra = $pdo->prepare("
            UPDATE compras
            SET status = 'RECEBIDA'
            WHERE id = :id
        ");

        $stmtUpdateCompra->execute([
            ':id' => $compraId
        ]);
    }

    $pdo->commit();

    responder(true, 'Entrada registrada e Almoxarifado atualizado com sucesso.', [
        'entrada_id' => $entradaId
    ], 201);

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro na entrada de estoque: ' . $erro->getMessage());

    responder(false, 'Não foi possível registrar a entrada de estoque.', [
        'erro' => $erro->getMessage()
    ], 500);
}
