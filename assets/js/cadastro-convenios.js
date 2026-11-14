document.addEventListener('DOMContentLoaded', function () {
    const form =
        document.getElementById('formConvenio');

    const convenioId =
        document.getElementById('convenioId');

    const pesquisa =
        document.getElementById('pesquisaConvenio');

    const resultados =
        document.getElementById('resultadosPesquisa');

    const modoCadastro =
        document.getElementById('modoCadastro');

    const mensagem =
        document.getElementById('mensagemConvenio');

    const botaoSalvar =
        document.getElementById('botaoSalvarConvenio');

    const textoBotaoSalvar =
        document.getElementById('textoBotaoSalvar');

    const campos = {
        nome:
            document.getElementById('nome'),

        codigo:
            document.getElementById('codigo'),

        cnpj:
            document.getElementById('cnpj'),

        registro_ans:
            document.getElementById('registroAns'),

        telefone:
            document.getElementById('telefone'),

        email:
            document.getElementById('email'),

        ativo:
            document.getElementById('ativo')
    };

    function numeros(valor) {
        return String(valor ?? '')
            .replace(/\D/g, '');
    }

    function formatarCnpj(valor) {
        const n =
            numeros(valor).slice(0, 14);

        return n
            .replace(
                /^(\d{2})(\d)/,
                '$1.$2'
            )
            .replace(
                /^(\d{2})\.(\d{3})(\d)/,
                '$1.$2.$3'
            )
            .replace(
                /\.(\d{3})(\d)/,
                '.$1/$2'
            )
            .replace(
                /(\d{4})(\d)/,
                '$1-$2'
            );
    }

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
            `insurance-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function ocultarMensagem() {
        mensagem.hidden = true;
        mensagem.textContent = '';
        mensagem.className =
            'insurance-message';
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
                : 'Salvar convênio';
    }

    function novoCadastro() {
        form.reset();

        convenioId.value = '';
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
                <div class="insurance-result-empty">
                    Nenhum convênio encontrado.
                </div>
            `;

            return;
        }

        resultados.innerHTML = lista
            .map((item) => `
                <button
                    type="button"
                    class="insurance-result-item"
                    data-id="${Number(item.id)}"
                >
                    <div>
                        <strong>
                            ${escapar(item.nome)}
                        </strong>

                        <span>
                            ${escapar(item.codigo || 'Sem código')}
                            ${item.registro_ans ? ` · ANS ${escapar(item.registro_ans)}` : ''}
                        </span>
                    </div>

                    <span
                        class="insurance-result-status ${Number(item.ativo) === 1 ? 'active' : 'inactive'}"
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

    async function pesquisarConvenios() {
        const termo =
            pesquisa.value.trim();

        if (termo === '') {
            mostrarMensagem(
                'Informe nome, código, CNPJ ou registro ANS.',
                'error'
            );

            pesquisa.focus();
            return;
        }

        try {
            const resposta = await fetch(
                `/safe-health/modules/cadastros/api/convenios.php?acao=pesquisar&q=${encodeURIComponent(termo)}`,
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
                resultado.dados.convenios ?? []
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

    async function carregarConvenio(id) {
        try {
            const resposta = await fetch(
                `/safe-health/modules/cadastros/api/convenios.php?acao=buscar&id=${encodeURIComponent(id)}`,
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
                resultado.dados.convenio;

            convenioId.value =
                String(item.id);

            campos.nome.value =
                item.nome ?? '';

            campos.codigo.value =
                item.codigo ?? '';

            campos.cnpj.value =
                formatarCnpj(item.cnpj ?? '');

            campos.registro_ans.value =
                item.registro_ans ?? '';

            campos.telefone.value =
                item.telefone ?? '';

            campos.email.value =
                item.email ?? '';

            campos.ativo.value =
                String(Number(item.ativo));

            modoEdicao(true);
            resultados.hidden = true;

            mostrarMensagem(
                `Convênio ${item.nome} carregado para alteração.`
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

            dados.convenio_id =
                Number(
                    convenioId.value || 0
                );

            try {
                const resposta = await fetch(
                    '/safe-health/modules/cadastros/api/convenios.php',
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

                convenioId.value =
                    String(
                        resultado.dados.convenio_id
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

    campos.cnpj.addEventListener(
        'input',
        () => {
            campos.cnpj.value =
                formatarCnpj(
                    campos.cnpj.value
                );
        }
    );

    document
        .getElementById(
            'botaoPesquisarConvenio'
        )
        .addEventListener(
            'click',
            pesquisarConvenios
        );

    document
        .getElementById(
            'botaoNovoConvenio'
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
                pesquisarConvenios();
            }
        }
    );

    resultados.addEventListener(
        'click',
        (evento) => {
            const botao =
                evento.target.closest(
                    '.insurance-result-item'
                );

            if (!botao) {
                return;
            }

            carregarConvenio(
                Number(botao.dataset.id)
            );
        }
    );
});
