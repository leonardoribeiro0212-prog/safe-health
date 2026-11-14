<?php

declare(strict_types=1);

$tituloPagina = 'Cadastro de Exames de Imagem';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/cadastro-exames-imagem.css?v=1"
>

<div class="app-content">

    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">

        <section class="imgexam-page">

            <div class="imgexam-heading">

                <div>
                    <span class="imgexam-eyebrow">
                        Cadastros
                    </span>

                    <h2>Cadastro de Exames de Imagem</h2>

                    <p>
                        Cadastre exames de imagem ou pesquise um registro
                        existente para atualizar seus dados.
                    </p>
                </div>

                <div
                    id="modoCadastro"
                    class="imgexam-mode-badge"
                >
                    Novo cadastro
                </div>

            </div>

            <section class="imgexam-search-card">

                <div>
                    <h3>Pesquisar exame</h3>

                    <p>
                        Pesquise por nome, código, modalidade ou região anatômica.
                    </p>
                </div>

                <div class="imgexam-search-row">

                    <div class="imgexam-search-input">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            id="pesquisaExame"
                            placeholder="Digite nome, código, modalidade ou região"
                            autocomplete="off"
                        >
                    </div>

                    <button
                        type="button"
                        id="botaoPesquisarExame"
                        class="imgexam-search-button"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Pesquisar
                    </button>

                    <button
                        type="button"
                        id="botaoNovoExame"
                        class="imgexam-secondary-button"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Novo exame
                    </button>

                </div>

                <div
                    id="resultadosPesquisa"
                    class="imgexam-search-results"
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

                <section class="imgexam-card">

                    <div class="imgexam-section-title">

                        <div class="imgexam-section-icon">
                            <i class="fa-solid fa-x-ray"></i>
                        </div>

                        <div>
                            <h3>Identificação do exame</h3>

                            <p>
                                Dados utilizados na solicitação médica,
                                diagnóstico por imagem e faturamento.
                            </p>
                        </div>

                    </div>

                    <div class="imgexam-form-grid">

                        <div class="imgexam-field col-8">
                            <label for="nome">
                                Nome do exame
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                maxlength="150"
                                placeholder="Ex.: Tomografia de crânio"
                                required
                            >
                        </div>

                        <div class="imgexam-field col-4">
                            <label for="codigo">
                                Código
                            </label>

                            <input
                                type="text"
                                id="codigo"
                                name="codigo"
                                maxlength="60"
                                placeholder="Ex.: IMG_0001"
                            >
                        </div>

                        <div class="imgexam-field col-6">
                            <label for="modalidade">
                                Modalidade
                            </label>

                            <select
                                id="modalidade"
                                name="modalidade"
                            >
                                <option value="">Selecione</option>
                                <option value="RAIO_X">Raio-X</option>
                                <option value="TOMOGRAFIA">Tomografia Computadorizada</option>
                                <option value="RESSONANCIA">Ressonância Magnética</option>
                                <option value="ULTRASSONOGRAFIA">Ultrassonografia</option>
                                <option value="MAMOGRAFIA">Mamografia</option>
                                <option value="DENSITOMETRIA">Densitometria Óssea</option>
                                <option value="ANGIOGRAFIA">Angiografia</option>
                                <option value="MEDICINA_NUCLEAR">Medicina Nuclear</option>
                                <option value="OUTRO">Outro</option>
                            </select>
                        </div>

                        <div class="imgexam-field col-6">
                            <label for="regiaoAnatomica">
                                Região anatômica
                            </label>

                            <input
                                type="text"
                                id="regiaoAnatomica"
                                name="regiao_anatomica"
                                maxlength="100"
                                placeholder="Ex.: Crânio, tórax, abdome"
                            >
                        </div>

                    </div>

                </section>

                <section class="imgexam-card">

                    <div class="imgexam-section-title">

                        <div class="imgexam-section-icon">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>

                        <div>
                            <h3>Preparo e faturamento</h3>

                            <p>
                                Informações complementares para execução
                                do exame e cobrança.
                            </p>
                        </div>

                    </div>

                    <div class="imgexam-form-grid">

                        <div class="imgexam-field col-12">
                            <label for="preparo">
                                Orientações de preparo
                            </label>

                            <textarea
                                id="preparo"
                                name="preparo"
                                rows="4"
                                placeholder="Ex.: Jejum de 6 horas, retirar objetos metálicos..."
                            ></textarea>
                        </div>

                        <div class="imgexam-field col-3">
                            <label for="usaContraste">
                                Utiliza contraste
                            </label>

                            <select
                                id="usaContraste"
                                name="usa_contraste"
                            >
                                <option value="0">Não</option>
                                <option value="1">Sim</option>
                            </select>
                        </div>

                        <div class="imgexam-field col-3">
                            <label for="prazoResultado">
                                Prazo do resultado (horas)
                            </label>

                            <input
                                type="number"
                                id="prazoResultado"
                                name="prazo_resultado_horas"
                                min="0"
                                step="1"
                            >
                        </div>

                        <div class="imgexam-field col-3">
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

                        <div class="imgexam-field col-3">
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
                    class="imgexam-message"
                    hidden
                ></div>

                <div class="imgexam-actions">

                    <button
                        type="button"
                        id="botaoLimpar"
                        class="imgexam-secondary-button"
                    >
                        <i class="fa-solid fa-eraser"></i>
                        Limpar
                    </button>

                    <button
                        type="submit"
                        id="botaoSalvarExame"
                        class="imgexam-save-button"
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

<script src="/safe-health/assets/js/cadastro-exames-imagem.js?v=1"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
