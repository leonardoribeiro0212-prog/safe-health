<?php

declare(strict_types=1);

$tituloPagina = 'Dashboard';

/*
 * A página inicial já é aberta após o login.
 * Não aplicamos um código de permissão aqui porque o código
 * cadastrado para o Dashboard pode ser diferente no banco.
 */
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$nomeUsuario = trim(
    (string) ($_SESSION['usuario_nome'] ?? 'Usuário')
);
?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/dashboard.css?v=6"
>

<div class="app-content">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="main-content dashboard-page">

        <section class="dashboard-welcome">
            <div class="dashboard-welcome-icon">
                <i class="fa-solid fa-hospital"></i>
            </div>

            <div>
                <span class="dashboard-eyebrow">
                    Visão geral
                </span>

                <h2>
                    Bem-vindo,
                    <?= htmlspecialchars(
                        $nomeUsuario,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>!
                </h2>

                <p>
                    Acompanhe os principais indicadores e o fluxo
                    hospitalar do Safe Health.
                </p>
            </div>
        </section>

        <section
            class="dashboard-cards"
            aria-label="Indicadores hospitalares"
        >
            <article class="dashboard-card dashboard-card-ticket">
                <div class="dashboard-card-icon">
                    <i class="fa-solid fa-ticket"></i>
                </div>

                <div class="dashboard-card-info">
                    <strong id="indicadorSenhasRecepcao">
                        —
                    </strong>

                    <span>Senhas aguardando</span>

                    <small>
                        Aguardando chamada na recepção
                    </small>
                </div>
            </article>

            <article class="dashboard-card dashboard-card-patient">
                <div class="dashboard-card-icon">
                    <i class="fa-solid fa-user-group"></i>
                </div>

                <div class="dashboard-card-info">
                    <strong id="indicadorAtendimentos">
                        —
                    </strong>

                    <span>Pacientes em atendimento</span>

                    <small>
                        Atendimentos atualmente abertos
                    </small>
                </div>
            </article>

            <article class="dashboard-card dashboard-card-triage">
                <div class="dashboard-card-icon">
                    <i class="fa-solid fa-stethoscope"></i>
                </div>

                <div class="dashboard-card-info">
                    <strong id="indicadorTriagem">
                        —
                    </strong>

                    <span>Aguardando triagem</span>

                    <small>
                        Pacientes na fila da triagem
                    </small>
                </div>
            </article>

            <article class="dashboard-card dashboard-card-bed">
                <div class="dashboard-card-icon">
                    <i class="fa-solid fa-bed"></i>
                </div>

                <div class="dashboard-card-info">
                    <strong id="indicadorInternados">
                        —
                    </strong>

                    <span>Internados</span>

                    <small>
                        Internações com atendimento aberto
                    </small>
                </div>
            </article>
        </section>

        <div class="dashboard-update-line">
            <span id="dashboardStatus">
                Carregando indicadores...
            </span>

            <span>
                Atualização automática a cada 5 segundos
            </span>
        </div>

        <section class="dashboard-panel">
            <div class="dashboard-panel-header">
                <div>
                    <span class="dashboard-eyebrow">
                        Atendimento
                    </span>

                    <h3>Fluxo hospitalar</h3>

                    <p>
                        Principais etapas do atendimento no Safe Health.
                    </p>
                </div>
            </div>

            <div class="hospital-flow">
                <article class="flow-step">
                    <span class="flow-number">1</span>

                    <div class="flow-icon flow-icon-ticket">
                        <i class="fa-solid fa-ticket"></i>
                    </div>

                    <strong>Totem</strong>

                    <p>
                        Geração da senha de atendimento.
                    </p>
                </article>

                <i class="fa-solid fa-arrow-right flow-arrow"></i>

                <article class="flow-step">
                    <span class="flow-number">2</span>

                    <div class="flow-icon flow-icon-patient">
                        <i class="fa-solid fa-user-group"></i>
                    </div>

                    <strong>Recepção</strong>

                    <p>
                        Identificação e cadastro do paciente.
                    </p>
                </article>

                <i class="fa-solid fa-arrow-right flow-arrow"></i>

                <article class="flow-step">
                    <span class="flow-number">3</span>

                    <div class="flow-icon flow-icon-triage">
                        <i class="fa-solid fa-stethoscope"></i>
                    </div>

                    <strong>Enfermagem</strong>

                    <p>
                        Triagem e registro de sinais vitais.
                    </p>
                </article>

                <i class="fa-solid fa-arrow-right flow-arrow"></i>

                <article class="flow-step">
                    <span class="flow-number">4</span>

                    <div class="flow-icon flow-icon-chart">
                        <i class="fa-solid fa-notes-medical"></i>
                    </div>

                    <strong>Prontuário</strong>

                    <p>
                        Atendimento clínico e prescrição.
                    </p>
                </article>

                <i class="fa-solid fa-arrow-right flow-arrow"></i>

                <article class="flow-step">
                    <span class="flow-number">5</span>

                    <div class="flow-icon flow-icon-pharmacy">
                        <i class="fa-solid fa-capsules"></i>
                    </div>

                    <strong>Farmácia</strong>

                    <p>
                        Dispensação de medicamentos.
                    </p>
                </article>

                <i class="fa-solid fa-arrow-right flow-arrow"></i>

                <article class="flow-step">
                    <span class="flow-number">6</span>

                    <div class="flow-icon flow-icon-billing">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>

                    <strong>Faturamento</strong>

                    <p>
                        Emissão e conferência da conta.
                    </p>
                </article>

                <i class="fa-solid fa-arrow-right flow-arrow"></i>

                <article class="flow-step">
                    <span class="flow-number">7</span>

                    <div class="flow-icon flow-icon-discharge">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <strong>Alta</strong>

                    <p>
                        Finalização do atendimento.
                    </p>
                </article>
            </div>
        </section>

    </main>

    <script src="/safe-health/assets/js/dashboard.js?v=6"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
