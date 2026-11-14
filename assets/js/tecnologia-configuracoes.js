document.addEventListener('DOMContentLoaded', function () {
    const form =
        document.getElementById('formConfiguracoes');

    const mensagem =
        document.getElementById('mensagemConfiguracoes');

    const campos =
        Array.from(
            document.querySelectorAll(
                '[data-chave]'
            )
        );

    function escapar(valor) {
        return String(valor ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function lerJson(resposta) {
        const texto =
            await resposta.text();

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

    function mostrarMensagem(
        texto,
        tipo = 'success'
    ) {
        mensagem.hidden = false;
        mensagem.className =
            `settings-message ${tipo}`;
        mensagem.textContent = texto;
    }

    async function carregarConfiguracoes() {
        mensagem.hidden = true;

        try {
            const resposta = await fetch(
                '/safe-health/modules/tecnologia/api/configuracoes.php',
                {
                    headers: {
                        Accept: 'application/json'
                    },
                    cache: 'no-store'
                }
            );

            const resultado =
                await lerJson(resposta);

            if (
                !resposta.ok ||
                !resultado.sucesso
            ) {
                throw new Error(
                    resultado.mensagem ||
                    'Não foi possível carregar as configurações.'
                );
            }

            const mapa = {};

            (resultado.dados.configuracoes ?? [])
                .forEach(
                    item => {
                        mapa[item.chave] =
                            item.valor ?? '';
                    }
                );

            campos.forEach(
                campo => {
                    const chave =
                        campo.dataset.chave;

                    if (
                        Object.prototype.hasOwnProperty.call(
                            mapa,
                            chave
                        )
                    ) {
                        campo.value =
                            mapa[chave];
                    }
                }
            );

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao carregar configurações.',
                'error'
            );
        }
    }

    form.addEventListener(
        'submit',
        async function (evento) {
            evento.preventDefault();

            if (!form.reportValidity()) {
                return;
            }

            const configuracoes = {};

            campos.forEach(
                campo => {
                    configuracoes[
                        campo.dataset.chave
                    ] =
                        campo.value;
                }
            );

            const botao =
                document.getElementById(
                    'botaoSalvar'
                );

            botao.disabled = true;

            try {
                const resposta = await fetch(
                    '/safe-health/modules/tecnologia/api/configuracoes.php',
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json',
                            Accept:
                                'application/json'
                        },

                        body:
                            JSON.stringify({
                                configuracoes
                            })
                    }
                );

                const resultado =
                    await lerJson(resposta);

                if (
                    !resposta.ok ||
                    !resultado.sucesso
                ) {
                    throw new Error(
                        resultado.mensagem ||
                        'Não foi possível salvar as configurações.'
                    );
                }

                mostrarMensagem(
                    resultado.mensagem
                );

            } catch (erro) {
                mostrarMensagem(
                    erro instanceof Error
                        ? erro.message
                        : 'Erro ao salvar configurações.',
                    'error'
                );

            } finally {
                botao.disabled = false;
            }
        }
    );

    document.getElementById(
        'botaoRecarregar'
    ).addEventListener(
        'click',
        carregarConfiguracoes
    );

    carregarConfiguracoes();
});
