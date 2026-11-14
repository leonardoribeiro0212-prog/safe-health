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

$guiche = trim(
    (string) ($entrada['guiche'] ?? '')
);

if (
    $senhaId === false ||
    $senhaId === null ||
    $senhaId <= 0
) {
    responderJson(false, 'Senha inválida.', [], 422);
}

if ($guiche === '') {
    responderJson(false, 'Informe o guichê.', [], 422);
}

if (mb_strlen($guiche) > 100) {
    responderJson(
        false,
        'O nome do guichê é muito longo.',
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

    $statusAnterior = (string) $senha['status'];

    if (
        !in_array(
            $statusAnterior,
            [
                'AGUARDANDO_RECEPCAO',
                'CHAMADA_RECEPCAO'
            ],
            true
        )
    ) {
        $pdo->rollBack();

        responderJson(
            false,
            'Esta senha não está disponível para chamada.',
            [
                'status_atual' => $statusAnterior
            ],
            409
        );
    }

    $stmtAtualizar = $pdo->prepare(
        "
        UPDATE senhas_atendimento
        SET
            status = 'CHAMADA_RECEPCAO',
            etapa_chamada = 'RECEPCAO',
            local_chamada = :local_chamada,
            chamada_em = NOW()
        WHERE id = :senha_id
        "
    );

    $stmtAtualizar->execute([
        ':senha_id' => $senhaId,
        ':local_chamada' => $guiche
    ]);

    if ($stmtAtualizar->rowCount() !== 1) {
        throw new RuntimeException(
            'Não foi possível atualizar a senha.'
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
            'RECEPCAO',
            :local_chamada,
            NOW(),
            'CHAMADA'
        )
        "
    );

    $stmtChamada->execute([
        ':senha_id' => $senhaId,
        ':local_chamada' => $guiche
    ]);

    $chamadaId = (int) $pdo->lastInsertId();

    $pdo->commit();

    $rechamada =
        $statusAnterior === 'CHAMADA_RECEPCAO';

    responderJson(
        true,
        $rechamada
            ? 'Senha rechamada com sucesso.'
            : 'Senha chamada com sucesso.',
        [
            'chamada_id' => $chamadaId,
            'senha_id' => (int) $senha['id'],
            'numero' => (string) $senha['numero'],
            'fila' => (string) $senha['fila'],
            'tipo' => (string) $senha['tipo'],
            'guiche' => $guiche,
            'rechamada' => $rechamada,
            'status_anterior' => $statusAnterior,
            'status_atual' => 'CHAMADA_RECEPCAO'
        ]
    );
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro ao chamar senha na recepção: ' .
        $erro->getMessage()
    );

    responderJson(
        false,
        'Não foi possível chamar a senha.',
        [
            'erro' => $erro->getMessage()
        ],
        500
    );
}