document.addEventListener('DOMContentLoaded', function () {
    const pesquisa =
        document.getElementById('campoPesquisaUsuario');

    const listaUsuarios =
        document.getElementById('listaUsuarios');

    const cardAlteracao =
        document.getElementById('cardAlteracao');

    const usuarioId =
        document.getElementById('usuarioId');

    const novaSenha =
        document.getElementById('novaSenha');

    const confirmarSenha =
        document.getElementById('confirmarSenha');

    const mensagem =
        document.getElementById('mensagemSenha');

    const senhaMatch =
        document.getElementById('senhaMatch');

    const form =
        document.getElementById('formAlteracaoSenha');

    const regras = {
        tamanho:
            document.getElementById('regraTamanho'),
        maiuscula:
            document.getElementById('regraMaiuscula'),
        minuscula:
            document.getElementById('regraMinuscula'),
        numero:
            document.getElementById('regraNumero')
    };

    function escapar(valor) {
        return String(valor ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
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

    function mostrarMensagem(texto, tipo = 'success') {
        mensagem.hidden = false;
        mensagem.className =
            `password-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function atualizarRegra(elemento, valida) {
        elemento.classList.toggle('valid', valida);

        elemento.querySelector('i').className =
            valida
                ? 'fa-solid fa-circle-check'
                : 'fa-solid fa-circle';
    }

    function validarConfirmacao() {
        if (confirmarSenha.value === '') {
            senhaMatch.hidden = true;
            return;
        }

        const confere =
            novaSenha.value ===
            confirmarSenha.value;

        senhaMatch.hidden = false;
        senhaMatch.className =
            `match-message ${confere ? 'valid' : 'invalid'}`;

        senhaMatch.textContent =
            confere
                ? 'As senhas conferem.'
                : 'As senhas não conferem.';
    }

    function validarNovaSenha() {
        const valor = novaSenha.value;

        atualizarRegra(
            regras.tamanho,
            valor.length >= 8
        );

        atualizarRegra(
            regras.maiuscula,
            /[A-Z]/.test(valor)
        );

        atualizarRegra(
            regras.minuscula,
            /[a-z]/.test(valor)
        );

        atualizarRegra(
            regras.numero,
            /[0-9]/.test(valor)
        );

        validarConfirmacao();
    }

    function limparFormularioSenha() {
        form.reset();
        mensagem.hidden = true;
        senhaMatch.hidden = true;

        Object.values(regras).forEach(
            elemento => {
                elemento.classList.remove('valid');
                elemento.querySelector('i').className =
                    'fa-solid fa-circle';
            }
        );
    }

    function selecionarUsuario(usuario) {
        usuarioId.value = usuario.id;

        document.getElementById(
            'usuarioNome'
        ).textContent =
            usuario.nome;

        document.getElementById(
            'usuarioEmail'
        ).textContent =
            usuario.email;

        document.getElementById(
            'usuarioStatus'
        ).textContent =
            Number(usuario.ativo) === 1
                ? 'Ativo'
                : 'Inativo';

        limparFormularioSenha();

        usuarioId.value = usuario.id;

        cardAlteracao.hidden = false;

        cardAlteracao.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    async function pesquisarUsuarios() {
        const q = pesquisa.value.trim();

        if (!q) {
            listaUsuarios.innerHTML = `
                <div class="empty-state">
                    Digite um nome ou e-mail para pesquisar.
                </div>
            `;
            return;
        }

        listaUsuarios.innerHTML = `
            <div class="empty-state">
                Pesquisando usuários...
            </div>
        `;

        try {
            const resposta = await fetch(
                `/safe-health/modules/tecnologia/api/alteracao-senha.php?acao=pesquisar&q=${encodeURIComponent(q)}`,
                {
                    headers: {
                        Accept: 'application/json'
                    },
                    cache: 'no-store'
                }
            );

            const resultado = await lerJson(resposta);

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(
                    resultado.mensagem ||
                    'Não foi possível pesquisar os usuários.'
                );
            }

            const usuarios =
                resultado.dados.usuarios ?? [];

            if (usuarios.length === 0) {
                listaUsuarios.innerHTML = `
                    <div class="empty-state">
                        Nenhum usuário encontrado.
                    </div>
                `;
                return;
            }

            listaUsuarios.innerHTML =
                usuarios.map(usuario => `
                    <button
                        type="button"
                        class="user-result"
                        data-id="${Number(usuario.id)}"
                        data-nome="${escapar(usuario.nome)}"
                        data-email="${escapar(usuario.email)}"
                        data-ativo="${Number(usuario.ativo)}"
                    >
                        <span class="user-avatar">
                            ${escapar(
                                String(usuario.nome || 'U')
                                    .charAt(0)
                                    .toUpperCase()
                            )}
                        </span>

                        <span class="user-result-info">
                            <strong>${escapar(usuario.nome)}</strong>
                            <small>${escapar(usuario.email)}</small>
                        </span>

                        <span class="user-status ${Number(usuario.ativo) === 1 ? 'active' : 'inactive'}">
                            ${Number(usuario.ativo) === 1 ? 'Ativo' : 'Inativo'}
                        </span>

                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                `).join('');

        } catch (erro) {
            listaUsuarios.innerHTML = `
                <div class="empty-state error">
                    ${escapar(
                        erro instanceof Error
                            ? erro.message
                            : 'Erro ao pesquisar usuários.'
                    )}
                </div>
            `;
        }
    }

    listaUsuarios.addEventListener(
        'click',
        function (evento) {
            const botao =
                evento.target.closest('.user-result');

            if (!botao) return;

            selecionarUsuario({
                id: Number(botao.dataset.id),
                nome: botao.dataset.nome,
                email: botao.dataset.email,
                ativo: Number(botao.dataset.ativo)
            });
        }
    );

    document.querySelectorAll(
        '.toggle-password'
    ).forEach(
        toggle => {
            toggle.addEventListener(
                'click',
                function () {
                    const input =
                        document.getElementById(
                            this.dataset.target
                        );

                    const mostrar =
                        input.type === 'password';

                    input.type =
                        mostrar ? 'text' : 'password';

                    this.querySelector('i').className =
                        mostrar
                            ? 'fa-solid fa-eye-slash'
                            : 'fa-solid fa-eye';
                }
            );
        }
    );

    novaSenha.addEventListener(
        'input',
        validarNovaSenha
    );

    confirmarSenha.addEventListener(
        'input',
        validarConfirmacao
    );

    document.getElementById(
        'botaoPesquisarUsuario'
    ).addEventListener(
        'click',
        pesquisarUsuarios
    );

    pesquisa.addEventListener(
        'keydown',
        function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                pesquisarUsuarios();
            }
        }
    );

    document.getElementById(
        'botaoCancelar'
    ).addEventListener(
        'click',
        function () {
            limparFormularioSenha();
            usuarioId.value = '';
            cardAlteracao.hidden = true;
        }
    );

    form.addEventListener(
        'submit',
        async function (evento) {
            evento.preventDefault();

            if (!form.reportValidity()) {
                return;
            }

            const senha = novaSenha.value;

            if (
                senha.length < 8 ||
                !/[A-Z]/.test(senha) ||
                !/[a-z]/.test(senha) ||
                !/[0-9]/.test(senha)
            ) {
                mostrarMensagem(
                    'A nova senha ainda não atende aos requisitos.',
                    'error'
                );
                return;
            }

            if (senha !== confirmarSenha.value) {
                mostrarMensagem(
                    'A confirmação da nova senha não confere.',
                    'error'
                );
                return;
            }

            const botao =
                document.getElementById('botaoAlterar');

            botao.disabled = true;

            try {
                const resposta = await fetch(
                    '/safe-health/modules/tecnologia/api/alteracao-senha.php',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json'
                        },
                        body: JSON.stringify({
                            usuario_id:
                                Number(usuarioId.value),
                            nova_senha:
                                novaSenha.value,
                            confirmar_senha:
                                confirmarSenha.value
                        })
                    }
                );

                const resultado = await lerJson(resposta);

                if (!resposta.ok || !resultado.sucesso) {
                    throw new Error(
                        resultado.mensagem ||
                        'Não foi possível alterar a senha.'
                    );
                }

                novaSenha.value = '';
                confirmarSenha.value = '';
                validarNovaSenha();

                mostrarMensagem(
                    resultado.mensagem
                );

            } catch (erro) {
                mostrarMensagem(
                    erro instanceof Error
                        ? erro.message
                        : 'Erro ao alterar senha.',
                    'error'
                );
            } finally {
                botao.disabled = false;
            }
        }
    );
});
