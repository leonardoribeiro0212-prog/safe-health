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
    responderJson(false, 'Método não permitido.', [], 405);
}

$conteudo = file_get_contents('php://input');
$entrada = json_decode($conteudo ?: '', true);

if (!is_array($entrada)) {
    responderJson(false, 'Os dados enviados são inválidos.', [], 400);
}

$senhaId = filter_var(
    $entrada['senha_id'] ?? null,
    FILTER_VALIDATE_INT
);

$localChamada = trim(
    (string) ($entrada['local_chamada'] ?? 'Sala de triagem')
);

if (
    $senhaId === false ||
    $senhaId === null ||
    $senhaId <= 0
) {
    responderJson(false, 'Senha inválida.', [], 422);
}

if ($localChamada === '') {
    $localChamada = 'Sala de triagem';
}

if (mb_strlen($localChamada) > 100) {
    responderJson(false, 'O local da chamada é muito longo.', [], 422);
}

try {
    $pdo->beginTransaction();

    $stmtSenha = $pdo->prepare(
        "
        SELECT
            sa.id,
            sa.numero,
            sa.status,
            f.nome AS fila,
            f.tipo
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
        responderJson(false, 'Senha não encontrada.', [], 404);
    }

    if (strtoupper((string) $senha['tipo']) !== 'URGENCIA') {
        $pdo->rollBack();

        responderJson(
            false,
            'Apenas senhas de urgência passam pela triagem.',
            [],
            422
        );
    }

    if ((string) $senha['status'] !== 'AGUARDANDO_TRIAGEM') {
        $pdo->rollBack();

        responderJson(
            false,
            'Esta senha não está aguardando triagem.',
            [
                'status_atual' => $senha['status']
            ],
            409
        );
    }

    $stmtAtualizar = $pdo->prepare(
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

    $stmtAtualizar->execute([
        ':senha_id' => $senhaId,
        ':local_chamada' => $localChamada
    ]);

    if ($stmtAtualizar->rowCount() !== 1) {
        throw new RuntimeException(
            'Não foi possível atualizar a chamada da triagem.'
        );
    }

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
        ':local_chamada' => $localChamada
    ]);

    $chamadaId = (int) $pdo->lastInsertId();

    $pdo->commit();

    responderJson(
        true,
        'Senha chamada para a triagem.',
        [
            'chamada_id' => $chamadaId,
            'senha_id' => (int) $senha['id'],
            'numero' => (string) $senha['numero'],
            'fila' => (string) $senha['fila'],
            'local_chamada' => $localChamada,
            'etapa' => 'TRIAGEM'
        ]
    );
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro ao chamar senha na triagem: ' .
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
