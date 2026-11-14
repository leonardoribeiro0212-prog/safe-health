<?php
declare(strict_types=1);

$tituloPagina = 'Entrada de Estoque';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/logistica-entrada-estoque.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="entry-page">

    <div class="entry-heading">
        <div>
            <span class="entry-eyebrow">Logística</span>
            <h2>Entrada de Estoque</h2>
            <p>
                Registre o recebimento de materiais e atualize automaticamente o Almoxarifado.
            </p>
        </div>
    </div>

    <section class="entry-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-cart-shopping"></i>
            </div>

            <div>
                <h3>Receber compra</h3>
                <p>
                    Você pode carregar uma compra existente ou fazer uma entrada avulsa.
                </p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-file-invoice"></i>

                <input
                    type="text"
                    id="campoCompra"
                    placeholder="Ex.: compra #12"
                    autocomplete="off"
                >
            </div>

            <button
                type="button"
                id="botaoBuscarCompra"
                class="primary-button"
            >
                <i class="fa-solid fa-magnifying-glass"></i>
                Buscar compra
            </button>

            <button
                type="button"
                id="botaoEntradaAvulsa"
                class="secondary-button"
            >
                <i class="fa-solid fa-plus"></i>
                Entrada avulsa
            </button>
        </div>

        <div
            id="listaCompras"
            class="purchase-list"
        ></div>
    </section>

    <form
        id="formEntrada"
        class="entry-card"
        hidden
    >
        <input
            type="hidden"
            id="compraId"
            name="compra_id"
        >

        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-box-open"></i>
            </div>

            <div>
                <h3 id="tituloEntrada">Nova entrada</h3>
                <p>
                    Informe os lotes, validades e quantidades recebidas.
                </p>
            </div>
        </div>

        <div class="form-grid">
            <div class="field col-4">
                <label for="fornecedor">
                    Fornecedor
                </label>

                <input
                    type="text"
                    id="fornecedor"
                    name="fornecedor"
                    maxlength="150"
                >
            </div>

            <div class="field col-4">
                <label for="numeroDocumento">
                    Nota fiscal / documento
                </label>

                <input
                    type="text"
                    id="numeroDocumento"
                    name="numero_documento"
                    maxlength="80"
                >
            </div>

            <div class="field col-4">
                <label for="observacoes">
                    Observações
                </label>

                <input
                    type="text"
                    id="observacoes"
                    name="observacoes"
                    maxlength="255"
                >
            </div>
        </div>

        <div class="items-box">
            <div class="items-heading">
                <div>
                    <h4>Materiais recebidos</h4>
                    <p>
                        Cada lote será controlado separadamente no Almoxarifado.
                    </p>
                </div>

                <button
                    type="button"
                    id="botaoAdicionarItem"
                    class="secondary-button"
                >
                    <i class="fa-solid fa-plus"></i>
                    Adicionar material
                </button>
            </div>

            <div id="listaItensEntrada"></div>
        </div>

        <div
            id="mensagemEntrada"
            class="entry-message"
            hidden
        ></div>

        <div class="form-actions">
            <button
                type="button"
                id="botaoCancelar"
                class="secondary-button"
            >
                Cancelar
            </button>

            <button
                type="submit"
                id="botaoSalvar"
                class="primary-button"
            >
                <i class="fa-solid fa-floppy-disk"></i>
                Registrar entrada
            </button>
        </div>
    </form>

    <section class="entry-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>

            <div>
                <h3>Últimas entradas</h3>
                <p>
                    Histórico recente de recebimentos do Almoxarifado.
                </p>
            </div>
        </div>

        <div id="listaEntradas">
            <div class="empty-state">
                Carregando entradas...
            </div>
        </div>
    </section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/logistica-entrada-estoque.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
