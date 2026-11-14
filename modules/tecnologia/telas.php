<?php
declare(strict_types=1);

$tituloPagina = 'Telas';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/tecnologia-telas.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="screens-page">

    <div class="screens-heading">
        <div>
            <span class="screens-eyebrow">Tecnologia</span>
            <h2>Telas</h2>
            <p>
                Cadastre e administre as telas disponíveis no Safe Health e sua exibição no menu.
            </p>
        </div>

        <button
            type="button"
            id="botaoNovaTela"
            class="primary-button"
        >
            <i class="fa-solid fa-plus"></i>
            Nova tela
        </button>
    </div>

    <section class="screens-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-filter"></i>
            </div>

            <div>
                <h3>Pesquisar e filtrar</h3>
                <p>
                    Pesquise por nome, código, módulo ou URL.
                </p>
            </div>
        </div>

        <div class="filter-grid">
            <div class="search-input">
                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    id="campoPesquisa"
                    placeholder="Digite nome, código, módulo ou URL"
                    autocomplete="off"
                >
            </div>

            <select id="filtroModulo">
                <option value="">Todos os módulos</option>
            </select>

            <select id="filtroStatus">
                <option value="">Todos os status</option>
                <option value="1">Ativas</option>
                <option value="0">Inativas</option>
            </select>

            <button
                type="button"
                id="botaoPesquisar"
                class="primary-button"
            >
                <i class="fa-solid fa-magnifying-glass"></i>
                Pesquisar
            </button>

            <button
                type="button"
                id="botaoLimpar"
                class="secondary-button"
            >
                <i class="fa-solid fa-eraser"></i>
                Limpar
            </button>
        </div>
    </section>

    <section class="screens-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-display"></i>
            </div>

            <div>
                <h3>Telas cadastradas</h3>
                <p>
                    O cadastro abaixo alimenta a sidebar e as permissões do sistema.
                </p>
            </div>
        </div>

        <div id="listaTelas">
            <div class="empty-state">
                Carregando telas...
            </div>
        </div>
    </section>

    <form
        id="formTela"
        class="screens-card"
        hidden
    >
        <input
            type="hidden"
            id="telaId"
            name="tela_id"
        >

        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>

            <div>
                <h3 id="tituloFormulario">Nova tela</h3>
                <p>
                    Configure identificação, rota, posição e comportamento da tela.
                </p>
            </div>
        </div>

        <div class="form-layout">

            <div class="form-grid">
                <div class="field col-6">
                    <label for="nome">
                        Nome da tela
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        maxlength="120"
                        required
                    >
                </div>

                <div class="field col-6">
                    <label for="codigo">
                        Código
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="codigo"
                        name="codigo"
                        maxlength="150"
                        placeholder="Ex.: enfermagem.anotacao_enfermagem"
                        required
                    >
                </div>

                <div class="field col-4">
                    <label for="modulo">
                        Módulo
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="modulo"
                        name="modulo"
                        maxlength="100"
                        list="listaModulos"
                        required
                    >

                    <datalist id="listaModulos"></datalist>
                </div>

                <div class="field col-8">
                    <label for="url">
                        URL
                    </label>

                    <input
                        type="text"
                        id="url"
                        name="url"
                        maxlength="255"
                        placeholder="/safe-health/modules/..."
                    >
                </div>

                <div class="field col-4">
                    <label for="icone">
                        Ícone Font Awesome
                    </label>

                    <input
                        type="text"
                        id="icone"
                        name="icone"
                        maxlength="100"
                        placeholder="fa-file"
                    >
                </div>

                <div class="field col-2">
                    <label for="ordem">
                        Ordem
                    </label>

                    <input
                        type="number"
                        id="ordem"
                        name="ordem"
                        min="0"
                        step="1"
                        value="0"
                    >
                </div>

                <div class="field col-3">
                    <label for="abrirNovaAba">
                        Abrir em nova aba
                    </label>

                    <select
                        id="abrirNovaAba"
                        name="abrir_nova_aba"
                    >
                        <option value="0">Não</option>
                        <option value="1">Sim</option>
                    </select>
                </div>

                <div class="field col-3">
                    <label for="ativa">
                        Status
                    </label>

                    <select
                        id="ativa"
                        name="ativa"
                    >
                        <option value="1">Ativa</option>
                        <option value="0">Inativa</option>
                    </select>
                </div>
            </div>

            <aside class="preview-card">
                <span class="preview-label">Prévia do menu</span>

                <div class="preview-menu">
                    <i
                        id="previewIcone"
                        class="fa-solid fa-file"
                    ></i>

                    <div>
                        <strong id="previewNome">Nome da tela</strong>
                        <span id="previewModulo">Módulo não informado</span>
                    </div>
                </div>

                <div class="preview-details">
                    <div>
                        <span>Código</span>
                        <strong id="previewCodigo">-</strong>
                    </div>

                    <div>
                        <span>Posição</span>
                        <strong id="previewOrdem">0</strong>
                    </div>

                    <div>
                        <span>Abertura</span>
                        <strong id="previewAbertura">Mesma aba</strong>
                    </div>

                    <div>
                        <span>Status</span>
                        <strong id="previewStatus">Ativa</strong>
                    </div>
                </div>
            </aside>

        </div>

        <div class="warning-box">
            <i class="fa-solid fa-circle-info"></i>

            <span>
                Para retirar uma tela do sistema, use o status Inativa.
                Não excluímos registros para preservar permissões e histórico.
            </span>
        </div>

        <div
            id="mensagemTela"
            class="screens-message"
            hidden
        ></div>

        <div class="form-actions">
            <button
                type="button"
                id="botaoCancelar"
                class="secondary-button"
            >
                Cancelar
            </button>

            <button
                type="submit"
                id="botaoSalvar"
                class="primary-button"
            >
                <i class="fa-solid fa-floppy-disk"></i>
                Salvar tela
            </button>
        </div>
    </form>

</section>
</main>
</div>

<script src="/safe-health/assets/js/tecnologia-telas.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
