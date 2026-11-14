document.addEventListener('DOMContentLoaded', function () {
    const form =
        document.getElementById('formMaterial');

    const materialId =
        document.getElementById('materialId');

    const pesquisa =
        document.getElementById('pesquisaMaterial');

    const resultados =
        document.getElementById('resultadosPesquisa');

    const modoCadastro =
        document.getElementById('modoCadastro');

    const mensagem =
        document.getElementById('mensagemMaterial');

    const botaoSalvar =
        document.getElementById('botaoSalvarMaterial');

    const textoBotaoSalvar =
        document.getElementById('textoBotaoSalvar');

    const campos = {
        nome:
            document.getElementById('nome'),

        codigo:
            document.getElementById('codigo'),

        categoria:
            document.getElementById('categoria'),

        unidade_medida:
            document.getElementById('unidadeMedida'),

        fabricante:
            document.getElementById('fabricante'),

        valor_unitario:
            document.getElementById('valorUnitario'),

        estoque_minimo:
            document.getElementById('estoqueMinimo'),

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
            `material-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function ocultarMensagem() {
        mensagem.hidden = true;
        mensagem.textContent = '';
        mensagem.className =
            'material-message';
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
                : 'Salvar material';
    }

    function novoCadastro() {
        form.reset();

        materialId.value = '';
        campos.valor_unitario.value = '0.00';
        campos.estoque_minimo.value = '0.000';
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
                <div class="material-result-empty">
                    Nenhum material encontrado.
                </div>
            `;

            return;
        }

        resultados.innerHTML = lista
            .map((item) => `
                <button
                    type="button"
                    class="material-result-item"
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
                        class="material-result-status ${Number(item.ativo) === 1 ? 'active' : 'inactive'}"
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

    async function pesquisarMateriais() {
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
                `/safe-health/modules/cadastros/api/materiais.php?acao=pesquisar&q=${encodeURIComponent(termo)}`,
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
                resultado.dados.materiais ?? []
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

    async function carregarMaterial(id) {
        try {
            const resposta = await fetch(
                `/safe-health/modules/cadastros/api/materiais.php?acao=buscar&id=${encodeURIComponent(id)}`,
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
                resultado.dados.material;

            materialId.value =
                String(item.id);

            campos.nome.value =
                item.nome ?? '';

            campos.codigo.value =
                item.codigo ?? '';

            campos.categoria.value =
                item.categoria ?? '';

            campos.unidade_medida.value =
                item.unidade_medida ?? '';

            campos.fabricante.value =
                item.fabricante ?? '';

            campos.valor_unitario.value =
                Number(
                    item.valor_unitario ?? 0
                ).toFixed(2);

            campos.estoque_minimo.value =
                Number(
                    item.estoque_minimo ?? 0
                ).toFixed(3);

            campos.ativo.value =
                String(
                    Number(item.ativo ?? 1)
                );

            modoEdicao(true);
            resultados.hidden = true;

            mostrarMensagem(
                `Material ${item.nome} carregado para alteração.`
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

            dados.material_id =
                Number(
                    materialId.value || 0
                );

            try {
                const resposta = await fetch(
                    '/safe-health/modules/cadastros/api/materiais.php',
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

                materialId.value =
                    String(
                        resultado.dados.material_id
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
            'botaoPesquisarMaterial'
        )
        .addEventListener(
            'click',
            pesquisarMateriais
        );

    document
        .getElementById(
            'botaoNovoMaterial'
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
                pesquisarMateriais();
            }
        }
    );

    resultados.addEventListener(
        'click',
        (evento) => {
            const botao =
                evento.target.closest(
                    '.material-result-item'
                );

            if (!botao) {
                return;
            }

            carregarMaterial(
                Number(botao.dataset.id)
            );
        }
    );
});
