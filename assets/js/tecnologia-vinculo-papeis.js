document.addEventListener('DOMContentLoaded', () => {
    const usuario = document.getElementById('usuario');
    const lista = document.getElementById('listaPapeis');
    const geral = document.getElementById('visaoGeral');
    const info = document.getElementById('usuarioInfo');
    const salvar = document.getElementById('salvar');
    const mensagem = document.getElementById('mensagem');

    let dados = {usuarios:[], papeis:[], vinculos:[], geral:[]};

    const esc = v => String(v ?? '')
        .replaceAll('&','&amp;').replaceAll('<','&lt;')
        .replaceAll('>','&gt;').replaceAll('"','&quot;')
        .replaceAll("'",'&#039;');

    async function json(res) {
        const t = await res.text();
        try { return JSON.parse(t); }
        catch { console.error(t); throw new Error('O servidor retornou uma resposta inválida.'); }
    }

    function msg(texto, tipo='success') {
        mensagem.hidden = false;
        mensagem.className = 'message ' + tipo;
        mensagem.textContent = texto;
    }

    function renderUsuarios() {
        const atual = usuario.value;
        usuario.innerHTML = '<option value="">Selecione um usuário</option>' +
            dados.usuarios.map(u => `
                <option value="${Number(u.id)}">${esc(u.nome)} — ${esc(u.email)}</option>
            `).join('');
        if (atual) usuario.value = atual;
    }

    function renderGeral() {
        if (!dados.geral.length) {
            geral.innerHTML = '<div class="empty">Nenhum usuário cadastrado.</div>';
            return;
        }
        geral.innerHTML = `
            <div class="vp-table">
                <div class="vp-row vp-header"><span>Usuário</span><span>E-mail</span><span>Papéis</span><span>Status</span></div>
                ${dados.geral.map(u => {
                    const papeis = u.papeis ? u.papeis.split('||') : [];
                    return `
                    <div class="vp-row">
                        <span><strong>${esc(u.nome)}</strong></span>
                        <span>${esc(u.email)}</span>
                        <span class="role-tags">${papeis.length
                            ? papeis.map(p=>`<b>${esc(p)}</b>`).join('')
                            : '<em>Sem papel atribuído</em>'}</span>
                        <span><i class="status ${Number(u.ativo)===1?'active':'inactive'}">${Number(u.ativo)===1?'Ativo':'Inativo'}</i></span>
                    </div>`;
                }).join('')}
            </div>`;
    }

    function renderPapeis() {
        const uid = Number(usuario.value);
        mensagem.hidden = true;

        if (!uid) {
            info.hidden = true;
            salvar.disabled = true;
            lista.innerHTML = '<div class="empty">Selecione um usuário para carregar os vínculos.</div>';
            return;
        }

        const u = dados.usuarios.find(x => Number(x.id) === uid);
        info.hidden = false;
        info.innerHTML = `
            <div><span>Usuário selecionado</span><strong>${esc(u?.nome || '-')}</strong></div>
            <div><span>E-mail</span><strong>${esc(u?.email || '-')}</strong></div>
        `;

        const marcados = new Set(
            dados.vinculos
                .filter(v => Number(v.usuario_id) === uid)
                .map(v => Number(v.papel_id))
        );

        lista.innerHTML = dados.papeis.map(p => `
            <label class="role-card">
                <input type="checkbox" name="papel" value="${Number(p.id)}" ${marcados.has(Number(p.id))?'checked':''}>
                <span class="check-ui"><i class="fa-solid fa-check"></i></span>
                <span class="role-content">
                    <strong>${esc(p.nome)}</strong>
                    <small>${esc(p.descricao || 'Sem descrição')}</small>
                </span>
            </label>
        `).join('');

        salvar.disabled = false;
    }

    async function carregar() {
        try {
            const res = await fetch('/safe-health/modules/tecnologia/api/vinculo-papeis.php', {
                headers:{Accept:'application/json'}, cache:'no-store'
            });
            const r = await json(res);
            if (!res.ok || !r.sucesso) throw new Error(r.mensagem || 'Erro ao carregar.');
            dados = r.dados;
            renderUsuarios();
            renderGeral();
            renderPapeis();
        } catch(e) {
            msg(e.message || 'Erro ao carregar vínculos.', 'error');
        }
    }

    usuario.addEventListener('change', renderPapeis);

    salvar.addEventListener('click', async () => {
        const uid = Number(usuario.value);
        if (!uid) return;

        const papeis = [...document.querySelectorAll('input[name="papel"]:checked')]
            .map(x => Number(x.value));

        salvar.disabled = true;
        try {
            const res = await fetch('/safe-health/modules/tecnologia/api/vinculo-papeis.php', {
                method:'POST',
                headers:{'Content-Type':'application/json', Accept:'application/json'},
                body:JSON.stringify({usuario_id:uid, papeis})
            });
            const r = await json(res);
            if (!res.ok || !r.sucesso) throw new Error(r.mensagem || 'Erro ao salvar.');
            msg(r.mensagem);
            await carregar();
            usuario.value = String(uid);
            renderPapeis();
            msg('Vínculos salvos com sucesso.');
        } catch(e) {
            msg(e.message || 'Erro ao salvar vínculos.', 'error');
        } finally {
            salvar.disabled = false;
        }
    });

    document.getElementById('recarregar').addEventListener('click', carregar);
    carregar();
});
