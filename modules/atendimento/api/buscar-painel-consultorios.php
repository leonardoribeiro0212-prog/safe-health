<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

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
        JSON_UNESCAPED_UNICODE
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
    $stmtAtual = $pdo->prepare(
        "
        SELECT
            cs.id AS chamada_id,
            cs.local_chamada,

            DATE_FORMAT(
                cs.chamada_em,
                '%d/%m/%Y %H:%i:%s'
            ) AS chamada_em,

            sa.id AS senha_id,
            sa.numero,

            f.nome AS fila,
            f.codigo AS codigo_fila

        FROM chamadas_senhas cs

        INNER JOIN senhas_atendimento sa
            ON sa.id = cs.senha_id

        INNER JOIN filas f
            ON f.id = sa.fila_id

        WHERE cs.etapa = 'CONSULTORIO'
          AND f.tipo = 'URGENCIA'
          AND DATE(cs.chamada_em) = CURDATE()

        ORDER BY
            cs.chamada_em DESC,
            cs.id DESC

        LIMIT 1
        "
    );

    $stmtAtual->execute();

    $chamadaAtual = $stmtAtual->fetch(
        PDO::FETCH_ASSOC
    ) ?: null;

    $stmtHistorico = $pdo->prepare(
        "
        SELECT
            cs.id AS chamada_id,
            cs.local_chamada,

            DATE_FORMAT(
                cs.chamada_em,
                '%H:%i'
            ) AS horario,

            sa.numero,

            f.nome AS fila

        FROM chamadas_senhas cs

        INNER JOIN senhas_atendimento sa
            ON sa.id = cs.senha_id

        INNER JOIN filas f
            ON f.id = sa.fila_id

        WHERE cs.etapa = 'CONSULTORIO'
          AND f.tipo = 'URGENCIA'
          AND DATE(cs.chamada_em) = CURDATE()

        ORDER BY
            cs.chamada_em DESC,
            cs.id DESC

        LIMIT 10
        "
    );

    $stmtHistorico->execute();

    $historico = $stmtHistorico->fetchAll(
        PDO::FETCH_ASSOC
    );

    responderJson(
        true,
        'Painel atualizado.',
        [
            'chamada_atual' => $chamadaAtual,
            'historico' => $historico,
            'atualizado_em' => date('d/m/Y H:i:s')
        ]
    );
} catch (Throwable $erro) {
    error_log(
        'Erro no painel dos consultórios: ' .
        $erro->getMessage()
    );

    responderJson(
        false,
        'Não foi possível atualizar o painel.',
        [],
        500
    );
}