<?php

session_start();

$tituloPagina = 'Cadastro de Paciente';

$senhaId = (int) ($_GET['senha_id'] ?? 0);
$cpf = preg_replace('/\D/', '', (string) ($_GET['cpf'] ?? ''));

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/cadastro-paciente.css"
>

<div class="app-content">

    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">

        <section class="cadastro-paciente-page">

            <div class="cadastro-heading">

                <div>
                    <span class="cadastro-eyebrow">
                        Atendimento
                    </span>

                    <h2>Cadastro de Paciente</h2>

                    <p>
                        Preencha os dados para incluir o paciente
                        no sistema.
                    </p>
                </div>

                <div class="senha-vinculada">
                    <span>Senha vinculada</span>
                    <strong>#<?= $senhaId ?></strong>
                </div>

            </div>

            <form id="formCadastroPaciente">

                <input
                    type="hidden"
                    id="senhaId"
                    name="senha_id"
                    value="<?= $senhaId ?>"
                >

                <section class="form-card">

                    <div class="section-title">
                        <div class="section-icon">
                            <i class="fa-solid fa-user"></i>
                        </div>

                        <div>
                            <h3>Dados pessoais</h3>
                            <p>
                                Informações principais de identificação.
                            </p>
                        </div>
                    </div>

                    <div class="form-grid">

                        <div class="form-group col-8">
                            <label for="nome">
                                Nome completo
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

                        <div class="form-group col-4">
                            <label for="cpf">
                                CPF
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="cpf"
                                name="cpf"
                                maxlength="14"
                                value="<?= htmlspecialchars($cpf) ?>"
                                required
                            >
                        </div>

                        <div class="form-group col-4">
                            <label for="dataNascimento">
                                Data de nascimento
                                <span>*</span>
                            </label>

                            <input
                                type="date"
                                id="dataNascimento"
                                name="data_nascimento"
                                required
                            >
                        </div>

                        <div class="form-group col-4">
                            <label for="sexo">
                                Sexo
                                <span>*</span>
                            </label>

                            <select
                                id="sexo"
                                name="sexo"
                                required
                            >
                                <option value="">
                                    Selecione
                                </option>

                                <option value="M">
                                    Masculino
                                </option>

                                <option value="F">
                                    Feminino
                                </option>

                                <option value="OUTRO">
                                    Outro
                                </option>
                            </select>
                        </div>

                        <div class="form-group col-4">
                            <label for="cartaoSus">
                                Cartão SUS
                            </label>

                            <input
                                type="text"
                                id="cartaoSus"
                                name="cartao_sus"
                                maxlength="20"
                            >
                        </div>

                        <div class="form-group col-12">
                            <label for="nomeMae">
                                Nome da mãe
                            </label>

                            <input
                                type="text"
                                id="nomeMae"
                                name="nome_mae"
                                maxlength="150"
                            >
                        </div>

                    </div>

                </section>

                <section class="form-card">

                    <div class="section-title">
                        <div class="section-icon">
                            <i class="fa-solid fa-address-book"></i>
                        </div>

                        <div>
                            <h3>Contato</h3>
                            <p>
                                Telefone e e-mail do paciente.
                            </p>
                        </div>
                    </div>

                    <div class="form-grid">

                        <div class="form-group col-6">
                            <label for="telefone">
                                Telefone
                            </label>

                            <input
                                type="text"
                                id="telefone"
                                name="telefone"
                                maxlength="20"
                            >
                        </div>

                        <div class="form-group col-6">
                            <label for="email">
                                E-mail
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="150"
                            >
                        </div>

                    </div>

                </section>

                <section class="form-card">

                    <div class="section-title">
                        <div class="section-icon">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>

                        <div>
                            <h3>Endereço</h3>
                            <p>
                                Informe o CEP para preencher o endereço.
                            </p>
                        </div>
                    </div>

                    <div class="form-grid">

                        <div class="form-group col-3">
                            <label for="cep">
                                CEP
                            </label>

                            <div class="input-action">
                                <input
                                    type="text"
                                    id="cep"
                                    name="cep"
                                    maxlength="9"
                                >

                                <button
                                    type="button"
                                    id="buscarCep"
                                    title="Buscar CEP"
                                >
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group col-7">
                            <label for="endereco">
                                Endereço
                            </label>

                            <input
                                type="text"
                                id="endereco"
                                name="endereco"
                                maxlength="200"
                            >
                        </div>

                        <div class="form-group col-2">
                            <label for="numero">
                                Número
                            </label>

                            <input
                                type="text"
                                id="numero"
                                name="numero"
                                maxlength="20"
                            >
                        </div>

                        <div class="form-group col-4">
                            <label for="complemento">
                                Complemento
                            </label>

                            <input
                                type="text"
                                id="complemento"
                                name="complemento"
                                maxlength="100"
                            >
                        </div>

                        <div class="form-group col-4">
                            <label for="bairro">
                                Bairro
                            </label>

                            <input
                                type="text"
                                id="bairro"
                                name="bairro"
                                maxlength="100"
                            >
                        </div>

                        <div class="form-group col-3">
                            <label for="cidade">
                                Cidade
                            </label>

                            <input
                                type="text"
                                id="cidade"
                                name="cidade"
                                maxlength="100"
                            >
                        </div>

                        <div class="form-group col-1">
                            <label for="estado">
                                UF
                            </label>

                            <input
                                type="text"
                                id="estado"
                                name="estado"
                                maxlength="2"
                            >
                        </div>

                    </div>

                </section>

                <div
                    id="mensagemCadastro"
                    class="cadastro-message"
                    hidden
                ></div>

                <div class="form-actions">

                    <a
                        href="/safe-health/modules/atendimento/atender.php?senha_id=<?= $senhaId ?>"
                        class="cancel-button"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        id="salvarPaciente"
                        class="save-button"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>
                        Salvar paciente
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

<script src="/safe-health/assets/js/cadastro-paciente.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>