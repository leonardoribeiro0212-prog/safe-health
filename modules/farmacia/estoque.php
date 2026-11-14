<?php
declare(strict_types=1);

$tituloPagina = 'Estoque da Farmácia';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/farmacia-estoque.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="stock-page">

    <div class="stock-heading">
        <div>
            <span class="stock-eyebrow">Farmácia</span>
            <h2>Estoque da Farmácia</h2>
            <p>
                Consulte saldos, lotes e validades e registre movimentações de estoque.
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

    <section class="stock-summary">
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

    <section class="stock-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div>
                <h3>Pesquisar estoque</h3>
                <p>Pesquise por medicamento, lote ou localização.</p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-pills"></i>

                <input
                    type="text"
                    id="campoPesquisa"
                    placeholder="Digite medicamento, lote ou localização"
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

    <section class="stock-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>

            <div>
                <h3>Saldo por lote</h3>
                <p>Controle individualizado por medicamento e lote.</p>
            </div>
        </div>

        <div id="listaEstoque">
            <div class="empty-state">Carregando estoque...</div>
        </div>
    </section>

    <section class="stock-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-arrow-right-arrow-left"></i>
            </div>

            <div>
                <h3>Registrar movimentação</h3>
                <p>
                    Use Entrada para recebimento e Ajustes para correções manuais.
                </p>
            </div>
        </div>

        <form id="formMovimentacao">
            <input
                type="hidden"
                id="estoqueId"
                name="estoque_id"
            >

            <div class="form-grid">

                <div class="field col-6">
                    <label for="medicamentoId">
                        Medicamento
                        <span>*</span>
                    </label>

                    <select
                        id="medicamentoId"
                        name="medicamento_id"
                        required
                    >
                        <option value="">Selecione</option>
                    </select>
                </div>

                <div class="field col-3">
                    <label for="lote">
                        Lote
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="lote"
                        name="lote"
                        maxlength="80"
                        required
                    >
                </div>

                <div class="field col-3">
                    <label for="validade">
                        Validade
                    </label>

                    <input
                        type="date"
                        id="validade"
                        name="validade"
                    >
                </div>

                <div class="field col-3">
                    <label for="tipo">
                        Tipo
                        <span>*</span>
                    </label>

                    <select
                        id="tipo"
                        name="tipo"
                        required
                    >
                        <option value="ENTRADA">Entrada</option>
                        <option value="AJUSTE_POSITIVO">Ajuste positivo</option>
                        <option value="AJUSTE_NEGATIVO">Ajuste negativo</option>
                    </select>
                </div>

                <div class="field col-3">
                    <label for="quantidade">
                        Quantidade
                        <span>*</span>
                    </label>

                    <input
                        type="number"
                        id="quantidade"
                        name="quantidade"
                        min="0.001"
                        step="0.001"
                        value="1"
                        required
                    >
                </div>

                <div class="field col-3">
                    <label for="quantidadeMinima">
                        Estoque mínimo
                    </label>

                    <input
                        type="number"
                        id="quantidadeMinima"
                        name="quantidade_minima"
                        min="0"
                        step="0.001"
                        value="0"
                    >
                </div>

                <div class="field col-3">
                    <label for="localizacao">
                        Localização
                    </label>

                    <input
                        type="text"
                        id="localizacao"
                        name="localizacao"
                        maxlength="120"
                        placeholder="Ex.: Prateleira A-03"
                    >
                </div>

                <div class="field col-6">
                    <label for="referencia">
                        Referência
                    </label>

                    <input
                        type="text"
                        id="referencia"
                        name="referencia"
                        maxlength="150"
                        placeholder="Ex.: NF 12345 / Inventário"
                    >
                </div>

                <div class="field col-6">
                    <label for="observacoes">
                        Observações
                    </label>

                    <textarea
                        id="observacoes"
                        name="observacoes"
                        rows="3"
                        placeholder="Observações da movimentação..."
                    ></textarea>
                </div>

            </div>

            <div
                id="mensagemEstoque"
                class="stock-message"
                hidden
            ></div>

            <div class="form-actions">
                <button
                    type="reset"
                    class="secondary-button"
                >
                    <i class="fa-solid fa-eraser"></i>
                    Limpar
                </button>

                <button
                    type="submit"
                    id="botaoSalvar"
                    class="primary-button"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Registrar movimentação
                </button>
            </div>
        </form>
    </section>

    <section class="stock-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>

            <div>
                <h3>Últimas movimentações</h3>
                <p>Histórico recente do estoque da Farmácia.</p>
            </div>
        </div>

        <div id="listaMovimentacoes">
            <div class="empty-state">Carregando movimentações...</div>
        </div>
    </section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/farmacia-estoque.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
