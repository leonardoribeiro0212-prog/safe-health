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

    if ($acao === 'listar') {
        $q = trim((string) ($_GET['q'] ?? ''));

        $sql = "
            SELECT
                c.id,
                c.fornecedor,
                c.numero_documento,
                c.status,
                c.prioridade,
                c.criado_em,
                c.atualizado_em,
                COUNT(ci.id) AS total_itens,
                COALESCE(SUM(ci.quantidade * ci.valor_unitario), 0) AS valor_total
            FROM compras c
            LEFT JOIN compra_itens ci
                ON ci.compra_id = c.id
            WHERE 1 = 1
        ";

        $params = [];

        if ($q !== '') {
            $numero = preg_replace('/\D/', '', $q) ?? '';

            $sql .= "
                AND (
                    CAST(c.id AS CHAR) = :id
                    OR c.fornecedor LIKE :q
                    OR c.numero_documento LIKE :q
                    OR c.status LIKE :q
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
                c.criado_em,
                c.atualizado_em

            ORDER BY
                c.criado_em DESC,
                c.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        responder(true, 'Compras carregadas.', [
            'compras' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($acao === 'detalhes') {
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
                observacoes,
                criado_em,
                atualizado_em
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
                ci.id,
                ci.material_id,
                ci.quantidade,
                ci.valor_unitario,
                ci.observacoes,
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
$status = strtoupper(trim((string) ($entrada['status'] ?? 'RASCUNHO')));
$prioridade = strtoupper(trim((string) ($entrada['prioridade'] ?? 'ROTINA')));
$observacoes = trim((string) ($entrada['observacoes'] ?? ''));
$itens = $entrada['itens'] ?? [];

if (!in_array(
    $status,
    ['RASCUNHO', 'SOLICITADA', 'APROVADA', 'EM_COMPRA', 'RECEBIDA', 'CANCELADA'],
    true
)) {
    responder(false, 'Status inválido.', [], 422);
}

if (!in_array(
    $prioridade,
    ['ROTINA', 'URGENTE'],
    true
)) {
    responder(false, 'Prioridade inválida.', [], 422);
}

if (!is_array($itens) || count($itens) === 0) {
    responder(false, 'Adicione pelo menos um item à compra.', [], 422);
}

$itensValidados = [];

foreach ($itens as $item) {
    if (!is_array($item)) {
        continue;
    }

    $materialId = (int) ($item['material_id'] ?? 0);
    $quantidade = str_replace(',', '.', trim((string) ($item['quantidade'] ?? '0')));
    $valorUnitario = str_replace(',', '.', trim((string) ($item['valor_unitario'] ?? '0')));
    $obsItem = trim((string) ($item['observacoes'] ?? ''));

    if ($materialId <= 0) {
        responder(false, 'Selecione o material de todos os itens.', [], 422);
    }

    if (!is_numeric($quantidade) || (float) $quantidade <= 0) {
        responder(false, 'Informe uma quantidade válida para todos os itens.', [], 422);
    }

    if (!is_numeric($valorUnitario) || (float) $valorUnitario < 0) {
        responder(false, 'Informe um valor unitário válido.', [], 422);
    }

    $itensValidados[] = [
        'material_id' => $materialId,
        'quantidade' => round((float) $quantidade, 3),
        'valor_unitario' => round((float) $valorUnitario, 2),
        'observacoes' => $obsItem !== '' ? $obsItem : null
    ];
}

try {
    $pdo->beginTransaction();

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

    if ($compraId > 0) {
        $stmtCompra = $pdo->prepare("
            SELECT id
            FROM compras
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");

        $stmtCompra->execute([
            ':id' => $compraId
        ]);

        if (!$stmtCompra->fetchColumn()) {
            $pdo->rollBack();
            responder(false, 'Compra não encontrada.', [], 404);
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE compras
            SET
                fornecedor = :fornecedor,
                numero_documento = :numero_documento,
                status = :status,
                prioridade = :prioridade,
                observacoes = :observacoes
            WHERE id = :id
        ");

        $stmtUpdate->execute([
            ':fornecedor' => $fornecedor !== '' ? $fornecedor : null,
            ':numero_documento' => $numeroDocumento !== '' ? $numeroDocumento : null,
            ':status' => $status,
            ':prioridade' => $prioridade,
            ':observacoes' => $observacoes !== '' ? $observacoes : null,
            ':id' => $compraId
        ]);

        $stmtDelete = $pdo->prepare("
            DELETE FROM compra_itens
            WHERE compra_id = :compra_id
        ");

        $stmtDelete->execute([
            ':compra_id' => $compraId
        ]);

    } else {
        $stmtInsert = $pdo->prepare("
            INSERT INTO compras (
                usuario_solicitante_id,
                fornecedor,
                numero_documento,
                status,
                prioridade,
                observacoes
            )
            VALUES (
                :usuario_solicitante_id,
                :fornecedor,
                :numero_documento,
                :status,
                :prioridade,
                :observacoes
            )
        ");

        $stmtInsert->execute([
            ':usuario_solicitante_id' => $usuarioId,
            ':fornecedor' => $fornecedor !== '' ? $fornecedor : null,
            ':numero_documento' => $numeroDocumento !== '' ? $numeroDocumento : null,
            ':status' => $status,
            ':prioridade' => $prioridade,
            ':observacoes' => $observacoes !== '' ? $observacoes : null
        ]);

        $compraId = (int) $pdo->lastInsertId();
    }

    $stmtItem = $pdo->prepare("
        INSERT INTO compra_itens (
            compra_id,
            material_id,
            quantidade,
            valor_unitario,
            observacoes
        )
        VALUES (
            :compra_id,
            :material_id,
            :quantidade,
            :valor_unitario,
            :observacoes
        )
    ");

    foreach ($itensValidados as $item) {
        $stmtItem->execute([
            ':compra_id' => $compraId,
            ':material_id' => $item['material_id'],
            ':quantidade' => $item['quantidade'],
            ':valor_unitario' => $item['valor_unitario'],
            ':observacoes' => $item['observacoes']
        ]);
    }

    $pdo->commit();

    responder(true, 'Compra salva com sucesso.', [
        'compra_id' => $compraId
    ], 201);

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro em compras: ' . $erro->getMessage());

    responder(false, 'Não foi possível salvar a compra.', [
        'erro' => $erro->getMessage()
    ], 500);
}
