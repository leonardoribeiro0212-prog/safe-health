<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

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

$entrada = json_decode(
    file_get_contents('php://input') ?: '',
    true
);

$senhaId = filter_var(
    $entrada['senha_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$senhaId || $senhaId <= 0) {
    responderJson(false, 'Senha inválida.', [], 422);
}

try {
    $pdo->beginTransaction();

    $stmtSenha = $pdo->prepare(
        "
        SELECT id, numero, status, fila_id
        FROM senhas_atendimento
        WHERE id = :senha_id
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

    /*
     * Nesta tela a lista contém somente AGUARDANDO_TRIAGEM.
     * Se a senha já avançou para recepção, atendimento ou outra etapa,
     * ela não pode ser apagada daqui.
     */
    if ($senha['status'] !== 'AGUARDANDO_TRIAGEM') {
        $pdo->rollBack();

        responderJson(
            false,
            'Esta senha não pode mais ser excluída porque já avançou no fluxo.',
            ['status_atual' => $senha['status']],
            409
        );
    }

    /*
     * Segurança adicional: se por qualquer motivo já existir um
     * atendimento vinculado, preservamos o histórico.
     */
    $stmtAtendimento = $pdo->prepare(
        "
        SELECT id, numero_atendimento, status
        FROM atendimentos
        WHERE senha_id = :senha_id
        LIMIT 1
        "
    );

    $stmtAtendimento->execute([
        ':senha_id' => $senhaId
    ]);

    $atendimento = $stmtAtendimento->fetch(PDO::FETCH_ASSOC);

    if ($atendimento) {
        $pdo->rollBack();

        responderJson(
            false,
            'Não é possível excluir esta senha porque já existe um atendimento vinculado.',
            [
                'atendimento_id' => (int) $atendimento['id'],
                'numero_atendimento' => $atendimento['numero_atendimento'],
                'status_atendimento' => $atendimento['status']
            ],
            409
        );
    }

    /*
     * Chamadas feitas para a sala de triagem podem existir mesmo
     * enquanto a senha continua AGUARDANDO_TRIAGEM.
     * Elas são removidas antes da senha.
     */
    $stmtChamadas = $pdo->prepare(
        "
        DELETE FROM chamadas_senhas
        WHERE senha_id = :senha_id
        "
    );

    $stmtChamadas->execute([
        ':senha_id' => $senhaId
    ]);

    $stmtExcluir = $pdo->prepare(
        "
        DELETE FROM senhas_atendimento
        WHERE id = :senha_id
          AND status = 'AGUARDANDO_TRIAGEM'
        "
    );

    $stmtExcluir->execute([
        ':senha_id' => $senhaId
    ]);

    if ($stmtExcluir->rowCount() !== 1) {
        throw new RuntimeException(
            'A senha não foi excluída.'
        );
    }

    $pdo->commit();

    responderJson(
        true,
        'Senha ' . $senha['numero'] . ' excluída com sucesso.',
        [
            'senha_id' => (int) $senha['id'],
            'numero' => $senha['numero']
        ]
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro ao excluir senha na triagem: ' .
        $erro->getMessage()
    );

    responderJson(
        false,
        'Não foi possível excluir a senha.',
        ['erro' => $erro->getMessage()],
        500
    );
}
