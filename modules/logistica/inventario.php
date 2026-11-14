<?php
declare(strict_types=1);

$tituloPagina = 'Inventário';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/logistica-inventario.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="inventory-page">

    <div class="inventory-heading">
        <div>
            <span class="inventory-eyebrow">Logística</span>
            <h2>Inventário</h2>
            <p>
                Compare o saldo físico com o saldo do sistema e ajuste divergências do Almoxarifado.
            </p>
        </div>

        <button
            type="button"
            id="botaoNovoInventario"
            class="primary-button"
        >
            <i class="fa-solid fa-plus"></i>
            Novo inventário
        </button>
    </div>

    <section class="inventory-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div>
                <h3>Pesquisar material</h3>
                <p>
                    Filtre por nome, código, categoria, lote ou localização.
                </p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-box"></i>

                <input
                    type="text"
                    id="campoPesquisa"
                    placeholder="Digite material, código, lote ou localização"
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

    <form
        id="formInventario"
        class="inventory-card"
        hidden
    >
        <input
            type="hidden"
            id="inventarioId"
            name="inventario_id"
        >

        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>

            <div>
                <h3 id="tituloInventario">Novo inventário</h3>
                <p>
                    Informe a quantidade física encontrada em cada lote.
                </p>
            </div>
        </div>

        <div class="field">
            <label for="observacoes">
                Observações gerais
            </label>

            <textarea
                id="observacoes"
                name="observacoes"
                rows="3"
                placeholder="Ex.: contagem mensal do almoxarifado..."
            ></textarea>
        </div>

        <div id="listaContagem" class="count-list">
            <div class="empty-state">
                Carregando estoque...
            </div>
        </div>

        <div
            id="mensagemInventario"
            class="inventory-message"
            hidden
        ></div>

        <div class="form-actions">
            <button
                type="button"
                id="botaoCancelarInventario"
                class="secondary-button"
            >
                Cancelar
            </button>

            <button
                type="submit"
                id="botaoFinalizarInventario"
                class="primary-button"
            >
                <i class="fa-solid fa-check"></i>
                Finalizar inventário
            </button>
        </div>
    </form>

    <section class="inventory-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>

            <div>
                <h3>Histórico de inventários</h3>
                <p>
                    Consulte inventários já realizados e suas divergências.
                </p>
            </div>
        </div>

        <div id="listaInventarios">
            <div class="empty-state">
                Carregando histórico...
            </div>
        </div>
    </section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/logistica-inventario.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
