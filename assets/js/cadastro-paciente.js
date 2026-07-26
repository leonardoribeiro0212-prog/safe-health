document.addEventListener('DOMContentLoaded', function () {
    const formulario =
        document.getElementById('formCadastroPaciente');

    const campoCpf =
        document.getElementById('cpf');

    const campoTelefone =
        document.getElementById('telefone');

    const campoCep =
        document.getElementById('cep');

    const campoEstado =
        document.getElementById('estado');

    const botaoBuscarCep =
        document.getElementById('buscarCep');

    const botaoSalvar =
        document.getElementById('salvarPaciente');

    const mensagem =
        document.getElementById('mensagemCadastro');

    function somenteNumeros(valor) {
        return String(valor ?? '').replace(/\D/g, '');
    }

    function formatarCpf(valor) {
        const numeros = somenteNumeros(valor).slice(0, 11);

        return numeros
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(
                /^(\d{3})\.(\d{3})(\d)/,
                '$1.$2.$3'
            )
            .replace(
                /^(\d{3})\.(\d{3})\.(\d{3})(\d)/,
                '$1.$2.$3-$4'
            );
    }

    function formatarCep(valor) {
        const numeros = somenteNumeros(valor).slice(0, 8);

        return numeros.replace(
            /^(\d{5})(\d)/,
            '$1-$2'
        );
    }

    function formatarTelefone(valor) {
        const numeros = somenteNumeros(valor).slice(0, 11);

        if (numeros.length <= 10) {
            return numeros
                .replace(/^(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{4})(\d)/, '$1-$2');
        }

        return numeros
            .replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{5})(\d)/, '$1-$2');
    }

    function mostrarMensagem(texto, tipo) {
        mensagem.hidden = false;
        mensagem.className = `cadastro-message ${tipo}`;
        mensagem.textContent = texto;
    }

    campoCpf.value = formatarCpf(campoCpf.value);

    campoCpf.addEventListener('input', function () {
        campoCpf.value = formatarCpf(campoCpf.value);
    });

    campoCep.addEventListener('input', function () {
        campoCep.value = formatarCep(campoCep.value);
    });

    campoTelefone.addEventListener('input', function () {
        campoTelefone.value =
            formatarTelefone(campoTelefone.value);
    });

    campoEstado.addEventListener('input', function () {
        campoEstado.value = campoEstado.value
            .replace(/[^a-zA-Z]/g, '')
            .toUpperCase()
            .slice(0, 2);
    });

    async function buscarEndereco() {
        const cep = somenteNumeros(campoCep.value);

        if (cep.length !== 8) {
            mostrarMensagem(
                'Informe um CEP com 8 números.',
                'error'
            );

            return;
        }

        mostrarMensagem(
            'Consultando CEP...',
            'loading'
        );

        try {
            const resposta = await fetch(
                `https://viacep.com.br/ws/${cep}/json/`
            );

            const dados = await resposta.json();

            if (dados.erro) {
                throw new Error('CEP não encontrado.');
            }

            document.getElementById('endereco').value =
                dados.logradouro || '';

            document.getElementById('bairro').value =
                dados.bairro || '';

            document.getElementById('cidade').value =
                dados.localidade || '';

            campoEstado.value = dados.uf || '';

            mensagem.hidden = true;

            document.getElementById('numero').focus();
        } catch (erro) {
            mostrarMensagem(
                erro.message ||
                'Não foi possível consultar o CEP.',
                'error'
            );
        }
    }

    botaoBuscarCep.addEventListener(
        'click',
        buscarEndereco
    );

    campoCep.addEventListener('blur', function () {
        if (somenteNumeros(campoCep.value).length === 8) {
            buscarEndereco();
        }
    });

    formulario.addEventListener(
        'submit',
        async function (evento) {
            evento.preventDefault();

            const cpf = somenteNumeros(campoCpf.value);
            const senhaId =
                document.getElementById('senhaId').value;

            if (cpf.length !== 11) {
                mostrarMensagem(
                    'Informe um CPF com 11 números.',
                    'error'
                );

                campoCpf.focus();
                return;
            }

            const dados = {
                nome:
                    document.getElementById('nome').value.trim(),

                cpf,

                data_nascimento:
                    document
                        .getElementById('dataNascimento')
                        .value,

                sexo:
                    document.getElementById('sexo').value,

                nome_mae:
                    document.getElementById('nomeMae').value.trim(),

                telefone:
                    campoTelefone.value.trim(),

                email:
                    document.getElementById('email').value.trim(),

                cartao_sus:
                    document.getElementById('cartaoSus').value.trim(),

                cep:
                    somenteNumeros(campoCep.value),

                endereco:
                    document.getElementById('endereco').value.trim(),

                numero:
                    document.getElementById('numero').value.trim(),

                complemento:
                    document
                        .getElementById('complemento')
                        .value
                        .trim(),

                bairro:
                    document.getElementById('bairro').value.trim(),

                cidade:
                    document.getElementById('cidade').value.trim(),

                estado:
                    campoEstado.value.trim()
            };

            botaoSalvar.disabled = true;

            botaoSalvar.innerHTML = `
                <i class="fa-solid fa-spinner fa-spin"></i>
                Salvando...
            `;

            mostrarMensagem(
                'Salvando paciente...',
                'loading'
            );

            try {
                const resposta = await fetch(
                    '/safe-health/modules/atendimento/api/pacientes/salvar.php',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json'
                        },
                        body: JSON.stringify(dados)
                    }
                );

                const textoResposta = await resposta.text();

                let resultado;

                try {
                    resultado = JSON.parse(textoResposta);
                } catch {
                    throw new Error(
                        'A API retornou uma resposta inválida.'
                    );
                }

                if (!resposta.ok || !resultado.sucesso) {
                    throw new Error(
                        resultado.mensagem ||
                        'Não foi possível cadastrar o paciente.'
                    );
                }

                mostrarMensagem(
                    'Paciente cadastrado com sucesso!',
                    'success'
                );

                setTimeout(function () {
                    window.location.href =
                        `/safe-health/modules/atendimento/atender.php?senha_id=${encodeURIComponent(senhaId)}&cpf=${encodeURIComponent(resultado.cpf)}`;
                }, 900);
            } catch (erro) {
                mostrarMensagem(
                    erro.message ||
                    'Erro ao cadastrar paciente.',
                    'error'
                );

                botaoSalvar.disabled = false;

                botaoSalvar.innerHTML = `
                    <i class="fa-solid fa-floppy-disk"></i>
                    Salvar paciente
                `;
            }
        }
    );
});