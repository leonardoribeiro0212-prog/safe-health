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
    $tipo = strtoupper(
        trim($_GET['tipo'] ?? 'URGENCIA')
    );

    if (!in_array(
        $tipo,
        ['URGENCIA', 'AMBULATORIO'],
        true
    )) {
        responderJson(
            false,
            'Tipo de atendimento inválido.',
            [],
            422
        );
    }

    $stmt = $pdo->prepare(
        "
        SELECT
            sa.id,
            sa.numero,
            sa.status,
            sa.data_emissao,
            sa.emitida_em,

            f.id AS fila_id,
            f.nome AS fila,
            f.codigo AS codigo_fila,
            f.tipo

        FROM senhas_atendimento sa

        INNER JOIN filas f
            ON f.id = sa.fila_id

        WHERE f.tipo = :tipo
          AND f.ativa = 1
          AND DATE(
                COALESCE(
                    sa.emitida_em,
                    sa.data_emissao
                )
              ) = CURDATE()

          AND sa.status IN (
              'AGUARDANDO_RECEPCAO',
              'CHAMADA_RECEPCAO'
          )

        ORDER BY
            CASE
                WHEN sa.status = 'CHAMADA_RECEPCAO'
                THEN 0
                ELSE 1
            END,

            COALESCE(
                sa.emitida_em,
                sa.data_emissao
            ) ASC,

            sa.id ASC
        "
    );

    $stmt->execute([
        ':tipo' => $tipo
    ]);

    $senhas = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    responderJson(
        true,
        'Senhas carregadas.',
        [
            'tipo' => $tipo,
            'senhas' => $senhas,
            'total' => count($senhas),
            'atualizado_em' => date('d/m/Y H:i:s')
        ]
    );
} catch (Throwable $erro) {
    error_log(
        'Erro ao listar senhas da recepção: ' .
        $erro->getMessage()
    );

    responderJson(
        false,
        'Não foi possível carregar as senhas.',
        [],
        500
    );
}