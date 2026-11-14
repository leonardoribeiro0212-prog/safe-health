document.addEventListener('DOMContentLoaded', function () {
    const form =
        document.getElementById('formExame');

    const exameId =
        document.getElementById('exameId');

    const pesquisa =
        document.getElementById('pesquisaExame');

    const resultados =
        document.getElementById('resultadosPesquisa');

    const modoCadastro =
        document.getElementById('modoCadastro');

    const mensagem =
        document.getElementById('mensagemExame');

    const botaoSalvar =
        document.getElementById('botaoSalvarExame');

    const textoBotaoSalvar =
        document.getElementById('textoBotaoSalvar');

    const campos = {
        nome:
            document.getElementById('nome'),

        codigo:
            document.getElementById('codigo'),

        categoria:
            document.getElementById('categoria'),

        material_biologico:
            document.getElementById('materialBiologico'),

        preparo:
            document.getElementById('preparo'),

        prazo_resultado_horas:
            document.getElementById('prazoResultado'),

        valor_unitario:
            document.getElementById('valorUnitario'),

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
            `lab-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function ocultarMensagem() {
        mensagem.hidden = true;
        mensagem.textContent = '';
        mensagem.className =
            'lab-message';
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
                : 'Salvar exame';
    }

    function novoCadastro() {
        form.reset();

        exameId.value = '';
        campos.valor_unitario.value = '0.00';
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
                <div class="lab-result-empty">
                    Nenhum exame encontrado.
                </div>
            `;

            return;
        }

        resultados.innerHTML = lista
            .map((item) => `
                <button
                    type="button"
                    class="lab-result-item"
                    data-id="${Number(item.id)}"
                >
                    <div>
                        <strong>
                            ${escapar(item.nome)}
                        </strong>

                        <span>
                            ${escapar(item.codigo || 'Sem código')}
                            ${item.categoria ? ` · ${escapar(item.categoria)}` : ''}
                            ${item.material_biologico ? ` · ${escapar(item.material_biologico)}` : ''}
                        </span>
                    </div>

                    <span
                        class="lab-result-status ${Number(item.ativo) === 1 ? 'active' : 'inactive'}"
                    >
                        ${Number(item.ativo) === 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </button>
            `)
            .join('');
    }

    async function lerJson(resposta) {
        const texto = await resposta.text();

        try {
            return JSON.parse(texto);
        } catch {
            throw new Error(
                'O servidor retornou uma resposta inválida.'
            );
        }
    }

    async function pesquisarExames() {
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
                `/safe-health/modules/cadastros/api/exames-laboratoriais.php?acao=pesquisar&q=${encodeURIComponent(termo)}`,
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
                resultado.dados.exames ?? []
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

    async function carregarExame(id) {
        try {
            const resposta = await fetch(
                `/safe-health/modules/cadastros/api/exames-laboratoriais.php?acao=buscar&id=${encodeURIComponent(id)}`,
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
                resultado.dados.exame;

            exameId.value =
                String(item.id);

            campos.nome.value =
                item.nome ?? '';

            campos.codigo.value =
                item.codigo ?? '';

            campos.categoria.value =
                item.categoria ?? '';

            campos.material_biologico.value =
                item.material_biologico ?? '';

            campos.preparo.value =
                item.preparo ?? '';

            campos.prazo_resultado_horas.value =
                item.prazo_resultado_horas ?? '';

            campos.valor_unitario.value =
                Number(
                    item.valor_unitario ?? 0
                ).toFixed(2);

            campos.ativo.value =
                String(
                    Number(item.ativo ?? 1)
                );

            modoEdicao(true);
            resultados.hidden = true;

            mostrarMensagem(
                `Exame ${item.nome} carregado para alteração.`
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

            dados.exame_id =
                Number(
                    exameId.value || 0
                );

            try {
                const resposta = await fetch(
                    '/safe-health/modules/cadastros/api/exames-laboratoriais.php',
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

                exameId.value =
                    String(
                        resultado.dados.exame_id
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
            'botaoPesquisarExame'
        )
        .addEventListener(
            'click',
            pesquisarExames
        );

    document
        .getElementById(
            'botaoNovoExame'
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
                pesquisarExames();
            }
        }
    );

    resultados.addEventListener(
        'click',
        (evento) => {
            const botao =
                evento.target.closest(
                    '.lab-result-item'
                );

            if (!botao) {
                return;
            }

            carregarExame(
                Number(botao.dataset.id)
            );
        }
    );
});
