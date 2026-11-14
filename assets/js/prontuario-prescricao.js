document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('.rx-page');
    if (!page) return;

    const atendimentoId = Number(page.dataset.atendimentoId || 0);
    const prescricaoId = Number(page.dataset.prescricaoId || 0);
    const modal = document.getElementById('rxModal');
    const busca = document.getElementById('rxBuscaMedicamento');
    const resultados = document.getElementById('rxSearchResults');
    const form = document.getElementById('rxItemForm');
    const feedback = document.getElementById('rxFormFeedback');
    const medFields = document.getElementById('rxMedicationFields');
    const examFields = document.getElementById('rxExamFields');
    let itemSelecionado = null;
    let timerBusca = null;

    const apiUrl =
        '/safe-health/modules/prontuario/api/prescricao.php';

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function api(payload, method = 'POST') {
        let url = apiUrl;
        const options = {
            method,
            headers: { 'Accept': 'application/json' }
        };

        if (method === 'GET') {
            url += '?' + new URLSearchParams(payload).toString();
        } else {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(payload);
        }

        const response = await fetch(url, options);
        let result;

        try {
            result = await response.json();
        } catch {
            throw new Error('A resposta do servidor não pôde ser processada.');
        }

        if (!response.ok || !result.sucesso) {
            throw new Error(result.mensagem || 'Não foi possível concluir a operação.');
        }

        return result;
    }

    function abrirModal() {
        modal?.classList.add('open');
        modal?.setAttribute('aria-hidden', 'false');
        busca?.focus();
    }

    function fecharModal() {
        modal?.classList.remove('open');
        modal?.setAttribute('aria-hidden', 'true');
        resetarSelecao();
        if (busca) busca.value = '';
        if (resultados) resultados.innerHTML = '';
    }

    function resetarSelecao() {
        itemSelecionado = null;
        form.hidden = true;
        form.reset();
        document.getElementById('rxItemId').value = '';
        document.getElementById('rxItemTipo').value = '';
        document.getElementById('rxSelectedMed').innerHTML = '';
        medFields.hidden = false;
        examFields.hidden = true;
        medFields.style.display = 'grid';
        examFields.style.display = 'none';

        const campoFrequencia =
            document.getElementById('rxFrequencia');

        if (campoFrequencia) {
            campoFrequencia.required = false;
            campoFrequencia.disabled = true;
            campoFrequencia.value = '';
            campoFrequencia.innerHTML =
                '<option value="">Selecione</option>';
        }
        if (feedback) {
            feedback.hidden = true;
            feedback.textContent = '';
        }
    }

    function rotuloTipo(tipo) {
        if (tipo === 'MEDICAMENTO') return 'Medicamento';
        if (tipo === 'EXAME_LABORATORIAL') return 'Exame laboratorial';
        if (tipo === 'EXAME_IMAGEM') return 'Exame de imagem';
        return tipo;
    }

    async function pesquisar() {
        const q = busca?.value.trim() || '';

        if (q.length < 2) {
            resultados.innerHTML =
                '<div class="rx-search-message">Digite pelo menos 2 caracteres.</div>';
            return;
        }

        resultados.innerHTML =
            '<div class="rx-search-message">Pesquisando itens cadastrados...</div>';

        try {
            const result = await api(
                {
                    acao: 'pesquisar_medicamentos',
                    atendimento_id: atendimentoId,
                    q
                },
                'GET'
            );

            const itens = result.dados?.itens || [];

            if (!itens.length) {
                resultados.innerHTML =
                    '<div class="rx-search-message">Nenhum item cadastrado encontrado.</div>';
                return;
            }

            resultados.innerHTML = itens.map((item, index) => {
                const alertas = item.alertas_alergia || [];
                const alertHtml = alertas.length
                    ? `<div class="rx-search-allergy">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Possível correspondência com alergia ativa:
                        ${alertas.map(a => escapeHtml(a.substancia)).join(', ')}
                       </div>`
                    : '';

                return `
                    <button type="button" class="rx-search-result" data-index="${index}">
                        <div class="rx-result-main">
                            <span class="rx-result-type">${escapeHtml(rotuloTipo(item.tipo))}</span>
                            <strong>${escapeHtml(item.nome)}</strong>
                            <span>${escapeHtml(item.subtitulo || item.codigo || '')}</span>
                        </div>
                        ${alertHtml}
                    </button>
                `;
            }).join('');

            resultados
                .querySelectorAll('.rx-search-result')
                .forEach(button => {
                    button.addEventListener('click', () => {
                        selecionarItem(
                            itens[Number(button.dataset.index)]
                        );
                    });
                });
        } catch (error) {
            resultados.innerHTML =
                `<div class="rx-search-message error">${escapeHtml(error.message)}</div>`;
        }
    }

    function selecionarItem(item) {
        itemSelecionado = item;

        document.getElementById('rxItemId').value = item.id;
        document.getElementById('rxItemTipo').value = item.tipo;

        const alertas = item.alertas_alergia || [];

        document.getElementById('rxSelectedMed').innerHTML = `
            <div>
                <span class="rx-result-type">${escapeHtml(rotuloTipo(item.tipo))}</span>
                <strong>${escapeHtml(item.nome)}</strong>
                <span>${escapeHtml(item.subtitulo || item.codigo || '')}</span>
            </div>
            ${
                alertas.length
                    ? `<div class="rx-selected-warning">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Há possível correspondência textual com alergia ativa.
                        Confirme clinicamente antes de prescrever.
                       </div>`
                    : ''
            }
        `;

        const isMed = item.tipo === 'MEDICAMENTO';

        medFields.hidden = !isMed;
        examFields.hidden = isMed;

        const campoFrequencia =
            document.getElementById('rxFrequencia');

        if (!isMed) {
            medFields.style.display = 'none';
            medFields.hidden = true;

            examFields.style.display = 'block';
            examFields.hidden = false;

            if (campoFrequencia) {
                campoFrequencia.required = false;
                campoFrequencia.disabled = true;
                campoFrequencia.value = '';
            }
        } else {
            medFields.style.display = 'grid';
            medFields.hidden = false;

            examFields.style.display = 'none';
            examFields.hidden = true;

            if (campoFrequencia) {
                campoFrequencia.required = true;
            }
        }

        if (isMed) {
            document.getElementById('rxDoseValor').textContent =
                item.dose_padrao || 'Não cadastrado';

            document.getElementById('rxViaValor').textContent =
                item.via_administracao || 'Não cadastrada';

            if (campoFrequencia) {
                const frequencias =
                    Array.isArray(item.frequencias)
                        ? item.frequencias
                        : [];

                campoFrequencia.innerHTML =
                    '<option value="">Selecione</option>'
                    + frequencias.map(function (frequencia) {
                        return '<option value="'
                            + escapeHtml(frequencia.codigo)
                            + '">'
                            + escapeHtml(frequencia.descricao)
                            + '</option>';
                    }).join('');

                campoFrequencia.disabled =
                    frequencias.length === 0;
            }

            document.getElementById('rxQuantidadeValor').textContent =
                item.quantidade_padrao || 'Não cadastrada';
        }

        resultados.innerHTML = '';
        form.hidden = false;

        if (isMed) {
            document.getElementById('btnAdicionarItemRx')?.focus();
        } else {
            document.getElementById('rxDetalhesExame')?.focus();
        }
    }

    document.getElementById('btnAdicionarMedicamento')
        ?.addEventListener('click', abrirModal);

    document.querySelectorAll('[data-close-rx-modal]')
        .forEach(el => el.addEventListener('click', fecharModal));

    document.getElementById('btnPesquisarMedicamento')
        ?.addEventListener('click', pesquisar);

    busca?.addEventListener('input', function () {
        clearTimeout(timerBusca);

        const q = this.value.trim();

        if (q.length < 2) {
            resultados.innerHTML = '';
            return;
        }

        timerBusca = setTimeout(pesquisar, 280);
    });

    document.getElementById('btnTrocarMedicamento')
        ?.addEventListener('click', function () {
            resetarSelecao();
            busca?.focus();
        });

    form?.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!itemSelecionado) return;

        let payload;

        if (itemSelecionado.tipo === 'MEDICAMENTO') {
            payload = {
                acao: 'adicionar_item',
                atendimento_id: atendimentoId,
                prescricao_id: prescricaoId,
                medicamento_id: Number(itemSelecionado.id),
                frequencia:
                    document.getElementById('rxFrequencia')?.value || '',
                instrucoes: ''
            };
        } else {
            payload = {
                acao: 'adicionar_solicitacao',
                atendimento_id: atendimentoId,
                prescricao_id: prescricaoId,
                tipo_item: itemSelecionado.tipo,
                item_id: Number(itemSelecionado.id),
                detalhes: document.getElementById('rxDetalhesExame').value.trim()
            };
        }

        try {
            const result = await api(payload);
            feedback.textContent = result.mensagem;
            feedback.className = 'allergy-form-feedback success';
            feedback.hidden = false;
            setTimeout(() => window.location.reload(), 350);
        } catch (error) {
            feedback.textContent = error.message;
            feedback.className = 'allergy-form-feedback error';
            feedback.hidden = false;
        }
    });

    document.querySelectorAll('.rx-remove-item')
        .forEach(button => {
            button.addEventListener('click', async function () {
                if (!confirm(`Remover "${button.dataset.itemName}" do rascunho?`)) {
                    return;
                }

                button.disabled = true;
                try {
                    await api({
                        acao: 'remover_item',
                        atendimento_id: atendimentoId,
                        item_id: Number(button.dataset.itemId)
                    });
                    location.reload();
                } catch (error) {
                    alert(error.message);
                    button.disabled = false;
                }
            });
        });

    document.querySelectorAll('.rx-remove-request')
        .forEach(button => {
            button.addEventListener('click', async function () {
                if (!confirm(`Remover "${button.dataset.requestName}" do rascunho?`)) {
                    return;
                }

                button.disabled = true;
                try {
                    await api({
                        acao: 'remover_solicitacao',
                        atendimento_id: atendimentoId,
                        solicitacao_id: Number(button.dataset.requestId)
                    });
                    location.reload();
                } catch (error) {
                    alert(error.message);
                    button.disabled = false;
                }
            });
        });

    async function salvarRascunho() {
        const result = await api({
            acao: 'salvar_observacoes',
            atendimento_id: atendimentoId,
            prescricao_id: prescricaoId,
            observacoes: document.getElementById('rxObservacoes')?.value.trim() || ''
        });
        return result;
    }

    document.getElementById('btnSalvarObservacoes')
        ?.addEventListener('click', async function () {
            this.disabled = true;
            try {
                const result = await salvarRascunho();
                alert(result.mensagem);
                location.reload();
            } catch (error) {
                alert(error.message);
                this.disabled = false;
            }
        });

    document.getElementById('btnSalvarRascunho')
        ?.addEventListener('click', async function () {
            this.disabled = true;
            try {
                const result = await salvarRascunho();
                alert('Prescrição salva como rascunho.');
                location.reload();
            } catch (error) {
                alert(error.message);
                this.disabled = false;
            }
        });

    async function finalizar() {
        if (
            !confirm(
                'Fechar e assinar esta prescrição?\n\nApós a assinatura, os itens não poderão ser editados diretamente.'
            )
        ) {
            return;
        }

        await salvarRascunho();

        const result = await api({
            acao: 'finalizar',
            atendimento_id: atendimentoId,
            prescricao_id: prescricaoId
        });

        alert(result.mensagem);
        location.reload();
    }

    document.getElementById('btnFinalizarPrescricao')
        ?.addEventListener('click', async function () {
            this.disabled = true;
            try {
                await finalizar();
            } catch (error) {
                alert(error.message);
                this.disabled = false;
            }
        });


    async function novoDocumento() {
        const result = await api({
            acao: 'novo_rascunho',
            atendimento_id: atendimentoId
        });

        const novoId = Number(result.dados?.prescricao_id || 0);

        if (!novoId) {
            throw new Error('O servidor não retornou o novo documento.');
        }

        window.location.href =
            '/safe-health/modules/prontuario/index.php'
            + '?aba=prescricao'
            + '&atendimento_id=' + encodeURIComponent(atendimentoId)
            + '&prescricao_id=' + encodeURIComponent(novoId);
    }

    document.addEventListener('prontuario:novo', async function (event) {
        if (event.detail?.aba !== 'prescricao') {
            return;
        }

        event.preventDefault();

        try {
            await novoDocumento();
        } catch (error) {
            window.alert(error.message);
        }
    });

    document.addEventListener('prontuario:salvar', async function (event) {
        if (event.detail?.aba !== 'prescricao') {
            return;
        }

        event.preventDefault();

        try {
            await salvarRascunho();
            window.alert('Prescrição salva como rascunho.');
        } catch (error) {
            window.alert(error.message);
        }
    });

    document.addEventListener('prontuario:assinar', async function (event) {
        if (event.detail?.aba !== 'prescricao') {
            return;
        }

        event.preventDefault();

        try {
            await finalizar();
        } catch (error) {
            window.alert(error.message);
        }
    });

    const btnExcluirPrescricao = document.getElementById('btnExcluirPrescricao');

    btnExcluirPrescricao?.addEventListener('click', async function () {
        const prescricaoIdAtual = prescricaoId;

        if (!prescricaoIdAtual) {
            window.alert('Não existe uma prescrição em rascunho para excluir.');
            return;
        }

        const confirmar = window.confirm(
            'Deseja excluir esta prescrição em rascunho?\n\n' +
            'Os itens e observações ainda não assinados serão removidos. ' +
            'Documentos já assinados nunca podem ser excluídos.'
        );

        if (!confirmar) {
            return;
        }

        try {
            const result = await api({
                acao: 'excluir_rascunho',
                atendimento_id: atendimentoId,
                prescricao_id: prescricaoIdAtual
            });

            window.alert(result.mensagem);
            window.location.reload();
        } catch (error) {
            window.alert(error.message);
        }
    });
});
