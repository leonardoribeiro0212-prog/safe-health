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

        modalidade:
            document.getElementById('modalidade'),

        regiao_anatomica:
            document.getElementById('regiaoAnatomica'),

        preparo:
            document.getElementById('preparo'),

        usa_contraste:
            document.getElementById('usaContraste'),

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
            `imgexam-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function ocultarMensagem() {
        mensagem.hidden = true;
        mensagem.textContent = '';
        mensagem.className =
            'imgexam-message';
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
        campos.usa_contraste.value = '0';
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
                <div class="imgexam-result-empty">
                    Nenhum exame encontrado.
                </div>
            `;

            return;
        }

        resultados.innerHTML = lista
            .map((item) => `
                <button
                    type="button"
                    class="imgexam-result-item"
                    data-id="${Number(item.id)}"
                >
                    <div>
                        <strong>
                            ${escapar(item.nome)}
                        </strong>

                        <span>
                            ${escapar(item.codigo || 'Sem código')}
                            ${item.modalidade ? ` · ${escapar(item.modalidade)}` : ''}
                            ${item.regiao_anatomica ? ` · ${escapar(item.regiao_anatomica)}` : ''}
                        </span>
                    </div>

                    <span
                        class="imgexam-result-status ${Number(item.ativo) === 1 ? 'active' : 'inactive'}"
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

    async function pesquisarExames() {
        const termo =
            pesquisa.value.trim();

        if (termo === '') {
            mostrarMensagem(
                'Informe nome, código, modalidade ou região.',
                'error'
            );

            pesquisa.focus();
            return;
        }

        try {
            const resposta = await fetch(
                `/safe-health/modules/cadastros/api/exames-imagem.php?acao=pesquisar&q=${encodeURIComponent(termo)}`,
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
                `/safe-health/modules/cadastros/api/exames-imagem.php?acao=buscar&id=${encodeURIComponent(id)}`,
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

            campos.modalidade.value =
                item.modalidade ?? '';

            campos.regiao_anatomica.value =
                item.regiao_anatomica ?? '';

            campos.preparo.value =
                item.preparo ?? '';

            campos.usa_contraste.value =
                String(
                    Number(item.usa_contraste ?? 0)
                );

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
                    '/safe-health/modules/cadastros/api/exames-imagem.php',
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
                    '.imgexam-result-item'
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
