<?php
declare(strict_types=1);

$tituloPagina = 'Prescrições Pendentes';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/farmacia-prescricoes-pendentes.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="pharmacy-page">

    <div class="pharmacy-heading">
        <div>
            <span class="pharmacy-eyebrow">Farmácia</span>
            <h2>Prescrições Pendentes</h2>
            <p>
                Prescrições médicas liberadas para análise e dispensação pela Farmácia.
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

    <section class="pharmacy-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div>
                <h3>Pesquisar prescrição</h3>
                <p>
                    Pesquise pelo número do atendimento, paciente ou número da prescrição.
                </p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-prescription"></i>

                <input
                    type="text"
                    id="campoPesquisa"
                    placeholder="Ex.: AT000009, paciente ou prescrição #12"
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

    <section class="pharmacy-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-clock"></i>
            </div>

            <div>
                <h3>Fila da Farmácia</h3>
                <p>
                    Prescrições liberadas e ainda não concluídas.
                </p>
            </div>
        </div>

        <div id="listaPrescricoes">
            <div class="empty-state">
                Carregando prescrições...
            </div>
        </div>
    </section>

    <section
        id="detalhesCard"
        class="pharmacy-card"
        hidden
    >
        <div class="details-header">
            <div class="section-title no-margin">
                <div class="section-icon">
                    <i class="fa-solid fa-file-prescription"></i>
                </div>

                <div>
                    <h3 id="detalheTitulo">Prescrição</h3>
                    <p id="detalheSubtitulo">Detalhes da prescrição selecionada.</p>
                </div>
            </div>

            <span
                id="detalheStatus"
                class="status-badge"
            ></span>
        </div>

        <div class="patient-grid">
            <div class="info-box">
                <span>Paciente</span>
                <strong id="detalhePaciente">-</strong>
            </div>

            <div class="info-box">
                <span>Atendimento</span>
                <strong id="detalheAtendimento">-</strong>
            </div>

            <div class="info-box">
                <span>Convênio</span>
                <strong id="detalheConvenio">-</strong>
            </div>

            <div class="info-box">
                <span>Data da prescrição</span>
                <strong id="detalheData">-</strong>
            </div>
        </div>

        <div class="items-title">
            <h4>Medicamentos prescritos</h4>
        </div>

        <div id="listaItensPrescricao"></div>

        <div id="observacoesPrescricao" class="prescription-note" hidden></div>

        <div class="details-actions">
            <button
                type="button"
                id="botaoIniciarDispensacao"
                class="primary-button"
            >
                <i class="fa-solid fa-box-open"></i>
                Iniciar dispensação
            </button>
        </div>
    </section>

    <div
        id="mensagemFarmacia"
        class="pharmacy-message"
        hidden
    ></div>

</section>
</main>
</div>

<script src="/safe-health/assets/js/farmacia-prescricoes-pendentes.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
