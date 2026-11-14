<?php
declare(strict_types=1);

$tituloPagina = 'Auditoria';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/tecnologia-auditoria.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="audit-page">

    <div class="audit-heading">
        <div>
            <span class="audit-eyebrow">Tecnologia</span>
            <h2>Auditoria do Sistema</h2>
            <p>
                Consulte os eventos de segurança, alterações e acessos registrados no Safe Health.
            </p>
        </div>

        <button
            type="button"
            id="botaoAtualizar"
            class="secondary-button"
        >
            <i class="fa-solid fa-rotate"></i>
            Atualizar
        </button>
    </div>

    <section class="audit-summary">
        <div class="summary-card">
            <span>Eventos registrados</span>
            <strong id="resumoEventos">0</strong>
        </div>

        <div class="summary-card">
            <span>Hoje</span>
            <strong id="resumoHoje">0</strong>
        </div>

        <div class="summary-card">
            <span>Alterações</span>
            <strong id="resumoAlteracoes">0</strong>
        </div>

        <div class="summary-card">
            <span>Acessos</span>
            <strong id="resumoAcessos">0</strong>
        </div>
    </section>

    <section class="audit-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-filter"></i>
            </div>

            <div>
                <h3>Filtros de auditoria</h3>
                <p>
                    Filtre por período, usuário, módulo, ação ou descrição.
                </p>
            </div>
        </div>

        <div class="filter-grid">

            <div class="field">
                <label for="dataInicio">Data inicial</label>
                <input type="date" id="dataInicio">
            </div>

            <div class="field">
                <label for="dataFim">Data final</label>
                <input type="date" id="dataFim">
            </div>

            <div class="field">
                <label for="usuarioFiltro">Usuário</label>
                <select id="usuarioFiltro">
                    <option value="">Todos os usuários</option>
                </select>
            </div>

            <div class="field">
                <label for="moduloFiltro">Módulo</label>
                <select id="moduloFiltro">
                    <option value="">Todos os módulos</option>
                </select>
            </div>

            <div class="field">
                <label for="acaoFiltro">Ação</label>
                <select id="acaoFiltro">
                    <option value="">Todas as ações</option>
                </select>
            </div>

            <div class="field field-search">
                <label for="campoPesquisa">Pesquisar</label>

                <div class="search-input">
                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        id="campoPesquisa"
                        placeholder="Tela, entidade, registro ou descrição"
                        autocomplete="off"
                    >
                </div>
            </div>
        </div>

        <div class="filter-actions">
            <button
                type="button"
                id="botaoLimpar"
                class="secondary-button"
            >
                <i class="fa-solid fa-eraser"></i>
                Limpar
            </button>

            <button
                type="button"
                id="botaoPesquisar"
                class="primary-button"
            >
                <i class="fa-solid fa-magnifying-glass"></i>
                Pesquisar
            </button>
        </div>
    </section>

    <section class="audit-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>

            <div>
                <h3>Eventos registrados</h3>
                <p>
                    Os registros de auditoria são somente para consulta.
                </p>
            </div>
        </div>

        <div id="listaAuditoria">
            <div class="empty-state">
                Carregando auditoria...
            </div>
        </div>
    </section>

    <section
        id="detalhesCard"
        class="audit-card"
        hidden
    >
        <div class="details-header">
            <div class="section-title no-margin">
                <div class="section-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>

                <div>
                    <h3 id="tituloDetalhes">Detalhes do evento</h3>
                    <p>
                        Informações completas do registro de auditoria.
                    </p>
                </div>
            </div>

            <button
                type="button"
                id="botaoFecharDetalhes"
                class="icon-button"
                aria-label="Fechar detalhes"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="details-grid">
            <div class="info-box">
                <span>Data/Hora</span>
                <strong id="detalheData">-</strong>
            </div>

            <div class="info-box">
                <span>Usuário</span>
                <strong id="detalheUsuario">-</strong>
            </div>

            <div class="info-box">
                <span>Ação</span>
                <strong id="detalheAcao">-</strong>
            </div>

            <div class="info-box">
                <span>Módulo</span>
                <strong id="detalheModulo">-</strong>
            </div>

            <div class="info-box">
                <span>Tela</span>
                <strong id="detalheTela">-</strong>
            </div>

            <div class="info-box">
                <span>Entidade</span>
                <strong id="detalheEntidade">-</strong>
            </div>

            <div class="info-box">
                <span>Registro</span>
                <strong id="detalheRegistro">-</strong>
            </div>

            <div class="info-box">
                <span>IP</span>
                <strong id="detalheIp">-</strong>
            </div>

            <div class="info-box">
                <span>Método HTTP</span>
                <strong id="detalheMetodo">-</strong>
            </div>
        </div>

        <div class="detail-block">
            <span>URL</span>
            <code id="detalheUrl">-</code>
        </div>

        <div class="detail-block">
            <span>Descrição</span>
            <p id="detalheDescricao">-</p>
        </div>

        <div class="changes-grid">
            <div class="detail-block">
                <span>Dados anteriores</span>
                <pre id="detalheAnterior">Nenhum dado anterior registrado.</pre>
            </div>

            <div class="detail-block">
                <span>Dados novos</span>
                <pre id="detalheNovo">Nenhum dado novo registrado.</pre>
            </div>
        </div>
    </section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/tecnologia-auditoria.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
