<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../config/database.php';

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(
        ['sucesso' => false, 'mensagem' => 'Sessão inválida.'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

$busca = trim((string) ($_GET['q'] ?? ''));

if (mb_strlen($busca) < 2) {
    echo json_encode(
        ['sucesso' => true, 'dados' => []],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

try {
    $codigo = strtoupper(
        preg_replace('/\s+/', '', $busca)
    );

    $stmt = $pdo->prepare("
        SELECT id, codigo, descricao
        FROM cid10
        WHERE ativo = 1
          AND (
              codigo LIKE :codigo
              OR descricao LIKE :descricao
          )
        ORDER BY
            CASE
                WHEN codigo = :codigo_exato THEN 0
                WHEN codigo LIKE :codigo_inicio THEN 1
                ELSE 2
            END,
            codigo
        LIMIT 20
    ");

    $stmt->execute([
        ':codigo' => '%' . $codigo . '%',
        ':descricao' => '%' . $busca . '%',
        ':codigo_exato' => $codigo,
        ':codigo_inicio' => $codigo . '%'
    ]);

    echo json_encode(
        [
            'sucesso' => true,
            'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $erro) {
    error_log('Erro busca CID-10: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode(
        [
            'sucesso' => false,
            'mensagem' => 'Não foi possível pesquisar a CID-10.'
        ],
        JSON_UNESCAPED_UNICODE
    );
}
