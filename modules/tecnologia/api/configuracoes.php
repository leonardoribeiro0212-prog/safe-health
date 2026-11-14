<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';

$auditoriaDisponivel =
    file_exists(
        __DIR__ . '/../../../includes/auditoria.php'
    );

if ($auditoriaDisponivel) {
    require_once __DIR__ . '/../../../includes/auditoria.php';
}

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

$tipos = [
    'sistema.nome' => 'TEXTO',
    'sistema.instituicao' => 'TEXTO',
    'sistema.email_suporte' => 'EMAIL',
    'sistema.manutencao' => 'BOOLEANO',
    'sessao.tempo_minutos' => 'NUMERO',
    'sessao.pagina_inicial' => 'TEXTO',
    'seguranca.max_tentativas_login' => 'NUMERO',
    'seguranca.tempo_bloqueio_minutos' => 'NUMERO'
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("
        SELECT
            chave,
            valor,
            tipo,
            grupo,
            descricao,
            atualizado_em
        FROM configuracoes_sistema
        ORDER BY grupo, chave
    ");

    responder(
        true,
        'Configurações carregadas.',
        [
            'configuracoes' =>
                $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]
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

$configuracoes =
    $entrada['configuracoes'] ?? null;

if (!is_array($configuracoes)) {
    responder(
        false,
        'Configurações inválidas.',
        [],
        422
    );
}

$validadas = [];

foreach ($tipos as $chave => $tipo) {
    if (!array_key_exists($chave, $configuracoes)) {
        responder(
            false,
            "Configuração ausente: {$chave}.",
            [],
            422
        );
    }

    $valor =
        trim(
            (string) $configuracoes[$chave]
        );

    if ($tipo === 'EMAIL') {
        if (
            $valor !== '' &&
            !filter_var(
                $valor,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            responder(
                false,
                'Informe um e-mail de suporte válido.',
                [],
                422
            );
        }
    }

    if ($tipo === 'BOOLEANO') {
        if (!in_array($valor, ['0', '1'], true)) {
            responder(
                false,
                'Valor booleano inválido.',
                [],
                422
            );
        }
    }

    if ($tipo === 'NUMERO') {
        if (
            $valor === '' ||
            !ctype_digit($valor)
        ) {
            responder(
                false,
                "Valor numérico inválido para {$chave}.",
                [],
                422
            );
        }
    }

    $validadas[$chave] = $valor;
}

$tempoSessao =
    (int) $validadas['sessao.tempo_minutos'];

$tentativas =
    (int) $validadas['seguranca.max_tentativas_login'];

$tempoBloqueio =
    (int) $validadas['seguranca.tempo_bloqueio_minutos'];

if ($tempoSessao < 5 || $tempoSessao > 1440) {
    responder(
        false,
        'O tempo de sessão deve ficar entre 5 e 1440 minutos.',
        [],
        422
    );
}

if ($tentativas < 1 || $tentativas > 20) {
    responder(
        false,
        'O máximo de tentativas deve ficar entre 1 e 20.',
        [],
        422
    );
}

if ($tempoBloqueio < 1 || $tempoBloqueio > 1440) {
    responder(
        false,
        'O tempo de bloqueio deve ficar entre 1 e 1440 minutos.',
        [],
        422
    );
}

try {
    $pdo->beginTransaction();

    $stmtAnterior = $pdo->prepare("
        SELECT chave, valor
        FROM configuracoes_sistema
        WHERE chave IN (
            'sistema.nome',
            'sistema.instituicao',
            'sistema.email_suporte',
            'sistema.manutencao',
            'sessao.tempo_minutos',
            'sessao.pagina_inicial',
            'seguranca.max_tentativas_login',
            'seguranca.tempo_bloqueio_minutos'
        )
    ");

    $stmtAnterior->execute();

    $anteriores = [];

    foreach (
        $stmtAnterior->fetchAll(PDO::FETCH_ASSOC)
        as $linha
    ) {
        $anteriores[$linha['chave']] =
            $linha['valor'];
    }

    $stmtSalvar = $pdo->prepare("
        UPDATE configuracoes_sistema
        SET valor = :valor
        WHERE chave = :chave
    ");

    foreach ($validadas as $chave => $valor) {
        $stmtSalvar->execute([
            ':valor' => $valor,
            ':chave' => $chave
        ]);
    }

    $pdo->commit();

    if (
        function_exists('registrarAuditoria')
    ) {
        registrarAuditoria(
            $pdo,
            'ALTERACAO',
            'Configurações gerais do Safe Health foram alteradas.',
            'Tecnologia',
            'Configurações',
            'configuracoes_sistema',
            null,
            $anteriores,
            $validadas
        );
    }

    responder(
        true,
        'Configurações salvas com sucesso.'
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro ao salvar configurações: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível salvar as configurações.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
