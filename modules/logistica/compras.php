<?php
declare(strict_types=1);

$tituloPagina = 'Compras';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/logistica-compras.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="purchase-page">

    <div class="purchase-heading">
        <div>
            <span class="purchase-eyebrow">Logística</span>
            <h2>Compras</h2>
            <p>
                Crie solicitações de compra de materiais hospitalares e acompanhe o andamento.
            </p>
        </div>

        <button
            type="button"
            id="botaoNovaCompra"
            class="primary-button"
        >
            <i class="fa-solid fa-plus"></i>
            Nova compra
        </button>
    </div>

    <section class="purchase-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div>
                <h3>Pesquisar compras</h3>
                <p>
                    Pesquise por número da compra, fornecedor ou status.
                </p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-cart-shopping"></i>

                <input
                    type="text"
                    id="campoPesquisa"
                    placeholder="Ex.: 12, fornecedor ou status"
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
                id="botaoLimparPesquisa"
                class="secondary-button"
            >
                <i class="fa-solid fa-eraser"></i>
                Limpar
            </button>
        </div>
    </section>

    <section class="purchase-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-list"></i>
            </div>

            <div>
                <h3>Solicitações de compra</h3>
                <p>
                    Acompanhe compras em rascunho, solicitadas, aprovadas e recebidas.
                </p>
            </div>
        </div>

        <div id="listaCompras">
            <div class="empty-state">
                Carregando compras...
            </div>
        </div>
    </section>

    <form
        id="formCompra"
        class="purchase-card"
        hidden
    >
        <input
            type="hidden"
            id="compraId"
            name="compra_id"
        >

        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-file-circle-plus"></i>
            </div>

            <div>
                <h3 id="tituloFormulario">Nova compra</h3>
                <p>
                    Inclua os materiais necessários e salve a solicitação.
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
                    placeholder="Nome do fornecedor"
                >
            </div>

            <div class="field col-4">
                <label for="numeroDocumento">
                    Nº documento / cotação
                </label>

                <input
                    type="text"
                    id="numeroDocumento"
                    name="numero_documento"
                    maxlength="80"
                >
            </div>

            <div class="field col-2">
                <label for="prioridade">
                    Prioridade
                </label>

                <select
                    id="prioridade"
                    name="prioridade"
                >
                    <option value="ROTINA">Rotina</option>
                    <option value="URGENTE">Urgente</option>
                </select>
            </div>

            <div class="field col-2">
                <label for="status">
                    Status
                </label>

                <select
                    id="status"
                    name="status"
                >
                    <option value="RASCUNHO">Rascunho</option>
                    <option value="SOLICITADA">Solicitada</option>
                    <option value="APROVADA">Aprovada</option>
                    <option value="EM_COMPRA">Em compra</option>
                    <option value="RECEBIDA">Recebida</option>
                    <option value="CANCELADA">Cancelada</option>
                </select>
            </div>

            <div class="field col-12">
                <label for="observacoes">
                    Observações
                </label>

                <textarea
                    id="observacoes"
                    name="observacoes"
                    rows="3"
                    placeholder="Observações gerais da compra..."
                ></textarea>
            </div>
        </div>

        <div class="items-box">
            <div class="items-heading">
                <div>
                    <h4>Itens da compra</h4>
                    <p>Adicione um ou mais materiais hospitalares.</p>
                </div>

                <button
                    type="button"
                    id="botaoAdicionarItem"
                    class="secondary-button"
                >
                    <i class="fa-solid fa-plus"></i>
                    Adicionar item
                </button>
            </div>

            <div id="listaItensCompra"></div>
        </div>

        <div
            id="mensagemCompra"
            class="purchase-message"
            hidden
        ></div>

        <div class="form-actions">
            <button
                type="button"
                id="botaoCancelarEdicao"
                class="secondary-button"
            >
                Cancelar
            </button>

            <button
                type="submit"
                id="botaoSalvarCompra"
                class="primary-button"
            >
                <i class="fa-solid fa-floppy-disk"></i>
                Salvar compra
            </button>
        </div>
    </form>

</section>
</main>
</div>

<script src="/safe-health/assets/js/logistica-compras.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
