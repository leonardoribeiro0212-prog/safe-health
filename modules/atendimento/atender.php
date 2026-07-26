<?php

session_start();

require_once __DIR__ . '/../../config/database.php';

$tituloPagina = 'Identificação do Paciente';
$senhaId = (int) ($_GET['senha_id'] ?? 0);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/identificacao-paciente.css"
>

<div class="app-content">

    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">

        <section class="identificacao-page">

            <div class="page-heading">
                <div>
                    <span class="page-eyebrow">
                        Recepção
                    </span>

                    <h2>Identificação do Paciente</h2>

                    <p>
                        Localize o paciente pelo CPF para continuar
                        a abertura do atendimento.
                    </p>
                </div>

                <div class="ticket-reference">
                    <span>Senha vinculada</span>
                    <strong>#<?= $senhaId ?></strong>
                </div>
            </div>

            <div class="identificacao-card">

                <form
                    id="formPesquisaPaciente"
                    class="search-form"
                >
                    <input
                        type="hidden"
                        id="senhaId"
                        value="<?= $senhaId ?>"
                    >

                    <div class="form-field">
                        <label for="cpf">
                            CPF do paciente
                        </label>

                        <input
                            type="text"
                            id="cpf"
                            name="cpf"
                            maxlength="14"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="000.000.000-00"
                            required
                        >
                    </div>

                    <button
                        type="submit"
                        class="search-button"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Pesquisar
                    </button>
                </form>

                <div
                    id="mensagemPesquisa"
                    class="search-message"
                    hidden
                ></div>

                <div
                    id="resultadoPaciente"
                    class="patient-result"
                    hidden
                ></div>

            </div>

        </section>

    </main>

</div>

<script src="/safe-health/assets/js/identificacao-paciente.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>