<?php
declare(strict_types=1);

$tituloPagina = 'Dispensação';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$prescricaoId = isset($_GET['prescricao_id'])
    ? (int) $_GET['prescricao_id']
    : 0;
?>
<link rel="stylesheet" href="/safe-health/assets/css/farmacia-dispensacao.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="pharmacy-page">

    <div class="pharmacy-heading">
        <div>
            <span class="pharmacy-eyebrow">Farmácia</span>
            <h2>Dispensação</h2>
            <p>
                Confira os medicamentos prescritos e registre a quantidade efetivamente dispensada.
            </p>
        </div>
    </div>

    <section class="pharmacy-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div>
                <h3>Localizar prescrição</h3>
                <p>Informe o número da prescrição ou do atendimento.</p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-file-prescription"></i>

                <input
                    type="text"
                    id="campoPesquisa"
                    placeholder="Ex.: 12 ou AT000009"
                    autocomplete="off"
                    value="<?= $prescricaoId > 0 ? htmlspecialchars((string) $prescricaoId) : '' ?>"
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

    <section
        id="dadosPrescricaoCard"
        class="pharmacy-card"
        hidden
    >
        <div class="details-header">
            <div class="section-title no-margin">
                <div class="section-icon">
                    <i class="fa-solid fa-prescription-bottle-medical"></i>
                </div>

                <div>
                    <h3 id="tituloPrescricao">Prescrição</h3>
                    <p id="subtituloPrescricao">Dados da prescrição selecionada.</p>
                </div>
            </div>

            <span
                id="statusPrescricao"
                class="status-badge"
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
                <strong id="atendimentoConvenio">-</strong>
            </div>

            <div class="info-box">
                <span>Data da prescrição</span>
                <strong id="dataPrescricao">-</strong>
            </div>
        </div>
    </section>

    <section
        id="itensCard"
        class="pharmacy-card"
        hidden
    >
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-box-open"></i>
            </div>

            <div>
                <h3>Itens para dispensação</h3>
                <p>
                    Registre cada item separadamente. Itens já dispensados permanecem disponíveis para consulta.
                </p>
            </div>
        </div>

        <div id="listaItensDispensacao"></div>
    </section>

    <div
        id="mensagemDispensacao"
        class="pharmacy-message"
        hidden
    ></div>

</section>
</main>
</div>

<script>
window.SAFE_HEALTH_PRESCRICAO_INICIAL = <?= $prescricaoId ?>;
</script>
<script src="/safe-health/assets/js/farmacia-dispensacao.js?v=1"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
