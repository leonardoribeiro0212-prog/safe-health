document.addEventListener('DOMContentLoaded', function () {
    const form =
        document.getElementById('formAbrirAtendimento');

    const mensagem =
        document.getElementById('mensagemAtendimento');

    const botao =
        document.getElementById('btnCriarAtendimento');

    const medico =
        document.getElementById('profissionalResponsavel');

    const especialidade =
        document.getElementById('especialidade');

    function mostrarMensagem(
        texto,
        tipo = 'error'
    ) {
        mensagem.hidden = false;
        mensagem.className =
            `form-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function atualizarEspecialidade() {
        const opcao =
            medico.options[
                medico.selectedIndex
            ];

        const valor =
            opcao?.dataset?.especialidade || '';

        especialidade.value = valor;
    }

    medico.addEventListener(
        'change',
        atualizarEspecialidade
    );

    atualizarEspecialidade();

    form.addEventListener(
        'submit',
        async function (evento) {
            evento.preventDefault();

            mensagem.hidden = true;

            const pacienteId =
                Number(
                    form.querySelector(
                        '[name="paciente_id"]'
                    ).value
                );

            const senhaId =
                Number(
                    form.querySelector(
                        '[name="senha_id"]'
                    ).value
                );

            const tipoAtendimento =
                document.getElementById(
                    'tipoAtendimento'
                ).value;

            const origem =
                document.getElementById(
                    'origem'
                ).value;

            const convenio =
                document.getElementById(
                    'convenio'
                ).value;

            const profissionalId =
                Number(medico.value);

            atualizarEspecialidade();

            if (
                !pacienteId ||
                !senhaId ||
                !tipoAtendimento ||
                !origem ||
                !convenio ||
                !profissionalId ||
                !especialidade.value
            ) {
                mostrarMensagem(
                    'Preencha todos os campos obrigatórios.'
                );
                return;
            }

            botao.disabled = true;

            try {
                const resposta = await fetch(
                    '/safe-health/modules/atendimento/api/atendimentos/salvar.php',
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json',
                            Accept:
                                'application/json'
                        },

                        body: JSON.stringify({
                            paciente_id:
                                pacienteId,

                            senha_id:
                                senhaId,

                            tipo_atendimento:
                                tipoAtendimento,

                            origem:
                                origem,

                            convenio:
                                convenio,

                            profissional_id:
                                profissionalId
                        })
                    }
                );

                const texto =
                    await resposta.text();

                let resultado;

                try {
                    resultado =
                        JSON.parse(texto);
                } catch {
                    console.error(
                        'Resposta inválida:',
                        texto
                    );

                    throw new Error(
                        'O servidor retornou uma resposta inválida.'
                    );
                }

                if (
                    !resposta.ok ||
                    !resultado.sucesso
                ) {
                    throw new Error(
                        resultado.mensagem ||
                        'Não foi possível criar o atendimento.'
                    );
                }

                const numero =
                    resultado.dados
                        ?.numero_atendimento;

                const referencia =
                    document.getElementById(
                        'numeroAtendimentoCriado'
                    );

                if (
                    referencia &&
                    numero
                ) {
                    referencia.textContent =
                        numero;
                }

                mostrarMensagem(
                    resultado.mensagem ||
                    'Atendimento criado com sucesso.',
                    'success'
                );

            } catch (erro) {
                console.error(erro);

                mostrarMensagem(
                    erro instanceof Error
                        ? erro.message
                        : 'Não foi possível criar o atendimento.'
                );

            } finally {
                botao.disabled = false;
            }
        }
    );
});
