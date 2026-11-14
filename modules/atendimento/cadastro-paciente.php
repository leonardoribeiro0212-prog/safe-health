<?php

declare(strict_types=1);

session_start();

$tituloPagina = 'Cadastro de Paciente';

$senhaId = (int) ($_GET['senha_id'] ?? 0);

$cpfInicial = preg_replace(
    '/\D/',
    '',
    (string) ($_GET['cpf'] ?? '')
);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/cadastro-paciente.css?v=7"
>

<style>
    .patient-search-card {
        margin-bottom: 22px;
        padding: 22px;
        border: 1px solid #dfe7eb;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 8px 24px rgba(22, 70, 85, 0.06);
    }

    .patient-search-header {
        margin-bottom: 15px;
    }

    .patient-search-header h3 {
        margin: 0 0 5px;
        color: #173448;
        font-size: 18px;
    }

    .patient-search-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .patient-search-row {
        display: flex;
        align-items: flex-end;
        gap: 12px;
    }

    .patient-search-field {
        flex: 1;
    }

    .patient-search-field label {
        display: block;
        margin-bottom: 7px;
        color: #334155;
        font-size: 14px;
        font-weight: 700;
    }

    .patient-search-input {
        position: relative;
    }

    .patient-search-input i {
        position: absolute;
        top: 50%;
        left: 14px;
        transform: translateY(-50%);
        color: #78909c;
    }

    .patient-search-input input {
        width: 100%;
        min-height: 48px;
        padding: 0 14px 0 42px;
        border: 1px solid #cad8e0;
        border-radius: 10px;
        background: #ffffff;
        color: #172033;
        font: inherit;
        outline: none;
    }

    .patient-search-input input:focus {
        border-color: #087f9b;
        box-shadow: 0 0 0 3px rgba(8, 127, 155, 0.10);
    }

    .patient-search-button,
    .patient-new-button {
        display: inline-flex;
        min-height: 48px;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 18px;
        border-radius: 10px;
        font: inherit;
        font-weight: 700;
        cursor: pointer;
    }

    .patient-search-button {
        border: 1px solid #087f9b;
        background: #087f9b;
        color: #ffffff;
    }

    .patient-new-button {
        border: 1px solid #cad8e0;
        background: #ffffff;
        color: #334155;
    }

    .patient-edit-status {
        display: none;
        align-items: center;
        gap: 8px;
        margin-top: 14px;
        padding: 11px 13px;
        border: 1px solid #86efac;
        border-radius: 10px;
        background: #f0fdf4;
        color: #166534;
        font-size: 14px;
        font-weight: 700;
    }

    .patient-edit-status.show {
        display: flex;
    }

    @media (max-width: 760px) {
        .patient-search-row {
            align-items: stretch;
            flex-direction: column;
        }

        .patient-search-button,
        .patient-new-button {
            width: 100%;
        }
    }
</style>

<div class="app-content">

    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">

        <section class="cadastro-paciente-page">

            <div class="cadastro-heading">

                <div>
                    <span class="cadastro-eyebrow">
                        Cadastros
                    </span>

                    <h2 id="tituloCadastroPaciente">
                        Cadastro de Paciente
                    </h2>

                    <p id="subtituloCadastroPaciente">
                        Pesquise um paciente pelo CPF para alterar
                        seus dados ou preencha o formulário para
                        realizar um novo cadastro.
                    </p>
                </div>

                <?php if ($senhaId > 0): ?>
                    <div class="senha-vinculada">
                        <span>Senha vinculada</span>
                        <strong>#<?= $senhaId ?></strong>
                    </div>
                <?php endif; ?>

            </div>

            <section class="patient-search-card">

                <div class="patient-search-header">
                    <h3>Pesquisar paciente</h3>

                    <p>
                        Digite o CPF para localizar o cadastro
                        que deseja consultar ou alterar.
                    </p>
                </div>

                <div class="patient-search-row">

                    <div class="patient-search-field">
                        <label for="cpfPesquisa">
                            CPF do paciente
                        </label>

                        <div class="patient-search-input">
                            <i class="fa-solid fa-magnifying-glass"></i>

                            <input
                                type="text"
                                id="cpfPesquisa"
                                maxlength="14"
                                value="<?= htmlspecialchars(
                                    $cpfInicial,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="000.000.000-00"
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <button
                        type="button"
                        id="botaoPesquisarPaciente"
                        class="patient-search-button"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Pesquisar
                    </button>

                    <button
                        type="button"
                        id="botaoNovoPaciente"
                        class="patient-new-button"
                    >
                        <i class="fa-solid fa-user-plus"></i>
                        Novo cadastro
                    </button>

                </div>

                <div
                    id="statusPacienteEncontrado"
                    class="patient-edit-status"
                >
                    <i class="fa-solid fa-user-check"></i>

                    <span id="textoPacienteEncontrado">
                        Paciente localizado.
                    </span>
                </div>

            </section>

            <form id="formCadastroPaciente">

                <input
                    type="hidden"
                    id="senhaId"
                    name="senha_id"
                    value="<?= $senhaId ?>"
                >

                <input
                    type="hidden"
                    id="pacienteId"
                    name="paciente_id"
                    value=""
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
                                value="<?= htmlspecialchars(
                                    $cpfInicial,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
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

                    <?php if ($senhaId > 0): ?>
                        <a
                            href="/safe-health/modules/atendimento/atender.php?senha_id=<?= $senhaId ?>"
                            class="cancel-button"
                        >
                            <i class="fa-solid fa-arrow-left"></i>
                            Cancelar
                        </a>
                    <?php else: ?>
                        <a
                            href="/safe-health/index.php"
                            class="cancel-button"
                        >
                            <i class="fa-solid fa-arrow-left"></i>
                            Voltar
                        </a>
                    <?php endif; ?>

                    <button
                        type="submit"
                        id="salvarPaciente"
                        class="save-button"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span id="textoBotaoSalvar">
                            Salvar paciente
                        </span>
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>


<script>
/*
 * Safe Health - contingência para consulta de CEP.
 *
 * O cadastro atual utiliza o ViaCEP no arquivo cadastro-paciente.js.
 * Em algumas redes o domínio viacep.com.br pode ficar indisponível
 * ou expirar por timeout. Este interceptador mantém o código atual
 * funcionando e tenta automaticamente a BrasilAPI como contingência.
 *
 * Para todas as outras chamadas do sistema, o fetch original é mantido.
 */
(function () {
    const fetchOriginal = window.fetch.bind(window);

    function ehViaCep(recurso) {
        const url =
            typeof recurso === 'string'
                ? recurso
                : recurso?.url || '';

        return /https?:\/\/(?:www\.)?viacep\.com\.br\/ws\/\d{8}\/json\/?/i.test(url);
    }

    function extrairCep(recurso) {
        const url =
            typeof recurso === 'string'
                ? recurso
                : recurso?.url || '';

        const match = url.match(/\/ws\/(\d{8})\/json/i);

        return match ? match[1] : '';
    }

    async function consultarComTimeout(
        url,
        opcoes = {},
        timeoutMs = 4500
    ) {
        const controller = new AbortController();

        const timer = window.setTimeout(
            () => controller.abort(),
            timeoutMs
        );

        try {
            return await fetchOriginal(
                url,
                {
                    ...opcoes,
                    signal: controller.signal
                }
            );
        } finally {
            window.clearTimeout(timer);
        }
    }

    window.fetch = async function (
        recurso,
        opcoes = {}
    ) {
        if (!ehViaCep(recurso)) {
            return fetchOriginal(
                recurso,
                opcoes
            );
        }

        const cep = extrairCep(recurso);

        /*
         * 1ª tentativa: ViaCEP, com timeout controlado.
         */
        try {
            const respostaViaCep =
                await consultarComTimeout(
                    typeof recurso === 'string'
                        ? recurso
                        : recurso.url,
                    opcoes
                );

            if (respostaViaCep.ok) {
                return respostaViaCep;
            }
        } catch (erroViaCep) {
            console.warn(
                'ViaCEP indisponível. Tentando serviço alternativo.',
                erroViaCep
            );
        }

        /*
         * 2ª tentativa: BrasilAPI.
         * Convertemos a resposta para o mesmo formato esperado
         * pelo código atual do ViaCEP.
         */
        if (cep) {
            try {
                const respostaBrasilApi =
                    await consultarComTimeout(
                        `https://brasilapi.com.br/api/cep/v1/${cep}`,
                        {
                            headers: {
                                Accept: 'application/json'
                            }
                        }
                    );

                if (respostaBrasilApi.ok) {
                    const dados =
                        await respostaBrasilApi.json();

                    const dadosCompativeis = {
                        cep:
                            dados.cep || cep,
                        logradouro:
                            dados.street || '',
                        complemento:
                            '',
                        bairro:
                            dados.neighborhood || '',
                        localidade:
                            dados.city || '',
                        uf:
                            dados.state || '',
                        ibge:
                            '',
                        gia:
                            '',
                        ddd:
                            '',
                        siafi:
                            ''
                    };

                    return new Response(
                        JSON.stringify(
                            dadosCompativeis
                        ),
                        {
                            status: 200,
                            headers: {
                                'Content-Type':
                                    'application/json; charset=utf-8'
                            }
                        }
                    );
                }
            } catch (erroAlternativo) {
                console.warn(
                    'Serviço alternativo de CEP também indisponível.',
                    erroAlternativo
                );
            }
        }

        /*
         * Se nenhum serviço responder, devolvemos uma resposta
         * compatível com o ViaCEP em vez de deixar o navegador
         * gerar "Failed to fetch". O usuário continua podendo
         * preencher o endereço manualmente.
         */
        return new Response(
            JSON.stringify({
                erro: true,
                indisponivel: true
            }),
            {
                status: 200,
                headers: {
                    'Content-Type':
                        'application/json; charset=utf-8'
                }
            }
        );
    };
})();
</script>

<script>
/*
 * Safe Health - validação local de CPF.
 * A mesma regra também é executada nas APIs PHP; portanto,
 * esta validação no navegador serve como retorno imediato ao usuário.
 */
(function () {
    function somenteNumeros(valor) {
        return String(valor || '').replace(/\D/g, '');
    }

    function validarCPF(valor) {
        const cpf = somenteNumeros(valor);

        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
            return false;
        }

        for (let posicao = 9; posicao <= 10; posicao++) {
            let soma = 0;
            let peso = posicao + 1;

            for (let i = 0; i < posicao; i++, peso--) {
                soma += Number(cpf[i]) * peso;
            }

            const resto = soma % 11;
            const digito = resto < 2 ? 0 : 11 - resto;

            if (Number(cpf[posicao]) !== digito) {
                return false;
            }
        }

        return true;
    }

    function formatarCPF(valor) {
        const cpf = somenteNumeros(valor).slice(0, 11);
        return cpf
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }

    function aplicarEstado(input, exibirMensagem = true) {
        if (!input) return true;

        input.value = formatarCPF(input.value);
        const vazio = somenteNumeros(input.value).length === 0;
        const valido = !vazio && validarCPF(input.value);

        input.setCustomValidity(
            valido || (!exibirMensagem && vazio)
                ? ''
                : 'CPF inválido. Verifique os números informados.'
        );

        input.style.borderColor =
            vazio ? '' : (valido ? '#198754' : '#dc3545');

        return valido;
    }

    const cpfCadastro = document.getElementById('cpf');
    const cpfPesquisa = document.getElementById('cpfPesquisa');
    const botaoPesquisar = document.getElementById('botaoPesquisarPaciente');
    const formulario = document.getElementById('formCadastroPaciente');

    [cpfCadastro, cpfPesquisa].forEach((input) => {
        if (!input) return;

        input.addEventListener('input', () => {
            input.value = formatarCPF(input.value);
            input.setCustomValidity('');
            input.style.borderColor = '';
        });

        input.addEventListener('blur', () => {
            if (somenteNumeros(input.value).length > 0) {
                aplicarEstado(input);
            }
        });
    });

    // Captura antes do JS original: CPF inválido nem chega à API de pesquisa.
    botaoPesquisar?.addEventListener('click', (evento) => {
        if (!aplicarEstado(cpfPesquisa)) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            cpfPesquisa.reportValidity();
            cpfPesquisa.focus();
        }
    }, true);

    // A API PHP repete a validação, mas bloqueamos antes para melhorar a UX.
    formulario?.addEventListener('submit', (evento) => {
        if (!aplicarEstado(cpfCadastro)) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            cpfCadastro.reportValidity();
            cpfCadastro.focus();
        }
    }, true);

    window.SafeHealthCPF = {
        validar: validarCPF,
        formatar: formatarCPF,
        normalizar: somenteNumeros
    };
})();
</script>

<script src="/safe-health/assets/js/cadastro-paciente.js?v=8"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
