<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/cpf.php';

function responder(
    bool $sucesso,
    string $mensagem,
    array $dados = [],
    int $status = 200
): never {
    http_response_code($status);

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
    responder(
        false,
        'Método não permitido.',
        [],
        405
    );
}

$entrada = json_decode(
    file_get_contents('php://input') ?: '',
    true
);

if (!is_array($entrada)) {
    responder(
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

$nome = trim((string) ($entrada['nome'] ?? ''));

$cpf = normalizarCpf((string) ($entrada['cpf'] ?? ''));

$dataNascimento = trim(
    (string) ($entrada['data_nascimento'] ?? '')
);

$sexo = strtoupper(
    trim((string) ($entrada['sexo'] ?? ''))
);

$nomeMae = trim((string) ($entrada['nome_mae'] ?? ''));
$telefone = trim((string) ($entrada['telefone'] ?? ''));
$email = trim((string) ($entrada['email'] ?? ''));
$cartaoSus = trim((string) ($entrada['cartao_sus'] ?? ''));

$cep = preg_replace(
    '/\D/',
    '',
    (string) ($entrada['cep'] ?? '')
);

$endereco = trim((string) ($entrada['endereco'] ?? ''));
$numero = trim((string) ($entrada['numero'] ?? ''));
$complemento = trim((string) ($entrada['complemento'] ?? ''));
$bairro = trim((string) ($entrada['bairro'] ?? ''));
$cidade = trim((string) ($entrada['cidade'] ?? ''));
$estado = strtoupper(trim((string) ($entrada['estado'] ?? '')));

if (
    $pacienteId === false ||
    $pacienteId === null ||
    $pacienteId <= 0
) {
    responder(
        false,
        'Paciente inválido.',
        [],
        422
    );
}

if ($nome === '') {
    responder(
        false,
        'Informe o nome do paciente.',
        [],
        422
    );
}

if (!validarCpf($cpf)) {
    responder(
        false,
        'CPF inválido. Verifique os números informados.',
        [],
        422
    );
}

if ($dataNascimento === '') {
    responder(
        false,
        'Informe a data de nascimento.',
        [],
        422
    );
}

if (!in_array($sexo, ['M', 'F', 'OUTRO'], true)) {
    responder(
        false,
        'Selecione uma opção de sexo válida.',
        [],
        422
    );
}

if (
    $email !== '' &&
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    responder(
        false,
        'Informe um e-mail válido.',
        [],
        422
    );
}

if ($estado !== '' && strlen($estado) !== 2) {
    responder(
        false,
        'Informe uma UF válida.',
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
        $pdo->rollBack();

        responder(
            false,
            'Paciente não encontrado.',
            [],
            404
        );
    }

    $stmtCpf = $pdo->prepare(
        "
        SELECT id
        FROM pacientes
        WHERE cpf = :cpf
          AND id <> :id
        LIMIT 1
        "
    );

    $stmtCpf->execute([
        ':cpf' => $cpf,
        ':id' => $pacienteId
    ]);

    if ($stmtCpf->fetchColumn()) {
        $pdo->rollBack();

        responder(
            false,
            'Já existe outro paciente cadastrado com esse CPF.',
            [],
            409
        );
    }

    $stmtAtualizar = $pdo->prepare(
        "
        UPDATE pacientes
        SET
            nome = :nome,
            cpf = :cpf,
            data_nascimento = :data_nascimento,
            sexo = :sexo,
            nome_mae = :nome_mae,
            telefone = :telefone,
            email = :email,
            cartao_sus = :cartao_sus,
            cep = :cep,
            endereco = :endereco,
            numero = :numero,
            complemento = :complemento,
            bairro = :bairro,
            cidade = :cidade,
            estado = :estado
        WHERE id = :id
        "
    );

    $stmtAtualizar->execute([
        ':nome' => $nome,
        ':cpf' => $cpf,
        ':data_nascimento' => $dataNascimento,
        ':sexo' => $sexo,
        ':nome_mae' => $nomeMae !== '' ? $nomeMae : null,
        ':telefone' => $telefone !== '' ? $telefone : null,
        ':email' => $email !== '' ? $email : null,
        ':cartao_sus' => $cartaoSus !== '' ? $cartaoSus : null,
        ':cep' => $cep !== '' ? $cep : null,
        ':endereco' => $endereco !== '' ? $endereco : null,
        ':numero' => $numero !== '' ? $numero : null,
        ':complemento' => $complemento !== '' ? $complemento : null,
        ':bairro' => $bairro !== '' ? $bairro : null,
        ':cidade' => $cidade !== '' ? $cidade : null,
        ':estado' => $estado !== '' ? $estado : null,
        ':id' => $pacienteId
    ]);

    $pdo->commit();

    responder(
        true,
        'Cadastro do paciente atualizado com sucesso.',
        [
            'paciente_id' => $pacienteId,
            'cpf' => $cpf
        ]
    );
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro ao atualizar cadastro do paciente: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível atualizar o cadastro do paciente.',
        [
            'erro' => $erro->getMessage()
        ],
        500
    );
}
