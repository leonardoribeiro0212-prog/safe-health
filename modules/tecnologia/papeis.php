<?php
declare(strict_types=1);

$tituloPagina = 'Papéis';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/tecnologia-papeis.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="roles-page">

    <div class="roles-heading">
        <div>
            <span class="roles-eyebrow">Tecnologia</span>
            <h2>Papéis</h2>
            <p>
                Cadastre e gerencie os papéis utilizados para controle de acesso no Safe Health.
            </p>
        </div>

        <button
            type="button"
            id="botaoNovoPapel"
            class="primary-button"
        >
            <i class="fa-solid fa-plus"></i>
            Novo papel
        </button>
    </div>

    <section class="roles-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div>
                <h3>Pesquisar papéis</h3>
                <p>Pesquise pelo nome ou descrição.</p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-id-badge"></i>

                <input
                    type="text"
                    id="campoPesquisa"
                    placeholder="Digite nome ou descrição"
                    autocomplete="off"
                >
            </div>

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
                id="botaoLimparPesquisa"
                class="secondary-button"
            >
                <i class="fa-solid fa-eraser"></i>
                Limpar
            </button>
        </div>
    </section>

    <section class="roles-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <div>
                <h3>Papéis cadastrados</h3>
                <p>
                    Visualize os papéis, usuários vinculados e quantidade de telas liberadas.
                </p>
            </div>
        </div>

        <div id="listaPapeis">
            <div class="empty-state">
                Carregando papéis...
            </div>
        </div>
    </section>

    <form
        id="formPapel"
        class="roles-card"
        hidden
    >
        <input
            type="hidden"
            id="papelId"
            name="papel_id"
        >

        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>

            <div>
                <h3 id="tituloFormulario">Novo papel</h3>
                <p>
                    Informe os dados básicos do papel. As permissões serão configuradas na tela Permissões.
                </p>
            </div>
        </div>

        <div class="form-grid">
            <div class="field col-5">
                <label for="nome">
                    Nome
                    <span>*</span>
                </label>

                <input
                    type="text"
                    id="nome"
                    name="nome"
                    maxlength="100"
                    required
                >
            </div>

            <div class="field col-5">
                <label for="descricao">
                    Descrição
                </label>

                <input
                    type="text"
                    id="descricao"
                    name="descricao"
                    maxlength="255"
                    placeholder="Ex.: acesso do corpo clínico"
                >
            </div>

            <div class="field col-2">
                <label for="ativo">Status</label>

                <select
                    id="ativo"
                    name="ativo"
                >
                    <option value="1">Ativo</option>
                    <option value="0">Inativo</option>
                </select>
            </div>
        </div>

        <div class="role-details">
            <div>
                <span>Usuários vinculados</span>
                <strong id="totalUsuariosVinculados">0</strong>
            </div>

            <div>
                <span>Telas liberadas</span>
                <strong id="totalTelasLiberadas">0</strong>
            </div>
        </div>

        <div
            id="mensagemPapel"
            class="roles-message"
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
                Salvar papel
            </button>
        </div>
    </form>

</section>
</main>
</div>

<script src="/safe-health/assets/js/tecnologia-papeis.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
