<?php

declare(strict_types=1);

$tituloPagina = 'Procedimentos / Tabela de Preços';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/permissoes.php';

exigirPermissao(
    $pdo,
    'FATURAMENTO_PROCEDIMENTOS'
);

$convenioId = filter_input(
    INPUT_GET,
    'convenio_id',
    FILTER_VALIDATE_INT
);

$convenios = $pdo->query("
    SELECT id, nome, codigo
    FROM convenios
    WHERE ativo = 1
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

if (!$convenioId && $convenios) {
    $convenioId = (int) $convenios[0]['id'];
}

$convenioAtual = null;

foreach ($convenios as $convenio) {
    if ((int) $convenio['id'] === (int) $convenioId) {
        $convenioAtual = $convenio;
        break;
    }
}

$tipoAtual = strtoupper(
    trim(
        (string) ($_GET['tipo'] ?? 'MEDICAMENTO')
    )
);

$tiposPermitidos = [
    'MEDICAMENTO',
    'EXAME_LABORATORIAL',
    'EXAME_IMAGEM',
    'PROCEDIMENTO'
];

if (!in_array($tipoAtual, $tiposPermitidos, true)) {
    $tipoAtual = 'MEDICAMENTO';
}

$busca = trim((string) ($_GET['q'] ?? ''));

$catalogos = [
    'MEDICAMENTO' => [
        'rotulo' => 'Medicamentos',
        'icone' => 'fa-pills',
        'tabela' => 'medicamentos'
    ],
    'EXAME_LABORATORIAL' => [
        'rotulo' => 'Exames laboratoriais',
        'icone' => 'fa-flask-vial',
        'tabela' => 'exames_laboratoriais'
    ],
    'EXAME_IMAGEM' => [
        'rotulo' => 'Exames de imagem',
        'icone' => 'fa-x-ray',
        'tabela' => 'exames_imagem'
    ],
    'PROCEDIMENTO' => [
        'rotulo' => 'Procedimentos',
        'icone' => 'fa-stethoscope',
        'tabela' => 'procedimentos'
    ]
];

$itens = [];
$resumo = [
    'total' => 0,
    'precificados' => 0,
    'pendentes' => 0
];

if ($convenioAtual) {
    $tabela = $catalogos[$tipoAtual]['tabela'];

    $sql = "
        SELECT
            c.id,
            c.nome,
            c.codigo,
            c.valor_unitario AS valor_base,
            pc.id AS preco_id,
            pc.valor AS valor_convenio,
            pc.exige_autorizacao,
            pc.ativo AS preco_ativo
        FROM {$tabela} c
        LEFT JOIN precos_convenio pc
            ON pc.convenio_id = :convenio_id
           AND pc.tipo_item = :tipo_item
           AND pc.item_id = c.id
        WHERE c.ativo = 1
    ";

    $params = [
        ':convenio_id' => (int) $convenioAtual['id'],
        ':tipo_item' => $tipoAtual
    ];

    if ($busca !== '') {
        $sql .= "
            AND (
                c.nome LIKE :busca
                OR COALESCE(c.codigo, '') LIKE :busca
            )
        ";
        $params[':busca'] = '%' . $busca . '%';
    }

    $sql .= " ORDER BY c.nome";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtResumo = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(
                CASE
                    WHEN pc.id IS NOT NULL
                     AND pc.ativo = 1
                    THEN 1 ELSE 0
                END
            ) AS precificados
        FROM {$tabela} c
        LEFT JOIN precos_convenio pc
            ON pc.convenio_id = :convenio_id
           AND pc.tipo_item = :tipo_item
           AND pc.item_id = c.id
        WHERE c.ativo = 1
    ");
    $stmtResumo->execute([
        ':convenio_id' => (int) $convenioAtual['id'],
        ':tipo_item' => $tipoAtual
    ]);

    $dadosResumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [];
    $resumo['total'] = (int) ($dadosResumo['total'] ?? 0);
    $resumo['precificados'] = (int) ($dadosResumo['precificados'] ?? 0);
    $resumo['pendentes'] = max(
        0,
        $resumo['total'] - $resumo['precificados']
    );
}

function dinheiroTabela(mixed $valor): string
{
    return 'R$ ' . number_format(
        (float) $valor,
        2,
        ',',
        '.'
    );
}
?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/faturamento-procedimentos.css?v=1"
>

<div class="app-content">

    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">

        <section
            class="pricing-page"
            data-convenio-id="<?= (int) ($convenioAtual['id'] ?? 0) ?>"
            data-tipo-item="<?= htmlspecialchars($tipoAtual) ?>"
        >

            <header class="pricing-heading">
                <div>
                    <span class="pricing-eyebrow">
                        Faturamento hospitalar
                    </span>

                    <h2>Procedimentos / Tabela de Preços</h2>

                    <p>
                        Defina quanto cada convênio pagará por medicamentos,
                        exames e procedimentos utilizados na conta hospitalar.
                    </p>
                </div>

                <?php if ($convenioAtual): ?>
                    <div class="pricing-current-plan">
                        <i class="fa-solid fa-building-shield"></i>
                        <div>
                            <span>Convênio selecionado</span>
                            <strong>
                                <?= htmlspecialchars($convenioAtual['nome']) ?>
                            </strong>
                        </div>
                    </div>
                <?php endif; ?>
            </header>

            <section class="pricing-toolbar">

                <form method="get" class="pricing-filters">
                    <input
                        type="hidden"
                        name="tipo"
                        value="<?= htmlspecialchars($tipoAtual) ?>"
                    >

                    <label class="pricing-filter">
                        <span>Convênio</span>

                        <select
                            name="convenio_id"
                            onchange="this.form.submit()"
                        >
                            <?php foreach ($convenios as $convenio): ?>
                                <option
                                    value="<?= (int) $convenio['id'] ?>"
                                    <?= (int) $convenio['id'] === (int) $convenioId
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars($convenio['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="pricing-filter pricing-search">
                        <span>Pesquisar</span>

                        <div>
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input
                                type="search"
                                name="q"
                                value="<?= htmlspecialchars($busca) ?>"
                                placeholder="Nome ou código"
                            >
                        </div>
                    </label>

                    <button class="pricing-search-button" type="submit">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Pesquisar
                    </button>
                </form>

            </section>

            <nav class="pricing-tabs">

                <?php foreach ($catalogos as $codigo => $info): ?>
                    <?php
                    $url = '/safe-health/modules/faturamento/procedimentos.php'
                        . '?convenio_id=' . (int) $convenioId
                        . '&tipo=' . urlencode($codigo);
                    ?>

                    <a
                        href="<?= htmlspecialchars($url) ?>"
                        class="pricing-tab <?= $tipoAtual === $codigo ? 'active' : '' ?>"
                    >
                        <i class="fa-solid <?= htmlspecialchars($info['icone']) ?>"></i>
                        <span><?= htmlspecialchars($info['rotulo']) ?></span>
                    </a>
                <?php endforeach; ?>

            </nav>

            <div class="pricing-summary">
                <article>
                    <span>Itens cadastrados</span>
                    <strong><?= $resumo['total'] ?></strong>
                </article>

                <article class="success">
                    <span>Precificados</span>
                    <strong><?= $resumo['precificados'] ?></strong>
                </article>

                <article class="<?= $resumo['pendentes'] > 0 ? 'warning' : '' ?>">
                    <span>Pendentes de preço</span>
                    <strong><?= $resumo['pendentes'] ?></strong>
                </article>
            </div>

            <section class="pricing-card">

                <div class="pricing-card-heading">
                    <div>
                        <span>
                            <?= htmlspecialchars(
                                $catalogos[$tipoAtual]['rotulo']
                            ) ?>
                        </span>

                        <h3>
                            Preços para
                            <?= htmlspecialchars(
                                $convenioAtual['nome'] ?? 'convênio'
                            ) ?>
                        </h3>
                    </div>

                    <button
                        type="button"
                        id="btnSalvarTodosPrecos"
                        class="pricing-save-all"
                        <?= !$itens ? 'disabled' : '' ?>
                    >
                        <i class="fa-solid fa-floppy-disk"></i>
                        Salvar alterações
                    </button>
                </div>

                <?php if (!$convenioAtual): ?>

                    <div class="pricing-empty">
                        <i class="fa-solid fa-building-circle-exclamation"></i>
                        <strong>Nenhum convênio ativo encontrado.</strong>
                        <span>
                            Cadastre um convênio antes de montar a tabela de preços.
                        </span>
                    </div>

                <?php elseif (!$itens): ?>

                    <div class="pricing-empty">
                        <i class="fa-solid fa-box-open"></i>
                        <strong>Nenhum item encontrado.</strong>
                        <span>
                            Altere os filtros ou cadastre itens no módulo Cadastros.
                        </span>
                    </div>

                <?php else: ?>

                    <div class="pricing-table-wrap">
                        <table class="pricing-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Código</th>
                                    <th>Valor-base</th>
                                    <th>Valor do convênio</th>
                                    <th>Autorização</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($itens as $item): ?>
                                    <?php
                                    $possuiPreco =
                                        $item['preco_id'] !== null
                                        && (int) ($item['preco_ativo'] ?? 1) === 1;
                                    ?>

                                    <tr
                                        class="pricing-row <?= $possuiPreco ? 'priced' : 'pending' ?>"
                                        data-item-id="<?= (int) $item['id'] ?>"
                                    >
                                        <td>
                                            <div class="pricing-item-name">
                                                <div class="pricing-item-icon">
                                                    <i class="fa-solid <?= htmlspecialchars($catalogos[$tipoAtual]['icone']) ?>"></i>
                                                </div>

                                                <div>
                                                    <strong>
                                                        <?= htmlspecialchars($item['nome']) ?>
                                                    </strong>

                                                    <span>
                                                        <?= htmlspecialchars(
                                                            $catalogos[$tipoAtual]['rotulo']
                                                        ) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $item['codigo'] ?: '—'
                                            ) ?>
                                        </td>

                                        <td>
                                            <span class="pricing-base-price">
                                                <?= htmlspecialchars(
                                                    dinheiroTabela(
                                                        $item['valor_base']
                                                    )
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="pricing-value-input">
                                                <span>R$</span>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="js-price-value"
                                                    value="<?= $item['valor_convenio'] !== null
                                                        ? htmlspecialchars(
                                                            number_format(
                                                                (float) $item['valor_convenio'],
                                                                2,
                                                                '.',
                                                                ''
                                                            )
                                                        )
                                                        : '' ?>"
                                                    placeholder="0,00"
                                                >
                                            </div>
                                        </td>

                                        <td>
                                            <label class="pricing-switch">
                                                <input
                                                    type="checkbox"
                                                    class="js-requires-auth"
                                                    <?= (int) ($item['exige_autorizacao'] ?? 0) === 1
                                                        ? 'checked'
                                                        : '' ?>
                                                >
                                                <span></span>
                                                <em>Exigir</em>
                                            </label>
                                        </td>

                                        <td>
                                            <span class="pricing-status <?= $possuiPreco ? 'active' : 'pending' ?>">
                                                <i class="fa-solid <?= $possuiPreco
                                                    ? 'fa-circle-check'
                                                    : 'fa-clock' ?>"></i>

                                                <?= $possuiPreco
                                                    ? 'Precificado'
                                                    : 'Pendente' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            </section>

            <div
                id="pricingFeedback"
                class="pricing-feedback"
                hidden
            ></div>

        </section>

    </main>

</div>

<script src="/safe-health/assets/js/faturamento-procedimentos.js?v=1"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
