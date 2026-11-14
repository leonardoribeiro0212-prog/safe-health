<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';

function responderJson(
    bool $sucesso,
    string $mensagem,
    array $dados = [],
    int $statusHttp = 200
): never {
    http_response_code($statusHttp);

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
    responderJson(
        false,
        'Método não permitido.',
        [],
        405
    );
}

try {
    /*
     * RECEPÇÃO
     *
     * A recepção atende senhas de urgência e ambulatório.
     * Por isso, não existe filtro por f.tipo nesta consulta.
     */
    $stmtRecepcao = $pdo->query(
        "
        SELECT
            cs.id AS chamada_id,
            cs.etapa,
            cs.local_chamada,

            DATE_FORMAT(
                cs.chamada_em,
                '%d/%m/%Y %H:%i:%s'
            ) AS chamada_em,

            sa.id AS senha_id,
            sa.numero,

            f.nome AS fila,
            f.codigo AS codigo_fila,
            f.tipo AS tipo_fila

        FROM chamadas_senhas cs

        INNER JOIN senhas_atendimento sa
            ON sa.id = cs.senha_id

        INNER JOIN filas f
            ON f.id = sa.fila_id

        WHERE UPPER(TRIM(cs.etapa)) = 'RECEPCAO'

        ORDER BY
            cs.chamada_em DESC,
            cs.id DESC

        LIMIT 1
        "
    );

    $recepcao =
        $stmtRecepcao->fetch(PDO::FETCH_ASSOC) ?: null;

    /*
     * TRIAGEM
     *
     * A triagem continua restrita às senhas de urgência,
     * conforme a regra de negócio definida.
     */
    $stmtTriagem = $pdo->query(
        "
        SELECT
            cs.id AS chamada_id,
            cs.etapa,
            cs.local_chamada,

            DATE_FORMAT(
                cs.chamada_em,
                '%d/%m/%Y %H:%i:%s'
            ) AS chamada_em,

            sa.id AS senha_id,
            sa.numero,

            f.nome AS fila,
            f.codigo AS codigo_fila,
            f.tipo AS tipo_fila

        FROM chamadas_senhas cs

        INNER JOIN senhas_atendimento sa
            ON sa.id = cs.senha_id

        INNER JOIN filas f
            ON f.id = sa.fila_id

        WHERE UPPER(TRIM(cs.etapa)) = 'TRIAGEM'
          AND UPPER(TRIM(f.tipo)) = 'URGENCIA'

        ORDER BY
            cs.chamada_em DESC,
            cs.id DESC

        LIMIT 1
        "
    );

    $triagem =
        $stmtTriagem->fetch(PDO::FETCH_ASSOC) ?: null;

    /*
     * HISTÓRICO
     *
     * Exibe chamadas de recepção de qualquer tipo de fila
     * e chamadas de triagem apenas para urgência.
     */
    $stmtHistorico = $pdo->query(
        "
        SELECT
            cs.id AS chamada_id,
            UPPER(TRIM(cs.etapa)) AS etapa,
            cs.local_chamada,

            DATE_FORMAT(
                cs.chamada_em,
                '%H:%i'
            ) AS horario,

            sa.numero,

            f.nome AS fila,
            f.tipo AS tipo_fila

        FROM chamadas_senhas cs

        INNER JOIN senhas_atendimento sa
            ON sa.id = cs.senha_id

        INNER JOIN filas f
            ON f.id = sa.fila_id

        WHERE
            (
                UPPER(TRIM(cs.etapa)) = 'RECEPCAO'
            )
            OR
            (
                UPPER(TRIM(cs.etapa)) = 'TRIAGEM'
                AND UPPER(TRIM(f.tipo)) = 'URGENCIA'
            )

        ORDER BY
            cs.chamada_em DESC,
            cs.id DESC

        LIMIT 8
        "
    );

    $historico =
        $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);

    responderJson(
        true,
        'Painel atualizado.',
        [
            'recepcao' => $recepcao,
            'triagem' => $triagem,
            'historico' => $historico,
            'atualizado_em' => date('d/m/Y H:i:s')
        ]
    );
} catch (Throwable $erro) {
    error_log(
        'Erro ao buscar painel de recepção e triagem: ' .
        $erro->getMessage()
    );

    responderJson(
        false,
        'Não foi possível atualizar o painel.',
        [
            'erro' => $erro->getMessage()
        ],
        500
    );
}
