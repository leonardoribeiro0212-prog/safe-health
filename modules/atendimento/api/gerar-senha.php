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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(
        false,
        'Método não permitido.',
        [],
        405
    );
}

$conteudo = file_get_contents('php://input');

$dadosRecebidos = json_decode(
    $conteudo ?: '',
    true
);

if (!is_array($dadosRecebidos)) {
    responderJson(
        false,
        'Os dados enviados são inválidos.',
        [],
        400
    );
}

$codigoFila = strtoupper(
    trim(
        (string) ($dadosRecebidos['fila'] ?? '')
    )
);

$filasPermitidas = [
    'PS_ADULTO'   => 'URGENCIA',
    'PS_INFANTIL'  => 'URGENCIA',
    'PS_ORTOPEDIA' => 'URGENCIA',

    'AGENDAMENTO' => 'AMBULATORIO',
    'CONSULTA'    => 'AMBULATORIO',
    'EXAMES'      => 'AMBULATORIO'
];

if (!array_key_exists($codigoFila, $filasPermitidas)) {
    responderJson(
        false,
        'A fila selecionada é inválida.',
        [],
        422
    );
}

$tipoFila = $filasPermitidas[$codigoFila];

try {
    $pdo->beginTransaction();

    /*
     * Bloqueia a fila enquanto a senha é gerada.
     */
    $stmtFila = $pdo->prepare(
        "
        SELECT
            id,
            nome,
            codigo,
            tipo,
            prefixo
        FROM filas
        WHERE codigo = :codigo
          AND tipo = :tipo
          AND ativa = 1
        LIMIT 1
        FOR UPDATE
        "
    );

    $stmtFila->execute([
        ':codigo' => $codigoFila,
        ':tipo'   => $tipoFila
    ]);

    $fila = $stmtFila->fetch(PDO::FETCH_ASSOC);

    if (!$fila) {
        $pdo->rollBack();

        responderJson(
            false,
            'Fila não encontrada ou desativada.',
            [],
            404
        );
    }

    $prefixo = strtoupper(
        trim((string) ($fila['prefixo'] ?? ''))
    );

    if ($prefixo === '') {
        $pdo->rollBack();

        responderJson(
            false,
            'A fila selecionada não possui prefixo configurado.',
            [],
            500
        );
    }

    /*
     * Busca o maior sequencial já utilizado para esta fila
     * e incrementa +1 para a próxima senha.
     */
    $stmtSequencial = $pdo->prepare(
        "
        SELECT COALESCE(
            MAX(sequencial),
            0
        )
        FROM senhas_atendimento
        WHERE fila_id = :fila_id
        "
    );

    $stmtSequencial->execute([
        ':fila_id' => (int) $fila['id']
    ]);

    $ultimoSequencial = (int) $stmtSequencial->fetchColumn();
    $sequencial = $ultimoSequencial + 1; // CORREÇÃO: Incremento do sequencial

    $numeroSenha = $prefixo . str_pad((string) $sequencial, 3, '0', STR_PAD_LEFT);
    $dataAtual = date('Y-m-d');

    /*
     * Apenas atendimentos de urgência passam pela triagem.
     */
    $statusInicial = strtoupper($fila['tipo']) === 'URGENCIA'
        ? 'AGUARDANDO_TRIAGEM'
        : 'AGUARDANDO_RECEPCAO';

    $stmtInserir = $pdo->prepare(
        "
        INSERT INTO senhas_atendimento (
            fila_id,
            numero,
            sequencial,
            status,
            data_emissao,
            emitida_em
        )
        VALUES (
            :fila_id,
            :numero,
            :sequencial,
            :status,
            :data_emissao,
            NOW()
        )
        "
    );

    // CORREÇÃO: Executado apenas UMA vez
    $stmtInserir->execute([
        ':fila_id'      => (int) $fila['id'],
        ':numero'       => $numeroSenha,
        ':sequencial'   => $sequencial,
        ':status'       => $statusInicial,
        ':data_emissao' => $dataAtual
    ]);

    $senhaId = (int) $pdo->lastInsertId();

    $pdo->commit();

    responderJson(
        true,
        'Senha gerada com sucesso.',
        [
            'id'          => $senhaId,
            'numero'      => $numeroSenha,
            'fila'        => $fila['nome'],
            'codigo_fila' => $fila['codigo'],
            'tipo_fila'   => $fila['tipo'],
            'status'      => $statusInicial,
            'sequencial'  => $sequencial,
            'emitida_em'  => date('d/m/Y H:i:s')
        ],
        201
    );
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responderJson(
        false,
        'Não foi possível gerar a senha.',
        [
            'erro'    => $erro->getMessage(),
            'arquivo' => basename($erro->getFile()),
            'linha'   => $erro->getLine()
        ],
        500
    );
}