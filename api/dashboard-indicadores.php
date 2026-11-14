<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

function responder(
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
    responder(
        false,
        'Método não permitido.',
        [],
        405
    );
}

try {
    $sql = "
        SELECT
            (
                SELECT COUNT(*)
                FROM senhas_atendimento sa
                INNER JOIN filas f ON f.id = sa.fila_id
                WHERE sa.status = 'AGUARDANDO_RECEPCAO'
                  AND f.ativa = 1
                  AND f.tipo IN ('URGENCIA', 'AMBULATORIO')
                  AND DATE(COALESCE(sa.emitida_em, sa.data_emissao)) = CURDATE()
            ) AS senhas_recepcao,

            (
                SELECT COUNT(*)
                FROM atendimentos a
                WHERE a.data_alta IS NULL
                  AND a.status NOT IN ('FINALIZADO', 'CANCELADO', 'ALTA')
            ) AS atendimentos_abertos,

            (
                SELECT COUNT(*)
                FROM senhas_atendimento s
                INNER JOIN filas f ON f.id = s.fila_id
                WHERE s.status = 'AGUARDANDO_TRIAGEM'
                  AND f.tipo = 'URGENCIA'
                  AND f.ativa = 1
                  AND DATE(COALESCE(s.emitida_em, s.data_emissao)) = CURDATE()
            ) AS aguardando_triagem,

            (
                SELECT COUNT(*)
                FROM atendimentos a
                WHERE a.data_alta IS NULL
                  AND a.status NOT IN ('FINALIZADO', 'CANCELADO', 'ALTA')
                  AND a.tipo_atendimento = 'INTERNACAO'
            ) AS internados
    ";

    $consulta = $pdo->query($sql);
    $indicadores = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$indicadores) {
        throw new RuntimeException(
            'Os indicadores não foram retornados.'
        );
    }

    responder(
        true,
        'Indicadores atualizados.',
        [
            'senhas_recepcao' =>
                (int) $indicadores['senhas_recepcao'],

            'atendimentos_abertos' =>
                (int) $indicadores['atendimentos_abertos'],

            'aguardando_triagem' =>
                (int) $indicadores['aguardando_triagem'],

            'internados' =>
                (int) $indicadores['internados'],

            'atualizado_em' =>
                date('d/m/Y H:i:s')
        ]
    );
} catch (Throwable $erro) {
    error_log(
        'Erro ao carregar indicadores do dashboard: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível atualizar os indicadores.',
        [
            'erro' => $erro->getMessage()
        ],
        500
    );
}
