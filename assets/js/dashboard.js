const indicadorSenhasRecepcao =
    document.getElementById('indicadorSenhasRecepcao');

const indicadorAtendimentos =
    document.getElementById('indicadorAtendimentos');

const indicadorTriagem =
    document.getElementById('indicadorTriagem');

const indicadorInternados =
    document.getElementById('indicadorInternados');

const dashboardStatus =
    document.getElementById('dashboardStatus');

let atualizandoDashboard = false;

function definirValor(elemento, valor) {
    if (!elemento) {
        return;
    }

    const novoValor = Number(valor ?? 0);

    elemento.textContent =
        Number.isFinite(novoValor)
            ? String(novoValor)
            : '0';
}

async function lerJson(resposta) {
    const texto = await resposta.text();

    if (texto.trim() === '') {
        throw new Error(
            'O servidor não retornou uma resposta.'
        );
    }

    try {
        return JSON.parse(texto);
    } catch {
        console.error(
            'Resposta inválida do servidor:',
            texto
        );

        throw new Error(
            'O servidor retornou uma resposta inválida.'
        );
    }
}

async function atualizarDashboard() {
    if (atualizandoDashboard) {
        return;
    }

    atualizandoDashboard = true;

    try {
        const resposta = await fetch(
            '/safe-health/api/dashboard-indicadores.php',
            {
                method: 'GET',
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
                'Não foi possível atualizar o dashboard.'
            );
        }

        const dados = resultado.dados ?? {};

        definirValor(
            indicadorSenhasRecepcao,
            dados.senhas_recepcao
        );

        definirValor(
            indicadorAtendimentos,
            dados.atendimentos_abertos
        );

        definirValor(
            indicadorTriagem,
            dados.aguardando_triagem
        );

        definirValor(
            indicadorInternados,
            dados.internados
        );

        dashboardStatus.textContent =
            `Última atualização: ${dados.atualizado_em}`;

        dashboardStatus.classList.remove(
            'dashboard-update-error'
        );
    } catch (erro) {
        console.error(erro);

        dashboardStatus.textContent =
            erro instanceof Error
                ? erro.message
                : 'Não foi possível atualizar os indicadores.';

        dashboardStatus.classList.add(
            'dashboard-update-error'
        );
    } finally {
        atualizandoDashboard = false;
    }
}

atualizarDashboard();

window.setInterval(
    atualizarDashboard,
    5000
);
