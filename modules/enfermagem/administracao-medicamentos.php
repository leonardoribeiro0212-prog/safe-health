<?php
declare(strict_types=1);

$tituloPagina = 'Administração de Medicamentos';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/enfermagem-administracao-medicamentos.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="nursing-page">

<div class="nursing-heading">
    <span class="nursing-eyebrow">Enfermagem</span>
    <h2>Administração de Medicamentos</h2>
    <p>Selecione um atendimento em aberto e registre a administração dos medicamentos do paciente.</p>
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

    <div id="listaAtendimentosAbertos">
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

<form id="formAdministracao" hidden>
    <input type="hidden" id="atendimentoId" name="atendimento_id">
    <input type="hidden" id="pacienteId" name="paciente_id">

    <section class="nursing-card">
        <div class="section-title">
            <div class="section-icon"><i class="fa-solid fa-syringe"></i></div>
            <div>
                <h3>Registrar administração</h3>
                <p>Nesta etapa, o medicamento é selecionado do cadastro. Quando criarmos a Prescrição Médica, esta tela passará a receber os itens prescritos automaticamente.</p>
            </div>
        </div>

        <div class="form-grid">
            <div class="field col-6">
                <label for="medicamentoId">Medicamento <span>*</span></label>
                <select id="medicamentoId" name="medicamento_id" required>
                    <option value="">Selecione</option>
                </select>
            </div>

            <div class="field col-3">
                <label for="dose">Dose</label>
                <input type="text" id="dose" name="dose" maxlength="80" placeholder="Ex.: 500 mg">
            </div>

            <div class="field col-3">
                <label for="viaAdministracao">Via</label>
                <select id="viaAdministracao" name="via_administracao">
                    <option value="">Selecione</option>
                    <option value="ORAL">Oral</option>
                    <option value="EV">Endovenosa</option>
                    <option value="IM">Intramuscular</option>
                    <option value="SC">Subcutânea</option>
                    <option value="SL">Sublingual</option>
                    <option value="TOPICA">Tópica</option>
                    <option value="INALATORIA">Inalatória</option>
                    <option value="RETAL">Retal</option>
                    <option value="OUTRA">Outra</option>
                </select>
            </div>

            <div class="field col-4">
                <label for="horarioPrevisto">Horário previsto</label>
                <input type="datetime-local" id="horarioPrevisto" name="horario_previsto">
            </div>

            <div class="field col-4">
                <label for="status">Status <span>*</span></label>
                <select id="status" name="status" required>
                    <option value="ADMINISTRADO">Administrado</option>
                    <option value="NAO_ADMINISTRADO">Não administrado</option>
                </select>
            </div>

            <div class="field col-4" id="campoDataAdministracao">
                <label for="dataHoraAdministracao">Data/hora da administração</label>
                <input type="datetime-local" id="dataHoraAdministracao" name="data_hora_administracao">
            </div>

            <div class="field col-12" id="campoMotivo" hidden>
                <label for="motivoNaoAdministracao">Motivo da não administração <span>*</span></label>
                <input
                    type="text"
                    id="motivoNaoAdministracao"
                    name="motivo_nao_administracao"
                    maxlength="255"
                    placeholder="Ex.: paciente recusou, medicamento indisponível..."
                >
            </div>

            <div class="field col-12">
                <label for="observacoes">Observações</label>
                <textarea id="observacoes" name="observacoes" rows="4" placeholder="Observações do registro..."></textarea>
            </div>
        </div>
    </section>

    <div id="mensagemAdministracao" class="nursing-message" hidden></div>

    <div class="form-actions">
        <button type="reset" class="secondary-button">
            <i class="fa-solid fa-eraser"></i> Limpar
        </button>

        <button type="submit" id="botaoSalvar" class="primary-button">
            <i class="fa-solid fa-floppy-disk"></i> Salvar administração
        </button>
    </div>
</form>

<section id="historicoCard" class="nursing-card" hidden>
    <div class="section-title">
        <div class="section-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
        <div>
            <h3>Histórico de administração</h3>
            <p>Registros de medicamentos deste atendimento.</p>
        </div>
    </div>

    <div id="historicoAdministracoes" class="history-list"></div>
</section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/enfermagem-administracao-medicamentos.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
