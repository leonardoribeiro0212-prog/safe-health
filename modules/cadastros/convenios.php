<?php

declare(strict_types=1);

$tituloPagina = 'Cadastro de Convênios';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/cadastro-convenios.css?v=1"
>

<div class="app-content">

    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">

        <section class="insurance-page">

            <div class="insurance-heading">

                <div>
                    <span class="insurance-eyebrow">
                        Cadastros
                    </span>

                    <h2>Cadastro de Convênios</h2>

                    <p>
                        Cadastre novos convênios ou pesquise
                        um registro existente para alterar seus dados.
                    </p>
                </div>

                <div
                    id="modoCadastro"
                    class="insurance-mode-badge"
                >
                    Novo cadastro
                </div>

            </div>

            <section class="insurance-search-card">

                <div>
                    <h3>Pesquisar convênio</h3>

                    <p>
                        Pesquise por nome, código, CNPJ
                        ou registro ANS.
                    </p>
                </div>

                <div class="insurance-search-row">

                    <div class="insurance-search-input">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            id="pesquisaConvenio"
                            placeholder="Digite nome, código, CNPJ ou registro ANS"
                            autocomplete="off"
                        >
                    </div>

                    <button
                        type="button"
                        id="botaoPesquisarConvenio"
                        class="insurance-search-button"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Pesquisar
                    </button>

                    <button
                        type="button"
                        id="botaoNovoConvenio"
                        class="insurance-secondary-button"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Novo convênio
                    </button>

                </div>

                <div
                    id="resultadosPesquisa"
                    class="insurance-search-results"
                    hidden
                ></div>

            </section>

            <form id="formConvenio">

                <input
                    type="hidden"
                    id="convenioId"
                    name="convenio_id"
                    value=""
                >

                <section class="insurance-card">

                    <div class="insurance-section-title">

                        <div class="insurance-section-icon">
                            <i class="fa-solid fa-handshake"></i>
                        </div>

                        <div>
                            <h3>Identificação do convênio</h3>

                            <p>
                                Dados administrativos e de identificação.
                            </p>
                        </div>

                    </div>

                    <div class="insurance-form-grid">

                        <div class="insurance-field col-8">
                            <label for="nome">
                                Nome do convênio
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                maxlength="150"
                                placeholder="Ex.: Bradesco Saúde"
                                required
                            >
                        </div>

                        <div class="insurance-field col-4">
                            <label for="codigo">
                                Código
                            </label>

                            <input
                                type="text"
                                id="codigo"
                                name="codigo"
                                maxlength="60"
                                placeholder="Ex.: BRADESCO_SAUDE"
                            >

                            <small>
                                Se ficar vazio, o sistema gera
                                automaticamente a partir do nome.
                            </small>
                        </div>

                        <div class="insurance-field col-4">
                            <label for="cnpj">
                                CNPJ
                            </label>

                            <input
                                type="text"
                                id="cnpj"
                                name="cnpj"
                                maxlength="18"
                                placeholder="00.000.000/0000-00"
                            >
                        </div>

                        <div class="insurance-field col-4">
                            <label for="registroAns">
                                Registro ANS
                            </label>

                            <input
                                type="text"
                                id="registroAns"
                                name="registro_ans"
                                maxlength="30"
                            >
                        </div>

                        <div class="insurance-field col-4">
                            <label for="ativo">
                                Status
                                <span>*</span>
                            </label>

                            <select
                                id="ativo"
                                name="ativo"
                                required
                            >
                                <option value="1">
                                    Ativo
                                </option>

                                <option value="0">
                                    Inativo
                                </option>
                            </select>
                        </div>

                    </div>

                </section>

                <section class="insurance-card">

                    <div class="insurance-section-title">

                        <div class="insurance-section-icon">
                            <i class="fa-solid fa-address-book"></i>
                        </div>

                        <div>
                            <h3>Contato</h3>

                            <p>
                                Informações de contato do convênio.
                            </p>
                        </div>

                    </div>

                    <div class="insurance-form-grid">

                        <div class="insurance-field col-6">
                            <label for="telefone">
                                Telefone
                            </label>

                            <input
                                type="text"
                                id="telefone"
                                name="telefone"
                                maxlength="20"
                            >
                        </div>

                        <div class="insurance-field col-6">
                            <label for="email">
                                E-mail
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="150"
                            >
                        </div>

                    </div>

                </section>

                <div
                    id="mensagemConvenio"
                    class="insurance-message"
                    hidden
                ></div>

                <div class="insurance-actions">

                    <button
                        type="button"
                        id="botaoLimpar"
                        class="insurance-secondary-button"
                    >
                        <i class="fa-solid fa-eraser"></i>
                        Limpar
                    </button>

                    <button
                        type="submit"
                        id="botaoSalvarConvenio"
                        class="insurance-save-button"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>

                        <span id="textoBotaoSalvar">
                            Salvar convênio
                        </span>
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

<script src="/safe-health/assets/js/cadastro-convenios.js?v=1"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
