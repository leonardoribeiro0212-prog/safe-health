<?php
declare(strict_types=1);

$tituloPagina = 'Saída de Estoque';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/logistica-saida-estoque.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="exit-page">

    <div class="exit-heading">
        <div>
            <span class="exit-eyebrow">Logística</span>
            <h2>Saída de Estoque</h2>
            <p>
                Registre a retirada de materiais do Almoxarifado para setores ou atendimentos.
            </p>
        </div>
    </div>

    <section class="exit-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-hospital-user"></i>
            </div>

            <div>
                <h3>Vincular atendimento</h3>
                <p>
                    Opcional. Use quando a saída estiver relacionada diretamente a um paciente.
                </p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    id="numeroAtendimento"
                    placeholder="Ex.: AT000009"
                    autocomplete="off"
                >
            </div>

            <button
                type="button"
                id="botaoBuscarAtendimento"
                class="primary-button"
            >
                <i class="fa-solid fa-magnifying-glass"></i>
                Buscar
            </button>

            <button
                type="button"
                id="botaoLimparAtendimento"
                class="secondary-button"
            >
                <i class="fa-solid fa-xmark"></i>
                Sem atendimento
            </button>
        </div>

        <div
            id="dadosAtendimento"
            class="attendance-summary"
            hidden
        >
            <div>
                <span>Paciente</span>
                <strong id="pacienteNome">-</strong>
            </div>

            <div>
                <span>Atendimento</span>
                <strong id="atendimentoNumero">-</strong>
            </div>

            <div>
                <span>Convênio</span>
                <strong id="atendimentoConvenio">-</strong>
            </div>

            <div>
                <span>Status</span>
                <strong id="atendimentoStatus">-</strong>
            </div>
        </div>
    </section>

    <form id="formSaida" class="exit-card">
        <input type="hidden" id="atendimentoId" name="atendimento_id">
        <input type="hidden" id="pacienteId" name="paciente_id">

        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-arrow-up-from-bracket"></i>
            </div>

            <div>
                <h3>Nova saída</h3>
                <p>
                    Selecione os lotes e informe as quantidades que serão retiradas.
                </p>
            </div>
        </div>

        <div class="form-grid">
            <div class="field col-4">
                <label for="destino">
                    Destino
                    <span>*</span>
                </label>

                <input
                    type="text"
                    id="destino"
                    name="destino"
                    maxlength="150"
                    placeholder="Ex.: Centro Cirúrgico, Enfermagem, UTI"
                    required
                >
            </div>

            <div class="field col-4">
                <label for="motivo">
                    Motivo
                </label>

                <input
                    type="text"
                    id="motivo"
                    name="motivo"
                    maxlength="150"
                    placeholder="Ex.: consumo assistencial"
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
                    <h4>Materiais da saída</h4>
                    <p>
                        Somente lotes com saldo disponível podem ser utilizados.
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

            <div id="listaItensSaida"></div>
        </div>

        <div
            id="mensagemSaida"
            class="exit-message"
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
                Registrar saída
            </button>
        </div>
    </form>

    <section class="exit-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>

            <div>
                <h3>Últimas saídas</h3>
                <p>
                    Histórico recente de retiradas do Almoxarifado.
                </p>
            </div>
        </div>

        <div id="listaSaidas">
            <div class="empty-state">
                Carregando saídas...
            </div>
        </div>
    </section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/logistica-saida-estoque.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
