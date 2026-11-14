<?php
declare(strict_types=1);

$tituloPagina = 'Solicitações da Farmácia';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/farmacia-solicitacoes.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="pharmacy-page">

    <div class="pharmacy-heading">
        <div>
            <span class="pharmacy-eyebrow">Farmácia</span>
            <h2>Solicitações da Farmácia</h2>
            <p>
                Registre solicitações de medicamentos e materiais vinculadas ao atendimento do paciente.
            </p>
        </div>
    </div>

    <section class="pharmacy-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div>
                <h3>Localizar atendimento</h3>
                <p>Pesquise pelo número do atendimento.</p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-hospital-user"></i>

                <input
                    type="text"
                    id="numeroAtendimento"
                    placeholder="Ex.: AT000009"
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
        </div>
    </section>

    <section class="pharmacy-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-list"></i>
            </div>

            <div>
                <h3>Atendimentos em aberto</h3>
                <p>Selecione um paciente para criar uma solicitação.</p>
            </div>
        </div>

        <div id="listaAtendimentosAbertos">
            <div class="empty-state">Carregando atendimentos...</div>
        </div>
    </section>

    <section
        id="dadosPacienteCard"
        class="pharmacy-card"
        hidden
    >
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-user"></i>
            </div>

            <div>
                <h3>Paciente</h3>
                <p>Dados do atendimento selecionado.</p>
            </div>
        </div>

        <div class="patient-grid">
            <div class="info-box">
                <span>Paciente</span>
                <strong id="pacienteNome">-</strong>
            </div>

            <div class="info-box">
                <span>CPF</span>
                <strong id="pacienteCpf">-</strong>
            </div>

            <div class="info-box">
                <span>Atendimento</span>
                <strong id="atendimentoNumero">-</strong>
            </div>

            <div class="info-box">
                <span>Convênio</span>
                <strong id="atendimentoConvenio">-</strong>
            </div>
        </div>
    </section>

    <form
        id="formSolicitacao"
        hidden
    >
        <input type="hidden" id="atendimentoId" name="atendimento_id">
        <input type="hidden" id="pacienteId" name="paciente_id">

        <section class="pharmacy-card">
            <div class="section-title">
                <div class="section-icon">
                    <i class="fa-solid fa-cart-plus"></i>
                </div>

                <div>
                    <h3>Nova solicitação</h3>
                    <p>
                        Escolha um medicamento ou material hospitalar e informe a quantidade.
                    </p>
                </div>
            </div>

            <div class="form-grid">

                <div class="field col-3">
                    <label for="tipoSolicitacao">
                        Tipo
                        <span>*</span>
                    </label>

                    <select
                        id="tipoSolicitacao"
                        name="tipo_solicitacao"
                        required
                    >
                        <option value="MEDICAMENTO">Medicamento</option>
                        <option value="MATERIAL">Material hospitalar</option>
                    </select>
                </div>

                <div class="field col-6" id="campoMedicamento">
                    <label for="medicamentoId">
                        Medicamento
                        <span>*</span>
                    </label>

                    <select
                        id="medicamentoId"
                        name="medicamento_id"
                    >
                        <option value="">Selecione</option>
                    </select>
                </div>

                <div class="field col-6" id="campoMaterial" hidden>
                    <label for="materialId">
                        Material hospitalar
                        <span>*</span>
                    </label>

                    <select
                        id="materialId"
                        name="material_id"
                    >
                        <option value="">Selecione</option>
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

                <div class="field col-12">
                    <label for="observacoes">
                        Observações
                    </label>

                    <textarea
                        id="observacoes"
                        name="observacoes"
                        rows="4"
                        placeholder="Observações da solicitação..."
                    ></textarea>
                </div>

            </div>
        </section>

        <div
            id="mensagemSolicitacao"
            class="pharmacy-message"
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
                Salvar solicitação
            </button>
        </div>
    </form>

    <section
        id="historicoCard"
        class="pharmacy-card"
        hidden
    >
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>

            <div>
                <h3>Solicitações do atendimento</h3>
                <p>Histórico de solicitações vinculadas a este atendimento.</p>
            </div>
        </div>

        <div id="historicoSolicitacoes"></div>
    </section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/farmacia-solicitacoes.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
