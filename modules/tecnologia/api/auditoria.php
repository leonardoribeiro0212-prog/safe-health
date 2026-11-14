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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responder(
        false,
        'Método não permitido.',
        [],
        405
    );
}

$acao = trim((string) ($_GET['acao'] ?? ''));

if ($acao === 'filtros') {
    $usuarios = $pdo->query("
        SELECT id, nome
        FROM usuarios
        ORDER BY nome
    ")->fetchAll(PDO::FETCH_ASSOC);

    $modulos = $pdo->query("
        SELECT DISTINCT modulo
        FROM auditoria_sistema
        WHERE modulo IS NOT NULL
          AND TRIM(modulo) <> ''
        ORDER BY modulo
    ")->fetchAll(PDO::FETCH_COLUMN);

    $acoes = $pdo->query("
        SELECT DISTINCT acao
        FROM auditoria_sistema
        WHERE acao IS NOT NULL
          AND TRIM(acao) <> ''
        ORDER BY acao
    ")->fetchAll(PDO::FETCH_COLUMN);

    responder(
        true,
        'Filtros carregados.',
        [
            'usuarios' => $usuarios,
            'modulos' => $modulos,
            'acoes' => $acoes
        ]
    );
}

if ($acao === 'resumo') {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS eventos,

            SUM(
                CASE
                    WHEN DATE(criado_em) = CURDATE()
                    THEN 1
                    ELSE 0
                END
            ) AS hoje,

            SUM(
                CASE
                    WHEN acao IN (
                        'ALTERACAO',
                        'ATUALIZACAO',
                        'EDICAO',
                        'CRIACAO',
                        'EXCLUSAO',
                        'INATIVACAO',
                        'ATIVACAO'
                    )
                    THEN 1
                    ELSE 0
                END
            ) AS alteracoes,

            SUM(
                CASE
                    WHEN acao IN (
                        'LOGIN',
                        'LOGOUT',
                        'ACESSO',
                        'CONSULTA',
                        'VISUALIZACAO'
                    )
                    THEN 1
                    ELSE 0
                END
            ) AS acessos

        FROM auditoria_sistema
    ");

    responder(
        true,
        'Resumo carregado.',
        [
            'resumo' => $stmt->fetch(PDO::FETCH_ASSOC)
        ]
    );
}

if ($acao === 'listar') {
    $dataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
    $dataFim = trim((string) ($_GET['data_fim'] ?? ''));
    $usuarioId = (int) ($_GET['usuario_id'] ?? 0);
    $modulo = trim((string) ($_GET['modulo'] ?? ''));
    $acaoFiltro = trim((string) ($_GET['acao_filtro'] ?? ''));
    $q = trim((string) ($_GET['q'] ?? ''));

    $sql = "
        SELECT
            a.id,
            a.usuario_id,
            a.acao,
            a.modulo,
            a.tela,
            a.entidade,
            a.registro_id,
            a.descricao,
            a.ip,
            a.metodo_http,
            a.url,
            a.criado_em,
            u.nome AS usuario_nome,
            u.email AS usuario_email

        FROM auditoria_sistema a

        LEFT JOIN usuarios u
            ON u.id = a.usuario_id

        WHERE 1 = 1
    ";

    $params = [];

    if ($dataInicio !== '') {
        $sql .= "
            AND a.criado_em >= :data_inicio
        ";

        $params[':data_inicio'] =
            $dataInicio . ' 00:00:00';
    }

    if ($dataFim !== '') {
        $sql .= "
            AND a.criado_em <= :data_fim
        ";

        $params[':data_fim'] =
            $dataFim . ' 23:59:59';
    }

    if ($usuarioId > 0) {
        $sql .= "
            AND a.usuario_id = :usuario_id
        ";

        $params[':usuario_id'] =
            $usuarioId;
    }

    if ($modulo !== '') {
        $sql .= "
            AND a.modulo = :modulo
        ";

        $params[':modulo'] =
            $modulo;
    }

    if ($acaoFiltro !== '') {
        $sql .= "
            AND a.acao = :acao
        ";

        $params[':acao'] =
            $acaoFiltro;
    }

    if ($q !== '') {
        $sql .= "
            AND (
                a.tela LIKE :q
                OR a.entidade LIKE :q
                OR a.registro_id LIKE :q
                OR a.descricao LIKE :q
                OR a.url LIKE :q
                OR u.nome LIKE :q
                OR u.email LIKE :q
            )
        ";

        $params[':q'] =
            '%' . $q . '%';
    }

    $sql .= "
        ORDER BY
            a.criado_em DESC,
            a.id DESC

        LIMIT 500
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    responder(
        true,
        'Auditoria carregada.',
        [
            'eventos' =>
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
            'Evento inválido.',
            [],
            422
        );
    }

    $stmt = $pdo->prepare("
        SELECT
            a.*,
            u.nome AS usuario_nome,
            u.email AS usuario_email

        FROM auditoria_sistema a

        LEFT JOIN usuarios u
            ON u.id = a.usuario_id

        WHERE a.id = :id

        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $evento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evento) {
        responder(
            false,
            'Evento não encontrado.',
            [],
            404
        );
    }

    responder(
        true,
        'Evento carregado.',
        [
            'evento' => $evento
        ]
    );
}

responder(
    false,
    'Ação inválida.',
    [],
    400
);
