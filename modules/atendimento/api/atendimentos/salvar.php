<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../config/database.php';

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

$pacienteId = (int) (
    $dadosRecebidos['paciente_id'] ?? 0
);

$senhaId = (int) (
    $dadosRecebidos['senha_id'] ?? 0
);

$tipoAtendimento = strtoupper(
    trim(
        (string) (
            $dadosRecebidos['tipo_atendimento'] ?? ''
        )
    )
);

$origem = strtoupper(
    trim(
        (string) (
            $dadosRecebidos['origem'] ?? ''
        )
    )
);

$convenio = trim(
    (string) (
        $dadosRecebidos['convenio'] ?? ''
    )
);

$especialidade = trim(
    (string) (
        $dadosRecebidos['especialidade'] ?? ''
    )
);

$profissionalResponsavel = trim(
    (string) (
        $dadosRecebidos['profissional_responsavel'] ?? ''
    )
);

$tiposAtendimentoPermitidos = [
    'URGENCIA',
    'AMBULATORIO',
    'INTERNACAO',
    'EXTERNO'
];

$origensPermitidas = [
    'CLINICA_MEDICA',
    'CLINICA_CIRURGICA',
    'CENTRO_CIRURGICO',
    'CONSULTORIO',
    'SALA_DE_PROCEDIMENTO',
    'PRONTO_SOCORRO_ADULTO',
    'PRONTO_SOCORRO_INFANTIL',
    'PRONTO_SOCORRO_ORTOPEDIA',
    'AMBULATORIO',
    'UNIDADE_DE_INTERNACAO',
    'UTI',
    'EXAMES',
    'EXTERNO'
];

if ($pacienteId <= 0 || $senhaId <= 0) {
    responderJson(
        false,
        'Paciente ou senha inválidos.',
        [],
        422
    );
}

if (
    !in_array(
        $tipoAtendimento,
        $tiposAtendimentoPermitidos,
        true
    )
) {
    responderJson(
        false,
        'O tipo de atendimento informado é inválido.',
        [],
        422
    );
}

if (!in_array($origem, $origensPermitidas, true)) {
    responderJson(
        false,
        'A origem informada é inválida.',
        [],
        422
    );
}

if (
    $convenio === '' ||
    $especialidade === '' ||
    $profissionalResponsavel === ''
) {
    responderJson(
        false,
        'Preencha todos os dados obrigatórios.',
        [],
        422
    );
}

try {
    $pdo->beginTransaction();

    $stmtPaciente = $pdo->prepare(
        "
        SELECT id
        FROM pacientes
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
        "
    );

    $stmtPaciente->execute([
        ':id' => $pacienteId
    ]);

    if (!$stmtPaciente->fetchColumn()) {
        throw new RuntimeException(
            'O paciente informado não foi encontrado.'
        );
    }

    $stmtSenha = $pdo->prepare(
        "
        SELECT
            id,
            numero,
            status
        FROM senhas_atendimento
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
        "
    );

    $stmtSenha->execute([
        ':id' => $senhaId
    ]);

    $senha = $stmtSenha->fetch(PDO::FETCH_ASSOC);

    if (!$senha) {
        throw new RuntimeException(
            'A senha informada não foi encontrada.'
        );
    }

    $stmtExistente = $pdo->prepare(
        "
        SELECT
            id,
            numero_atendimento
        FROM atendimentos
        WHERE senha_id = :senha_id
        LIMIT 1
        "
    );

    $stmtExistente->execute([
        ':senha_id' => $senhaId
    ]);

    $atendimentoExistente =
        $stmtExistente->fetch(PDO::FETCH_ASSOC);

    if ($atendimentoExistente) {
        $pdo->rollBack();

        responderJson(
            false,
            'Essa senha já possui um atendimento aberto.',
            [
                'atendimento_id' =>
                    (int) $atendimentoExistente['id'],

                'numero_atendimento' =>
                    $atendimentoExistente[
                        'numero_atendimento'
                    ]
            ],
            409
        );
    }

    /*
     * Insere inicialmente um número temporário único.
     * Depois utiliza o ID para gerar AT000001,
     * AT000002 e assim por diante.
     */
    $numeroTemporario =
        'TMP-' . bin2hex(random_bytes(8));

    $stmtInserir = $pdo->prepare(
        "
        INSERT INTO atendimentos (
            numero_atendimento,
            paciente_id,
            senha_id,
            origem,
            tipo_atendimento,
            convenio,
            especialidade,
            profissional_responsavel,
            status,
            data_entrada
        )
        VALUES (
            :numero_atendimento,
            :paciente_id,
            :senha_id,
            :origem,
            :tipo_atendimento,
            :convenio,
            :especialidade,
            :profissional_responsavel,
            'AGUARDANDO_TRIAGEM',
            NOW()
        )
        "
    );

    $stmtInserir->execute([
        ':numero_atendimento' => $numeroTemporario,
        ':paciente_id' => $pacienteId,
        ':senha_id' => $senhaId,
        ':origem' => $origem,
        ':tipo_atendimento' => $tipoAtendimento,
        ':convenio' => $convenio,
        ':especialidade' => $especialidade,
        ':profissional_responsavel' =>
            $profissionalResponsavel
    ]);

    $atendimentoId = (int) $pdo->lastInsertId();

    $numeroAtendimento =
        'AT' .
        str_pad(
            (string) $atendimentoId,
            6,
            '0',
            STR_PAD_LEFT
        );

    $stmtAtualizarNumero = $pdo->prepare(
        "
        UPDATE atendimentos
        SET numero_atendimento = :numero_atendimento
        WHERE id = :id
        "
    );

    $stmtAtualizarNumero->execute([
        ':numero_atendimento' => $numeroAtendimento,
        ':id' => $atendimentoId
    ]);

    $stmtAtualizarSenha = $pdo->prepare(
        "
        UPDATE senhas_atendimento
        SET
            status = 'AGUARDANDO_TRIAGEM',
            etapa_chamada = 'TRIAGEM',
            atendida_em = NOW()
        WHERE id = :id
        "
    );

    $stmtAtualizarSenha->execute([
        ':id' => $senhaId
    ]);

    $pdo->commit();

    responderJson(
        true,
        'Atendimento criado e encaminhado para triagem.',
        [
            'id' => $atendimentoId,
            'numero_atendimento' => $numeroAtendimento,
            'senha' => $senha['numero'],
            'status' => 'AGUARDANDO_TRIAGEM'
        ],
        201
    );
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responderJson(
        false,
        'Não foi possível criar o atendimento.',
        [
            'erro' => $erro->getMessage(),
            'arquivo' => basename($erro->getFile()),
            'linha' => $erro->getLine()
        ],
        500
    );
}