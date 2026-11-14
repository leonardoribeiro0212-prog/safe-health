document.addEventListener('DOMContentLoaded', function () {
    const papelId =
        document.getElementById('papelId');

    const painel =
        document.getElementById('painelPermissoes');

    const lista =
        document.getElementById('listaPermissoes');

    const filtro =
        document.getElementById('campoFiltro');

    const mensagem =
        document.getElementById('mensagemPermissoes');

    let telasAtuais = [];
    let papelAtual = null;

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
            `permissions-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function ocultarMensagem() {
        mensagem.hidden = true;
        mensagem.textContent = '';
    }

    function atualizarResumo() {
        document.getElementById(
            'totalTelas'
        ).textContent =
            telasAtuais.length;

        document.getElementById(
            'totalPermitidas'
        ).textContent =
            document.querySelectorAll(
                '.permission-checkbox:checked'
            ).length;
    }

    function agruparTelas(telas) {
        return telas.reduce(
            (grupos, tela) => {
                const modulo =
                    tela.modulo ||
                    'Outros';

                if (!grupos[modulo]) {
                    grupos[modulo] = [];
                }

                grupos[modulo].push(tela);

                return grupos;
            },
            {}
        );
    }

    function renderizarTelas() {
        const termo =
            filtro.value
                .trim()
                .toLowerCase();

        const filtradas =
            telasAtuais.filter(
                tela => {
                    if (!termo) {
                        return true;
                    }

                    return [
                        tela.nome,
                        tela.codigo,
                        tela.modulo,
                        tela.url
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase()
                        .includes(termo);
                }
            );

        if (filtradas.length === 0) {
            lista.innerHTML = `
                <div class="empty-state">
                    Nenhuma tela encontrada.
                </div>
            `;

            atualizarResumo();
            return;
        }

        const grupos =
            agruparTelas(filtradas);

        lista.innerHTML =
            Object.entries(grupos)
                .map(([modulo, telas]) => `
                    <section
                        class="module-block"
                        data-module="${escapar(modulo.toLowerCase())}"
                    >
                        <div class="module-header">
                            <div>
                                <i class="fa-solid fa-folder"></i>

                                <span>
                                    ${escapar(modulo)}
                                </span>
                            </div>

                            <label class="module-toggle">
                                <input
                                    type="checkbox"
                                    class="module-checkbox"
                                    data-module="${escapar(modulo)}"
                                >
                                <span>Selecionar módulo</span>
                            </label>
                        </div>

                        <div class="permission-list">
                            ${telas.map(tela => `
                                <label
                                    class="permission-item"
                                    data-search="${escapar(
                                        [
                                            tela.nome,
                                            tela.codigo,
                                            tela.modulo,
                                            tela.url
                                        ]
                                            .filter(Boolean)
                                            .join(' ')
                                            .toLowerCase()
                                    )}"
                                >
                                    <div class="permission-main">
                                        <input
                                            type="checkbox"
                                            class="permission-checkbox"
                                            value="${Number(tela.id)}"
                                            data-module="${escapar(modulo)}"
                                            ${Number(tela.permitida) === 1 ? 'checked' : ''}
                                        >

                                        <div class="permission-icon">
                                            <i class="fa-solid ${
                                                escapar(
                                                    tela.icone ||
                                                    'fa-file'
                                                )
                                            }"></i>
                                        </div>

                                        <div>
                                            <strong>
                                                ${escapar(tela.nome)}
                                            </strong>

                                            <span>
                                                ${escapar(tela.codigo)}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="permission-meta">
                                        <span>
                                            ${
                                                tela.url
                                                    ? escapar(tela.url)
                                                    : 'Sem URL'
                                            }
                                        </span>

                                        ${
                                            Number(tela.abrir_nova_aba) === 1
                                                ? '<small>Nova aba</small>'
                                                : ''
                                        }
                                    </div>
                                </label>
                            `).join('')}
                        </div>
                    </section>
                `)
                .join('');

        atualizarModuleCheckboxes();
        atualizarResumo();
    }

    function atualizarModuleCheckboxes() {
        document.querySelectorAll(
            '.module-checkbox'
        ).forEach(
            checkbox => {
                const modulo =
                    checkbox.dataset.module;

                const checkboxes =
                    Array.from(
                        document.querySelectorAll(
                            `.permission-checkbox[data-module="${CSS.escape(modulo)}"]`
                        )
                    );

                if (checkboxes.length === 0) {
                    checkbox.checked = false;
                    checkbox.indeterminate = false;
                    return;
                }

                const marcados =
                    checkboxes.filter(
                        item => item.checked
                    ).length;

                checkbox.checked =
                    marcados ===
                    checkboxes.length;

                checkbox.indeterminate =
                    marcados > 0 &&
                    marcados <
                    checkboxes.length;
            }
        );
    }

    async function carregarPapeis() {
        const resposta = await fetch(
            '/safe-health/modules/tecnologia/api/permissoes.php?acao=papeis',
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

        papelId.innerHTML = `
            <option value="">
                Selecione um papel
            </option>

            ${(resultado.dados.papeis ?? [])
                .map(papel => `
                    <option value="${Number(papel.id)}">
                        ${escapar(papel.nome)}
                    </option>
                `)
                .join('')}
        `;
    }

    async function carregarPermissoes() {
        const id =
            Number(papelId.value || 0);

        if (!id) {
            painel.hidden = true;
            telasAtuais = [];
            papelAtual = null;

            document.getElementById(
                'totalTelas'
            ).textContent = '0';

            document.getElementById(
                'totalPermitidas'
            ).textContent = '0';

            return;
        }

        lista.innerHTML = `
            <div class="empty-state">
                Carregando permissões...
            </div>
        `;

        painel.hidden = false;
        ocultarMensagem();

        try {
            const resposta = await fetch(
                `/safe-health/modules/tecnologia/api/permissoes.php?acao=carregar&papel_id=${encodeURIComponent(id)}`,
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
                    'Não foi possível carregar as permissões.'
                );
            }

            papelAtual =
                resultado.dados.papel;

            telasAtuais =
                resultado.dados.telas ?? [];

            document.getElementById(
                'tituloPapel'
            ).textContent =
                `Permissões — ${papelAtual.nome}`;

            filtro.value = '';

            renderizarTelas();

        } catch (erro) {
            lista.innerHTML = `
                <div class="empty-state error">
                    ${escapar(
                        erro instanceof Error
                            ? erro.message
                            : 'Erro ao carregar permissões.'
                    )}
                </div>
            `;
        }
    }

    function capturarPermissoes() {
        return Array.from(
            document.querySelectorAll(
                '.permission-checkbox:checked'
            )
        ).map(
            checkbox =>
                Number(checkbox.value)
        );
    }

    async function salvarPermissoes() {
        const id =
            Number(papelId.value || 0);

        if (!id) {
            return;
        }

        const botao =
            document.getElementById(
                'botaoSalvar'
            );

        botao.disabled = true;

        try {
            const resposta = await fetch(
                '/safe-health/modules/tecnologia/api/permissoes.php',
                {
                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json',
                        Accept:
                            'application/json'
                    },

                    body:
                        JSON.stringify({
                            papel_id: id,
                            telas:
                                capturarPermissoes()
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
                    'Não foi possível salvar as permissões.'
                );
            }

            mostrarMensagem(
                resultado.mensagem
            );

            /*
             * Atualiza o estado em memória para refletir o banco.
             */
            const permitidas =
                new Set(
                    capturarPermissoes()
                );

            telasAtuais =
                telasAtuais.map(
                    tela => ({
                        ...tela,
                        permitida:
                            permitidas.has(
                                Number(tela.id)
                            )
                                ? 1
                                : 0
                    })
                );

            atualizarResumo();

        } catch (erro) {
            mostrarMensagem(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao salvar permissões.',
                'error'
            );

        } finally {
            botao.disabled = false;
        }
    }

    lista.addEventListener(
        'change',
        function (evento) {
            if (
                evento.target.matches(
                    '.permission-checkbox'
                )
            ) {
                atualizarModuleCheckboxes();
                atualizarResumo();
                ocultarMensagem();
                return;
            }

            if (
                evento.target.matches(
                    '.module-checkbox'
                )
            ) {
                const modulo =
                    evento.target.dataset.module;

                document.querySelectorAll(
                    `.permission-checkbox[data-module="${CSS.escape(modulo)}"]`
                ).forEach(
                    checkbox => {
                        checkbox.checked =
                            evento.target.checked;
                    }
                );

                atualizarModuleCheckboxes();
                atualizarResumo();
                ocultarMensagem();
            }
        }
    );

    papelId.addEventListener(
        'change',
        carregarPermissoes
    );

    filtro.addEventListener(
        'input',
        renderizarTelas
    );

    document.getElementById(
        'botaoMarcarTodas'
    ).addEventListener(
        'click',
        function () {
            document.querySelectorAll(
                '.permission-checkbox'
            ).forEach(
                checkbox => {
                    checkbox.checked = true;
                }
            );

            atualizarModuleCheckboxes();
            atualizarResumo();
            ocultarMensagem();
        }
    );

    document.getElementById(
        'botaoDesmarcarTodas'
    ).addEventListener(
        'click',
        function () {
            document.querySelectorAll(
                '.permission-checkbox'
            ).forEach(
                checkbox => {
                    checkbox.checked = false;
                }
            );

            atualizarModuleCheckboxes();
            atualizarResumo();
            ocultarMensagem();
        }
    );

    document.getElementById(
        'botaoRecarregar'
    ).addEventListener(
        'click',
        carregarPermissoes
    );

    document.getElementById(
        'botaoSalvar'
    ).addEventListener(
        'click',
        salvarPermissoes
    );

    carregarPapeis().catch(
        erro => {
            alert(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao carregar os papéis.'
            );
        }
    );
});
