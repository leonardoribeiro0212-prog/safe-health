<?php
declare(strict_types=1);

$tituloPagina = 'Aferição';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/enfermagem-afericao.css?v=2">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="nursing-page">

<div class="nursing-heading">
    <span class="nursing-eyebrow">Enfermagem</span>
    <h2>Aferição</h2>
    <p>Pesquise um atendimento ou selecione um paciente com atendimento em aberto.</p>
</div>

<section class="nursing-card">
    <div class="section-title">
        <div class="section-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
        <div>
            <h3>Localizar atendimento</h3>
            <p>Informe o número do atendimento.</p>
        </div>
    </div>

    <div class="search-row">
        <div class="search-input">
            <i class="fa-solid fa-hospital-user"></i>
            <input type="text" id="numeroAtendimento" placeholder="Ex.: AT000009" autocomplete="off">
        </div>

        <button type="button" id="botaoPesquisar" class="primary-button">
            <i class="fa-solid fa-magnifying-glass"></i> Pesquisar
        </button>
    </div>
</section>

<section class="nursing-card">
    <div class="section-title">
        <div class="section-icon"><i class="fa-solid fa-list"></i></div>
        <div>
            <h3>Atendimentos em aberto</h3>
            <p>Pacientes que ainda não receberam alta médica.</p>
        </div>
    </div>

    <div id="listaAtendimentosAbertos" class="open-list">
        <div class="history-empty">Carregando atendimentos...</div>
    </div>
</section>

<section id="dadosPacienteCard" class="nursing-card" hidden>
    <div class="section-title">
        <div class="section-icon"><i class="fa-solid fa-user"></i></div>
        <div>
            <h3>Paciente</h3>
            <p>Dados do atendimento selecionado.</p>
        </div>
    </div>

    <div class="patient-grid">
        <div class="info-box"><span>Paciente</span><strong id="pacienteNome">-</strong></div>
        <div class="info-box"><span>CPF</span><strong id="pacienteCpf">-</strong></div>
        <div class="info-box"><span>Atendimento</span><strong id="atendimentoNumero">-</strong></div>
        <div class="info-box"><span>Convênio</span><strong id="atendimentoConvenio">-</strong></div>
    </div>
</section>

<form id="formAfericao" hidden>
    <input type="hidden" id="atendimentoId" name="atendimento_id">
    <input type="hidden" id="pacienteId" name="paciente_id">

    <section class="nursing-card">
        <div class="section-title">
            <div class="section-icon"><i class="fa-solid fa-heart-pulse"></i></div>
            <div>
                <h3>Nova aferição</h3>
                <p>Registre os sinais vitais atuais.</p>
            </div>
        </div>

        <div class="form-grid">
            <div class="field col-3">
                <label for="pressaoSistolica">Pressão sistólica</label>
                <input type="number" id="pressaoSistolica" name="pressao_sistolica" min="0" max="300" placeholder="mmHg">
            </div>

            <div class="field col-3">
                <label for="pressaoDiastolica">Pressão diastólica</label>
                <input type="number" id="pressaoDiastolica" name="pressao_diastolica" min="0" max="200" placeholder="mmHg">
            </div>

            <div class="field col-3">
                <label for="frequenciaCardiaca">Frequência cardíaca</label>
                <input type="number" id="frequenciaCardiaca" name="frequencia_cardiaca" min="0" max="300" placeholder="bpm">
            </div>

            <div class="field col-3">
                <label for="frequenciaRespiratoria">Frequência respiratória</label>
                <input type="number" id="frequenciaRespiratoria" name="frequencia_respiratoria" min="0" max="100" placeholder="irpm">
            </div>

            <div class="field col-3">
                <label for="temperatura">Temperatura</label>
                <input type="number" id="temperatura" name="temperatura" min="25" max="45" step="0.1" placeholder="°C">
            </div>

            <div class="field col-3">
                <label for="saturacao">Saturação</label>
                <input type="number" id="saturacao" name="saturacao" min="0" max="100" placeholder="%">
            </div>

            <div class="field col-3">
                <label for="glicemia">Glicemia</label>
                <input type="number" id="glicemia" name="glicemia" min="0" max="1000" placeholder="mg/dL">
            </div>

            <div class="field col-3">
                <label for="peso">Peso</label>
                <input type="number" id="peso" name="peso" min="0" max="500" step="0.01" placeholder="kg">
            </div>

            <div class="field col-3">
                <label for="escalaDor">Escala de dor</label>
                <select id="escalaDor" name="escala_dor">
                    <option value="">Não informado</option>
                    <?php for ($i = 0; $i <= 10; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="field col-12">
                <label for="observacoes">Observações</label>
                <textarea id="observacoes" name="observacoes" rows="4" placeholder="Observações da aferição..."></textarea>
            </div>
        </div>
    </section>

    <div id="mensagemAfericao" class="nursing-message" hidden></div>

    <div class="form-actions">
        <button type="reset" class="secondary-button">
            <i class="fa-solid fa-eraser"></i> Limpar
        </button>

        <button type="submit" id="botaoSalvar" class="primary-button">
            <i class="fa-solid fa-floppy-disk"></i> Salvar aferição
        </button>
    </div>
</form>

<section id="historicoCard" class="nursing-card" hidden>
    <div class="section-title">
        <div class="section-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
        <div>
            <h3>Histórico de aferições</h3>
            <p>Registros anteriores deste atendimento.</p>
        </div>
    </div>

    <div id="historicoAfericoes" class="history-list"></div>
</section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/enfermagem-afericao.js?v=2"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
