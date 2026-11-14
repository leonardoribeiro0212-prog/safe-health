document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.pricing-page');

    if (!page) {
        return;
    }

    const convenioId = Number(page.dataset.convenioId || 0);
    const tipoItem = String(page.dataset.tipoItem || '');
    const btnSalvar = document.getElementById('btnSalvarTodosPrecos');
    const feedback = document.getElementById('pricingFeedback');

    function mostrarFeedback(mensagem, tipo = 'success') {
        if (!feedback) {
            return;
        }

        feedback.textContent = mensagem;
        feedback.className = `pricing-feedback ${tipo}`;
        feedback.hidden = false;

        window.setTimeout(() => {
            feedback.hidden = true;
        }, 4500);
    }

    if (!btnSalvar) {
        return;
    }

    btnSalvar.addEventListener('click', async () => {
        if (!convenioId || !tipoItem) {
            mostrarFeedback(
                'Selecione um convênio válido.',
                'error'
            );
            return;
        }

        const linhas = Array.from(
            document.querySelectorAll('.pricing-row')
        );

        const itens = linhas.map((linha) => {
            const inputValor =
                linha.querySelector('.js-price-value');

            const inputAutorizacao =
                linha.querySelector('.js-requires-auth');

            return {
                item_id: Number(linha.dataset.itemId),
                valor: inputValor?.value?.trim() ?? '',
                exige_autorizacao:
                    Boolean(inputAutorizacao?.checked)
            };
        });

        btnSalvar.disabled = true;

        try {
            const resposta = await fetch(
                '/safe-health/modules/faturamento/api/procedimentos.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json'
                    },
                    body: JSON.stringify({
                        convenio_id: convenioId,
                        tipo_item: tipoItem,
                        itens
                    })
                }
            );

            const texto = await resposta.text();

            let resultado;

            try {
                resultado = JSON.parse(texto);
            } catch {
                console.error(texto);
                throw new Error(
                    'A API retornou uma resposta inválida.'
                );
            }

            if (!resposta.ok || !resultado.sucesso) {
                throw new Error(
                    resultado.mensagem ||
                    'Não foi possível salvar a tabela.'
                );
            }

            mostrarFeedback(resultado.mensagem);

            window.setTimeout(() => {
                window.location.reload();
            }, 800);

        } catch (erro) {
            console.error(erro);

            mostrarFeedback(
                erro.message ||
                'Não foi possível salvar a tabela.',
                'error'
            );

            btnSalvar.disabled = false;
        }
    });
});
