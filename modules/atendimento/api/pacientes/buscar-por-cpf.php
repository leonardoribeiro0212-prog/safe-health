<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/cpf.php';

try {
    $cpf = normalizarCpf((string) ($_GET['cpf'] ?? ''));

    if (!validarCpf($cpf)) {
        http_response_code(422);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'CPF inválido. Verifique os números informados.'
        ]);

        exit;
    }

    $stmt = $pdo->prepare(
        "
        SELECT
            id,
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
        FROM pacientes
        WHERE cpf = :cpf
        LIMIT 1
        "
    );

    $stmt->execute([
        ':cpf' => $cpf
    ]);

    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        echo json_encode([
            'sucesso' => true,
            'encontrado' => false,
            'cpf' => $cpf
        ]);

        exit;
    }

    echo json_encode(
        [
            'sucesso' => true,
            'encontrado' => true,
            'paciente' => $paciente
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $erro) {
    http_response_code(500);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Não foi possível pesquisar o paciente.',
        'erro' => $erro->getMessage()
    ]);
}
