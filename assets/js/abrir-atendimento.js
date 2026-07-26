document.addEventListener('DOMContentLoaded', function () {
    const formulario = document.getElementById(
        'formAbrirAtendimento'
    );

    if (!formulario) {
        return;
    }

    const mensagem = document.getElementById(
        'mensagemAtendimento'
    );

    const botaoEnviar =
        document.getElementById('btnCriarAtendimento') ||
        formulario.querySelector('button[type="submit"]');

    const referenciaAtendimento = document.getElementById(
        'referenciaAtendimento'
    );

    const numeroAtendimentoCriado = document.getElementById(
        'numeroAtendimentoCriado'
    );

    const textoOriginalBotao =
        botaoEnviar?.innerHTML || 'Criar atendimento';

    function mostrarMensagem(texto, tipo) {
        if (!mensagem) {
            return;
        }

        mensagem.hidden = false;
        mensagem.className = `form-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function alterarEstadoBotao(carregando) {
        if (!botaoEnviar) {
            return;
        }

        botaoEnviar.disabled = carregando;

        if (carregando) {
            botaoEnviar.innerHTML = `
                <i class="fa-solid fa-spinner fa-spin"></i>
                Criando atendimento...
            `;
        } else {
            botaoEnviar.innerHTML = textoOriginalBotao;
        }
    }

    function mostrarAtendimentoCriado(numeroAtendimento) {
        if (
            !referenciaAtendimento ||
            !numeroAtendimentoCriado
        ) {
            return;
        }

        numeroAtendimentoCriado.textContent =
            numeroAtendimento || 'Não informado';

        referenciaAtendimento.hidden = false;
    }

    formulario.addEventListener(
        'submit',
        async function (evento) {
            evento.preventDefault();

            if (botaoEnviar?.disabled) {
                return;
            }

            const dadosFormulario = new FormData(formulario);

            const dados = {
                paciente_id: Number(
                    dadosFormulario.get('paciente_id')
                ),

                senha_id: Number(
                    dadosFormulario.get('senha_id')
                ),

                origem: String(
                    dadosFormulario.get('origem') || ''
                ).trim(),

                tipo_atendimento: String(
                    dadosFormulario.get(
                        'tipo_atendimento'
                    ) || ''
                ).trim(),

                convenio: String(
                    dadosFormulario.get('convenio') || ''
                ).trim(),

                especialidade: String(
                    dadosFormulario.get(
                        'especialidade'
                    ) || ''
                ).trim(),

                profissional_responsavel: String(
                    dadosFormulario.get(
                        'profissional_responsavel'
                    ) || ''
                ).trim()
            };

            if (
                dados.paciente_id <= 0 ||
                dados.senha_id <= 0 ||
                !dados.origem ||
                !dados.tipo_atendimento ||
                !dados.convenio ||
                !dados.especialidade ||
                !dados.profissional_responsavel
            ) {
                mostrarMensagem(
                    'Preencha todos os campos obrigatórios.',
                    'error'
                );

                return;
            }

            alterarEstadoBotao(true);

            mostrarMensagem(
                'Criando atendimento...',
                'loading'
            );

            try {
                const resposta = await fetch(
                    '/safe-health/modules/atendimento/api/atendimentos/salvar.php',
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

                console.log(
                    'Resposta da criação do atendimento:',
                    textoResposta
                );

                let resultado;

                try {
                    resultado = JSON.parse(textoResposta);
                } catch (erroJson) {
                    console.error(
                        'Erro ao interpretar JSON:',
                        erroJson
                    );

                    throw new Error(
                        'A API retornou uma resposta inválida.'
                    );
                }

                if (!resposta.ok || !resultado.sucesso) {
                    throw new Error(
                        resultado.erro ||
                        resultado.mensagem ||
                        'Não foi possível criar o atendimento.'
                    );
                }

                const numeroAtendimento =
                    resultado.dados?.numero_atendimento ||
                    resultado.numero_atendimento ||
                    '';

                mostrarAtendimentoCriado(
                    numeroAtendimento
                );

                mostrarMensagem(
                    numeroAtendimento
                        ? `Atendimento ${numeroAtendimento} criado com sucesso.`
                        : 'Atendimento criado com sucesso.',
                    'success'
                );

                if (botaoEnviar) {
                    botaoEnviar.innerHTML = `
                        <i class="fa-solid fa-circle-check"></i>
                        Atendimento criado
                    `;
                }

                window.setTimeout(function () {
                    window.location.href =
                        '/safe-health/modules/atendimento/recepcao.php'
                        + '?sucesso='
                        + encodeURIComponent(
                            numeroAtendimento
                                ? `Atendimento ${numeroAtendimento} criado com sucesso.`
                                : 'Atendimento criado com sucesso.'
                        );
                }, 2500);
            } catch (erro) {
                console.error(
                    'Erro ao criar atendimento:',
                    erro
                );

                mostrarMensagem(
                    erro instanceof Error
                        ? erro.message
                        : 'Ocorreu um erro inesperado.',
                    'error'
                );

                alterarEstadoBotao(false);
            }
        }
    );
});