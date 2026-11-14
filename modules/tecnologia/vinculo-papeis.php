<?php
declare(strict_types=1);
$tituloPagina = 'Vínculo de Papéis';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/tecnologia-vinculo-papeis.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="vp-page">
    <div class="vp-heading">
        <div>
            <span class="vp-eyebrow">Tecnologia</span>
            <h2>Vínculo de Papéis</h2>
            <p>Associe um ou mais papéis aos usuários do Safe Health.</p>
        </div>
    </div>

    <section class="vp-card">
        <div class="section-title">
            <div class="section-icon"><i class="fa-solid fa-user-tag"></i></div>
            <div>
                <h3>Gerenciar vínculos</h3>
                <p>Selecione o usuário e marque os papéis que ele deverá possuir.</p>
            </div>
        </div>

        <div class="field">
            <label for="usuario">Usuário</label>
            <select id="usuario">
                <option value="">Selecione um usuário</option>
            </select>
        </div>

        <div id="usuarioInfo" class="user-info" hidden></div>

        <div class="roles-title">Papéis disponíveis</div>
        <div id="listaPapeis" class="roles-grid">
            <div class="empty">Selecione um usuário para carregar os vínculos.</div>
        </div>

        <div id="mensagem" class="message" hidden></div>

        <div class="actions">
            <button type="button" id="recarregar" class="secondary">
                <i class="fa-solid fa-rotate"></i> Recarregar
            </button>
            <button type="button" id="salvar" class="primary" disabled>
                <i class="fa-solid fa-floppy-disk"></i> Salvar vínculos
            </button>
        </div>
    </section>

    <section class="vp-card">
        <div class="section-title">
            <div class="section-icon"><i class="fa-solid fa-users-gear"></i></div>
            <div>
                <h3>Vínculos atuais</h3>
                <p>Visão geral dos usuários e dos papéis atribuídos.</p>
            </div>
        </div>
        <div id="visaoGeral"><div class="empty">Carregando...</div></div>
    </section>
</section>
</main>
</div>

<script src="/safe-health/assets/js/tecnologia-vinculo-papeis.js?v=1"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
