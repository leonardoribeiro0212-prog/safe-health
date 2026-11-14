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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responder(
        false,
        'Método não permitido.',
        [],
        405
    );
}

$acao = trim(
    (string) ($_GET['acao'] ?? '')
);

if ($acao === 'listar') {
    $q = trim(
        (string) ($_GET['q'] ?? '')
    );

    $sql = "
        SELECT
            ea.id,
            ea.material_id,
            ea.lote,
            ea.validade,
            ea.quantidade_atual,
            ea.quantidade_minima,
            ea.localizacao,
            ea.ativo,

            mh.nome AS material_nome,
            mh.codigo,
            mh.categoria,
            mh.unidade_medida,

            CASE
                WHEN ea.quantidade_atual <= 0
                    THEN 'SEM_SALDO'

                WHEN ea.quantidade_atual <= ea.quantidade_minima
                    THEN 'ESTOQUE_BAIXO'

                WHEN ea.validade IS NOT NULL
                     AND ea.validade < CURDATE()
                    THEN 'VENCIDO'

                WHEN ea.validade IS NOT NULL
                     AND ea.validade <= DATE_ADD(
                        CURDATE(),
                        INTERVAL 30 DAY
                     )
                    THEN 'PROXIMO_VENCIMENTO'

                ELSE 'NORMAL'
            END AS situacao

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
            ea.validade,
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

if ($acao === 'resumo') {
    $stmt = $pdo->query(
        "
        SELECT
            COUNT(*) AS itens,

            SUM(
                CASE
                    WHEN quantidade_atual > 0
                         AND quantidade_atual <= quantidade_minima
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

        FROM estoque_almoxarifado
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

responder(
    false,
    'Ação inválida.',
    [],
    400
);
