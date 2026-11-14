document.addEventListener('DOMContentLoaded', function () {
    const formulario =
        document.getElementById('formCadastroPaciente');

    const pacienteId =
        document.getElementById('pacienteId');

    const cpfPesquisa =
        document.getElementById('cpfPesquisa');

    const botaoPesquisar =
        document.getElementById('botaoPesquisarPaciente');

    const botaoNovo =
        document.getElementById('botaoNovoPaciente');

    const statusEncontrado =
        document.getElementById('statusPacienteEncontrado');

    const textoEncontrado =
        document.getElementById('textoPacienteEncontrado');

    const titulo =
        document.getElementById('tituloCadastroPaciente');

    const subtitulo =
        document.getElementById('subtituloCadastroPaciente');

    const botaoSalvar =
        document.getElementById('salvarPaciente');

    const textoBotaoSalvar =
        document.getElementById('textoBotaoSalvar');

    const mensagem =
        document.getElementById('mensagemCadastro');

    const buscarCep =
        document.getElementById('buscarCep');

    const campos = {
        nome: document.getElementById('nome'),
        cpf: document.getElementById('cpf'),
        data_nascimento:
            document.getElementById('dataNascimento'),
        sexo: document.getElementById('sexo'),
        nome_mae: document.getElementById('nomeMae'),
        telefone: document.getElementById('telefone'),
        email: document.getElementById('email'),
        cartao_sus: document.getElementById('cartaoSus'),
        cep: document.getElementById('cep'),
        endereco: document.getElementById('endereco'),
        numero: document.getElementById('numero'),
        complemento: document.getElementById('complemento'),
        bairro: document.getElementById('bairro'),
        cidade: document.getElementById('cidade'),
        estado: document.getElementById('estado')
    };

    let pesquisando = false;
    let salvando = false;

    function somenteNumeros(valor) {
        return String(valor ?? '').replace(/\D/g, '');
    }

    function formatarCpf(valor) {
        const numeros =
            somenteNumeros(valor).slice(0, 11);

        return numeros
            .replace(
                /^(\d{3})(\d)/,
                '$1.$2'
            )
            .replace(
                /^(\d{3})\.(\d{3})(\d)/,
                '$1.$2.$3'
            )
            .replace(
                /\.(\d{3})(\d)/,
                '.$1-$2'
            );
    }

    function formatarCep(valor) {
        const numeros =
            somenteNumeros(valor).slice(0, 8);

        return numeros.replace(
            /^(\d{5})(\d)/,
            '$1-$2'
        );
    }

    function mostrarMensagem(
        texto,
        tipo = 'success'
    ) {
        if (!mensagem) {
            return;
        }

        mensagem.hidden = false;
        mensagem.className =
            `cadastro-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function ocultarMensagem() {
        if (!mensagem) {
            return;
        }

        mensagem.hidden = true;
        mensagem.textContent = '';
        mensagem.className = 'cadastro-message';
    }

    function alterarModoEdicao(
        ativo,
        nomePaciente = ''
    ) {
        if (ativo) {
            titulo.textContent =
                'Alteração de Cadastro de Paciente';

            subtitulo.textContent =
                'Altere os dados necessários e salve o cadastro do paciente.';

            textoBotaoSalvar.textContent =
                'Salvar alterações';

            statusEncontrado.classList.add('show');

            textoEncontrado.textContent =
                nomePaciente
                    ? `Paciente localizado: ${nomePaciente}`
                    : 'Paciente localizado.';

            return;
        }

        pacienteId.value = '';

        titulo.textContent =
            'Cadastro de Paciente';

        subtitulo.textContent =
            'Pesquise um paciente pelo CPF para alterar seus dados ou preencha o formulário para realizar um novo cadastro.';

        textoBotaoSalvar.textContent =
            'Salvar paciente';

        statusEncontrado.classList.remove('show');

        textoEncontrado.textContent =
            'Paciente localizado.';
    }

    function preencherFormulario(paciente) {
        pacienteId.value =
            String(paciente.id ?? '');

        Object.entries(campos).forEach(
            ([nomeCampo, elemento]) => {
                if (!elemento) {
                    return;
                }

                let valor =
                    paciente[nomeCampo] ?? '';

                if (nomeCampo === 'cpf') {
                    valor = formatarCpf(valor);
                }

                if (nomeCampo === 'cep') {
                    valor = formatarCep(valor);
                }

                elemento.value = valor;
            }
        );

        cpfPesquisa.value =
            formatarCpf(paciente.cpf ?? '');

        alterarModoEdicao(
            true,
            paciente.nome ?? ''
        );
    }

    function limparFormulario() {
        formulario.reset();

        alterarModoEdicao(false);

        cpfPesquisa.value = '';

        const cpfInicial =
            new URLSearchParams(
                window.location.search
            ).get('cpf');

        if (cpfInicial) {
            campos.cpf.value =
                formatarCpf(cpfInicial);
        }

        ocultarMensagem();
    }

    async function lerJson(resposta) {
        const texto = await resposta.text();

        if (texto.trim() === '') {
            throw new Error(
                'O servidor não retornou uma resposta.'
            );
        }

        try {
            return JSON.parse(texto);
        } catch {
            console.error(
                'Resposta inválida:',
                texto
            );

            throw new Error(
                'O servidor retornou uma resposta inválida.'
            );
        }
    }

    async function pesquisarPaciente() {
        if (pesquisando) {
            return;
        }

        const cpf =
            somenteNumeros(cpfPesquisa.value);

        if (cpf.length !== 11) {
            mostrarMensagem(
                'Informe um CPF com 11 números para pesquisar.',
                'error'
            );

            cpfPesquisa.focus();
            return;
        }

        pesquisando = true;
        botaoPesquisar.disabled = true;
        ocultarMensagem();

        try {
            const resposta = await fetch(
                `/safe-health/modules/atendimento/api/pacientes/buscar-por-cpf.php?cpf=${encodeURIComponent(cpf)}`,
                {
                    headers: {
                        Accept: 'application/json'
                    },
                    cache: 'no-store'
                }
            );

            const resultado =
                await lerJson(resposta);

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(
                    resultado.mensagem ||
                    'Não foi possível pesquisar o paciente.'
                );
            }

            if (!resultado.encontrado) {
                limparFormulario();

                cpfPesquisa.value =
                    formatarCpf(cpf);

                campos.cpf.value =
                    formatarCpf(cpf);

                mostrarMensagem(
                    'Paciente não encontrado. Você pode realizar um novo cadastro com este CPF.',
                    'info'
                );

                campos.nome.focus();
                return;
            }

            preencherFormulario(
                resultado.paciente
            );

            mostrarMensagem(
                'Paciente encontrado. Os dados foram carregados para alteração.',
                'success'
            );

        } catch (erro) {
            console.error(erro);

            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Não foi possível pesquisar o paciente.',
                'error'
            );
        } finally {
            pesquisando = false;
            botaoPesquisar.disabled = false;
        }
    }

    function coletarDados() {
        const dados =
            Object.fromEntries(
                new FormData(formulario).entries()
            );

        dados.paciente_id =
            Number(pacienteId.value || 0);

        return dados;
    }

    async function salvarPaciente(evento) {
        evento.preventDefault();

        if (salvando) {
            return;
        }

        if (!formulario.reportValidity()) {
            return;
        }

        salvando = true;
        botaoSalvar.disabled = true;

        const dados = coletarDados();

        const editando =
            Number.isInteger(dados.paciente_id) &&
            dados.paciente_id > 0;

        const url = editando
            ? '/safe-health/modules/atendimento/api/pacientes/atualizar.php'
            : '/safe-health/modules/atendimento/api/pacientes/salvar.php';

        try {
            const resposta = await fetch(
                url,
                {
                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json'
                    },

                    body: JSON.stringify(dados)
                }
            );

            const resultado =
                await lerJson(resposta);

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(
                    resultado.mensagem ||
                    'Não foi possível salvar o paciente.'
                );
            }

            mostrarMensagem(
                resultado.mensagem,
                'success'
            );

            if (!editando) {
                const novoId =
                    resultado.paciente_id ??
                    resultado.dados?.paciente_id;

                if (novoId) {
                    pacienteId.value =
                        String(novoId);

                    alterarModoEdicao(
                        true,
                        campos.nome.value
                    );
                }
            }

        } catch (erro) {
            console.error(erro);

            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Não foi possível salvar o paciente.',
                'error'
            );
        } finally {
            salvando = false;
            botaoSalvar.disabled = false;
        }
    }

    async function pesquisarCep() {
        const cep = somenteNumeros(
            campos.cep.value
        );

        if (cep.length !== 8) {
            mostrarMensagem(
                'Informe um CEP com 8 números.',
                'error'
            );

            return;
        }

        try {
            const resposta = await fetch(
                `https://viacep.com.br/ws/${cep}/json/`
            );

            const dados = await resposta.json();

            if (!resposta.ok || dados.erro) {
                throw new Error(
                    'CEP não encontrado.'
                );
            }

            campos.endereco.value =
                dados.logradouro ?? '';

            campos.bairro.value =
                dados.bairro ?? '';

            campos.cidade.value =
                dados.localidade ?? '';

            campos.estado.value =
                dados.uf ?? '';

            campos.cep.value =
                formatarCep(cep);

            campos.numero.focus();

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Não foi possível consultar o CEP.',
                'error'
            );
        }
    }

    cpfPesquisa.addEventListener(
        'input',
        () => {
            cpfPesquisa.value =
                formatarCpf(cpfPesquisa.value);
        }
    );

    campos.cpf.addEventListener(
        'input',
        () => {
            campos.cpf.value =
                formatarCpf(campos.cpf.value);
        }
    );

    campos.cep.addEventListener(
        'input',
        () => {
            campos.cep.value =
                formatarCep(campos.cep.value);
        }
    );

    campos.estado.addEventListener(
        'input',
        () => {
            campos.estado.value =
                campos.estado.value
                    .toUpperCase()
                    .slice(0, 2);
        }
    );

    botaoPesquisar.addEventListener(
        'click',
        pesquisarPaciente
    );

    cpfPesquisa.addEventListener(
        'keydown',
        (evento) => {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                pesquisarPaciente();
            }
        }
    );

    botaoNovo.addEventListener(
        'click',
        limparFormulario
    );

    buscarCep?.addEventListener(
        'click',
        pesquisarCep
    );

    formulario.addEventListener(
        'submit',
        salvarPaciente
    );

    if (
        somenteNumeros(cpfPesquisa.value).length === 11
    ) {
        cpfPesquisa.value =
            formatarCpf(cpfPesquisa.value);
    }

    if (
        somenteNumeros(campos.cpf.value).length === 11
    ) {
        campos.cpf.value =
            formatarCpf(campos.cpf.value);
    }
});
