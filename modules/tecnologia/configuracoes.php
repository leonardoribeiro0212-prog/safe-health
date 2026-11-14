<?php
declare(strict_types=1);

$tituloPagina = 'Configurações';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/tecnologia-configuracoes.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="settings-page">

    <div class="settings-heading">
        <div>
            <span class="settings-eyebrow">Tecnologia</span>
            <h2>Configurações</h2>
            <p>
                Gerencie parâmetros gerais de funcionamento, sessão e segurança do Safe Health.
            </p>
        </div>
    </div>

    <form id="formConfiguracoes">

        <section class="settings-card">
            <div class="section-title">
                <div class="section-icon">
                    <i class="fa-solid fa-hospital"></i>
                </div>

                <div>
                    <h3>Identidade do sistema</h3>
                    <p>
                        Informações gerais exibidas no Safe Health.
                    </p>
                </div>
            </div>

            <div class="form-grid">
                <div class="field col-6">
                    <label for="sistemaNome">
                        Nome do sistema
                    </label>

                    <input
                        type="text"
                        id="sistemaNome"
                        data-chave="sistema.nome"
                        maxlength="150"
                    >
                </div>

                <div class="field col-6">
                    <label for="sistemaInstituicao">
                        Instituição
                    </label>

                    <input
                        type="text"
                        id="sistemaInstituicao"
                        data-chave="sistema.instituicao"
                        maxlength="180"
                        placeholder="Nome da instituição"
                    >
                </div>

                <div class="field col-6">
                    <label for="sistemaEmailSuporte">
                        E-mail de suporte
                    </label>

                    <input
                        type="email"
                        id="sistemaEmailSuporte"
                        data-chave="sistema.email_suporte"
                        maxlength="180"
                        placeholder="suporte@exemplo.com"
                    >
                </div>

                <div class="field col-6">
                    <label for="sistemaManutencao">
                        Modo manutenção
                    </label>

                    <select
                        id="sistemaManutencao"
                        data-chave="sistema.manutencao"
                    >
                        <option value="0">Desativado</option>
                        <option value="1">Ativado</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="settings-card">
            <div class="section-title">
                <div class="section-icon">
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div>
                    <h3>Sessão</h3>
                    <p>
                        Parâmetros de navegação e duração da sessão.
                    </p>
                </div>
            </div>

            <div class="form-grid">
                <div class="field col-4">
                    <label for="sessaoTempo">
                        Tempo da sessão (minutos)
                    </label>

                    <input
                        type="number"
                        id="sessaoTempo"
                        data-chave="sessao.tempo_minutos"
                        min="5"
                        max="1440"
                        step="1"
                    >
                </div>

                <div class="field col-8">
                    <label for="sessaoPaginaInicial">
                        Página inicial
                    </label>

                    <input
                        type="text"
                        id="sessaoPaginaInicial"
                        data-chave="sessao.pagina_inicial"
                        maxlength="255"
                        placeholder="/safe-health/index.php"
                    >
                </div>
            </div>
        </section>

        <section class="settings-card">
            <div class="section-title">
                <div class="section-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>

                <div>
                    <h3>Segurança</h3>
                    <p>
                        Parâmetros para proteção de acesso ao sistema.
                    </p>
                </div>
            </div>

            <div class="form-grid">
                <div class="field col-6">
                    <label for="maxTentativas">
                        Máximo de tentativas de login
                    </label>

                    <input
                        type="number"
                        id="maxTentativas"
                        data-chave="seguranca.max_tentativas_login"
                        min="1"
                        max="20"
                        step="1"
                    >
                </div>

                <div class="field col-6">
                    <label for="tempoBloqueio">
                        Tempo de bloqueio (minutos)
                    </label>

                    <input
                        type="number"
                        id="tempoBloqueio"
                        data-chave="seguranca.tempo_bloqueio_minutos"
                        min="1"
                        max="1440"
                        step="1"
                    >
                </div>
            </div>

            <div class="security-note">
                <i class="fa-solid fa-circle-info"></i>

                <span>
                    Estes parâmetros ficam centralizados para uso futuro pelo login e pelo controle de sessão.
                </span>
            </div>
        </section>

        <div
            id="mensagemConfiguracoes"
            class="settings-message"
            hidden
        ></div>

        <div class="settings-actions">
            <button
                type="button"
                id="botaoRecarregar"
                class="secondary-button"
            >
                <i class="fa-solid fa-rotate"></i>
                Recarregar
            </button>

            <button
                type="submit"
                id="botaoSalvar"
                class="primary-button"
            >
                <i class="fa-solid fa-floppy-disk"></i>
                Salvar configurações
            </button>
        </div>

    </form>

</section>
</main>
</div>

<script src="/safe-health/assets/js/tecnologia-configuracoes.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
