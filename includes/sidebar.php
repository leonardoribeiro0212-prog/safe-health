<?php

require_once __DIR__ . '/permissoes.php';

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
$telasPermitidas = buscarMenuUsuario($pdo, $usuarioId);

$menuAgrupado = [];

foreach ($telasPermitidas as $tela) {
    $modulo = $tela['modulo'] ?: 'Outros';
    $menuAgrupado[$modulo][] = $tela;
}

/* Ordena somente os módulos principais; mantém a ordem interna das telas. */
uksort($menuAgrupado, static function (string $a, string $b): int {
    $normalizar = static function (string $texto): string {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        return strtolower($ascii !== false ? $ascii : $texto);
    };
    return strnatcasecmp($normalizar($a), $normalizar($b));
});

$caminhoAtual = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$iconesModulos = [
    'Dashboard'   => 'fa-house',
    'Atendimento' => 'fa-ticket',
    'Enfermagem'  => 'fa-stethoscope',
    'Prontuário'  => 'fa-file-medical',
    'Farmácia'    => 'fa-pills',
    'Faturamento' => 'fa-file-invoice-dollar',
    'Logística'   => 'fa-box',
    'Cadastros'   => 'fa-address-book',
    'Tecnologia'  => 'fa-user-shield',
    'Relatórios'  => 'fa-chart-column'
];

function telaEstaAtiva(?string $url, string $caminhoAtual): bool
{
    if (empty($url)) {
        return false;
    }

    $caminhoTela = parse_url($url, PHP_URL_PATH);

    if (empty($caminhoTela)) {
        return false;
    }

    return $caminhoTela === $caminhoAtual;
}

function moduloEstaAtivo(array $telas, string $caminhoAtual): bool
{
    foreach ($telas as $tela) {
        $url = $tela['url'] ?? null;

        if (telaEstaAtiva($url, $caminhoAtual)) {
            return true;
        }
    }

    return false;
}
?>

<aside class="sidebar">

    <div class="sidebar-brand">
        <div class="sidebar-logo">SH</div>

        <div class="sidebar-brand-text">
            <strong>Safe Health</strong>
           
        </div>
    </div>

    <nav class="sidebar-menu">

        <?php foreach ($menuAgrupado as $modulo => $telas): ?>

            <?php
            $iconeModulo = $iconesModulos[$modulo] ?? 'fa-folder';
            $moduloAtivo = moduloEstaAtivo($telas, $caminhoAtual);
            ?>

            <?php if ($modulo === 'Dashboard'): ?>

                <?php foreach ($telas as $tela): ?>

                    <?php
                    $urlTela = $tela['url'] ?? '#';
                    $novaAba = (int) ($tela['abrir_nova_aba'] ?? 0) === 1;
                    ?>

                    <a
                        href="<?= htmlspecialchars($urlTela) ?>"
                        class="sidebar-link <?= telaEstaAtiva($urlTela, $caminhoAtual) ? 'active' : '' ?>"
                        <?= $novaAba ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                    >
                        <span class="sidebar-icon">
                            <i class="fa-solid <?= htmlspecialchars($iconeModulo) ?>"></i>
                        </span>

                        <span><?= htmlspecialchars($tela['nome']) ?></span>

                        <?php if ($novaAba): ?>
                            <i
                                class="fa-solid fa-arrow-up-right-from-square"
                                aria-hidden="true"
                            ></i>
                        <?php endif; ?>
                    </a>

                <?php endforeach; ?>

            <?php elseif ($modulo === 'Prontuário'): ?>

                <?php
                $urlProntuario = '/safe-health/modules/prontuario/atendimentos.php';
                $prontuarioAtivo = strpos($caminhoAtual, '/safe-health/modules/prontuario/') === 0;
                ?>

                <a
                    href="<?= htmlspecialchars($urlProntuario) ?>"
                    class="sidebar-link <?= $prontuarioAtivo ? 'active' : '' ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <span class="sidebar-icon">
                        <i class="fa-solid <?= htmlspecialchars($iconeModulo) ?>"></i>
                    </span>

                    <span>Prontuário</span>
                </a>

            <?php else: ?>

                <?php
                $submenuId = 'submenu-' . preg_replace(
                    '/[^a-z0-9]+/',
                    '-',
                    strtolower(
                        iconv(
                            'UTF-8',
                            'ASCII//TRANSLIT',
                            $modulo
                        )
                    )
                );

                $gruposInternos = [];
                $telasDiretas = [];

                foreach ($telas as $tela) {
                    $grupoMenu = trim($tela['grupo_menu'] ?? '');

                    if ($grupoMenu !== '') {
                        $gruposInternos[$grupoMenu][] = $tela;
                    } else {
                        $telasDiretas[] = $tela;
                    }
                }
                ?>

                <button
                    type="button"
                    class="sidebar-group-button <?= $moduloAtivo ? 'open' : '' ?>"
                    onclick="alternarSubmenu(
                        '<?= htmlspecialchars($submenuId) ?>',
                        this
                    )"
                >
                    <span class="sidebar-group-title">

                        <span class="sidebar-icon">
                            <i class="fa-solid <?= htmlspecialchars($iconeModulo) ?>"></i>
                        </span>

                        <span><?= htmlspecialchars($modulo) ?></span>

                    </span>

                    <i class="fa-solid fa-chevron-right sidebar-arrow"></i>
                </button>

                <div
                    id="<?= htmlspecialchars($submenuId) ?>"
                    class="sidebar-submenu <?= $moduloAtivo ? 'open' : '' ?>"
                >

                    <?php foreach ($gruposInternos as $nomeGrupo => $telasGrupo): ?>

                        <?php
                        $grupoId = 'grupo-' . preg_replace(
                            '/[^a-z0-9]+/',
                            '-',
                            strtolower(
                                iconv(
                                    'UTF-8',
                                    'ASCII//TRANSLIT',
                                    $modulo . '-' . $nomeGrupo
                                )
                            )
                        );

                        $grupoAtivo = moduloEstaAtivo(
                            $telasGrupo,
                            $caminhoAtual
                        );
                        ?>

                        <button
                            type="button"
                            class="sidebar-nested-button <?= $grupoAtivo ? 'open' : '' ?>"
                            onclick="alternarGrupoInterno(
                                '<?= htmlspecialchars($grupoId) ?>',
                                this
                            )"
                        >
                            <span><?= htmlspecialchars($nomeGrupo) ?></span>

                            <i
                                class="fa-solid fa-chevron-right sidebar-nested-arrow"
                            ></i>
                        </button>

                        <div
                            id="<?= htmlspecialchars($grupoId) ?>"
                            class="sidebar-nested-submenu <?= $grupoAtivo ? 'open' : '' ?>"
                        >

                            <?php foreach ($telasGrupo as $tela): ?>

                                <?php
                                $urlTela = $tela['url'] ?? '#';

                                $novaAba =
                                    (int) ($tela['abrir_nova_aba'] ?? 0) === 1;
                                ?>

                                <a
                                    href="<?= htmlspecialchars($urlTela) ?>"
                                    class="<?= telaEstaAtiva($urlTela, $caminhoAtual) ? 'active' : '' ?>"
                                    <?= $novaAba ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                                >
                                    <span>
                                        <?= htmlspecialchars($tela['nome']) ?>
                                    </span>

                                    <?php if ($novaAba): ?>
                                        <i
                                            class="fa-solid fa-arrow-up-right-from-square"
                                            aria-hidden="true"
                                        ></i>
                                    <?php endif; ?>
                                </a>

                            <?php endforeach; ?>

                        </div>

                    <?php endforeach; ?>

                    <?php foreach ($telasDiretas as $tela): ?>

                        <?php
                        $urlTela = $tela['url'] ?? '#';

                        $novaAba =
                            (int) ($tela['abrir_nova_aba'] ?? 0) === 1;
                        ?>

                        <a
                            href="<?= htmlspecialchars($urlTela) ?>"
                            class="<?= telaEstaAtiva($urlTela, $caminhoAtual) ? 'active' : '' ?>"
                            <?= $novaAba ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                        >
                            <span><?= htmlspecialchars($tela['nome']) ?></span>

                            <?php if ($novaAba): ?>
                                <i
                                    class="fa-solid fa-arrow-up-right-from-square"
                                    aria-hidden="true"
                                ></i>
                            <?php endif; ?>
                        </a>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        <?php endforeach; ?>

    </nav>

    <div class="sidebar-footer">

        <a href="/safe-health/logout.php" class="sidebar-link logout-link">

            <span class="sidebar-icon">
                <i class="fa-solid fa-right-from-bracket"></i>
            </span>

            <span>Sair</span>

        </a>

    </div>

</aside>

<script>
function alternarSubmenu(id, botao) {
    const submenu = document.getElementById(id);

    if (!submenu) {
        return;
    }

    submenu.classList.toggle('open');
    botao.classList.toggle('open');
}

function alternarGrupoInterno(id, botao) {
    const submenu = document.getElementById(id);

    if (!submenu) {
        return;
    }

    submenu.classList.toggle('open');
    botao.classList.toggle('open');
}
</script>