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

function booleanoBanco(mixed $valor): int
{
    return (int) $valor === 1
        ? 1
        : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim((string) ($_GET['acao'] ?? ''));

    if ($acao === 'modulos') {
        $stmt = $pdo->query("
            SELECT DISTINCT modulo
            FROM telas
            WHERE modulo IS NOT NULL
              AND TRIM(modulo) <> ''
            ORDER BY modulo
        ");

        responder(
            true,
            'Módulos carregados.',
            [
                'modulos' =>
                    $stmt->fetchAll(PDO::FETCH_COLUMN)
            ]
        );
    }

    if ($acao === 'listar') {
        $q =
            trim((string) ($_GET['q'] ?? ''));

        $modulo =
            trim((string) ($_GET['modulo'] ?? ''));

        $status =
            trim((string) ($_GET['status'] ?? ''));

        $sql = "
            SELECT
                id,
                nome,
                codigo,
                modulo,
                url,
                icone,
                ordem,
                abrir_nova_aba,
                ativa

            FROM telas

            WHERE 1 = 1
        ";

        $params = [];

        if ($q !== '') {
            $sql .= "
                AND (
                    nome LIKE :q
                    OR codigo LIKE :q
                    OR modulo LIKE :q
                    OR url LIKE :q
                )
            ";

            $params[':q'] =
                '%' . $q . '%';
        }

        if ($modulo !== '') {
            $sql .= "
                AND modulo = :modulo
            ";

            $params[':modulo'] =
                $modulo;
        }

        if (
            $status === '0' ||
            $status === '1'
        ) {
            $sql .= "
                AND ativa = :ativa
            ";

            $params[':ativa'] =
                (int) $status;
        }

        $sql .= "
            ORDER BY
                modulo,
                ordem,
                nome,
                id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        responder(
            true,
            'Telas carregadas.',
            [
                'telas' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'detalhes') {
        $id = filter_var(
            $_GET['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id) {
            responder(
                false,
                'Tela inválida.',
                [],
                422
            );
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                nome,
                codigo,
                modulo,
                url,
                icone,
                ordem,
                abrir_nova_aba,
                ativa

            FROM telas

            WHERE id = :id

            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id
        ]);

        $tela =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tela) {
            responder(
                false,
                'Tela não encontrada.',
                [],
                404
            );
        }

        responder(
            true,
            'Tela carregada.',
            [
                'tela' => $tela
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
        'Dados inválidos.',
        [],
        400
    );
}

$telaId =
    (int) ($entrada['tela_id'] ?? 0);

$nome =
    trim((string) ($entrada['nome'] ?? ''));

$codigo =
    trim((string) ($entrada['codigo'] ?? ''));

$modulo =
    trim((string) ($entrada['modulo'] ?? ''));

$url =
    trim((string) ($entrada['url'] ?? ''));

$icone =
    trim((string) ($entrada['icone'] ?? ''));

$ordem =
    (int) ($entrada['ordem'] ?? 0);

$abrirNovaAba =
    booleanoBanco(
        $entrada['abrir_nova_aba'] ?? 0
    );

$ativa =
    booleanoBanco(
        $entrada['ativa'] ?? 1
    );

if ($nome === '') {
    responder(
        false,
        'Informe o nome da tela.',
        [],
        422
    );
}

if ($codigo === '') {
    responder(
        false,
        'Informe o código da tela.',
        [],
        422
    );
}

if ($modulo === '') {
    responder(
        false,
        'Informe o módulo da tela.',
        [],
        422
    );
}

if ($ordem < 0) {
    responder(
        false,
        'A ordem não pode ser negativa.',
        [],
        422
    );
}

if (
    $url !== '' &&
    !str_starts_with(
        $url,
        '/safe-health/'
    )
) {
    responder(
        false,
        'A URL deve começar com /safe-health/.',
        [],
        422
    );
}

if (
    !preg_match(
        '/^[A-Za-z0-9_.-]+$/',
        $codigo
    )
) {
    responder(
        false,
        'O código deve usar apenas letras, números, ponto, hífen ou underscore.',
        [],
        422
    );
}

if (
    $icone !== '' &&
    !preg_match(
        '/^[A-Za-z0-9 _-]+$/',
        $icone
    )
) {
    responder(
        false,
        'Classe de ícone inválida.',
        [],
        422
    );
}

try {
    $stmtCodigo = $pdo->prepare("
        SELECT id
        FROM telas
        WHERE codigo = :codigo
          AND id <> :id
        LIMIT 1
    ");

    $stmtCodigo->execute([
        ':codigo' => $codigo,
        ':id' => $telaId
    ]);

    if ($stmtCodigo->fetchColumn()) {
        responder(
            false,
            'Já existe uma tela cadastrada com este código.',
            [],
            409
        );
    }

    if ($telaId > 0) {
        $stmtExiste = $pdo->prepare("
            SELECT id
            FROM telas
            WHERE id = :id
            LIMIT 1
        ");

        $stmtExiste->execute([
            ':id' => $telaId
        ]);

        if (!$stmtExiste->fetchColumn()) {
            responder(
                false,
                'Tela não encontrada.',
                [],
                404
            );
        }

        $stmt = $pdo->prepare("
            UPDATE telas
            SET
                nome = :nome,
                codigo = :codigo,
                modulo = :modulo,
                url = :url,
                icone = :icone,
                ordem = :ordem,
                abrir_nova_aba = :abrir_nova_aba,
                ativa = :ativa
            WHERE id = :id
        ");

        $stmt->execute([
            ':nome' => $nome,
            ':codigo' => $codigo,
            ':modulo' => $modulo,
            ':url' => $url !== '' ? $url : null,
            ':icone' => $icone !== '' ? $icone : null,
            ':ordem' => $ordem,
            ':abrir_nova_aba' => $abrirNovaAba,
            ':ativa' => $ativa,
            ':id' => $telaId
        ]);

        responder(
            true,
            'Tela atualizada com sucesso.',
            [
                'tela_id' =>
                    $telaId
            ]
        );

    } else {
        $stmt = $pdo->prepare("
            INSERT INTO telas (
                nome,
                codigo,
                modulo,
                url,
                icone,
                ordem,
                abrir_nova_aba,
                ativa
            )
            VALUES (
                :nome,
                :codigo,
                :modulo,
                :url,
                :icone,
                :ordem,
                :abrir_nova_aba,
                :ativa
            )
        ");

        $stmt->execute([
            ':nome' => $nome,
            ':codigo' => $codigo,
            ':modulo' => $modulo,
            ':url' => $url !== '' ? $url : null,
            ':icone' => $icone !== '' ? $icone : null,
            ':ordem' => $ordem,
            ':abrir_nova_aba' => $abrirNovaAba,
            ':ativa' => $ativa
        ]);

        responder(
            true,
            'Tela criada com sucesso.',
            [
                'tela_id' =>
                    (int) $pdo->lastInsertId()
            ],
            201
        );
    }

} catch (Throwable $erro) {
    error_log(
        'Erro na administração de telas: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível salvar a tela.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
