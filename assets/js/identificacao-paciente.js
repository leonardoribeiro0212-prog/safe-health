document.addEventListener('DOMContentLoaded', function () {
    const formulario =
        document.getElementById('formPesquisaPaciente');

    const campoCpf =
        document.getElementById('cpf');

    const senhaId =
        document.getElementById('senhaId');

    const mensagem =
        document.getElementById('mensagemPesquisa');

    const resultado =
        document.getElementById('resultadoPaciente');

    function somenteNumeros(valor) {
        return String(valor).replace(/\D/g, '');
    }

    function escaparHtml(valor) {
        return String(valor ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatarCpf(valor) {
        const numeros = somenteNumeros(valor).slice(0, 11);

        return numeros
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(
                /^(\d{3})\.(\d{3})\.(\d{3})(\d)/,
                '$1.$2.$3-$4'
            );
    }

    function formatarData(valor) {
        if (!valor) {
            return 'Não informada';
        }

        const partes = String(valor).split('-');

        if (partes.length !== 3) {
            return valor;
        }

        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }

    function mostrarMensagem(texto, tipo) {
        mensagem.hidden = false;
        mensagem.className = `search-message ${tipo}`;
        mensagem.textContent = texto;
    }

    function limparResultado() {
        resultado.hidden = true;
        resultado.innerHTML = '';
    }

    campoCpf.addEventListener('input', function () {
        campoCpf.value = formatarCpf(campoCpf.value);
    });

    formulario.addEventListener('submit', async function (evento) {
        evento.preventDefault();

        const cpf = somenteNumeros(campoCpf.value);

        limparResultado();

        if (cpf.length !== 11) {
            mostrarMensagem(
                'Informe um CPF válido com 11 números.',
                'error'
            );

            return;
        }

        mostrarMensagem(
            'Pesquisando paciente...',
            'loading'
        );

        try {
            const resposta = await fetch(
                `/safe-health/modules/atendimento/api/pacientes/buscar-por-cpf.php?cpf=${encodeURIComponent(cpf)}`,
                {
                    headers: {
                        Accept: 'application/json'
                    }
                }
            );

            const dados = await resposta.json();

            if (!resposta.ok || !dados.sucesso) {
                throw new Error(
                    dados.mensagem ||
                    'Não foi possível pesquisar o paciente.'
                );
            }

            if (!dados.encontrado) {
                mostrarMensagem(
                    'Paciente não encontrado.',
                    'warning'
                );

                resultado.hidden = false;

                resultado.innerHTML = `
                    <div class="not-found-card">
                        <div class="result-icon warning">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>

                        <div>
                            <h3>Paciente não cadastrado</h3>

                            <p>
                                Não encontramos nenhum paciente com o CPF
                                <strong>${escaparHtml(formatarCpf(cpf))}</strong>.
                            </p>
                        </div>

                        <a
                            class="register-button"
                            href="/safe-health/modules/atendimento/cadastro-paciente.php?senha_id=${encodeURIComponent(senhaId.value)}&cpf=${encodeURIComponent(cpf)}"
                        >
                            <i class="fa-solid fa-user-plus"></i>
                            Cadastrar paciente
                        </a>
                    </div>
                `;

                return;
            }

            mensagem.hidden = true;

            const paciente = dados.paciente;

            resultado.hidden = false;

            resultado.innerHTML = `
                <div class="patient-header">
                    <div class="result-icon success">
                        <i class="fa-solid fa-user-check"></i>
                    </div>

                    <div>
                        <span>Paciente encontrado</span>
                        <h3>${escaparHtml(paciente.nome)}</h3>
                    </div>
                </div>

                <div class="patient-data-grid">
                    <div>
                        <span>CPF</span>
                        <strong>
                            ${escaparHtml(formatarCpf(paciente.cpf))}
                        </strong>
                    </div>

                    <div>
                        <span>Data de nascimento</span>
                        <strong>
                            ${escaparHtml(
                formatarData(paciente.data_nascimento)
            )}
                        </strong>
                    </div>

                    <div>
                        <span>Sexo</span>
                        <strong>
                            ${escaparHtml(paciente.sexo)}
                        </strong>
                    </div>

                    <div>
                        <span>Nome da mãe</span>
                        <strong>
                            ${escaparHtml(
                paciente.nome_mae || 'Não informado'
            )}
                        </strong>
                    </div>

                    <div>
                        <span>Telefone</span>
                        <strong>
                            ${escaparHtml(
                paciente.telefone || 'Não informado'
            )}
                        </strong>
                    </div>

                    <div>
                        <span>Cartão SUS</span>
                        <strong>
                            ${escaparHtml(
                paciente.cartao_sus || 'Não informado'
            )}
                        </strong>
                    </div>
                </div>

                <div class="patient-actions">
                    <button
                        type="button"
                        class="secondary-button"
                        id="pesquisarOutroPaciente"
                    >
                        <i class="fa-solid fa-rotate-left"></i>
                        Pesquisar outro
                    </button>

                    <a
                        class="service-button"
                        href="/safe-health/modules/atendimento/abrir-atendimento.php?senha_id=${encodeURIComponent(senhaId.value)}&paciente_id=${encodeURIComponent(paciente.id)}"
                    >
                        <i class="fa-solid fa-arrow-right"></i>
                        Atender
                    </a>
                </div>
            `;

            document
                .getElementById('pesquisarOutroPaciente')
                ?.addEventListener('click', function () {
                    limparResultado();
                    mensagem.hidden = true;
                    campoCpf.value = '';
                    campoCpf.focus();
                });
        } catch (erro) {
            mostrarMensagem(
                erro.message ||
                'Erro ao pesquisar paciente.',
                'error'
            );
        }
    });

    const parametros = new URLSearchParams(
        window.location.search
    );

    const cpfRecebido = parametros.get('cpf');

    if (cpfRecebido) {
        campoCpf.value = formatarCpf(cpfRecebido);

        formulario.dispatchEvent(
            new Event('submit', {
                bubbles: true,
                cancelable: true
            })
        );
    } else {
        campoCpf.focus();
    }
});