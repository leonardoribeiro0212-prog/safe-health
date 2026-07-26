<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/database.php';

try {
    $cpf = preg_replace(
        '/\D/',
        '',
        (string) ($_GET['cpf'] ?? '')
    );

    if (strlen($cpf) !== 11) {
        http_response_code(422);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Informe um CPF com 11 números.'
        ]);

        exit;
    }

    $sql = "
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
            cidade,
            estado
        FROM pacientes
        WHERE cpf = :cpf
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

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

    echo json_encode([
        'sucesso' => true,
        'encontrado' => true,
        'paciente' => $paciente
    ]);
} catch (Throwable $erro) {
    http_response_code(500);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Não foi possível pesquisar o paciente.',
        'erro' => $erro->getMessage()
    ]);
}