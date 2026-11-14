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
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(
        false,
        'Método não permitido.',
        [],
        405
    );
}

$conteudo = file_get_contents('php://input');
$entrada = json_decode($conteudo ?: '', true);

if (!is_array($entrada)) {
    responderJson(
        false,
        'Os dados enviados são inválidos.',
        [],
        400
    );
}

$senhaId = filter_var(
    $entrada['senha_id'] ?? null,
    FILTER_VALIDATE_INT
);

$sala = trim(
    (string) ($entrada['sala'] ?? '')
);

if (
    $senhaId === false ||
    $senhaId === null ||
    $senhaId <= 0
) {
    responderJson(
        false,
        'A senha informada é inválida.',
        [],
        422
    );
}

if ($sala === '') {
    responderJson(
        false,
        'Selecione a sala de triagem.',
        [],
        422
    );
}

if (mb_strlen($sala) > 100) {
    responderJson(
        false,
        'O nome da sala é muito longo.',
        [],
        422
    );
}

try {
    $pdo->beginTransaction();

    $stmtSenha = $pdo->prepare(
        "
        SELECT
            sa.id,
            sa.numero,
            sa.status,
            f.nome AS fila_nome,
            f.codigo AS fila_codigo,
            f.tipo AS fila_tipo

        FROM senhas_atendimento sa

        INNER JOIN filas f
            ON f.id = sa.fila_id

        WHERE sa.id = :senha_id

        LIMIT 1

        FOR UPDATE
        "
    );

    $stmtSenha->execute([
        ':senha_id' => $senhaId
    ]);

    $senha = $stmtSenha->fetch(PDO::FETCH_ASSOC);

    if (!$senha) {
        $pdo->rollBack();

        responderJson(
            false,
            'A senha não foi encontrada.',
            [],
            404
        );
    }

    if (
        strtoupper(trim((string) $senha['fila_tipo'])) !==
        'URGENCIA'
    ) {
        $pdo->rollBack();

        responderJson(
            false,
            'Apenas senhas de urgência podem ser chamadas para a triagem.',
            [],
            422
        );
    }

    if (
        strtoupper(trim((string) $senha['status'])) !==
        'AGUARDANDO_TRIAGEM'
    ) {
        $pdo->rollBack();

        responderJson(
            false,
            'A senha não está aguardando triagem.',
            [
                'status_atual' => $senha['status']
            ],
            409
        );
    }

    /*
     * A chamada é registrada no histórico do painel.
     * O status continua AGUARDANDO_TRIAGEM para preservar
     * a regra atual: chamar não significa finalizar a triagem.
     */
    $stmtChamada = $pdo->prepare(
        "
        INSERT INTO chamadas_senhas (
            senha_id,
            etapa,
            local_chamada,
            chamada_em,
            status
        )
        VALUES (
            :senha_id,
            'TRIAGEM',
            :local_chamada,
            NOW(),
            'CHAMADA'
        )
        "
    );

    $stmtChamada->execute([
        ':senha_id' => $senhaId,
        ':local_chamada' => $sala
    ]);

    $chamadaId = (int) $pdo->lastInsertId();

    /*
     * Estes campos mantêm a chamada atual vinculada à senha.
     * O status da fila não é alterado.
     */
    $stmtAtualizarSenha = $pdo->prepare(
        "
        UPDATE senhas_atendimento
        SET
            etapa_chamada = 'TRIAGEM',
            local_chamada = :local_chamada,
            chamada_em = NOW()
        WHERE id = :senha_id
          AND status = 'AGUARDANDO_TRIAGEM'
        "
    );

    $stmtAtualizarSenha->execute([
        ':local_chamada' => $sala,
        ':senha_id' => $senhaId
    ]);

    if ($stmtAtualizarSenha->rowCount() !== 1) {
        throw new RuntimeException(
            'Não foi possível registrar a chamada na senha.'
        );
    }

    $pdo->commit();

    responderJson(
        true,
        'Senha chamada para a triagem.',
        [
            'chamada_id' => $chamadaId,
            'senha_id' => (int) $senha['id'],
            'numero' => (string) $senha['numero'],
            'fila' => (string) $senha['fila_nome'],
            'sala' => $sala,
            'etapa' => 'TRIAGEM',
            'status_senha' => 'AGUARDANDO_TRIAGEM'
        ]
    );
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro ao chamar senha para triagem: ' .
        $erro->getMessage()
    );

    responderJson(
        false,
        'Não foi possível chamar a senha para a triagem.',
        [
            'erro' => $erro->getMessage()
        ],
        500
    );
}
