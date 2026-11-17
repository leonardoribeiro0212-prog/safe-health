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

function valorTexto(
    mixed $valor,
    int $limite
): ?string {
    if ($valor === null) {
        return null;
    }

    $texto = trim((string) $valor);

    if ($texto === '') {
        return null;
    }

    return mb_substr($texto, 0, $limite);
}

function valorInteiro(
    mixed $valor,
    int $minimo,
    int $maximo
): ?int {
    if (
        $valor === null ||
        $valor === ''
    ) {
        return null;
    }

    $numero = filter_var(
        $valor,
        FILTER_VALIDATE_INT
    );

    if (
        $numero === false ||
        $numero < $minimo ||
        $numero > $maximo
    ) {
        return null;
    }

    return $numero;
}

function valorDecimal(
    mixed $valor,
    float $minimo,
    float $maximo
): ?float {
    if (
        $valor === null ||
        $valor === ''
    ) {
        return null;
    }

    $valorNormalizado = str_replace(
        ',',
        '.',
        trim((string) $valor)
    );

    if (!is_numeric($valorNormalizado)) {
        return null;
    }

    $numero = (float) $valorNormalizado;

    if (
        $numero < $minimo ||
        $numero > $maximo
    ) {
        return null;
    }

    return $numero;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(
        false,
        'Método não permitido.',
        [],
        405
    );
}

$conteudoRecebido = file_get_contents('php://input');

$dados = json_decode(
    $conteudoRecebido ?: '',
    true
);

if (!is_array($dados)) {
    responderJson(
        false,
        'Os dados enviados são inválidos.',
        [],
        400
    );
}

$senhaId = filter_var(
    $dados['senha_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $senhaId === false ||
    $senhaId === null ||
    $senhaId <= 0
) {
    responderJson(
        false,
        'A senha selecionada é inválida.',
        [],
        422
    );
}

$queixaPrincipal = valorTexto(
    $dados['queixa_principal'] ?? null,
    1000
);

$classificacaoRisco = strtoupper(
    trim(
        (string) (
            $dados['classificacao_risco'] ?? ''
        )
    )
);

$classificacoesPermitidas = [
    'VERMELHO',
    'LARANJA',
    'AMARELO',
    'VERDE',
    'AZUL'
];

if ($queixaPrincipal === null) {
    responderJson(
        false,
        'Informe a queixa principal.',
        [],
        422
    );
}

if (!in_array(
    $classificacaoRisco,
    $classificacoesPermitidas,
    true
)) {
    responderJson(
        false,
        'Selecione uma classificação de risco válida.',
        [],
        422
    );
}

$pressaoArterial = valorTexto(
    $dados['pressao_arterial'] ?? null,
    20
);

$frequenciaCardiaca = valorInteiro(
    $dados['frequencia_cardiaca'] ?? null,
    0,
    300
);

$frequenciaRespiratoria = valorInteiro(
    $dados['frequencia_respiratoria'] ?? null,
    0,
    100
);

$temperatura = valorDecimal(
    $dados['temperatura'] ?? null,
    25,
    45
);

$saturacao = valorInteiro(
    $dados['saturacao'] ?? null,
    0,
    100
);

$peso = valorDecimal(
    $dados['peso'] ?? null,
    0,
    500
);

$escalaDor = valorInteiro(
    $dados['escala_dor'] ?? null,
    0,
    10
);

$observacoes = valorTexto(
    $dados['observacoes'] ?? null,
    2000
);

try {
    $pdo->beginTransaction();

    /*
     * FOR UPDATE bloqueia a senha durante a transação.
     * Isso evita duas pessoas finalizarem a mesma triagem.
     */
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

    $senha = $stmtSenha->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$senha) {
        $pdo->rollBack();

        responderJson(
            false,
            'A senha informada não foi encontrada.',
            [],
            404
        );
    }

    if (
        strtoupper((string) $senha['fila_tipo']) !==
        'URGENCIA'
    ) {
        $pdo->rollBack();

        responderJson(
            false,
            'Apenas atendimentos de urgência realizam triagem.',
            [
                'tipo_atendimento' => $senha['fila_tipo'],
                'fila' => $senha['fila_nome']
            ],
            422
        );
    }

    // Evita repetir a mesma validação.
    if (
        $senha['status'] !==
        'AGUARDANDO_TRIAGEM'
    ) {
        $pdo->rollBack();

        responderJson(
            false,
            'Esta senha não está mais aguardando triagem.',
            [
                'status_atual' => $senha['status']
            ],
            409
        );
    }

    $stmtVerificarTriagem = $pdo->prepare(
        "
        SELECT id
        FROM triagens
        WHERE senha_id = :senha_id
        LIMIT 1
        "
    );

    $stmtVerificarTriagem->execute([
        ':senha_id' => $senhaId
    ]);

    if ($stmtVerificarTriagem->fetch()) {
        $pdo->rollBack();

        responderJson(
            false,
            'A triagem desta senha já foi registrada.',
            [],
            409
        );
    }

    $stmtTriagem = $pdo->prepare(
        "
        INSERT INTO triagens (
            senha_id,
            queixa_principal,
            pressao_arterial,
            frequencia_cardiaca,
            frequencia_respiratoria,
            temperatura,
            saturacao,
            peso,
            escala_dor,
            classificacao_risco,
            observacoes,
            realizada_em
        )
        VALUES (
            :senha_id,
            :queixa_principal,
            :pressao_arterial,
            :frequencia_cardiaca,
            :frequencia_respiratoria,
            :temperatura,
            :saturacao,
            :peso,
            :escala_dor,
            :classificacao_risco,
            :observacoes,
            NOW()
        )
        "
    );

    $stmtTriagem->execute([
        ':senha_id' => $senhaId,
        ':queixa_principal' => $queixaPrincipal,
        ':pressao_arterial' => $pressaoArterial,
        ':frequencia_cardiaca' => $frequenciaCardiaca,
        ':frequencia_respiratoria' => $frequenciaRespiratoria,
        ':temperatura' => $temperatura,
        ':saturacao' => $saturacao,
        ':peso' => $peso,
        ':escala_dor' => $escalaDor,
        ':classificacao_risco' => $classificacaoRisco,
        ':observacoes' => $observacoes
    ]);

    $triagemId = (int) $pdo->lastInsertId();

    $stmtAtualizarSenha = $pdo->prepare(
        "
        UPDATE senhas_atendimento

        SET status = 'AGUARDANDO_RECEPCAO'

        WHERE id = :senha_id
          AND status = 'AGUARDANDO_TRIAGEM'
        "
    );

    $stmtAtualizarSenha->execute([
        ':senha_id' => $senhaId
    ]);

    if ($stmtAtualizarSenha->rowCount() !== 1) {
        throw new RuntimeException(
            'Não foi possível atualizar o status da senha.'
        );
    }

    $pdo->commit();

    responderJson(
        true,
        'Triagem finalizada. A senha foi encaminhada para a Recepção.',
        [
            'triagem_id' => $triagemId,
            'senha_id' => $senhaId,
            'numero' => $senha['numero'],
            'fila' => $senha['fila_nome'],
            'classificacao_risco' => $classificacaoRisco,
            'status_anterior' => 'AGUARDANDO_TRIAGEM',
            'status_atual' => 'AGUARDANDO_RECEPCAO'
        ]
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro ao finalizar triagem: ' .
        $erro->getMessage()
    );

    responderJson(
        false,
        'Não foi possível finalizar a triagem.',
        [],
        500
    );
}