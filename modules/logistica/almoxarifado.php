<?php
declare(strict_types=1);

$tituloPagina = 'Almoxarifado';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/logistica-almoxarifado.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="warehouse-page">

    <div class="warehouse-heading">
        <div>
            <span class="warehouse-eyebrow">Logística</span>
            <h2>Almoxarifado</h2>
            <p>
                Consulte os materiais hospitalares disponíveis, lotes, validades e níveis de estoque.
            </p>
        </div>

        <button
            type="button"
            id="botaoAtualizar"
            class="secondary-button"
        >
            <i class="fa-solid fa-rotate"></i>
            Atualizar
        </button>
    </div>

    <section class="warehouse-summary">
        <div class="summary-card">
            <span>Itens em estoque</span>
            <strong id="resumoItens">0</strong>
        </div>

        <div class="summary-card">
            <span>Estoque baixo</span>
            <strong id="resumoBaixo">0</strong>
        </div>

        <div class="summary-card">
            <span>Próximos do vencimento</span>
            <strong id="resumoVencimento">0</strong>
        </div>

        <div class="summary-card">
            <span>Sem saldo</span>
            <strong id="resumoSemSaldo">0</strong>
        </div>
    </section>

    <section class="warehouse-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div>
                <h3>Pesquisar materiais</h3>
                <p>
                    Pesquise por nome, código, lote, categoria ou localização.
                </p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-box"></i>

                <input
                    type="text"
                    id="campoPesquisa"
                    placeholder="Digite material, código, lote, categoria ou localização"
                    autocomplete="off"
                >
            </div>

            <button
                type="button"
                id="botaoPesquisar"
                class="primary-button"
            >
                <i class="fa-solid fa-magnifying-glass"></i>
                Pesquisar
            </button>

            <button
                type="button"
                id="botaoLimpar"
                class="secondary-button"
            >
                <i class="fa-solid fa-eraser"></i>
                Limpar
            </button>
        </div>
    </section>

    <section class="warehouse-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>

            <div>
                <h3>Estoque do Almoxarifado</h3>
                <p>
                    Saldo atual por material e lote.
                </p>
            </div>
        </div>

        <div id="listaEstoque">
            <div class="empty-state">
                Carregando estoque...
            </div>
        </div>
    </section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/logistica-almoxarifado.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
