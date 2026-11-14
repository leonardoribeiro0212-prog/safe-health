<?php
declare(strict_types=1);

$tituloPagina = 'Permissões';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/tecnologia-permissoes.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="permissions-page">

    <div class="permissions-heading">
        <div>
            <span class="permissions-eyebrow">Tecnologia</span>
            <h2>Permissões</h2>
            <p>
                Defina quais telas cada papel pode acessar no Safe Health.
            </p>
        </div>
    </div>

    <section class="permissions-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <div>
                <h3>Selecionar papel</h3>
                <p>
                    Escolha um papel para visualizar e alterar suas permissões.
                </p>
            </div>
        </div>

        <div class="role-selector-row">
            <div class="field role-field">
                <label for="papelId">Papel</label>

                <select id="papelId">
                    <option value="">Selecione um papel</option>
                </select>
            </div>

            <div class="permission-summary">
                <div>
                    <span>Telas disponíveis</span>
                    <strong id="totalTelas">0</strong>
                </div>

                <div>
                    <span>Permissões concedidas</span>
                    <strong id="totalPermitidas">0</strong>
                </div>
            </div>
        </div>
    </section>

    <section
        id="painelPermissoes"
        class="permissions-card"
        hidden
    >
        <div class="permissions-toolbar">
            <div class="section-title no-margin">
                <div class="section-icon">
                    <i class="fa-solid fa-list-check"></i>
                </div>

                <div>
                    <h3 id="tituloPapel">Permissões do papel</h3>
                    <p>
                        Marque ou desmarque as telas permitidas para este papel.
                    </p>
                </div>
            </div>

            <div class="toolbar-actions">
                <button
                    type="button"
                    id="botaoMarcarTodas"
                    class="secondary-button"
                >
                    <i class="fa-solid fa-check-double"></i>
                    Marcar todas
                </button>

                <button
                    type="button"
                    id="botaoDesmarcarTodas"
                    class="secondary-button"
                >
                    <i class="fa-solid fa-xmark"></i>
                    Desmarcar todas
                </button>
            </div>
        </div>

        <div class="filter-row">
            <div class="search-input">
                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    id="campoFiltro"
                    placeholder="Filtrar módulo ou tela"
                    autocomplete="off"
                >
            </div>
        </div>

        <div id="listaPermissoes">
            <div class="empty-state">
                Carregando permissões...
            </div>
        </div>

        <div
            id="mensagemPermissoes"
            class="permissions-message"
            hidden
        ></div>

        <div class="form-actions">
            <button
                type="button"
                id="botaoRecarregar"
                class="secondary-button"
            >
                <i class="fa-solid fa-rotate"></i>
                Recarregar
            </button>

            <button
                type="button"
                id="botaoSalvar"
                class="primary-button"
            >
                <i class="fa-solid fa-floppy-disk"></i>
                Salvar permissões
            </button>
        </div>
    </section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/tecnologia-permissoes.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
