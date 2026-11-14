<?php

declare(strict_types=1);

$tituloPagina = 'Cadastro de Procedimentos';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/cadastro-procedimentos.css?v=1"
>

<div class="app-content">

    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">

        <section class="procedure-page">

            <div class="procedure-heading">

                <div>
                    <span class="procedure-eyebrow">
                        Cadastros
                    </span>

                    <h2>Cadastro de Procedimentos</h2>

                    <p>
                        Cadastre procedimentos hospitalares ou pesquise
                        um registro existente para atualizar seus dados.
                    </p>
                </div>

                <div
                    id="modoCadastro"
                    class="procedure-mode-badge"
                >
                    Novo cadastro
                </div>

            </div>

            <section class="procedure-search-card">

                <div>
                    <h3>Pesquisar procedimento</h3>

                    <p>
                        Pesquise por nome, código ou categoria.
                    </p>
                </div>

                <div class="procedure-search-row">

                    <div class="procedure-search-input">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            id="pesquisaProcedimento"
                            placeholder="Digite nome, código ou categoria"
                            autocomplete="off"
                        >
                    </div>

                    <button
                        type="button"
                        id="botaoPesquisarProcedimento"
                        class="procedure-search-button"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Pesquisar
                    </button>

                    <button
                        type="button"
                        id="botaoNovoProcedimento"
                        class="procedure-secondary-button"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Novo procedimento
                    </button>

                </div>

                <div
                    id="resultadosPesquisa"
                    class="procedure-search-results"
                    hidden
                ></div>

            </section>

            <form id="formProcedimento">

                <input
                    type="hidden"
                    id="procedimentoId"
                    name="procedimento_id"
                    value=""
                >

                <section class="procedure-card">

                    <div class="procedure-section-title">

                        <div class="procedure-section-icon">
                            <i class="fa-solid fa-notes-medical"></i>
                        </div>

                        <div>
                            <h3>Identificação do procedimento</h3>

                            <p>
                                Dados utilizados no prontuário,
                                assistência e faturamento hospitalar.
                            </p>
                        </div>

                    </div>

                    <div class="procedure-form-grid">

                        <div class="procedure-field col-8">
                            <label for="nome">
                                Nome do procedimento
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                maxlength="150"
                                placeholder="Ex.: Curativo simples"
                                required
                            >
                        </div>

                        <div class="procedure-field col-4">
                            <label for="codigo">
                                Código
                            </label>

                            <input
                                type="text"
                                id="codigo"
                                name="codigo"
                                maxlength="60"
                                placeholder="Ex.: PROC_0001"
                            >
                        </div>

                        <div class="procedure-field col-6">
                            <label for="categoria">
                                Categoria
                            </label>

                            <select
                                id="categoria"
                                name="categoria"
                            >
                                <option value="">Selecione</option>
                                <option value="ENFERMAGEM">Enfermagem</option>
                                <option value="MEDICO">Médico</option>
                                <option value="CIRURGICO">Cirúrgico</option>
                                <option value="DIAGNOSTICO">Diagnóstico</option>
                                <option value="TERAPEUTICO">Terapêutico</option>
                                <option value="FISIOTERAPIA">Fisioterapia</option>
                                <option value="AMBULATORIAL">Ambulatorial</option>
                                <option value="OUTRO">Outro</option>
                            </select>
                        </div>

                        <div class="procedure-field col-3">
                            <label for="duracaoEstimada">
                                Duração estimada (min)
                            </label>

                            <input
                                type="number"
                                id="duracaoEstimada"
                                name="duracao_estimada_minutos"
                                min="0"
                                step="1"
                            >
                        </div>

                        <div class="procedure-field col-3">
                            <label for="ativo">
                                Status
                                <span>*</span>
                            </label>

                            <select
                                id="ativo"
                                name="ativo"
                                required
                            >
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>

                        <div class="procedure-field col-12">
                            <label for="descricao">
                                Descrição / observações
                            </label>

                            <textarea
                                id="descricao"
                                name="descricao"
                                rows="4"
                                placeholder="Descreva o procedimento, quando necessário."
                            ></textarea>
                        </div>

                    </div>

                </section>

                <section class="procedure-card">

                    <div class="procedure-section-title">

                        <div class="procedure-section-icon">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>

                        <div>
                            <h3>Faturamento</h3>

                            <p>
                                Informações utilizadas para cobrança
                                e integração com a Conta Hospitalar.
                            </p>
                        </div>

                    </div>

                    <div class="procedure-form-grid">

                        <div class="procedure-field col-4">
                            <label for="valorUnitario">
                                Valor unitário
                            </label>

                            <input
                                type="number"
                                id="valorUnitario"
                                name="valor_unitario"
                                min="0"
                                step="0.01"
                                value="0.00"
                            >
                        </div>

                        <div class="procedure-field col-4">
                            <label for="exigeAutorizacao">
                                Exige autorização
                            </label>

                            <select
                                id="exigeAutorizacao"
                                name="exige_autorizacao"
                            >
                                <option value="0">Não</option>
                                <option value="1">Sim</option>
                            </select>
                        </div>

                    </div>

                </section>

                <div
                    id="mensagemProcedimento"
                    class="procedure-message"
                    hidden
                ></div>

                <div class="procedure-actions">

                    <button
                        type="button"
                        id="botaoLimpar"
                        class="procedure-secondary-button"
                    >
                        <i class="fa-solid fa-eraser"></i>
                        Limpar
                    </button>

                    <button
                        type="submit"
                        id="botaoSalvarProcedimento"
                        class="procedure-save-button"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>

                        <span id="textoBotaoSalvar">
                            Salvar procedimento
                        </span>
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

<script src="/safe-health/assets/js/cadastro-procedimentos.js?v=1"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
