<?php
declare(strict_types=1);

$tituloPagina = 'Usuários';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/tecnologia-usuarios.css?v=1">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="users-page">

    <div class="users-heading">
        <div>
            <span class="users-eyebrow">Tecnologia</span>
            <h2>Usuários</h2>
            <p>Cadastre, pesquise e administre os usuários do Safe Health.</p>
        </div>

        <button type="button" id="botaoNovoUsuario" class="primary-button">
            <i class="fa-solid fa-user-plus"></i>
            Novo usuário
        </button>
    </div>

    <section class="users-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <div>
                <h3>Pesquisar usuários</h3>
                <p>Pesquise por nome, nome de usuário ou e-mail.</p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-user"></i>
                <input
                    type="text"
                    id="campoPesquisa"
                    placeholder="Digite nome, usuário ou e-mail"
                    autocomplete="off"
                >
            </div>

            <button type="button" id="botaoPesquisar" class="primary-button">
                <i class="fa-solid fa-magnifying-glass"></i>
                Pesquisar
            </button>

            <button type="button" id="botaoLimparPesquisa" class="secondary-button">
                <i class="fa-solid fa-eraser"></i>
                Limpar
            </button>
        </div>
    </section>

    <section class="users-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <h3>Usuários cadastrados</h3>
                <p>Visualize status e papéis vinculados a cada usuário.</p>
            </div>
        </div>

        <div id="listaUsuarios">
            <div class="empty-state">Carregando usuários...</div>
        </div>
    </section>

    <form id="formUsuario" class="users-card" hidden>
        <input type="hidden" id="usuarioId" name="usuario_id">

        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-user-gear"></i>
            </div>
            <div>
                <h3 id="tituloFormulario">Novo usuário</h3>
                <p>Preencha os dados de acesso do usuário.</p>
            </div>
        </div>

        <div class="form-grid">
            <div class="field col-6">
                <label for="nome">
                    Nome
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

            <div class="field col-6">
                <label for="nome_usuario">
                    Nome de usuário
                    <span>*</span>
                </label>
                <input
                    type="text"
                    id="nome_usuario"
                    name="nome_usuario"
                    maxlength="80"
                    placeholder="Ex.: andre.pires"
                    autocomplete="off"
                    autocapitalize="none"
                    spellcheck="false"
                    required
                >
                <small>Use 3 a 80 caracteres: letras minúsculas, números, ponto, hífen ou sublinhado.</small>
            </div>

            <div class="field col-6">
                <label for="email">
                    E-mail
                    <span>*</span>
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    required
                >
            </div>

            <div class="field col-6">
                <label for="senha">
                    Senha
                    <span id="senhaObrigatoria">*</span>
                </label>
                <input
                    type="password"
                    id="senha"
                    name="senha"
                    minlength="6"
                    autocomplete="new-password"
                >
                <small id="ajudaSenha">
                    Obrigatória para novos usuários.
                </small>
            </div>

            <div class="field col-3">
                <label for="ativo">Status</label>
                <select id="ativo" name="ativo">
                    <option value="1">Ativo</option>
                    <option value="0">Inativo</option>
                </select>
            </div>

            <div class="field col-3">
                <label>Perfil interno</label>
                <input
                    type="text"
                    id="perfilInterno"
                    value="Mantido automaticamente"
                    disabled
                >
                <small>
                    Campo legado do sistema. Os acessos serão controlados por Papéis.
                </small>
            </div>
        </div>

        <div class="roles-box">
            <span>Papéis vinculados</span>
            <div id="papeisUsuario" class="role-list">
                Nenhum papel vinculado.
            </div>
            <small>
                Os vínculos serão administrados na tela “Vínculo de Papéis”.
            </small>
        </div>

        <div id="mensagemUsuario" class="users-message" hidden></div>

        <div class="form-actions">
            <button type="button" id="botaoCancelar" class="secondary-button">
                Cancelar
            </button>

            <button type="submit" id="botaoSalvar" class="primary-button">
                <i class="fa-solid fa-floppy-disk"></i>
                Salvar usuário
            </button>
        </div>
    </form>

</section>
</main>
</div>

<script src="/safe-health/assets/js/tecnologia-usuarios.js?v=3"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
