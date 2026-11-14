<?php

declare(strict_types=1);

$tituloPagina = 'Cadastro de Materiais Hospitalares';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/cadastro-materiais.css?v=1"
>

<div class="app-content">

    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">

        <section class="material-page">

            <div class="material-heading">

                <div>
                    <span class="material-eyebrow">
                        Cadastros
                    </span>

                    <h2>Cadastro de Materiais Hospitalares</h2>

                    <p>
                        Cadastre materiais utilizados na assistência
                        e pesquise registros existentes para alteração.
                    </p>
                </div>

                <div
                    id="modoCadastro"
                    class="material-mode-badge"
                >
                    Novo cadastro
                </div>

            </div>

            <section class="material-search-card">

                <div>
                    <h3>Pesquisar material</h3>

                    <p>
                        Pesquise por nome, código ou categoria.
                    </p>
                </div>

                <div class="material-search-row">

                    <div class="material-search-input">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            id="pesquisaMaterial"
                            placeholder="Digite nome, código ou categoria"
                            autocomplete="off"
                        >
                    </div>

                    <button
                        type="button"
                        id="botaoPesquisarMaterial"
                        class="material-search-button"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Pesquisar
                    </button>

                    <button
                        type="button"
                        id="botaoNovoMaterial"
                        class="material-secondary-button"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Novo material
                    </button>

                </div>

                <div
                    id="resultadosPesquisa"
                    class="material-search-results"
                    hidden
                ></div>

            </section>

            <form id="formMaterial">

                <input
                    type="hidden"
                    id="materialId"
                    name="material_id"
                    value=""
                >

                <section class="material-card">

                    <div class="material-section-title">

                        <div class="material-section-icon">
                            <i class="fa-solid fa-box-open"></i>
                        </div>

                        <div>
                            <h3>Identificação do material</h3>

                            <p>
                                Dados utilizados no estoque, assistência
                                e faturamento hospitalar.
                            </p>
                        </div>

                    </div>

                    <div class="material-form-grid">

                        <div class="material-field col-8">
                            <label for="nome">
                                Nome do material
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                maxlength="150"
                                required
                            >
                        </div>

                        <div class="material-field col-4">
                            <label for="codigo">
                                Código
                            </label>

                            <input
                                type="text"
                                id="codigo"
                                name="codigo"
                                maxlength="60"
                                placeholder="Ex.: MAT_0001"
                            >
                        </div>

                        <div class="material-field col-6">
                            <label for="categoria">
                                Categoria
                            </label>

                            <input
                                type="text"
                                id="categoria"
                                name="categoria"
                                maxlength="100"
                                placeholder="Ex.: Curativos, punção, descartáveis"
                            >
                        </div>

                        <div class="material-field col-3">
                            <label for="unidadeMedida">
                                Unidade de medida
                            </label>

                            <select
                                id="unidadeMedida"
                                name="unidade_medida"
                            >
                                <option value="">Selecione</option>
                                <option value="UN">Unidade</option>
                                <option value="CX">Caixa</option>
                                <option value="PCT">Pacote</option>
                                <option value="KIT">Kit</option>
                                <option value="RL">Rolo</option>
                                <option value="FR">Frasco</option>
                                <option value="PAR">Par</option>
                                <option value="ML">mL</option>
                                <option value="M">Metro</option>
                            </select>
                        </div>

                        <div class="material-field col-3">
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

                <section class="material-card">

                    <div class="material-section-title">

                        <div class="material-section-icon">
                            <i class="fa-solid fa-warehouse"></i>
                        </div>

                        <div>
                            <h3>Estoque e faturamento</h3>

                            <p>
                                Informações para controle de estoque
                                e lançamento na Conta Hospitalar.
                            </p>
                        </div>

                    </div>

                    <div class="material-form-grid">

                        <div class="material-field col-6">
                            <label for="fabricante">
                                Fabricante
                            </label>

                            <input
                                type="text"
                                id="fabricante"
                                name="fabricante"
                                maxlength="120"
                            >
                        </div>

                        <div class="material-field col-3">
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

                        <div class="material-field col-3">
                            <label for="estoqueMinimo">
                                Estoque mínimo
                            </label>

                            <input
                                type="number"
                                id="estoqueMinimo"
                                name="estoque_minimo"
                                min="0"
                                step="0.001"
                                value="0.000"
                            >
                        </div>

                    </div>

                </section>

                <div
                    id="mensagemMaterial"
                    class="material-message"
                    hidden
                ></div>

                <div class="material-actions">

                    <button
                        type="button"
                        id="botaoLimpar"
                        class="material-secondary-button"
                    >
                        <i class="fa-solid fa-eraser"></i>
                        Limpar
                    </button>

                    <button
                        type="submit"
                        id="botaoSalvarMaterial"
                        class="material-save-button"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>

                        <span id="textoBotaoSalvar">
                            Salvar material
                        </span>
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

<script src="/safe-health/assets/js/cadastro-materiais.js?v=1"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
