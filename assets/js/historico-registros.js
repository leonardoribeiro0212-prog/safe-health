(() => {
    const modal = document.getElementById('hrModal');
    const body = document.getElementById('hrModalBody');
    if (!modal || !body) return;

    const esc = v => String(v ?? '—').replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[c]));

    const campo = (titulo, valor, full = false) =>
        `<div class="hr-detail${full ? ' hr-detail-full' : ''}">
            <span>${esc(titulo)}</span><strong>${esc(valor)}</strong>
        </div>`;

    document.querySelectorAll('.hr-detail-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            let d = {};
            try { d = JSON.parse(btn.dataset.log || '{}'); } catch (_) {}

            body.innerHTML = `<div class="hr-detail-grid">
                ${campo('ID do log', d.id)}
                ${campo('Data/Hora', d.data)}
                ${campo('Usuário', d.usuario)}
                ${campo('E-mail', d.email)}
                ${campo('Ação', d.acao)}
                ${campo('Módulo', d.modulo)}
                ${campo('Tela', d.tela)}
                ${campo('Entidade', d.entidade)}
                ${campo('Registro ID', d.registro_id)}
                ${campo('IP', d.ip)}
                ${campo('Método HTTP', d.metodo_http)}
                ${campo('URL', d.url, true)}
                ${campo('Descrição', d.descricao, true)}
                <div class="hr-detail hr-detail-full">
                    <span>Dados anteriores</span>
                    <pre class="hr-json">${esc(d.dados_anteriores)}</pre>
                </div>
                <div class="hr-detail hr-detail-full">
                    <span>Dados novos</span>
                    <pre class="hr-json">${esc(d.dados_novos)}</pre>
                </div>
            </div>`;

            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    const close = () => {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    };

    modal.querySelectorAll('[data-close-modal]').forEach(el => el.addEventListener('click', close));
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.classList.contains('open')) close();
    });
})();