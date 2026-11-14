document.addEventListener('DOMContentLoaded', function () {
    const lista =
        document.getElementById('listaAuditoria');

    const detalhesCard =
        document.getElementById('detalhesCard');

    const dataInicio =
        document.getElementById('dataInicio');

    const dataFim =
        document.getElementById('dataFim');

    const usuarioFiltro =
        document.getElementById('usuarioFiltro');

    const moduloFiltro =
        document.getElementById('moduloFiltro');

    const acaoFiltro =
        document.getElementById('acaoFiltro');

    const campoPesquisa =
        document.getElementById('campoPesquisa');

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

    function statusLegivel(valor) {
        return String(valor ?? '')
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(
                /\b\w/g,
                letra => letra.toUpperCase()
            );
    }

    function classeAcao(acao) {
        const valor =
            String(acao ?? '').toUpperCase();

        if (
            [
                'LOGIN',
                'LOGOUT',
                'ACESSO',
                'CONSULTA',
                'VISUALIZACAO'
            ].includes(valor)
        ) {
            return 'access';
        }

        if (
            [
                'CRIACAO',
                'ALTERACAO',
                'ATUALIZACAO',
                'EDICAO',
                'ATIVACAO'
            ].includes(valor)
        ) {
            return 'change';
        }

        if (
            [
                'EXCLUSAO',
                'INATIVACAO',
                'BLOQUEIO'
            ].includes(valor)
        ) {
            return 'critical';
        }

        return 'neutral';
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

    function tentarFormatarJson(valor) {
        if (!valor) {
            return 'Nenhum dado registrado.';
        }

        try {
            const parsed =
                JSON.parse(valor);

            return JSON.stringify(
                parsed,
                null,
                2
            );
        } catch {
            return String(valor);
        }
    }

    async function carregarFiltros() {
        const resposta = await fetch(
            '/safe-health/modules/tecnologia/api/auditoria.php?acao=filtros',
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
                'Não foi possível carregar os filtros.'
            );
        }

        usuarioFiltro.innerHTML = `
            <option value="">
                Todos os usuários
            </option>

            ${(resultado.dados.usuarios ?? [])
                .map(usuario => `
                    <option value="${Number(usuario.id)}">
                        ${escapar(usuario.nome)}
                    </option>
                `)
                .join('')}
        `;

        moduloFiltro.innerHTML = `
            <option value="">
                Todos os módulos
            </option>

            ${(resultado.dados.modulos ?? [])
                .map(modulo => `
                    <option value="${escapar(modulo)}">
                        ${escapar(modulo)}
                    </option>
                `)
                .join('')}
        `;

        acaoFiltro.innerHTML = `
            <option value="">
                Todas as ações
            </option>

            ${(resultado.dados.acoes ?? [])
                .map(acao => `
                    <option value="${escapar(acao)}">
                        ${escapar(
                            statusLegivel(acao)
                        )}
                    </option>
                `)
                .join('')}
        `;
    }

    async function carregarResumo() {
        try {
            const resposta = await fetch(
                '/safe-health/modules/tecnologia/api/auditoria.php?acao=resumo',
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
                return;
            }

            const resumo =
                resultado.dados.resumo || {};

            document.getElementById(
                'resumoEventos'
            ).textContent =
                Number(resumo.eventos || 0);

            document.getElementById(
                'resumoHoje'
            ).textContent =
                Number(resumo.hoje || 0);

            document.getElementById(
                'resumoAlteracoes'
            ).textContent =
                Number(resumo.alteracoes || 0);

            document.getElementById(
                'resumoAcessos'
            ).textContent =
                Number(resumo.acessos || 0);

        } catch (erro) {
            console.error(erro);
        }
    }

    function renderizarEventos(eventos) {
        if (
            !Array.isArray(eventos) ||
            eventos.length === 0
        ) {
            lista.innerHTML = `
                <div class="empty-state">
                    Nenhum evento de auditoria encontrado.
                </div>
            `;

            return;
        }

        lista.innerHTML = `
            <div class="audit-table">

                <div class="audit-row audit-header">
                    <span>Data/Hora</span>
                    <span>Usuário</span>
                    <span>Ação</span>
                    <span>Módulo</span>
                    <span>Tela</span>
                    <span>Registro</span>
                    <span>IP</span>
                    <span>Ação</span>
                </div>

                ${eventos.map(evento => `
                    <div class="audit-row">

                        <span>
                            ${escapar(
                                formatarData(
                                    evento.criado_em
                                )
                            )}
                        </span>

                        <span>
                            <strong>
                                ${escapar(
                                    evento.usuario_nome ||
                                    'Sistema'
                                )}
                            </strong>

                            ${
                                evento.usuario_email
                                    ? `<small>${escapar(evento.usuario_email)}</small>`
                                    : ''
                            }
                        </span>

                        <span>
                            <span class="action-badge ${classeAcao(evento.acao)}">
                                ${escapar(
                                    statusLegivel(
                                        evento.acao
                                    )
                                )}
                            </span>
                        </span>

                        <span>
                            ${escapar(
                                evento.modulo ||
                                '-'
                            )}
                        </span>

                        <span>
                            ${escapar(
                                evento.tela ||
                                '-'
                            )}
                        </span>

                        <span>
                            ${
                                evento.registro_id
                                    ? '#' + escapar(evento.registro_id)
                                    : '-'
                            }
                        </span>

                        <span>
                            ${escapar(
                                evento.ip ||
                                '-'
                            )}
                        </span>

                        <span>
                            <button
                                type="button"
                                class="details-button"
                                data-id="${Number(evento.id)}"
                            >
                                <i class="fa-solid fa-eye"></i>
                                Detalhes
                            </button>
                        </span>

                    </div>
                `).join('')}

            </div>
        `;
    }

    async function carregarEventos() {
        const params =
            new URLSearchParams({
                acao: 'listar',
                data_inicio:
                    dataInicio.value,
                data_fim:
                    dataFim.value,
                usuario_id:
                    usuarioFiltro.value,
                modulo:
                    moduloFiltro.value,
                acao_filtro:
                    acaoFiltro.value,
                q:
                    campoPesquisa.value.trim()
            });

        lista.innerHTML = `
            <div class="empty-state">
                Carregando auditoria...
            </div>
        `;

        try {
            const resposta = await fetch(
                `/safe-health/modules/tecnologia/api/auditoria.php?${params.toString()}`,
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
                    'Não foi possível carregar a auditoria.'
                );
            }

            renderizarEventos(
                resultado.dados.eventos ?? []
            );

        } catch (erro) {
            lista.innerHTML = `
                <div class="empty-state error">
                    ${escapar(
                        erro instanceof Error
                            ? erro.message
                            : 'Erro ao carregar auditoria.'
                    )}
                </div>
            `;
        }
    }

    async function abrirDetalhes(id) {
        try {
            const resposta = await fetch(
                `/safe-health/modules/tecnologia/api/auditoria.php?acao=detalhes&id=${encodeURIComponent(id)}`,
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
                    'Não foi possível abrir o evento.'
                );
            }

            const evento =
                resultado.dados.evento;

            document.getElementById(
                'tituloDetalhes'
            ).textContent =
                `Evento de Auditoria #${evento.id}`;

            document.getElementById(
                'detalheData'
            ).textContent =
                formatarData(
                    evento.criado_em
                );

            document.getElementById(
                'detalheUsuario'
            ).textContent =
                evento.usuario_nome ||
                'Sistema';

            document.getElementById(
                'detalheAcao'
            ).textContent =
                statusLegivel(
                    evento.acao
                );

            document.getElementById(
                'detalheModulo'
            ).textContent =
                evento.modulo || '-';

            document.getElementById(
                'detalheTela'
            ).textContent =
                evento.tela || '-';

            document.getElementById(
                'detalheEntidade'
            ).textContent =
                evento.entidade || '-';

            document.getElementById(
                'detalheRegistro'
            ).textContent =
                evento.registro_id
                    ? '#' + evento.registro_id
                    : '-';

            document.getElementById(
                'detalheIp'
            ).textContent =
                evento.ip || '-';

            document.getElementById(
                'detalheMetodo'
            ).textContent =
                evento.metodo_http || '-';

            document.getElementById(
                'detalheUrl'
            ).textContent =
                evento.url || '-';

            document.getElementById(
                'detalheDescricao'
            ).textContent =
                evento.descricao || '-';

            document.getElementById(
                'detalheAnterior'
            ).textContent =
                tentarFormatarJson(
                    evento.dados_anteriores
                );

            document.getElementById(
                'detalheNovo'
            ).textContent =
                tentarFormatarJson(
                    evento.dados_novos
                );

            detalhesCard.hidden = false;

            detalhesCard.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

        } catch (erro) {
            alert(
                erro instanceof Error
                    ? erro.message
                    : 'Erro ao abrir evento.'
            );
        }
    }

    function limparFiltros() {
        dataInicio.value = '';
        dataFim.value = '';
        usuarioFiltro.value = '';
        moduloFiltro.value = '';
        acaoFiltro.value = '';
        campoPesquisa.value = '';

        carregarEventos();
    }

    lista.addEventListener(
        'click',
        function (evento) {
            const botao =
                evento.target.closest(
                    '.details-button'
                );

            if (!botao) {
                return;
            }

            abrirDetalhes(
                Number(botao.dataset.id)
            );
        }
    );

    document.getElementById(
        'botaoPesquisar'
    ).addEventListener(
        'click',
        carregarEventos
    );

    document.getElementById(
        'botaoLimpar'
    ).addEventListener(
        'click',
        limparFiltros
    );

    document.getElementById(
        'botaoAtualizar'
    ).addEventListener(
        'click',
        async function () {
            await Promise.allSettled([
                carregarFiltros(),
                carregarResumo(),
                carregarEventos()
            ]);
        }
    );

    document.getElementById(
        'botaoFecharDetalhes'
    ).addEventListener(
        'click',
        function () {
            detalhesCard.hidden = true;
        }
    );

    campoPesquisa.addEventListener(
        'keydown',
        function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                carregarEventos();
            }
        }
    );

    Promise.all([
        carregarFiltros(),
        carregarResumo(),
        carregarEventos()
    ]).catch(
        erro => {
            console.error(erro);
        }
    );
});
