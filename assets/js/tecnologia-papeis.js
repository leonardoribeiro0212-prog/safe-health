document.addEventListener('DOMContentLoaded', function () {
    const listaPapeis =
        document.getElementById('listaPapeis');

    const form =
        document.getElementById('formPapel');

    const campoPesquisa =
        document.getElementById('campoPesquisa');

    const papelId =
        document.getElementById('papelId');

    const mensagem =
        document.getElementById('mensagemPapel');

    function escapar(valor) {
        return String(valor ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatarData(valor) {
        if (!valor) return '-';

        const data =
            new Date(
                String(valor).replace(' ', 'T')
            );

        return Number.isNaN(data.getTime())
            ? valor
            : data.toLocaleString('pt-BR');
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
            `roles-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function novoPapel() {
        form.reset();
        papelId.value = '';

        document.getElementById(
            'tituloFormulario'
        ).textContent =
            'Novo papel';

        document.getElementById(
            'totalUsuariosVinculados'
        ).textContent = '0';

        document.getElementById(
            'totalTelasLiberadas'
        ).textContent = '0';

        mensagem.hidden = true;
        form.hidden = false;

        form.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function renderizarPapeis(lista) {
        if (
            !Array.isArray(lista) ||
            lista.length === 0
        ) {
            listaPapeis.innerHTML = `
                <div class="empty-state">
                    Nenhum papel encontrado.
                </div>
            `;
            return;
        }

        listaPapeis.innerHTML = `
            <div class="roles-table">

                <div class="role-row role-header">
                    <span>Papel</span>
                    <span>Descrição</span>
                    <span>Usuários</span>
                    <span>Telas</span>
                    <span>Status</span>
                    <span>Atualizado em</span>
                    <span>Ação</span>
                </div>

                ${lista.map(item => `
                    <div class="role-row">

                        <span>
                            <strong>
                                ${escapar(item.nome)}
                            </strong>
                            <small>#${Number(item.id)}</small>
                        </span>

                        <span>
                            ${escapar(
                                item.descricao ||
                                'Sem descrição'
                            )}
                        </span>

                        <span>
                            ${Number(item.total_usuarios || 0)}
                        </span>

                        <span>
                            ${Number(item.total_telas || 0)}
                        </span>

                        <span>
                            <span class="status-badge ${Number(item.ativo) === 1 ? 'active' : 'inactive'}">
                                ${Number(item.ativo) === 1 ? 'Ativo' : 'Inativo'}
                            </span>
                        </span>

                        <span>
                            ${escapar(
                                formatarData(
                                    item.atualizado_em
                                )
                            )}
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

    async function carregarPapeis() {
        const q =
            campoPesquisa.value.trim();

        listaPapeis.innerHTML =
            '<div class="empty-state">Carregando papéis...</div>';

        try {
            const resposta = await fetch(
                `/safe-health/modules/tecnologia/api/papeis.php?acao=listar&q=${encodeURIComponent(q)}`,
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
                    'Não foi possível carregar os papéis.'
                );
            }

            renderizarPapeis(
                resultado.dados.papeis ?? []
            );

        } catch (erro) {
            listaPapeis.innerHTML = `
                <div class="empty-state error">
                    ${escapar(
                        erro instanceof Error
                            ? erro.message
                            : 'Erro ao carregar papéis.'
                    )}
                </div>
            `;
        }
    }

    async function abrirPapel(id) {
        try {
            const resposta = await fetch(
                `/safe-health/modules/tecnologia/api/papeis.php?acao=detalhes&id=${encodeURIComponent(id)}`,
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
                    'Não foi possível abrir o papel.'
                );
            }

            const papel =
                resultado.dados.papel;

            papelId.value =
                papel.id;

            document.getElementById(
                'nome'
            ).value =
                papel.nome || '';

            document.getElementById(
                'descricao'
            ).value =
                papel.descricao || '';

            document.getElementById(
                'ativo'
            ).value =
                String(
                    Number(papel.ativo) === 1
                        ? 1
                        : 0
                );

            document.getElementById(
                'tituloFormulario'
            ).textContent =
                `Editar papel #${papel.id}`;

            document.getElementById(
                'totalUsuariosVinculados'
            ).textContent =
                Number(
                    papel.total_usuarios ||
                    0
                );

            document.getElementById(
                'totalTelasLiberadas'
            ).textContent =
                Number(
                    papel.total_telas ||
                    0
                );

            mensagem.hidden = true;
            form.hidden = false;

            form.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

        } catch (erro) {
            alert(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao abrir papel.'
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
                papel_id:
                    Number(
                        papelId.value ||
                        0
                    ),

                nome:
                    document.getElementById(
                        'nome'
                    ).value,

                descricao:
                    document.getElementById(
                        'descricao'
                    ).value,

                ativo:
                    Number(
                        document.getElementById(
                            'ativo'
                        ).value
                    )
            };

            try {
                const resposta = await fetch(
                    '/safe-health/modules/tecnologia/api/papeis.php',
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
                        'Não foi possível salvar o papel.'
                    );
                }

                papelId.value =
                    resultado.dados.papel_id;

                document.getElementById(
                    'tituloFormulario'
                ).textContent =
                    `Editar papel #${resultado.dados.papel_id}`;

                mostrarMensagem(
                    resultado.mensagem
                );

                await carregarPapeis();

            } catch (erro) {
                mostrarMensagem(
                    erro instanceof Error
                        ? erro.message
                        : 'Erro ao salvar papel.',
                    'error'
                );
            }
        }
    );

    listaPapeis.addEventListener(
        'click',
        function (evento) {
            const botao =
                evento.target.closest(
                    '.edit-button'
                );

            if (!botao) {
                return;
            }

            abrirPapel(
                Number(botao.dataset.id)
            );
        }
    );

    document.getElementById(
        'botaoNovoPapel'
    ).addEventListener(
        'click',
        novoPapel
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
        carregarPapeis
    );

    document.getElementById(
        'botaoLimparPesquisa'
    ).addEventListener(
        'click',
        function () {
            campoPesquisa.value = '';
            carregarPapeis();
        }
    );

    campoPesquisa.addEventListener(
        'keydown',
        function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                carregarPapeis();
            }
        }
    );

    carregarPapeis();
});
