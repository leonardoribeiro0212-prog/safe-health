<?php
declare(strict_types=1);

$tituloPagina = 'Alteração de Senha';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/tecnologia-alteracao-senha.css?v=2">

<div class="app-content">
<?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

<main class="main-content">
<section class="password-page">

    <div class="password-heading">
        <div>
            <span class="password-eyebrow">Tecnologia</span>
            <h2>Alteração de Senha</h2>
            <p>
                Pesquise um usuário e defina uma nova senha de acesso ao Safe Health.
            </p>
        </div>
    </div>

    <section class="password-card">
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-user-lock"></i>
            </div>

            <div>
                <h3>Selecionar usuário</h3>
                <p>
                    Localize o usuário pelo nome ou e-mail antes de alterar a senha.
                </p>
            </div>
        </div>

        <div class="search-row">
            <div class="search-input">
                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    id="campoPesquisaUsuario"
                    placeholder="Digite nome ou e-mail"
                    autocomplete="off"
                >
            </div>

            <button
                type="button"
                id="botaoPesquisarUsuario"
                class="primary-button"
            >
                <i class="fa-solid fa-magnifying-glass"></i>
                Pesquisar
            </button>
        </div>

        <div id="listaUsuarios" class="users-results"></div>
    </section>

    <section
        id="cardAlteracao"
        class="password-card"
        hidden
    >
        <div class="section-title">
            <div class="section-icon">
                <i class="fa-solid fa-key"></i>
            </div>

            <div>
                <h3>Definir nova senha</h3>
                <p>
                    A senha anterior não será exibida e não precisa ser informada.
                </p>
            </div>
        </div>

        <div class="selected-user">
            <div>
                <span>Usuário selecionado</span>
                <strong id="usuarioNome">-</strong>
            </div>

            <div>
                <span>E-mail</span>
                <strong id="usuarioEmail">-</strong>
            </div>

            <div>
                <span>Status</span>
                <strong id="usuarioStatus">-</strong>
            </div>
        </div>

        <form id="formAlteracaoSenha" autocomplete="off">
            <input type="hidden" id="usuarioId">

            <div class="field">
                <label for="novaSenha">
                    Nova senha
                    <span>*</span>
                </label>

                <div class="password-input">
                    <input
                        type="password"
                        id="novaSenha"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        data-target="novaSenha"
                        aria-label="Mostrar ou ocultar nova senha"
                    >
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="password-rules">
                <strong>A nova senha deve conter:</strong>

                <ul>
                    <li id="regraTamanho">
                        <i class="fa-solid fa-circle"></i>
                        Pelo menos 8 caracteres
                    </li>

                    <li id="regraMaiuscula">
                        <i class="fa-solid fa-circle"></i>
                        Pelo menos uma letra maiúscula
                    </li>

                    <li id="regraMinuscula">
                        <i class="fa-solid fa-circle"></i>
                        Pelo menos uma letra minúscula
                    </li>

                    <li id="regraNumero">
                        <i class="fa-solid fa-circle"></i>
                        Pelo menos um número
                    </li>
                </ul>
            </div>

            <div class="field">
                <label for="confirmarSenha">
                    Confirmar nova senha
                    <span>*</span>
                </label>

                <div class="password-input">
                    <input
                        type="password"
                        id="confirmarSenha"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        data-target="confirmarSenha"
                        aria-label="Mostrar ou ocultar confirmação"
                    >
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div id="senhaMatch" class="match-message" hidden></div>
            <div id="mensagemSenha" class="password-message" hidden></div>

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
                    id="botaoAlterar"
                    class="primary-button"
                >
                    <i class="fa-solid fa-shield-halved"></i>
                    Alterar senha
                </button>
            </div>
        </form>
    </section>

    <section class="security-card">
        <i class="fa-solid fa-circle-info"></i>

        <div>
            <strong>Redefinição administrativa</strong>

            <p>
                Esta tela redefine a senha do usuário selecionado. A senha atual não é necessária.
                A nova senha será armazenada usando <code>password_hash()</code>.
            </p>
        </div>
    </section>

</section>
</main>
</div>

<script src="/safe-health/assets/js/tecnologia-alteracao-senha.js?v=2"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
