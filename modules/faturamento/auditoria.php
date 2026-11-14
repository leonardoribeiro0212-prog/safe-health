<?php
declare(strict_types=1);

$tituloPagina = 'Auditoria';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/faturamento-auditoria.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="audit-page">

    <div class="audit-heading">
        <div>
            <span class="audit-eyebrow">Faturamento</span>
            <h2>Auditoria</h2>
            <p>
                Revise os lançamentos das contas encaminhadas para auditoria antes do fechamento.
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

    <section class="audit-summary">
        <div class="summary-card">
            <span>Contas em auditoria</span>
            <strong id="resumoContas">0</strong>
        </div>

        <div class="summary-card">
            <span>Itens pendentes</span>
            <strong id="resumoPendentes">0</strong>
        </div>

        <div class="summary-card">
            <span>Itens auditados</span>
            <strong id="resumoAuditados">0</strong>
        </div>

        <div class="summary-card">
            <span>Itens glosados</span>
            <strong id="resumoGlosados">0</strong>
        </div>
    </section>

    <section class="audit-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div>
                <h3>Pesquisar conta</h3>
                <p>
                    Pesquise pelo número da conta, atendimento ou paciente.
                </p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-file-invoice-dollar"></i>

                <input
                    type="text"
                    id="campoPesquisa"
                    placeholder="Ex.: #3, AT000009 ou nome do paciente"
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

    <section class="audit-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-list-check"></i>
            </div>

            <div>
                <h3>Fila de auditoria</h3>
                <p>
                    Contas com status EM_AUDITORIA.
                </p>
            </div>
        </div>

        <div id="listaContas">
            <div class="empty-state">
                Carregando contas...
            </div>
        </div>
    </section>

    <section
        id="detalhesCard"
        class="audit-card"
        hidden
    >
        <div class="details-header">
            <div class="section-title no-margin">
                <div class="section-icon">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>

                <div>
                    <h3 id="tituloConta">Auditoria da conta</h3>
                    <p id="subtituloConta">
                        Revise todos os lançamentos antes de concluir.
                    </p>
                </div>
            </div>

            <span
                id="statusConta"
                class="status-badge account"
            ></span>
        </div>

        <div class="patient-grid">
            <div class="info-box">
                <span>Paciente</span>
                <strong id="pacienteNome">-</strong>
            </div>

            <div class="info-box">
                <span>Atendimento</span>
                <strong id="atendimentoNumero">-</strong>
            </div>

            <div class="info-box">
                <span>Convênio</span>
                <strong id="convenio">-</strong>
            </div>

            <div class="info-box">
                <span>Abertura da conta</span>
                <strong id="dataAbertura">-</strong>
            </div>
        </div>

        <div class="financial-grid">
            <div class="financial-box">
                <span>Valor bruto</span>
                <strong id="valorBruto">R$ 0,00</strong>
            </div>

            <div class="financial-box glosa">
                <span>Glosas</span>
                <strong id="valorDesconto">R$ 0,00</strong>
            </div>

            <div class="financial-box total">
                <span>Valor líquido</span>
                <strong id="valorLiquido">R$ 0,00</strong>
            </div>
        </div>

        <div class="items-heading">
            <div>
                <h4>Itens da conta</h4>
                <p>
                    Marque cada lançamento como Auditado ou Glosado.
                </p>
            </div>

            <div class="legend">
                <span class="status-badge pending">Pendente</span>
                <span class="status-badge audited">Auditado</span>
                <span class="status-badge denied">Glosado</span>
            </div>
        </div>

        <div id="listaItens"></div>

        <div class="audit-conclusion">
            <label for="observacaoFinal">
                Observação final da auditoria
            </label>

            <textarea
                id="observacaoFinal"
                rows="4"
                maxlength="5000"
                placeholder="Observações gerais da auditoria..."
            ></textarea>
        </div>

        <div
            id="mensagemAuditoria"
            class="audit-message"
            hidden
        ></div>

        <div class="details-actions">
            <a
                id="linkContaHospitalar"
                class="secondary-link"
                href="#"
            >
                <i class="fa-solid fa-folder-open"></i>
                Abrir Conta Hospitalar
            </a>

            <button
                type="button"
                id="botaoConcluir"
                class="primary-button"
            >
                <i class="fa-solid fa-circle-check"></i>
                Concluir auditoria
            </button>
        </div>
    </section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/faturamento-auditoria.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
