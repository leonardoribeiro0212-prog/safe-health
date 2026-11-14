<?php
declare(strict_types=1);

$tituloPagina = 'Cadastro de Especialidades';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/cadastro-especialidades.css?v=1"
>

<div class="app-content">

    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">

        <section class="specialty-page">

            <div class="specialty-heading">

                <div>
                    <span class="specialty-eyebrow">
                        Cadastros
                    </span>

                    <h2>Cadastro de Especialidades</h2>

                    <p>
                        Cadastre novas especialidades ou pesquise
                        uma existente para atualizar seus dados.
                    </p>
                </div>

                <div
                    id="modoCadastro"
                    class="specialty-mode-badge"
                >
                    Novo cadastro
                </div>

            </div>

            <section class="specialty-search-card">

                <div>
                    <h3>Pesquisar especialidade</h3>

                    <p>
                        Pesquise pelo nome ou código da especialidade.
                    </p>
                </div>

                <div class="specialty-search-row">

                    <div class="specialty-search-input">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            id="pesquisaEspecialidade"
                            placeholder="Digite o nome ou código"
                            autocomplete="off"
                        >
                    </div>

                    <button
                        type="button"
                        id="botaoPesquisarEspecialidade"
                        class="specialty-search-button"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Pesquisar
                    </button>

                    <button
                        type="button"
                        id="botaoNovaEspecialidade"
                        class="specialty-secondary-button"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Nova especialidade
                    </button>

                </div>

                <div
                    id="resultadosPesquisa"
                    class="specialty-search-results"
                    hidden
                ></div>

            </section>

            <form id="formEspecialidade">

                <input
                    type="hidden"
                    id="especialidadeId"
                    name="especialidade_id"
                    value=""
                >

                <section class="specialty-card">

                    <div class="specialty-section-title">

                        <div class="specialty-section-icon">
                            <i class="fa-solid fa-stethoscope"></i>
                        </div>

                        <div>
                            <h3>Dados da especialidade</h3>

                            <p>
                                Informações utilizadas no cadastro
                                dos profissionais e atendimentos.
                            </p>
                        </div>

                    </div>

                    <div class="specialty-form-grid">

                        <div class="specialty-field col-8">
                            <label for="nome">
                                Nome da especialidade
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                maxlength="120"
                                placeholder="Ex.: Clínica Médica"
                                required
                            >
                        </div>

                        <div class="specialty-field col-4">
                            <label for="codigo">
                                Código
                            </label>

                            <input
                                type="text"
                                id="codigo"
                                name="codigo"
                                maxlength="60"
                                placeholder="Ex.: CLINICA_MEDICA"
                            >

                            <small>
                                Se deixado vazio, o sistema gera
                                automaticamente a partir do nome.
                            </small>
                        </div>

                        <div class="specialty-field col-4">
                            <label for="ativa">
                                Status
                                <span>*</span>
                            </label>

                            <select
                                id="ativa"
                                name="ativa"
                                required
                            >
                                <option value="1">
                                    Ativa
                                </option>

                                <option value="0">
                                    Inativa
                                </option>
                            </select>
                        </div>

                    </div>

                </section>

                <div
                    id="mensagemEspecialidade"
                    class="specialty-message"
                    hidden
                ></div>

                <div class="specialty-actions">

                    <button
                        type="button"
                        id="botaoLimpar"
                        class="specialty-secondary-button"
                    >
                        <i class="fa-solid fa-eraser"></i>
                        Limpar
                    </button>

                    <button
                        type="submit"
                        id="botaoSalvarEspecialidade"
                        class="specialty-save-button"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>

                        <span id="textoBotaoSalvar">
                            Salvar especialidade
                        </span>
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

<script src="/safe-health/assets/js/cadastro-especialidades.js?v=1"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
