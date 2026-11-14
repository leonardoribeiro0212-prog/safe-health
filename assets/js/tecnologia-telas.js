document.addEventListener('DOMContentLoaded', function () {
    const listaTelas =
        document.getElementById('listaTelas');

    const form =
        document.getElementById('formTela');

    const telaId =
        document.getElementById('telaId');

    const campoPesquisa =
        document.getElementById('campoPesquisa');

    const filtroModulo =
        document.getElementById('filtroModulo');

    const filtroStatus =
        document.getElementById('filtroStatus');

    const mensagem =
        document.getElementById('mensagemTela');

    const camposPreview = [
        'nome',
        'codigo',
        'modulo',
        'icone',
        'ordem',
        'abrirNovaAba',
        'ativa'
    ];

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
            `screens-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function atualizarPreview() {
        const nome =
            document.getElementById(
                'nome'
            ).value.trim();

        const codigo =
            document.getElementById(
                'codigo'
            ).value.trim();

        const modulo =
            document.getElementById(
                'modulo'
            ).value.trim();

        const icone =
            document.getElementById(
                'icone'
            ).value.trim();

        const ordem =
            document.getElementById(
                'ordem'
            ).value || '0';

        const abrirNovaAba =
            document.getElementById(
                'abrirNovaAba'
            ).value;

        const ativa =
            document.getElementById(
                'ativa'
            ).value;

        document.getElementById(
            'previewNome'
        ).textContent =
            nome || 'Nome da tela';

        document.getElementById(
            'previewModulo'
        ).textContent =
            modulo || 'Módulo não informado';

        document.getElementById(
            'previewCodigo'
        ).textContent =
            codigo || '-';

        document.getElementById(
            'previewOrdem'
        ).textContent =
            ordem;

        document.getElementById(
            'previewAbertura'
        ).textContent =
            abrirNovaAba === '1'
                ? 'Nova aba'
                : 'Mesma aba';

        document.getElementById(
            'previewStatus'
        ).textContent =
            ativa === '1'
                ? 'Ativa'
                : 'Inativa';

        const previewIcone =
            document.getElementById(
                'previewIcone'
            );

        previewIcone.className =
            `fa-solid ${icone || 'fa-file'}`;
    }

    function novaTela() {
        form.reset();

        telaId.value = '';

        document.getElementById(
            'ordem'
        ).value = '0';

        document.getElementById(
            'abrirNovaAba'
        ).value = '0';

        document.getElementById(
            'ativa'
        ).value = '1';

        document.getElementById(
            'tituloFormulario'
        ).textContent =
            'Nova tela';

        mensagem.hidden = true;
        form.hidden = false;

        atualizarPreview();

        form.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function renderizarTelas(lista) {
        if (
            !Array.isArray(lista) ||
            lista.length === 0
        ) {
            listaTelas.innerHTML = `
                <div class="empty-state">
                    Nenhuma tela encontrada.
                </div>
            `;

            return;
        }

        listaTelas.innerHTML = `
            <div class="screens-table">

                <div class="screen-row screen-header">
                    <span>Tela</span>
                    <span>Código</span>
                    <span>Módulo</span>
                    <span>URL</span>
                    <span>Ordem</span>
                    <span>Abertura</span>
                    <span>Status</span>
                    <span>Ação</span>
                </div>

                ${lista.map(item => `
                    <div class="screen-row">

                        <span class="screen-name">
                            <i class="fa-solid ${escapar(item.icone || 'fa-file')}"></i>

                            <div>
                                <strong>
                                    ${escapar(item.nome)}
                                </strong>
                                <small>#${Number(item.id)}</small>
                            </div>
                        </span>

                        <span>
                            <code>
                                ${escapar(item.codigo)}
                            </code>
                        </span>

                        <span>
                            ${escapar(item.modulo || '-')}
                        </span>

                        <span class="url-cell">
                            ${item.url ? escapar(item.url) : '<em>Sem URL</em>'}
                        </span>

                        <span>
                            ${Number(item.ordem || 0)}
                        </span>

                        <span>
                            ${
                                Number(item.abrir_nova_aba) === 1
                                    ? '<span class="open-badge new-tab">Nova aba</span>'
                                    : '<span class="open-badge">Mesma aba</span>'
                            }
                        </span>

                        <span>
                            <span class="status-badge ${Number(item.ativa) === 1 ? 'active' : 'inactive'}">
                                ${Number(item.ativa) === 1 ? 'Ativa' : 'Inativa'}
                            </span>
                        </span>

                        <span>
                            <button
                                type="button"
                                class="edit-button"
                                data-id="${Number(item.id)}"
                            >
                                <i class="fa-solid fa-pen"></i>
                                Editar
                            </button>
                        </span>

                    </div>
                `).join('')}

            </div>
        `;
    }

    async function carregarModulos() {
        const resposta = await fetch(
            '/safe-health/modules/tecnologia/api/telas.php?acao=modulos',
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
                'Não foi possível carregar os módulos.'
            );
        }

        const modulos =
            resultado.dados.modulos ?? [];

        filtroModulo.innerHTML = `
            <option value="">
                Todos os módulos
            </option>

            ${modulos.map(modulo => `
                <option value="${escapar(modulo)}">
                    ${escapar(modulo)}
                </option>
            `).join('')}
        `;

        document.getElementById(
            'listaModulos'
        ).innerHTML =
            modulos.map(modulo => `
                <option value="${escapar(modulo)}"></option>
            `).join('');
    }

    async function carregarTelas() {
        const params =
            new URLSearchParams({
                acao:
                    'listar',

                q:
                    campoPesquisa.value.trim(),

                modulo:
                    filtroModulo.value,

                status:
                    filtroStatus.value
            });

        listaTelas.innerHTML = `
            <div class="empty-state">
                Carregando telas...
            </div>
        `;

        try {
            const resposta = await fetch(
                `/safe-health/modules/tecnologia/api/telas.php?${params.toString()}`,
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
                    'Não foi possível carregar as telas.'
                );
            }

            renderizarTelas(
                resultado.dados.telas ??
                []
            );

        } catch (erro) {
            listaTelas.innerHTML = `
                <div class="empty-state error">
                    ${escapar(
                        erro instanceof Error
                            ? erro.message
                            : 'Erro ao carregar telas.'
                    )}
                </div>
            `;
        }
    }

    async function abrirTela(id) {
        try {
            const resposta = await fetch(
                `/safe-health/modules/tecnologia/api/telas.php?acao=detalhes&id=${encodeURIComponent(id)}`,
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
                    'Não foi possível abrir a tela.'
                );
            }

            const tela =
                resultado.dados.tela;

            telaId.value =
                tela.id;

            document.getElementById(
                'nome'
            ).value =
                tela.nome || '';

            document.getElementById(
                'codigo'
            ).value =
                tela.codigo || '';

            document.getElementById(
                'modulo'
            ).value =
                tela.modulo || '';

            document.getElementById(
                'url'
            ).value =
                tela.url || '';

            document.getElementById(
                'icone'
            ).value =
                tela.icone || '';

            document.getElementById(
                'ordem'
            ).value =
                Number(
                    tela.ordem ||
                    0
                );

            document.getElementById(
                'abrirNovaAba'
            ).value =
                String(
                    Number(
                        tela.abrir_nova_aba
                    ) === 1
                        ? 1
                        : 0
                );

            document.getElementById(
                'ativa'
            ).value =
                String(
                    Number(tela.ativa) === 1
                        ? 1
                        : 0
                );

            document.getElementById(
                'tituloFormulario'
            ).textContent =
                `Editar tela #${tela.id}`;

            mensagem.hidden = true;
            form.hidden = false;

            atualizarPreview();

            form.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

        } catch (erro) {
            alert(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao abrir tela.'
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

            const dados = {
                tela_id:
                    Number(
                        telaId.value ||
                        0
                    ),

                nome:
                    document.getElementById(
                        'nome'
                    ).value,

                codigo:
                    document.getElementById(
                        'codigo'
                    ).value,

                modulo:
                    document.getElementById(
                        'modulo'
                    ).value,

                url:
                    document.getElementById(
                        'url'
                    ).value,

                icone:
                    document.getElementById(
                        'icone'
                    ).value,

                ordem:
                    Number(
                        document.getElementById(
                            'ordem'
                        ).value ||
                        0
                    ),

                abrir_nova_aba:
                    Number(
                        document.getElementById(
                            'abrirNovaAba'
                        ).value
                    ),

                ativa:
                    Number(
                        document.getElementById(
                            'ativa'
                        ).value
                    )
            };

            try {
                const resposta = await fetch(
                    '/safe-health/modules/tecnologia/api/telas.php',
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json',
                            Accept:
                                'application/json'
                        },

                        body:
                            JSON.stringify(dados)
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
                        'Não foi possível salvar a tela.'
                    );
                }

                telaId.value =
                    resultado.dados.tela_id;

                document.getElementById(
                    'tituloFormulario'
                ).textContent =
                    `Editar tela #${resultado.dados.tela_id}`;

                mostrarMensagem(
                    resultado.mensagem
                );

                await Promise.all([
                    carregarTelas(),
                    carregarModulos()
                ]);

            } catch (erro) {
                mostrarMensagem(
                    erro instanceof Error
                        ? erro.message
                        : 'Erro ao salvar tela.',
                    'error'
                );
            }
        }
    );

    listaTelas.addEventListener(
        'click',
        function (evento) {
            const botao =
                evento.target.closest(
                    '.edit-button'
                );

            if (!botao) {
                return;
            }

            abrirTela(
                Number(botao.dataset.id)
            );
        }
    );

    camposPreview.forEach(
        id => {
            document.getElementById(id)
                .addEventListener(
                    'input',
                    atualizarPreview
                );

            document.getElementById(id)
                .addEventListener(
                    'change',
                    atualizarPreview
                );
        }
    );

    document.getElementById(
        'botaoNovaTela'
    ).addEventListener(
        'click',
        novaTela
    );

    document.getElementById(
        'botaoCancelar'
    ).addEventListener(
        'click',
        function () {
            form.hidden = true;
        }
    );

    document.getElementById(
        'botaoPesquisar'
    ).addEventListener(
        'click',
        carregarTelas
    );

    document.getElementById(
        'botaoLimpar'
    ).addEventListener(
        'click',
        function () {
            campoPesquisa.value = '';
            filtroModulo.value = '';
            filtroStatus.value = '';
            carregarTelas();
        }
    );

    campoPesquisa.addEventListener(
        'keydown',
        function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                carregarTelas();
            }
        }
    );

    filtroModulo.addEventListener(
        'change',
        carregarTelas
    );

    filtroStatus.addEventListener(
        'change',
        carregarTelas
    );

    Promise.all([
        carregarModulos(),
        carregarTelas()
    ]).catch(
        erro => {
            alert(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao carregar a tela.'
            );
        }
    );
});
