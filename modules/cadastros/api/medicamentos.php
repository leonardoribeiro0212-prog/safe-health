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

function gerarCodigo(string $nome): string
{
    $texto = mb_strtoupper(
        trim($nome),
        'UTF-8'
    );

    $mapa = [
        'Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','Ä'=>'A',
        'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
        'Ó'=>'O','Ò'=>'O','Õ'=>'O','Ô'=>'O','Ö'=>'O',
        'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
        'Ç'=>'C'
    ];

    $texto = strtr(
        $texto,
        $mapa
    );

    $texto = preg_replace(
        '/[^A-Z0-9]+/',
        '_',
        $texto
    ) ?? '';

    return trim(
        $texto,
        '_'
    );
}


function sincronizarFrequenciasMedicamento(
    PDO $pdo,
    int $medicamentoId,
    array $frequencias
): void {
    $stmt = $pdo->prepare("
        DELETE FROM medicamento_frequencias
        WHERE medicamento_id = :medicamento_id
    ");
    $stmt->execute([
        ':medicamento_id' => $medicamentoId
    ]);

    if (!$frequencias) {
        return;
    }

    $valida = $pdo->prepare("
        SELECT id
        FROM frequencias_prescricao
        WHERE id = :id
          AND ativo = 1
        LIMIT 1
    ");

    $insere = $pdo->prepare("
        INSERT INTO medicamento_frequencias (
            medicamento_id,
            frequencia_id
        )
        VALUES (
            :medicamento_id,
            :frequencia_id
        )
    ");

    foreach ($frequencias as $frequenciaId) {
        $valida->execute([':id' => $frequenciaId]);

        if (!$valida->fetchColumn()) {
            continue;
        }

        $insere->execute([
            ':medicamento_id' => $medicamentoId,
            ':frequencia_id' => $frequenciaId
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = trim(
        (string) ($_GET['acao'] ?? '')
    );

    if ($acao === 'pesquisar') {
        $q = trim(
            (string) ($_GET['q'] ?? '')
        );

        if ($q === '') {
            responder(
                false,
                'Informe um termo para pesquisar.',
                [],
                422
            );
        }

        $stmt = $pdo->prepare(
            "
            SELECT
                id,
                nome,
                principio_ativo,
                codigo,
                apresentacao,
                concentracao,
                unidade_medida,
                via_administracao,
                dose_padrao,
                quantidade_padrao,
                fabricante,
                valor_unitario,
                controlado,
                ativo
            FROM medicamentos
            WHERE
                nome LIKE :q
                OR principio_ativo LIKE :q
                OR codigo LIKE :q
            ORDER BY
                ativo DESC,
                nome
            LIMIT 40
            "
        );

        $stmt->execute([
            ':q' => '%' . $q . '%'
        ]);

        responder(
            true,
            'Pesquisa concluída.',
            [
                'medicamentos' =>
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]
        );
    }

    if ($acao === 'buscar') {
        $id = filter_var(
            $_GET['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id) {
            responder(
                false,
                'Medicamento inválido.',
                [],
                422
            );
        }

        $stmt = $pdo->prepare(
            "
            SELECT
                id,
                nome,
                principio_ativo,
                codigo,
                apresentacao,
                concentracao,
                unidade_medida,
                via_administracao,
                dose_padrao,
                quantidade_padrao,
                fabricante,
                valor_unitario,
                controlado,
                ativo
            FROM medicamentos
            WHERE id = :id
            LIMIT 1
            "
        );

        $stmt->execute([
            ':id' => $id
        ]);

        $medicamento =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$medicamento) {
            responder(
                false,
                'Medicamento não encontrado.',
                [],
                404
            );
        }

        responder(
            true,
            'Medicamento carregado.',
            [
                'medicamento' => $medicamento
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

$medicamentoId = (int) (
    $entrada['medicamento_id'] ?? 0
);

$nome = trim(
    (string) ($entrada['nome'] ?? '')
);

$principioAtivo = trim(
    (string) ($entrada['principio_ativo'] ?? '')
);

$codigoInformado = trim(
    (string) ($entrada['codigo'] ?? '')
);

$codigo = gerarCodigo(
    $codigoInformado !== ''
        ? $codigoInformado
        : $nome
);

$apresentacao = trim(
    (string) ($entrada['apresentacao'] ?? '')
);

$concentracao = trim(
    (string) ($entrada['concentracao'] ?? '')
);

$unidadeMedida = strtoupper(
    trim((string) ($entrada['unidade_medida'] ?? ''))
);

$viaAdministracao = strtoupper(
    trim((string) ($entrada['via_administracao'] ?? ''))
);

$dosePadrao = trim(
    (string) ($entrada['dose_padrao'] ?? '')
);

$frequenciasPrescricao = array_values(
    array_unique(
        array_filter(
            array_map(
                'intval',
                is_array($entrada['frequencias_prescricao'] ?? null)
                    ? $entrada['frequencias_prescricao']
                    : []
            ),
            static fn (int $id): bool => $id > 0
        )
    )
);

$quantidadePadrao = (int) (
    $entrada['quantidade_padrao'] ?? 1
);

$fabricante = trim(
    (string) ($entrada['fabricante'] ?? '')
);

$valorUnitario = str_replace(
    ',',
    '.',
    trim((string) ($entrada['valor_unitario'] ?? '0'))
);

$controlado = (string) (
    $entrada['controlado'] ?? '0'
);

$ativo = (string) (
    $entrada['ativo'] ?? '1'
);

if ($nome === '') {
    responder(
        false,
        'Informe o nome do medicamento.',
        [],
        422
    );
}

if ($codigo === '') {
    responder(
        false,
        'Não foi possível gerar o código do medicamento.',
        [],
        422
    );
}

if (
    $valorUnitario === '' ||
    !is_numeric($valorUnitario) ||
    (float) $valorUnitario < 0
) {
    responder(
        false,
        'Informe um valor unitário válido.',
        [],
        422
    );
}

if ($quantidadePadrao < 1) {
    responder(
        false,
        'A quantidade padrão deve ser maior que zero.',
        [],
        422
    );
}

if (!in_array(
    $controlado,
    ['0', '1'],
    true
)) {
    responder(
        false,
        'O campo controlado é inválido.',
        [],
        422
    );
}

if (!in_array(
    $ativo,
    ['0', '1'],
    true
)) {
    responder(
        false,
        'O status é inválido.',
        [],
        422
    );
}

try {
    $pdo->beginTransaction();

    $stmtCodigo = $pdo->prepare(
        "
        SELECT id
        FROM medicamentos
        WHERE codigo = :codigo
          AND id <> :id
        LIMIT 1
        "
    );

    $stmtCodigo->execute([
        ':codigo' => $codigo,
        ':id' => $medicamentoId
    ]);

    if ($stmtCodigo->fetchColumn()) {
        $pdo->rollBack();

        responder(
            false,
            'Já existe um medicamento com esse código.',
            [],
            409
        );
    }

    $parametros = [
        ':nome' => $nome,
        ':principio_ativo' =>
            $principioAtivo !== ''
                ? $principioAtivo
                : null,

        ':codigo' => $codigo,

        ':apresentacao' =>
            $apresentacao !== ''
                ? $apresentacao
                : null,

        ':concentracao' =>
            $concentracao !== ''
                ? $concentracao
                : null,

        ':unidade_medida' =>
            $unidadeMedida !== ''
                ? $unidadeMedida
                : null,

        ':via_administracao' =>
            $viaAdministracao !== ''
                ? $viaAdministracao
                : null,

        ':dose_padrao' =>
            $dosePadrao !== ''
                ? $dosePadrao
                : null,

        ':quantidade_padrao' =>
            $quantidadePadrao,

        ':fabricante' =>
            $fabricante !== ''
                ? $fabricante
                : null,

        ':valor_unitario' =>
            round(
                (float) $valorUnitario,
                2
            ),

        ':controlado' =>
            (int) $controlado,

        ':ativo' =>
            (int) $ativo
    ];

    if ($medicamentoId > 0) {
        $stmtExistente = $pdo->prepare(
            "
            SELECT id
            FROM medicamentos
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
            "
        );

        $stmtExistente->execute([
            ':id' => $medicamentoId
        ]);

        if (!$stmtExistente->fetchColumn()) {
            $pdo->rollBack();

            responder(
                false,
                'Medicamento não encontrado.',
                [],
                404
            );
        }

        $stmtAtualizar = $pdo->prepare(
            "
            UPDATE medicamentos
            SET
                nome = :nome,
                principio_ativo = :principio_ativo,
                codigo = :codigo,
                apresentacao = :apresentacao,
                concentracao = :concentracao,
                unidade_medida = :unidade_medida,
                via_administracao = :via_administracao,
                dose_padrao = :dose_padrao,
                quantidade_padrao = :quantidade_padrao,
                fabricante = :fabricante,
                valor_unitario = :valor_unitario,
                controlado = :controlado,
                ativo = :ativo
            WHERE id = :id
            "
        );

        $stmtAtualizar->execute(
            $parametros + [
                ':id' => $medicamentoId
            ]
        );

        $pdo->commit();

        sincronizarFrequenciasMedicamento(
            $pdo,
            $medicamentoId,
            $frequenciasPrescricao
        );

        responder(
            true,
            'Medicamento atualizado com sucesso.',
            [
                'medicamento_id' =>
                    $medicamentoId,

                'codigo' =>
                    $codigo,

                'modo' =>
                    'ALTERACAO'
            ]
        );
    }

    $stmtInserir = $pdo->prepare(
        "
        INSERT INTO medicamentos (
            nome,
            principio_ativo,
            codigo,
            apresentacao,
            concentracao,
            unidade_medida,
            via_administracao,
            dose_padrao,
            quantidade_padrao,
            fabricante,
            valor_unitario,
            controlado,
            ativo
        )
        VALUES (
            :nome,
            :principio_ativo,
            :codigo,
            :apresentacao,
            :concentracao,
            :unidade_medida,
            :via_administracao,
            :dose_padrao,
            :quantidade_padrao,
            :fabricante,
            :valor_unitario,
            :controlado,
            :ativo
        )
        "
    );

    $stmtInserir->execute(
        $parametros
    );

    $novoId =
        (int) $pdo->lastInsertId();

    $pdo->commit();

    $medicamentoId = (int) $pdo->lastInsertId();

        sincronizarFrequenciasMedicamento(
            $pdo,
            $medicamentoId,
            $frequenciasPrescricao
        );

        responder(
        true,
        'Medicamento cadastrado com sucesso.',
        [
            'medicamento_id' =>
                $novoId,

            'codigo' =>
                $codigo,

            'modo' =>
                'CADASTRO'
        ],
        201
    );

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro no cadastro de medicamentos: ' .
        $erro->getMessage()
    );

    responder(
        false,
        'Não foi possível salvar o medicamento.',
        [
            'erro' =>
                $erro->getMessage()
        ],
        500
    );
}
