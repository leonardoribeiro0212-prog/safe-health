<?php

$tituloPagina = 'Triagem';
$codigoTela = 'atendimento.triagem';

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<div class="app-content triagem-layout">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>

    <main class="main-content">

        <link
            rel="stylesheet"
            href="/safe-health/modules/atendimento/triagem/triagem.css"
        >

        <section class="triagem-page">

            <section class="summary-grid">

                <article class="summary-card">
                    <div class="summary-icon waiting">
                        <i class="fa-solid fa-users"></i>
                    </div>

                    <div>
                        <span>Aguardando triagem</span>
                        <strong id="totalAguardando">0</strong>
                    </div>
                </article>

                <article class="summary-card">
                    <div class="summary-icon current">
                        <i class="fa-solid fa-stethoscope"></i>
                    </div>

                    <div>
                        <span>Senha selecionada</span>
                        <strong id="senhaSelecionadaResumo">---</strong>
                    </div>
                </article>

                <article class="summary-card">
                    <div class="summary-icon update">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div>
                        <span>Última atualização</span>

                        <strong
                            class="update-time"
                            id="ultimaAtualizacao"
                        >
                            --:--:--
                        </strong>
                    </div>
                </article>

            </section>

            <section class="triagem-content">

                <section class="queue-section">

                    <div class="section-toolbar">

                        <div>
                            <h2>Senhas aguardando triagem</h2>

                            <p>
                                Selecione uma senha para iniciar a avaliação.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="refresh-button"
                            id="botaoAtualizar"
                        >
                            <i class="fa-solid fa-rotate"></i>
                            Atualizar
                        </button>

                    </div>

                    <div class="table-container">

                        <table>
                            <thead>
                                <tr>
                                    <th>Senha</th>
                                    <th>Fila</th>
                                    <th>Emissão</th>
                                    <th>Espera</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>

                            <tbody id="tabelaSenhas">
                                <tr>
                                    <td
                                        colspan="5"
                                        class="empty-row"
                                    >
                                        Carregando senhas...
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                    </div>

                </section>

                <section class="form-section">

                    <div class="form-header">

                        <div>
                            <span class="form-label">
                                Triagem selecionada
                            </span>

                            <h2 id="senhaSelecionada">
                                Nenhuma senha selecionada
                            </h2>

                            <p id="filaSelecionada">
                                Selecione uma senha na lista ao lado.
                            </p>
                        </div>

                        <div
                            class="ticket-badge"
                            id="ticketBadge"
                        >
                            ---
                        </div>

                    </div>

                    <form id="formTriagem">

                        <input
                            type="hidden"
                            id="senhaId"
                            name="senha_id"
                        >

                        <fieldset id="camposTriagem" disabled>

                            <div class="form-group full-width">
                                <label for="queixaPrincipal">
                                    Queixa principal
                                    <span class="required">*</span>
                                </label>

                                <textarea
                                    id="queixaPrincipal"
                                    name="queixa_principal"
                                    rows="4"
                                    maxlength="1000"
                                    required
                                    placeholder="Descreva a principal queixa informada pelo paciente."
                                ></textarea>
                            </div>

                            <div class="form-grid">

                                <div class="form-group">
                                    <label for="pressaoArterial">
                                        Pressão arterial
                                    </label>

                                    <input
                                        type="text"
                                        id="pressaoArterial"
                                        name="pressao_arterial"
                                        maxlength="20"
                                        placeholder="Ex.: 120x80"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="frequenciaCardiaca">
                                        Frequência cardíaca
                                    </label>

                                    <div class="input-unit">
                                        <input
                                            type="number"
                                            id="frequenciaCardiaca"
                                            name="frequencia_cardiaca"
                                            min="0"
                                            max="300"
                                            placeholder="Ex.: 80"
                                        >

                                        <span>bpm</span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="frequenciaRespiratoria">
                                        Frequência respiratória
                                    </label>

                                    <div class="input-unit">
                                        <input
                                            type="number"
                                            id="frequenciaRespiratoria"
                                            name="frequencia_respiratoria"
                                            min="0"
                                            max="100"
                                            placeholder="Ex.: 18"
                                        >

                                        <span>irpm</span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="temperatura">
                                        Temperatura
                                    </label>

                                    <div class="input-unit">
                                        <input
                                            type="number"
                                            id="temperatura"
                                            name="temperatura"
                                            min="25"
                                            max="45"
                                            step="0.1"
                                            placeholder="Ex.: 36.7"
                                        >

                                        <span>°C</span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="saturacao">
                                        Saturação
                                    </label>

                                    <div class="input-unit">
                                        <input
                                            type="number"
                                            id="saturacao"
                                            name="saturacao"
                                            min="0"
                                            max="100"
                                            placeholder="Ex.: 98"
                                        >

                                        <span>%</span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="peso">
                                        Peso
                                    </label>

                                    <div class="input-unit">
                                        <input
                                            type="number"
                                            id="peso"
                                            name="peso"
                                            min="0"
                                            max="500"
                                            step="0.01"
                                            placeholder="Ex.: 75.5"
                                        >

                                        <span>kg</span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="escalaDor">
                                        Escala de dor
                                    </label>

                                    <select
                                        id="escalaDor"
                                        name="escala_dor"
                                    >
                                        <option value="">
                                            Selecione
                                        </option>

                                        <?php for ($dor = 0; $dor <= 10; $dor++): ?>
                                            <option value="<?= $dor ?>">
                                                <?= $dor ?>
                                            </option>
                                        <?php endfor; ?>

                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="classificacaoRisco">
                                        Classificação de risco
                                        <span class="required">*</span>
                                    </label>

                                    <select
                                        id="classificacaoRisco"
                                        name="classificacao_risco"
                                        required
                                    >
                                        <option value="">
                                            Selecione
                                        </option>

                                        <option value="VERMELHO">
                                            Vermelho — Emergência
                                        </option>

                                        <option value="LARANJA">
                                            Laranja — Muito urgente
                                        </option>

                                        <option value="AMARELO">
                                            Amarelo — Urgente
                                        </option>

                                        <option value="VERDE">
                                            Verde — Pouco urgente
                                        </option>

                                        <option value="AZUL">
                                            Azul — Não urgente
                                        </option>
                                    </select>
                                </div>

                            </div>

                            <div class="form-group full-width">
                                <label for="observacoes">
                                    Observações
                                </label>

                                <textarea
                                    id="observacoes"
                                    name="observacoes"
                                    rows="4"
                                    maxlength="2000"
                                    placeholder="Registre informações adicionais da avaliação."
                                ></textarea>
                            </div>

                            <div class="form-actions">

                                <button
                                    type="button"
                                    class="secondary-button"
                                    id="botaoCancelar"
                                >
                                    <i class="fa-solid fa-xmark"></i>
                                    Cancelar
                                </button>

                                <button
                                    type="submit"
                                    class="finish-button"
                                    id="botaoFinalizar"
                                >
                                    <i class="fa-solid fa-check"></i>
                                    Finalizar triagem
                                </button>

                            </div>

                        </fieldset>

                    </form>

                </section>

            </section>

        </section>

        <div
            class="triagem-toast"
            id="toast"
        ></div>

    </main>
</div>

<script src="/safe-health/modules/atendimento/triagem/triagem.js"></script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>