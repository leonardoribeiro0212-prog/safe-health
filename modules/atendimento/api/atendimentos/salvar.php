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

$pacienteId = filter_var(
    $entrada['paciente_id'] ?? null,
    FILTER_VALIDATE_INT
);

$senhaId = filter_var(
    $entrada['senha_id'] ?? null,
    FILTER_VALIDATE_INT
);

$tipoAtendimento = strtoupper(
    trim((string) ($entrada['tipo_atendimento'] ?? ''))
);

$origem = strtoupper(
    trim((string) ($entrada['origem'] ?? ''))
);

$convenio = trim(
    (string) ($entrada['convenio'] ?? '')
);

$profissionalId = filter_var(
    $entrada['profissional_id'] ?? null,
    FILTER_VALIDATE_INT
);

$tiposPermitidos = [
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

if (
    $pacienteId === false ||
    $pacienteId === null ||
    $pacienteId <= 0 ||
    $senhaId === false ||
    $senhaId === null ||
    $senhaId <= 0
) {
    responderJson(
        false,
        'Paciente ou senha inválidos.',
        [],
        422
    );
}

if (!in_array($tipoAtendimento, $tiposPermitidos, true)) {
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
    $profissionalId === false ||
    $profissionalId === null ||
    $profissionalId <= 0
) {
    responderJson(
        false,
        'Preencha todos os dados obrigatórios.',
        [],
        422
    );
}

try {
    /*
     * O médico e a especialidade são obtidos diretamente do cadastro.
     * Assim o atendimento não aceita uma especialidade diferente
     * daquela vinculada ao profissional.
     */
    $stmtMedico = $pdo->prepare(
        "
        SELECT
            m.id,
            m.nome,
            m.crm,
            m.uf_crm,
            m.especialidade_id,
            e.nome AS especialidade_nome
        FROM medicos m
        INNER JOIN especialidades e
            ON e.id = m.especialidade_id
        WHERE m.id = :id
          AND m.ativo = 1
        LIMIT 1
        "
    );

    $stmtMedico->execute([
        ':id' => $profissionalId
    ]);

    $medico = $stmtMedico->fetch(PDO::FETCH_ASSOC);

    if (!$medico) {
        responderJson(
            false,
            'O médico selecionado não foi encontrado ou está inativo.',
            [],
            422
        );
    }

    $profissionalResponsavel =
        (string) $medico['nome'];

    $especialidade =
        strtoupper(
            strtr(
                (string) $medico['especialidade_nome'],
                [
                    'Á' => 'A',
                    'À' => 'A',
                    'Â' => 'A',
                    'Ã' => 'A',
                    'É' => 'E',
                    'Ê' => 'E',
                    'Í' => 'I',
                    'Ó' => 'O',
                    'Ô' => 'O',
                    'Õ' => 'O',
                    'Ú' => 'U',
                    'Ç' => 'C',
                    'á' => 'A',
                    'à' => 'A',
                    'â' => 'A',
                    'ã' => 'A',
                    'é' => 'E',
                    'ê' => 'E',
                    'í' => 'I',
                    'ó' => 'O',
                    'ô' => 'O',
                    'õ' => 'O',
                    'ú' => 'U',
                    'ç' => 'C'
                ]
            )
        );

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
        throw new DomainException(
            'O paciente informado não foi encontrado.'
        );
    }

    $stmtSenha = $pdo->prepare(
        "
        SELECT
            sa.id,
            sa.numero,
            sa.status,
            f.tipo AS tipo_fila
        FROM senhas_atendimento sa
        INNER JOIN filas f
            ON f.id = sa.fila_id
        WHERE sa.id = :id
        LIMIT 1
        FOR UPDATE
        "
    );

    $stmtSenha->execute([
        ':id' => $senhaId
    ]);

    $senha = $stmtSenha->fetch(PDO::FETCH_ASSOC);

    if (!$senha) {
        throw new DomainException(
            'A senha informada não foi encontrada.'
        );
    }

    if ($senha['status'] !== 'CHAMADA_RECEPCAO') {
        $pdo->rollBack();

        responderJson(
            false,
            'A senha precisa estar chamada na recepção.',
            [
                'status_atual' => $senha['status']
            ],
            409
        );
    }

    $tipoFila = strtoupper((string) $senha['tipo_fila']);

    if (
        ($tipoFila === 'URGENCIA' && $tipoAtendimento !== 'URGENCIA') ||
        ($tipoFila === 'AMBULATORIO' && $tipoAtendimento !== 'AMBULATORIO')
    ) {
        $pdo->rollBack();

        responderJson(
            false,
            'O tipo de atendimento não corresponde à fila da senha.',
            [
                'tipo_fila' => $tipoFila,
                'tipo_atendimento' => $tipoAtendimento
            ],
            422
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
            'Essa senha já possui um atendimento criado.',
            [
                'atendimento_id' =>
                    (int) $atendimentoExistente['id'],

                'numero_atendimento' =>
                    (string) $atendimentoExistente[
                        'numero_atendimento'
                    ]
            ],
            409
        );
    }

    $numeroTemporario =
        'TMP-' . bin2hex(random_bytes(12));

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
            profissional_id,
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
            :profissional_id,
            :profissional_responsavel,
            'AGUARDANDO_MEDICO',
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
        ':profissional_id' => $profissionalId,
        ':profissional_responsavel' =>
            $profissionalResponsavel
    ]);

    $atendimentoId = (int) $pdo->lastInsertId();

    if ($atendimentoId <= 0) {
        throw new RuntimeException(
            'Não foi possível obter o identificador do atendimento.'
        );
    }

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
            status = 'ATENDIMENTO_CRIADO',
            etapa_chamada = NULL,
            local_chamada = NULL,
            atendida_em = NOW()
        WHERE id = :id
          AND status = 'CHAMADA_RECEPCAO'
        "
    );

    $stmtAtualizarSenha->execute([
        ':id' => $senhaId
    ]);

    if ($stmtAtualizarSenha->rowCount() !== 1) {
        throw new RuntimeException(
            'Não foi possível finalizar a senha na recepção.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Criação automática da Conta Hospitalar
    |--------------------------------------------------------------------------
    */

    $stmtCriarConta = $pdo->prepare(
        "
        INSERT INTO contas_hospitalares (
            atendimento_id,
            paciente_id,
            convenio_id,
            data_abertura,
            valor_bruto,
            valor_desconto,
            valor_coparticipacao,
            valor_liquido,
            status
        )
        VALUES (
            :atendimento_id,
            :paciente_id,
            NULL,
            NOW(),
            0.00,
            0.00,
            0.00,
            0.00,
            'ABERTA'
        )
        "
    );

    $stmtCriarConta->execute([
        ':atendimento_id' => $atendimentoId,
        ':paciente_id' => $pacienteId
    ]);

    $contaHospitalarId = (int) $pdo->lastInsertId();

    if ($contaHospitalarId <= 0) {
        throw new RuntimeException(
            'Não foi possível criar a Conta Hospitalar.'
        );
    }

    $pdo->commit();

    responderJson(
        true,
        'Atendimento e Conta Hospitalar criados com sucesso.',
        [
            'id' => $atendimentoId,
            'atendimento_id' => $atendimentoId,
            'numero_atendimento' => $numeroAtendimento,
            'conta_hospitalar_id' => $contaHospitalarId,
            'status_conta_hospitalar' => 'ABERTA',
            'senha_id' => (int) $senha['id'],
            'senha' => (string) $senha['numero'],
            'status_atendimento' => 'AGUARDANDO_MEDICO',
            'status_senha' => 'ATENDIMENTO_CRIADO',
            'redirecionar_para' =>
                '/safe-health/modules/atendimento/recepcao.php'
        ],
        201
    );
} catch (DomainException $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responderJson(
        false,
        $erro->getMessage(),
        [],
        404
    );
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro ao criar atendimento: ' .
        $erro->getMessage()
    );

    responderJson(
        false,
        'Não foi possível criar o atendimento.',
        [
            'erro' => $erro->getMessage()
        ],
        500
    );
}