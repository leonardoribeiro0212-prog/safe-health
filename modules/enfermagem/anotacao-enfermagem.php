<?php
declare(strict_types=1);

$tituloPagina = 'Anotação de Enfermagem';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/enfermagem-anotacao.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="nursing-page">

<div class="nursing-heading">
    <span class="nursing-eyebrow">Enfermagem</span>
    <h2>Anotação de Enfermagem</h2>
    <p>Selecione um atendimento em aberto ou pesquise pelo número do atendimento.</p>
</div>

<section class="nursing-card">
    <div class="section-title">
        <div class="section-icon">
            <i class="fa-solid fa-magnifying-glass"></i>
        </div>

        <div>
            <h3>Localizar atendimento</h3>
            <p>Informe o número do atendimento.</p>
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

<section class="nursing-card">
    <div class="section-title">
        <div class="section-icon">
            <i class="fa-solid fa-list"></i>
        </div>

        <div>
            <h3>Atendimentos em aberto</h3>
            <p>Pacientes que ainda não receberam alta médica.</p>
        </div>
    </div>

    <div id="listaAtendimentosAbertos" class="open-list">
        <div class="history-empty">
            Carregando atendimentos...
        </div>
    </div>
</section>

<section
    id="dadosPacienteCard"
    class="nursing-card"
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
    id="formAnotacao"
    hidden
>
    <input
        type="hidden"
        id="atendimentoId"
        name="atendimento_id"
    >

    <input
        type="hidden"
        id="pacienteId"
        name="paciente_id"
    >

    <section class="nursing-card">

        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-file-pen"></i>
            </div>

            <div>
                <h3>Nova anotação</h3>
                <p>
                    O registro será incluído no histórico e não substituirá anotações anteriores.
                </p>
            </div>
        </div>

        <div class="field">
            <label for="conteudo">
                Anotação de enfermagem
                <span>*</span>
            </label>

            <textarea
                id="conteudo"
                name="conteudo"
                rows="8"
                maxlength="10000"
                placeholder="Digite a anotação de enfermagem..."
                required
            ></textarea>

            <small id="contadorCaracteres">
                0 / 10000 caracteres
            </small>
        </div>

    </section>

    <div
        id="mensagemAnotacao"
        class="nursing-message"
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
            Salvar anotação
        </button>
    </div>
</form>

<section
    id="historicoCard"
    class="nursing-card"
    hidden
>
    <div class="section-title">
        <div class="section-icon">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>

        <div>
            <h3>Histórico de anotações</h3>
            <p>
                Registros cronológicos deste atendimento.
            </p>
        </div>
    </div>

    <div
        id="historicoAnotacoes"
        class="history-list"
    ></div>
</section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/enfermagem-anotacao.js?v=1"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
