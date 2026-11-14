<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim(
        (string) ($_GET['acao'] ?? '')
    );

    if ($acao === 'listar_abertos') {
        $stmt = $pdo->query(
            "
            SELECT
                a.id AS atendimento_id,
                a.numero_atendimento,
                a.paciente_id,
                a.tipo_atendimento,
                a.status,
                a.data_entrada,
                a.convenio,
                p.nome AS paciente_nome,
                p.cpf AS paciente_cpf
            FROM atendimentos a
            INNER JOIN pacientes p
                ON p.id = a.paciente_id
            WHERE
                a.status <> 'ALTA'
                AND a.data_alta IS NULL
            ORDER BY
                a.data_entrada ASC,
                a.id ASC
            "
        );

        responder(
            true,
            'Atendimentos em aberto carregados.',
            [
                'atendimentos' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'buscar_atendimento') {
        $numero = trim(
            (string) ($_GET['numero'] ?? '')
        );

        if ($numero === '') {
            responder(
                false,
                'Informe o número do atendimento.',
                [],
                422
            );
        }

        $numeroSemPrefixo =
            preg_replace('/\D/', '', $numero) ?? '';

        $stmt = $pdo->prepare(
            "
            SELECT
                a.id AS atendimento_id,
                a.numero_atendimento,
                a.paciente_id,
                a.convenio,
                a.tipo_atendimento,
                a.status,
                a.data_entrada,
                p.nome AS paciente_nome,
                p.cpf AS paciente_cpf,
                p.data_nascimento,
                p.sexo,
                p.telefone,
                p.cartao_sus
            FROM atendimentos a
            INNER JOIN pacientes p
                ON p.id = a.paciente_id
            WHERE
                (
                    a.numero_atendimento = :numero
                    OR CAST(a.id AS CHAR) = :numero_id
                )
                AND a.status <> 'ALTA'
                AND a.data_alta IS NULL
            LIMIT 1
            "
        );

        $stmt->execute([
            ':numero' => $numero,
            ':numero_id' => $numeroSemPrefixo
        ]);

        $atendimento =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$atendimento) {
            responder(
                false,
                'Atendimento não encontrado ou já encerrado.',
                [],
                404
            );
        }

        responder(
            true,
            'Atendimento localizado.',
            [
                'atendimento' => $atendimento
            ]
        );
    }

    if ($acao === 'historico') {
        $atendimentoId = filter_var(
            $_GET['atendimento_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$atendimentoId) {
            responder(
                false,
                'Atendimento inválido.',
                [],
                422
            );
        }

        $stmt = $pdo->prepare(
            "
            SELECT
                ae.id,
                ae.conteudo,
                ae.data_hora,
                ae.usuario_id,
                u.nome AS usuario_nome

            FROM anotacoes_enfermagem ae

            LEFT JOIN usuarios u
                ON u.id = ae.usuario_id

            WHERE
                ae.atendimento_id = :atendimento_id

            ORDER BY
                ae.data_hora DESC,
                ae.id DESC
            "
        );

        $stmt->execute([
            ':atendimento_id' =>
                $atendimentoId
        ]);

        responder(
            true,
            'Histórico carregado.',
            [
                'anotacoes' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    responder(
        false,
        'Ação inválida.',
        [],
        400
    );
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

$atendimentoId =
    (int) ($entrada['atendimento_id'] ?? 0);

$pacienteId =
    (int) ($entrada['paciente_id'] ?? 0);

$conteudo = trim(
    (string) ($entrada['conteudo'] ?? '')
);

if (
    $atendimentoId <= 0 ||
    $pacienteId <= 0
) {
    responder(
        false,
        'Atendimento ou paciente inválido.',
        [],
        422
    );
}

if ($conteudo === '') {
    responder(
        false,
        'Digite a anotação de enfermagem.',
        [],
        422
    );
}

if (mb_strlen($conteudo) > 10000) {
    responder(
        false,
        'A anotação excede o limite de 10.000 caracteres.',
        [],
        422
    );
}

try {
    $stmtAtendimento = $pdo->prepare(
        "
        SELECT id
        FROM atendimentos
        WHERE
            id = :atendimento_id
            AND paciente_id = :paciente_id
            AND status <> 'ALTA'
            AND data_alta IS NULL
        LIMIT 1
        "
    );

    $stmtAtendimento->execute([
        ':atendimento_id' =>
            $atendimentoId,

        ':paciente_id' =>
            $pacienteId
    ]);

    if (!$stmtAtendimento->fetchColumn()) {
        responder(
            false,
            'O atendimento não está mais aberto para novas anotações.',
            [],
            422
        );
    }

    $usuarioId =
        $_SESSION['usuario_id'] ??
        $_SESSION['id_usuario'] ??
        null;

    $stmt = $pdo->prepare(
        "
        INSERT INTO anotacoes_enfermagem (
            atendimento_id,
            paciente_id,
            usuario_id,
            conteudo
        )
        VALUES (
            :atendimento_id,
            :paciente_id,
            :usuario_id,
            :conteudo
        )
        "
    );

    $stmt->execute([
        ':atendimento_id' =>
            $atendimentoId,

        ':paciente_id' =>
            $pacienteId,

        ':usuario_id' =>
            $usuarioId,

        ':conteudo' =>
            $conteudo
    ]);

    responder(
        true,
        'Anotação de enfermagem registrada com sucesso.',
        [
            'anotacao_id' =>
                (int) $pdo->lastInsertId()
        ],
        201
    );

} catch (Throwable $erro) {
    error_log(
        'Erro ao salvar anotação de enfermagem: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível registrar a anotação de enfermagem.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
