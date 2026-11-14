document.addEventListener('DOMContentLoaded', function () {
    const form =
        document.getElementById('formProcedimento');

    const procedimentoId =
        document.getElementById('procedimentoId');

    const pesquisa =
        document.getElementById('pesquisaProcedimento');

    const resultados =
        document.getElementById('resultadosPesquisa');

    const modoCadastro =
        document.getElementById('modoCadastro');

    const mensagem =
        document.getElementById('mensagemProcedimento');

    const botaoSalvar =
        document.getElementById('botaoSalvarProcedimento');

    const textoBotaoSalvar =
        document.getElementById('textoBotaoSalvar');

    const campos = {
        nome:
            document.getElementById('nome'),

        codigo:
            document.getElementById('codigo'),

        categoria:
            document.getElementById('categoria'),

        descricao:
            document.getElementById('descricao'),

        duracao_estimada_minutos:
            document.getElementById('duracaoEstimada'),

        valor_unitario:
            document.getElementById('valorUnitario'),

        exige_autorizacao:
            document.getElementById('exigeAutorizacao'),

        ativo:
            document.getElementById('ativo')
    };

    function escapar(valor) {
        return String(valor ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function mostrarMensagem(
        texto,
        tipo = 'success'
    ) {
        mensagem.hidden = false;
        mensagem.className =
            `procedure-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function ocultarMensagem() {
        mensagem.hidden = true;
        mensagem.textContent = '';
        mensagem.className =
            'procedure-message';
    }

    function modoEdicao(ativo) {
        modoCadastro.textContent =
            ativo
                ? 'Alterando cadastro'
                : 'Novo cadastro';

        modoCadastro.classList.toggle(
            'editing',
            ativo
        );

        textoBotaoSalvar.textContent =
            ativo
                ? 'Salvar alterações'
                : 'Salvar procedimento';
    }

    function novoCadastro() {
        form.reset();

        procedimentoId.value = '';
        campos.valor_unitario.value = '0.00';
        campos.exige_autorizacao.value = '0';
        campos.ativo.value = '1';

        resultados.hidden = true;
        resultados.innerHTML = '';

        modoEdicao(false);
        ocultarMensagem();

        campos.nome.focus();
    }

    function renderizarResultados(lista) {
        resultados.hidden = false;

        if (
            !Array.isArray(lista) ||
            lista.length === 0
        ) {
            resultados.innerHTML = `
                <div class="procedure-result-empty">
                    Nenhum procedimento encontrado.
                </div>
            `;

            return;
        }

        resultados.innerHTML = lista
            .map((item) => `
                <button
                    type="button"
                    class="procedure-result-item"
                    data-id="${Number(item.id)}"
                >
                    <div>
                        <strong>
                            ${escapar(item.nome)}
                        </strong>

                        <span>
                            ${escapar(item.codigo || 'Sem código')}
                            ${item.categoria ? ` · ${escapar(item.categoria)}` : ''}
                        </span>
                    </div>

                    <span
                        class="procedure-result-status ${Number(item.ativo) === 1 ? 'active' : 'inactive'}"
                    >
                        ${Number(item.ativo) === 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </button>
            `)
            .join('');
    }

    async function lerJson(resposta) {
        const texto =
            await resposta.text();

        try {
            return JSON.parse(texto);
        } catch {
            throw new Error(
                'O servidor retornou uma resposta inválida.'
            );
        }
    }

    async function pesquisarProcedimentos() {
        const termo =
            pesquisa.value.trim();

        if (termo === '') {
            mostrarMensagem(
                'Informe nome, código ou categoria.',
                'error'
            );

            pesquisa.focus();
            return;
        }

        try {
            const resposta = await fetch(
                `/safe-health/modules/cadastros/api/procedimentos.php?acao=pesquisar&q=${encodeURIComponent(termo)}`,
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
                    'Não foi possível pesquisar.'
                );
            }

            renderizarResultados(
                resultado.dados.procedimentos ?? []
            );

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Não foi possível pesquisar.',
                'error'
            );
        }
    }

    async function carregarProcedimento(id) {
        try {
            const resposta = await fetch(
                `/safe-health/modules/cadastros/api/procedimentos.php?acao=buscar&id=${encodeURIComponent(id)}`,
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
                    'Não foi possível carregar.'
                );
            }

            const item =
                resultado.dados.procedimento;

            procedimentoId.value =
                String(item.id);

            campos.nome.value =
                item.nome ?? '';

            campos.codigo.value =
                item.codigo ?? '';

            campos.categoria.value =
                item.categoria ?? '';

            campos.descricao.value =
                item.descricao ?? '';

            campos.duracao_estimada_minutos.value =
                item.duracao_estimada_minutos ?? '';

            campos.valor_unitario.value =
                Number(
                    item.valor_unitario ?? 0
                ).toFixed(2);

            campos.exige_autorizacao.value =
                String(
                    Number(item.exige_autorizacao ?? 0)
                );

            campos.ativo.value =
                String(
                    Number(item.ativo ?? 1)
                );

            modoEdicao(true);
            resultados.hidden = true;

            mostrarMensagem(
                `Procedimento ${item.nome} carregado para alteração.`
            );

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Não foi possível carregar.',
                'error'
            );
        }
    }

    form.addEventListener(
        'submit',
        async (evento) => {
            evento.preventDefault();

            if (!form.reportValidity()) {
                return;
            }

            botaoSalvar.disabled = true;

            const dados =
                Object.fromEntries(
                    new FormData(form).entries()
                );

            dados.procedimento_id =
                Number(
                    procedimentoId.value || 0
                );

            try {
                const resposta = await fetch(
                    '/safe-health/modules/cadastros/api/procedimentos.php',
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

                if (
                    !resposta.ok ||
                    !resultado.sucesso
                ) {
                    throw new Error(
                        resultado.mensagem ||
                        'Não foi possível salvar.'
                    );
                }

                procedimentoId.value =
                    String(
                        resultado.dados.procedimento_id
                    );

                campos.codigo.value =
                    resultado.dados.codigo ?? '';

                modoEdicao(true);

                mostrarMensagem(
                    resultado.mensagem
                );

            } catch (erro) {
                mostrarMensagem(
                    erro instanceof Error
                        ? erro.message
                        : 'Não foi possível salvar.',
                    'error'
                );
            } finally {
                botaoSalvar.disabled = false;
            }
        }
    );

    document
        .getElementById(
            'botaoPesquisarProcedimento'
        )
        .addEventListener(
            'click',
            pesquisarProcedimentos
        );

    document
        .getElementById(
            'botaoNovoProcedimento'
        )
        .addEventListener(
            'click',
            novoCadastro
        );

    document
        .getElementById(
            'botaoLimpar'
        )
        .addEventListener(
            'click',
            novoCadastro
        );

    pesquisa.addEventListener(
        'keydown',
        (evento) => {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                pesquisarProcedimentos();
            }
        }
    );

    resultados.addEventListener(
        'click',
        (evento) => {
            const botao =
                evento.target.closest(
                    '.procedure-result-item'
                );

            if (!botao) {
                return;
            }

            carregarProcedimento(
                Number(botao.dataset.id)
            );
        }
    );
});
