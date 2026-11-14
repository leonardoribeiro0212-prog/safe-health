document.addEventListener('DOMContentLoaded', function () {
    const form =
        document.getElementById('formMedicamento');

    const medicamentoId =
        document.getElementById('medicamentoId');

    const pesquisa =
        document.getElementById('pesquisaMedicamento');

    const resultados =
        document.getElementById('resultadosPesquisa');

    const modoCadastro =
        document.getElementById('modoCadastro');

    const mensagem =
        document.getElementById('mensagemMedicamento');

    const botaoSalvar =
        document.getElementById('botaoSalvarMedicamento');

    const textoBotaoSalvar =
        document.getElementById('textoBotaoSalvar');

    const campos = {
        nome:
            document.getElementById('nome'),

        principio_ativo:
            document.getElementById('principioAtivo'),

        codigo:
            document.getElementById('codigo'),

        apresentacao:
            document.getElementById('apresentacao'),

        concentracao:
            document.getElementById('concentracao'),

        unidade_medida:
            document.getElementById('unidadeMedida'),

        via_administracao:
            document.getElementById('viaAdministracao'),

        dose_padrao:
            document.getElementById('dosePadrao'),

        frequencias_prescricao:
            document.getElementById('frequenciasPrescricao'),

        quantidade_padrao:
            document.getElementById('quantidadePadrao'),

        fabricante:
            document.getElementById('fabricante'),

        valor_unitario:
            document.getElementById('valorUnitario'),

        controlado:
            document.getElementById('controlado'),

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
            `med-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function ocultarMensagem() {
        mensagem.hidden = true;
        mensagem.textContent = '';
        mensagem.className =
            'med-message';
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
                : 'Salvar medicamento';
    }

    function novoCadastro() {
        form.reset();

        medicamentoId.value = '';
        campos.valor_unitario.value = '0.00';
        campos.quantidade_padrao.value = '1';
        Array.from(
            campos.frequentencias_prescricao?.options || []
        ).forEach(function (option) {
            option.selected = false;
        });

        const frequenciasSelecionadas =
            Array.isArray(medicamento.frequencias_prescricao)
                ? medicamento.frequencias_prescricao.map(Number)
                : [];

        Array.from(
            campos.frequentencias_prescricao?.options || []
        ).forEach(function (option) {
            option.selected =
                frequenciasSelecionadas.includes(
                    Number(option.value)
                );
        });
        campos.controlado.value = '0';
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
                <div class="med-result-empty">
                    Nenhum medicamento encontrado.
                </div>
            `;

            return;
        }

        resultados.innerHTML = lista
            .map((item) => `
                <button
                    type="button"
                    class="med-result-item"
                    data-id="${Number(item.id)}"
                >
                    <div>
                        <strong>
                            ${escapar(item.nome)}
                        </strong>

                        <span>
                            ${escapar(item.principio_ativo || 'Princípio ativo não informado')}
                            ${item.concentracao ? ` · ${escapar(item.concentracao)}` : ''}
                            ${item.apresentacao ? ` · ${escapar(item.apresentacao)}` : ''}
                        </span>
                    </div>

                    <span
                        class="med-result-status ${Number(item.ativo) === 1 ? 'active' : 'inactive'}"
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

    async function pesquisarMedicamentos() {
        const termo =
            pesquisa.value.trim();

        if (termo === '') {
            mostrarMensagem(
                'Informe nome, princípio ativo ou código.',
                'error'
            );

            pesquisa.focus();
            return;
        }

        try {
            const resposta = await fetch(
                `/safe-health/modules/cadastros/api/medicamentos.php?acao=pesquisar&q=${encodeURIComponent(termo)}`,
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
                resultado.dados.medicamentos ?? []
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

    async function carregarMedicamento(id) {
        try {
            const resposta = await fetch(
                `/safe-health/modules/cadastros/api/medicamentos.php?acao=buscar&id=${encodeURIComponent(id)}`,
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
                resultado.dados.medicamento;

            medicamentoId.value =
                String(item.id);

            Object.entries(campos)
                .forEach(([chave, elemento]) => {
                    elemento.value =
                        item[chave] ?? '';
                });

            campos.valor_unitario.value =
                Number(
                    item.valor_unitario ?? 0
                ).toFixed(2);

            campos.controlado.value =
                String(
                    Number(item.controlado ?? 0)
                );

            campos.ativo.value =
                String(
                    Number(item.ativo ?? 1)
                );

            modoEdicao(true);
            resultados.hidden = true;

            mostrarMensagem(
                `Medicamento ${item.nome} carregado para alteração.`
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

            dados.medicamento_id =
                Number(
                    medicamentoId.value || 0
                );

            try {
                const resposta = await fetch(
                    '/safe-health/modules/cadastros/api/medicamentos.php',
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json'
                        },

                        body: JSON.stringify({
                ...dados,
                frequencias_prescricao:
                    Array.from(
                        campos.frequentencias_prescricao
                            ?.selectedOptions || []
                    ).map(
                        option => Number(option.value)
                    )
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
                        'Não foi possível salvar.'
                    );
                }

                medicamentoId.value =
                    String(
                        resultado.dados.medicamento_id
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
            'botaoPesquisarMedicamento'
        )
        .addEventListener(
            'click',
            pesquisarMedicamentos
        );

    document
        .getElementById(
            'botaoNovoMedicamento'
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
                pesquisarMedicamentos();
            }
        }
    );

    resultados.addEventListener(
        'click',
        (evento) => {
            const botao =
                evento.target.closest(
                    '.med-result-item'
                );

            if (!botao) {
                return;
            }

            carregarMedicamento(
                Number(botao.dataset.id)
            );
        }
    );
});
