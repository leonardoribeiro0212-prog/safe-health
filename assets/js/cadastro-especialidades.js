document.addEventListener('DOMContentLoaded', function () {
    const form =
        document.getElementById('formEspecialidade');

    const especialidadeId =
        document.getElementById('especialidadeId');

    const pesquisa =
        document.getElementById('pesquisaEspecialidade');

    const resultados =
        document.getElementById('resultadosPesquisa');

    const modoCadastro =
        document.getElementById('modoCadastro');

    const mensagem =
        document.getElementById('mensagemEspecialidade');

    const botaoSalvar =
        document.getElementById('botaoSalvarEspecialidade');

    const textoBotaoSalvar =
        document.getElementById('textoBotaoSalvar');

    const campos = {
        nome: document.getElementById('nome'),
        codigo: document.getElementById('codigo'),
        ativa: document.getElementById('ativa')
    };

    function escaparHtml(valor) {
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
            `specialty-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function ocultarMensagem() {
        mensagem.hidden = true;
        mensagem.textContent = '';
        mensagem.className =
            'specialty-message';
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
                : 'Salvar especialidade';
    }

    function novoCadastro() {
        form.reset();

        especialidadeId.value = '';

        campos.ativa.value = '1';

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
                <div class="specialty-result-empty">
                    Nenhuma especialidade encontrada.
                </div>
            `;

            return;
        }

        resultados.innerHTML = lista
            .map((item) => `
                <button
                    type="button"
                    class="specialty-result-item"
                    data-id="${Number(item.id)}"
                >
                    <div>
                        <strong>
                            ${escaparHtml(item.nome)}
                        </strong>

                        <span>
                            ${escaparHtml(item.codigo || 'Sem código')}
                        </span>
                    </div>

                    <span
                        class="specialty-result-status ${Number(item.ativa) === 1 ? 'active' : 'inactive'}"
                    >
                        ${Number(item.ativa) === 1 ? 'Ativa' : 'Inativa'}
                    </span>
                </button>
            `)
            .join('');
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
            throw new Error(
                'O servidor retornou uma resposta inválida.'
            );
        }
    }

    async function pesquisarEspecialidades() {
        const termo = pesquisa.value.trim();

        if (termo === '') {
            mostrarMensagem(
                'Informe o nome ou código para pesquisar.',
                'error'
            );

            pesquisa.focus();
            return;
        }

        try {
            const resposta = await fetch(
                `/safe-health/modules/cadastros/api/especialidades.php?acao=pesquisar&q=${encodeURIComponent(termo)}`,
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
                    'Não foi possível pesquisar.'
                );
            }

            renderizarResultados(
                resultado.dados.especialidades ?? []
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

    async function carregarEspecialidade(id) {
        try {
            const resposta = await fetch(
                `/safe-health/modules/cadastros/api/especialidades.php?acao=buscar&id=${encodeURIComponent(id)}`,
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
                    'Não foi possível carregar a especialidade.'
                );
            }

            const item =
                resultado.dados.especialidade;

            especialidadeId.value =
                String(item.id);

            campos.nome.value =
                item.nome ?? '';

            campos.codigo.value =
                item.codigo ?? '';

            campos.ativa.value =
                String(Number(item.ativa));

            modoEdicao(true);
            resultados.hidden = true;

            mostrarMensagem(
                `Especialidade ${item.nome} carregada para alteração.`
            );

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Não foi possível carregar a especialidade.',
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

            dados.especialidade_id =
                Number(especialidadeId.value || 0);

            try {
                const resposta = await fetch(
                    '/safe-health/modules/cadastros/api/especialidades.php',
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
                        'Não foi possível salvar.'
                    );
                }

                especialidadeId.value =
                    String(
                        resultado.dados.especialidade_id
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
            'botaoPesquisarEspecialidade'
        )
        .addEventListener(
            'click',
            pesquisarEspecialidades
        );

    document
        .getElementById(
            'botaoNovaEspecialidade'
        )
        .addEventListener(
            'click',
            novoCadastro
        );

    document
        .getElementById('botaoLimpar')
        .addEventListener(
            'click',
            novoCadastro
        );

    pesquisa.addEventListener(
        'keydown',
        (evento) => {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                pesquisarEspecialidades();
            }
        }
    );

    resultados.addEventListener(
        'click',
        (evento) => {
            const botao =
                evento.target.closest(
                    '.specialty-result-item'
                );

            if (!botao) {
                return;
            }

            carregarEspecialidade(
                Number(botao.dataset.id)
            );
        }
    );
});
