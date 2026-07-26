<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<link rel="stylesheet" href="/safe-health/assets/css/dashboard.css">

<div class="app-content">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="main-content">
        <section class="welcome-card">
            <div>
                <span class="eyebrow">Visão geral</span>

                <h2>
                    Bem-vindo,
                    <?= htmlspecialchars($_SESSION['usuario_nome']) ?>
                </h2>

                <p>
                    Acompanhe os principais indicadores e acesse
                    os módulos do Safe Health.
                </p>
            </div>

            <div class="welcome-icon">
                <i class="fa-solid fa-hospital"></i>
            </div>
        </section>

        <section class="dashboard-grid">
            <article class="metric-card">
                <div class="metric-icon">
                    <i class="fa-solid fa-ticket"></i>
                </div>

                <div>
                    <span>Senhas aguardando</span>
                    <strong>0</strong>
                </div>
            </article>

            <article class="metric-card">
                <div class="metric-icon">
                    <i class="fa-solid fa-user-injured"></i>
                </div>

                <div>
                    <span>Pacientes em atendimento</span>
                    <strong>0</strong>
                </div>
            </article>

            <article class="metric-card">
                <div class="metric-icon">
                    <i class="fa-solid fa-stethoscope"></i>
                </div>

                <div>
                    <span>Aguardando triagem</span>
                    <strong>0</strong>
                </div>
            </article>

            <article class="metric-card">
                <div class="metric-icon">
                    <i class="fa-solid fa-bed"></i>
                </div>

                <div>
                    <span>Internados</span>
                    <strong>0</strong>
                </div>
            </article>
        </section>

        <section class="content-grid">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <span class="eyebrow">Atendimento</span>
                        <h3>Fluxo hospitalar</h3>
                    </div>
                </div>

                <div class="flow-list">
                    <div class="flow-step">
                        <span>1</span>
                        <div>
                            <strong>Totem</strong>
                            <p>Geração da senha de atendimento.</p>
                        </div>
                    </div>

                    <div class="flow-step">
                        <span>2</span>
                        <div>
                            <strong>Recepção</strong>
                            <p>Identificação e cadastro do paciente.</p>
                        </div>
                    </div>

                    <div class="flow-step">
                        <span>3</span>
                        <div>
                            <strong>Enfermagem</strong>
                            <p>Triagem e registro de sinais vitais.</p>
                        </div>
                    </div>

                    <div class="flow-step">
                        <span>4</span>
                        <div>
                            <strong>Prontuário</strong>
                            <p>Atendimento clínico e prescrição.</p>
                        </div>
                    </div>

                    <div class="flow-step">
                        <span>5</span>
                        <div>
                            <strong>Alta</strong>
                            <p>Encerramento clínico e faturamento.</p>
                        </div>
                    </div>
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <span class="eyebrow">Sistema</span>
                        <h3>Informações do acesso</h3>
                    </div>
                </div>

                <dl class="details-list">
                    <div>
                        <dt>Usuário</dt>
                        <dd>
                            <?= htmlspecialchars($_SESSION['usuario_nome']) ?>
                        </dd>
                    </div>

                    <div>
                        <dt>E-mail</dt>
                        <dd>
                         <?= htmlspecialchars($_SESSION['usuario_email'] ?? 'E-mail não informado') ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Situação</dt>
                        <dd>
                            <span class="status-badge">
                                Sessão ativa
                            </span>
                        </dd>
                    </div>
                </dl>
            </article>
        </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>