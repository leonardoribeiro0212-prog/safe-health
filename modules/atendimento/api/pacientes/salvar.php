<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/cpf.php';

try {
    $entrada = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($entrada)) {
        throw new RuntimeException(
            'Os dados enviados são inválidos.'
        );
    }

    $nome = trim((string) ($entrada['nome'] ?? ''));

    $cpf = normalizarCpf((string) ($entrada['cpf'] ?? ''));

    $dataNascimento = trim(
        (string) ($entrada['data_nascimento'] ?? '')
    );

    $sexo = strtoupper(
        trim((string) ($entrada['sexo'] ?? ''))
    );

    $nomeMae = trim(
        (string) ($entrada['nome_mae'] ?? '')
    );

    $telefone = trim(
        (string) ($entrada['telefone'] ?? '')
    );

    $email = trim(
        (string) ($entrada['email'] ?? '')
    );

    $cartaoSus = trim(
        (string) ($entrada['cartao_sus'] ?? '')
    );

    $cep = preg_replace(
        '/\D/',
        '',
        (string) ($entrada['cep'] ?? '')
    );

    $endereco = trim(
        (string) ($entrada['endereco'] ?? '')
    );

    $numero = trim(
        (string) ($entrada['numero'] ?? '')
    );

    $complemento = trim(
        (string) ($entrada['complemento'] ?? '')
    );

    $bairro = trim(
        (string) ($entrada['bairro'] ?? '')
    );

    $cidade = trim(
        (string) ($entrada['cidade'] ?? '')
    );

    $estado = strtoupper(
        trim((string) ($entrada['estado'] ?? ''))
    );

    if ($nome === '') {
        http_response_code(422);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Informe o nome do paciente.'
        ]);

        exit;
    }

    if (!validarCpf($cpf)) {
        http_response_code(422);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'CPF inválido. Verifique os números informados.'
        ]);

        exit;
    }

    if ($dataNascimento === '') {
        http_response_code(422);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Informe a data de nascimento.'
        ]);

        exit;
    }

    if (!in_array($sexo, ['M', 'F', 'OUTRO'], true)) {
        http_response_code(422);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Selecione uma opção de sexo válida.'
        ]);

        exit;
    }

    if (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        http_response_code(422);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Informe um e-mail válido.'
        ]);

        exit;
    }

    $verificarCpf = $pdo->prepare(
        'SELECT id FROM pacientes WHERE cpf = :cpf LIMIT 1'
    );

    $verificarCpf->execute([
        ':cpf' => $cpf
    ]);

    if ($verificarCpf->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Já existe um paciente cadastrado com esse CPF.'
        ]);

        exit;
    }

    $sql = "
        INSERT INTO pacientes (
            nome,
            cpf,
            data_nascimento,
            sexo,
            nome_mae,
            telefone,
            email,
            cartao_sus,
            cep,
            endereco,
            numero,
            complemento,
            bairro,
            cidade,
            estado
        ) VALUES (
            :nome,
            :cpf,
            :data_nascimento,
            :sexo,
            :nome_mae,
            :telefone,
            :email,
            :cartao_sus,
            :cep,
            :endereco,
            :numero,
            :complemento,
            :bairro,
            :cidade,
            :estado
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
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
        ':estado' => $estado !== '' ? $estado : null
    ]);

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Paciente cadastrado com sucesso.',
        'paciente_id' => (int) $pdo->lastInsertId(),
        'cpf' => $cpf
    ]);
} catch (Throwable $erro) {
    http_response_code(500);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Não foi possível cadastrar o paciente.',
        'erro' => $erro->getMessage()
    ]);
}