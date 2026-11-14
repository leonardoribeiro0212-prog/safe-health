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
    $acao = trim((string) ($_GET['acao'] ?? ''));

    if ($acao === 'papeis') {
        $stmt = $pdo->query("
            SELECT
                id,
                nome,
                descricao
            FROM papeis
            WHERE ativo = 1
            ORDER BY nome
        ");

        responder(
            true,
            'Papéis carregados.',
            [
                'papeis' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'carregar') {
        $papelId = filter_var(
            $_GET['papel_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$papelId) {
            responder(
                false,
                'Papel inválido.',
                [],
                422
            );
        }

        $stmtPapel = $pdo->prepare("
            SELECT
                id,
                nome,
                descricao,
                ativo
            FROM papeis
            WHERE id = :id
            LIMIT 1
        ");

        $stmtPapel->execute([
            ':id' => $papelId
        ]);

        $papel =
            $stmtPapel->fetch(PDO::FETCH_ASSOC);

        if (!$papel) {
            responder(
                false,
                'Papel não encontrado.',
                [],
                404
            );
        }

        $stmt = $pdo->prepare("
            SELECT
                t.id,
                t.nome,
                t.codigo,
                t.modulo,
                t.url,
                t.icone,
                t.ordem,
                t.abrir_nova_aba,
                t.ativa,

                CASE
                    WHEN pt.tela_id IS NOT NULL
                    THEN 1
                    ELSE 0
                END AS permitida

            FROM telas t

            LEFT JOIN papel_tela pt
                ON pt.tela_id = t.id
               AND pt.papel_id = :papel_id

            WHERE t.ativa = 1

            ORDER BY
                t.modulo,
                t.ordem,
                t.nome,
                t.id
        ");

        $stmt->execute([
            ':papel_id' => $papelId
        ]);

        $telas =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

        responder(
            true,
            'Permissões carregadas.',
            [
                'papel' => $papel,
                'telas' => $telas
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

$papelId =
    (int) ($entrada['papel_id'] ?? 0);

$telas =
    $entrada['telas'] ?? [];

if ($papelId <= 0) {
    responder(
        false,
        'Selecione um papel.',
        [],
        422
    );
}

if (!is_array($telas)) {
    responder(
        false,
        'Lista de permissões inválida.',
        [],
        422
    );
}

$telaIds = [];

foreach ($telas as $telaId) {
    $id = (int) $telaId;

    if ($id > 0) {
        $telaIds[$id] = $id;
    }
}

$telaIds = array_values($telaIds);

try {
    $pdo->beginTransaction();

    $stmtPapel = $pdo->prepare("
        SELECT id
        FROM papeis
        WHERE id = :id
          AND ativo = 1
        LIMIT 1
        FOR UPDATE
    ");

    $stmtPapel->execute([
        ':id' => $papelId
    ]);

    if (!$stmtPapel->fetchColumn()) {
        $pdo->rollBack();

        responder(
            false,
            'O papel não existe ou está inativo.',
            [],
            422
        );
    }

    if (count($telaIds) > 0) {
        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($telaIds),
                    '?'
                )
            );

        $stmtValidar = $pdo->prepare("
            SELECT id
            FROM telas
            WHERE ativa = 1
              AND id IN ($placeholders)
        ");

        $stmtValidar->execute($telaIds);

        $validas =
            array_map(
                'intval',
                $stmtValidar->fetchAll(
                    PDO::FETCH_COLUMN
                )
            );

        sort($validas);

        $comparar = $telaIds;
        sort($comparar);

        if ($validas !== $comparar) {
            $pdo->rollBack();

            responder(
                false,
                'Uma ou mais telas selecionadas são inválidas ou estão inativas.',
                [],
                422
            );
        }
    }

    /*
     * A tela Permissões é a fonte de verdade do vínculo papel -> tela.
     * Portanto, substituímos os vínculos atuais pelos selecionados.
     */
    $stmtDelete = $pdo->prepare("
        DELETE FROM papel_tela
        WHERE papel_id = :papel_id
    ");

    $stmtDelete->execute([
        ':papel_id' => $papelId
    ]);

    if (count($telaIds) > 0) {
        $stmtInsert = $pdo->prepare("
            INSERT INTO papel_tela (
                papel_id,
                tela_id
            )
            VALUES (
                :papel_id,
                :tela_id
            )
        ");

        foreach ($telaIds as $telaId) {
            $stmtInsert->execute([
                ':papel_id' =>
                    $papelId,

                ':tela_id' =>
                    $telaId
            ]);
        }
    }

    $pdo->commit();

    responder(
        true,
        'Permissões atualizadas com sucesso.',
        [
            'papel_id' =>
                $papelId,

            'total_permissoes' =>
                count($telaIds)
        ]
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro na tela de permissões: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível salvar as permissões.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
