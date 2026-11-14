<?php

declare(strict_types=1);

$tituloPagina = 'Cadastro de Exames Laboratoriais';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/cadastro-exames-laboratoriais.css?v=1"
>

<div class="app-content">

    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">

        <section class="lab-page">

            <div class="lab-heading">

                <div>
                    <span class="lab-eyebrow">
                        Cadastros
                    </span>

                    <h2>Cadastro de Exames Laboratoriais</h2>

                    <p>
                        Cadastre exames laboratoriais ou pesquise
                        um registro existente para atualização.
                    </p>
                </div>

                <div
                    id="modoCadastro"
                    class="lab-mode-badge"
                >
                    Novo cadastro
                </div>

            </div>

            <section class="lab-search-card">

                <div>
                    <h3>Pesquisar exame</h3>

                    <p>
                        Pesquise por nome, código ou categoria.
                    </p>
                </div>

                <div class="lab-search-row">

                    <div class="lab-search-input">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            id="pesquisaExame"
                            placeholder="Digite nome, código ou categoria"
                            autocomplete="off"
                        >
                    </div>

                    <button
                        type="button"
                        id="botaoPesquisarExame"
                        class="lab-search-button"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Pesquisar
                    </button>

                    <button
                        type="button"
                        id="botaoNovoExame"
                        class="lab-secondary-button"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Novo exame
                    </button>

                </div>

                <div
                    id="resultadosPesquisa"
                    class="lab-search-results"
                    hidden
                ></div>

            </section>

            <form id="formExame">

                <input
                    type="hidden"
                    id="exameId"
                    name="exame_id"
                    value=""
                >

                <section class="lab-card">

                    <div class="lab-section-title">

                        <div class="lab-section-icon">
                            <i class="fa-solid fa-flask-vial"></i>
                        </div>

                        <div>
                            <h3>Identificação do exame</h3>

                            <p>
                                Dados utilizados na solicitação médica,
                                laboratório e faturamento.
                            </p>
                        </div>

                    </div>

                    <div class="lab-form-grid">

                        <div class="lab-field col-8">
                            <label for="nome">
                                Nome do exame
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                maxlength="150"
                                placeholder="Ex.: Hemograma completo"
                                required
                            >
                        </div>

                        <div class="lab-field col-4">
                            <label for="codigo">
                                Código
                            </label>

                            <input
                                type="text"
                                id="codigo"
                                name="codigo"
                                maxlength="60"
                                placeholder="Ex.: LAB_0001"
                            >
                        </div>

                        <div class="lab-field col-6">
                            <label for="categoria">
                                Categoria
                            </label>

                            <select
                                id="categoria"
                                name="categoria"
                            >
                                <option value="">Selecione</option>
                                <option value="HEMATOLOGIA">Hematologia</option>
                                <option value="BIOQUIMICA">Bioquímica</option>
                                <option value="COAGULACAO">Coagulação</option>
                                <option value="IMUNOLOGIA">Imunologia</option>
                                <option value="MICROBIOLOGIA">Microbiologia</option>
                                <option value="URINALISE">Urinálise</option>
                                <option value="PARASITOLOGIA">Parasitologia</option>
                                <option value="HORMONIOS">Hormônios</option>
                                <option value="SOROLOGIA">Sorologia</option>
                                <option value="OUTROS">Outros</option>
                            </select>
                        </div>

                        <div class="lab-field col-6">
                            <label for="materialBiologico">
                                Material biológico
                            </label>

                            <input
                                type="text"
                                id="materialBiologico"
                                name="material_biologico"
                                maxlength="100"
                                placeholder="Ex.: Sangue, urina, plasma"
                            >
                        </div>

                    </div>

                </section>

                <section class="lab-card">

                    <div class="lab-section-title">

                        <div class="lab-section-icon">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>

                        <div>
                            <h3>Preparo e faturamento</h3>

                            <p>
                                Informações complementares do exame.
                            </p>
                        </div>

                    </div>

                    <div class="lab-form-grid">

                        <div class="lab-field col-12">
                            <label for="preparo">
                                Orientações de preparo
                            </label>

                            <textarea
                                id="preparo"
                                name="preparo"
                                rows="4"
                                placeholder="Ex.: Jejum de 8 horas."
                            ></textarea>
                        </div>

                        <div class="lab-field col-4">
                            <label for="prazoResultado">
                                Prazo estimado do resultado (horas)
                            </label>

                            <input
                                type="number"
                                id="prazoResultado"
                                name="prazo_resultado_horas"
                                min="0"
                                step="1"
                            >
                        </div>

                        <div class="lab-field col-4">
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

                        <div class="lab-field col-4">
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

                    </div>

                </section>

                <div
                    id="mensagemExame"
                    class="lab-message"
                    hidden
                ></div>

                <div class="lab-actions">

                    <button
                        type="button"
                        id="botaoLimpar"
                        class="lab-secondary-button"
                    >
                        <i class="fa-solid fa-eraser"></i>
                        Limpar
                    </button>

                    <button
                        type="submit"
                        id="botaoSalvarExame"
                        class="lab-save-button"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>

                        <span id="textoBotaoSalvar">
                            Salvar exame
                        </span>
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

<script src="/safe-health/assets/js/cadastro-exames-laboratoriais.js?v=1"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
