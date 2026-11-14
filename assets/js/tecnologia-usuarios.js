document.addEventListener('DOMContentLoaded', function () {
    const listaUsuarios =
        document.getElementById('listaUsuarios');

    const form =
        document.getElementById('formUsuario');

    const campoPesquisa =
        document.getElementById('campoPesquisa');

    const usuarioId =
        document.getElementById('usuarioId');

    const senha =
        document.getElementById('senha');

    const mensagem =
        document.getElementById('mensagemUsuario');

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
        const texto = await resposta.text();

        try {
            return JSON.parse(texto);
        } catch {
            console.error('Resposta inválida:', texto);
            throw new Error('O servidor retornou uma resposta inválida.');
        }
    }

    function mostrarMensagem(
        texto,
        tipo = 'success'
    ) {
        mensagem.hidden = false;
        mensagem.className =
            `users-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function esconderFormulario() {
        form.hidden = true;
        mensagem.hidden = true;
    }

    function novoUsuario() {
        form.reset();

        usuarioId.value = '';
        senha.required = true;

        document.getElementById(
            'tituloFormulario'
        ).textContent = 'Novo usuário';

        document.getElementById(
            'senhaObrigatoria'
        ).hidden = false;

        document.getElementById(
            'ajudaSenha'
        ).textContent =
            'Obrigatória para novos usuários.';

        document.getElementById(
            'papeisUsuario'
        ).innerHTML =
            '<span class="role-empty">Nenhum papel vinculado.</span>';

        mensagem.hidden = true;
        form.hidden = false;

        form.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function renderizarUsuarios(lista) {
        if (
            !Array.isArray(lista) ||
            lista.length === 0
        ) {
            listaUsuarios.innerHTML = `
                <div class="empty-state">
                    Nenhum usuário encontrado.
                </div>
            `;
            return;
        }

        listaUsuarios.innerHTML = `
            <div class="users-table">

                <div class="user-row user-header">
                    <span>Usuário</span>
                    <span>E-mail</span>
                    <span>Papéis</span>
                    <span>Status</span>
                    <span>Criado em</span>
                    <span>Atualizado em</span>
                    <span>Ação</span>
                </div>

                ${lista.map(item => `
                    <div class="user-row">

                        <span>
                            <strong>
                                ${escapar(item.nome)}
                            </strong>
                            <small>@${escapar(item.nome_usuario || '')} · #${Number(item.id)}</small>
                        </span>

                        <span>
                            ${escapar(item.email)}
                        </span>

                        <span>
                            ${
                                item.papeis
                                    ? escapar(item.papeis)
                                    : '<em>Sem papel vinculado</em>'
                            }
                        </span>

                        <span>
                            <span class="status-badge ${Number(item.ativo) === 1 ? 'active' : 'inactive'}">
                                ${Number(item.ativo) === 1 ? 'Ativo' : 'Inativo'}
                            </span>
                        </span>

                        <span>
                            ${escapar(formatarData(item.criado_em))}
                        </span>

                        <span>
                            ${escapar(formatarData(item.atualizado_em))}
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

    async function carregarUsuarios() {
        const q =
            campoPesquisa.value.trim();

        listaUsuarios.innerHTML =
            '<div class="empty-state">Carregando usuários...</div>';

        try {
            const resposta = await fetch(
                `/safe-health/modules/tecnologia/api/usuarios.php?acao=listar&q=${encodeURIComponent(q)}`,
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
                    'Não foi possível carregar os usuários.'
                );
            }

            renderizarUsuarios(
                resultado.dados.usuarios ?? []
            );

        } catch (erro) {
            listaUsuarios.innerHTML = `
                <div class="empty-state error">
                    ${escapar(
                        erro instanceof Error
                            ? erro.message
                            : 'Erro ao carregar usuários.'
                    )}
                </div>
            `;
        }
    }

    async function abrirUsuario(id) {
        try {
            const resposta = await fetch(
                `/safe-health/modules/tecnologia/api/usuarios.php?acao=detalhes&id=${encodeURIComponent(id)}`,
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
                    'Não foi possível abrir o usuário.'
                );
            }

            const usuario =
                resultado.dados.usuario;

            usuarioId.value =
                usuario.id;

            document.getElementById(
                'nome'
            ).value =
                usuario.nome || '';

            document.getElementById(
                'nome_usuario'
            ).value =
                usuario.nome_usuario || '';

            document.getElementById(
                'email'
            ).value =
                usuario.email || '';

            document.getElementById(
                'ativo'
            ).value =
                String(
                    Number(usuario.ativo) === 1
                        ? 1
                        : 0
                );

            senha.value = '';
            senha.required = false;

            document.getElementById(
                'tituloFormulario'
            ).textContent =
                `Editar usuário #${usuario.id}`;

            document.getElementById(
                'senhaObrigatoria'
            ).hidden = true;

            document.getElementById(
                'ajudaSenha'
            ).textContent =
                'Deixe em branco para manter a senha atual.';

            const papeis =
                resultado.dados.papeis ?? [];

            document.getElementById(
                'papeisUsuario'
            ).innerHTML =
                papeis.length > 0
                    ? papeis.map(
                        papel =>
                            `<span class="role-badge">${escapar(papel.nome)}</span>`
                    ).join('')
                    : '<span class="role-empty">Nenhum papel vinculado.</span>';

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
                    : 'Erro ao abrir usuário.'
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
                usuario_id:
                    Number(usuarioId.value || 0),

                nome:
                    document.getElementById(
                        'nome'
                    ).value,

                nome_usuario:
                    document.getElementById(
                        'nome_usuario'
                    ).value.trim().toLowerCase(),

                email:
                    document.getElementById(
                        'email'
                    ).value,

                senha:
                    senha.value,

                ativo:
                    Number(
                        document.getElementById(
                            'ativo'
                        ).value
                    )
            };

            try {
                const resposta = await fetch(
                    '/safe-health/modules/tecnologia/api/usuarios.php',
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
                        'Não foi possível salvar o usuário.'
                    );
                }

                usuarioId.value =
                    resultado.dados.usuario_id;

                senha.value = '';
                senha.required = false;

                document.getElementById(
                    'senhaObrigatoria'
                ).hidden = true;

                document.getElementById(
                    'ajudaSenha'
                ).textContent =
                    'Deixe em branco para manter a senha atual.';

                mostrarMensagem(
                    resultado.mensagem
                );

                await carregarUsuarios();

            } catch (erro) {
                mostrarMensagem(
                    erro instanceof Error
                        ? erro.message
                        : 'Erro ao salvar usuário.',
                    'error'
                );
            }
        }
    );

    listaUsuarios.addEventListener(
        'click',
        function (evento) {
            const botao =
                evento.target.closest(
                    '.edit-button'
                );

            if (!botao) return;

            abrirUsuario(
                Number(botao.dataset.id)
            );
        }
    );

    document.getElementById(
        'botaoNovoUsuario'
    ).addEventListener(
        'click',
        novoUsuario
    );

    document.getElementById(
        'botaoCancelar'
    ).addEventListener(
        'click',
        esconderFormulario
    );

    document.getElementById(
        'botaoPesquisar'
    ).addEventListener(
        'click',
        carregarUsuarios
    );

    document.getElementById(
        'botaoLimparPesquisa'
    ).addEventListener(
        'click',
        function () {
            campoPesquisa.value = '';
            carregarUsuarios();
        }
    );

    campoPesquisa.addEventListener(
        'keydown',
        function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                carregarUsuarios();
            }
        }
    );

    carregarUsuarios();
});
