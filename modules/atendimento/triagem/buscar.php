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
    $stmt = $pdo->prepare(
        "
        SELECT
            s.id,
            s.numero,
            s.status,
            s.emitida_em,
            s.data_emissao,

            f.id AS fila_id,
            f.nome AS fila_nome,
            f.codigo AS fila_codigo,
            f.tipo AS fila_tipo,

            TIMESTAMPDIFF(
                MINUTE,
                s.emitida_em,
                NOW()
            ) AS tempo_espera_minutos

        FROM senhas_atendimento s

        INNER JOIN filas f
            ON f.id = s.fila_id

        WHERE s.status = 'AGUARDANDO_TRIAGEM'
        AND f.tipo = 'URGENCIA'
        AND f.ativa = 1

        ORDER BY
            s.emitida_em ASC,
            s.id ASC
        "
    );

    $stmt->execute();

    $senhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    responderJson(
        true,
        'Senhas aguardando triagem carregadas com sucesso.',
        [
            'total' => count($senhas),
            'senhas' => $senhas
        ]
    );
} catch (Throwable $erro) {
    responderJson(
        false,
        'Não foi possível carregar as senhas da triagem.',
        [
            'erro' => $erro->getMessage(),
            'arquivo' => basename($erro->getFile()),
            'linha' => $erro->getLine()
        ],
        500
    );
}