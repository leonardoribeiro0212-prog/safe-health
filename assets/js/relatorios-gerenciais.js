(() => {
    const escapeHtml = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const money = (v) => Number(v || 0).toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
    const palette = ['#2f7f91','#55a0ae','#7bb8c2','#9bcbd2','#b8dce1','#d3e9ec','#7c9ca4','#52737d'];

    document.querySelectorAll('.rg-chart').forEach(el => {
        let data = [];
        try { data = JSON.parse(el.dataset.values || '[]'); } catch (_) {}
        if (!data.length) {
            el.innerHTML = '<div class="rg-empty-chart">Sem dados para o período selecionado.</div>';
            return;
        }

        const type = el.dataset.chart;
        if (type === 'bars' || type === 'money-bars') {
            const max = Math.max(...data.map(x => Number(x.total || 0)), 1);
            el.innerHTML = '<div class="rg-bars">' + data.map(x => {
                const val = Number(x.total || 0);
                const pct = Math.max(2, (val / max) * 100);
                return `<div class="rg-bar-row">
                    <span class="rg-bar-label" title="${escapeHtml(x.rotulo)}">${escapeHtml(x.rotulo)}</span>
                    <div class="rg-bar-track"><div class="rg-bar-fill" style="width:${pct}%"></div></div>
                    <strong class="rg-bar-value">${type === 'money-bars' ? money(val) : val.toLocaleString('pt-BR')}</strong>
                </div>`;
            }).join('') + '</div>';
            return;
        }

        if (type === 'donut') {
            const total = data.reduce((s,x) => s + Number(x.total || 0), 0);
            let acc = 0;
            const stops = data.map((x,i) => {
                const start = total ? (acc / total) * 100 : 0;
                acc += Number(x.total || 0);
                const end = total ? (acc / total) * 100 : 0;
                return `${palette[i % palette.length]} ${start}% ${end}%`;
            });
            el.innerHTML = `<div class="rg-donut-wrap">
                <div class="rg-donut" style="background:conic-gradient(${stops.join(',')})">
                    <div class="rg-donut-center">${total.toLocaleString('pt-BR')}</div>
                </div>
                <div class="rg-legend">${data.map((x,i)=>`<div><i style="background:${palette[i%palette.length]}"></i><span>${escapeHtml(String(x.rotulo).replaceAll('_',' '))}: <b>${Number(x.total).toLocaleString('pt-BR')}</b></span></div>`).join('')}</div>
            </div>`;
            return;
        }

        if (type === 'line') {
            const width = 720, height = 220, padX = 35, padY = 22;
            const vals = data.map(x => Number(x.total || 0));
            const max = Math.max(...vals, 1);
            const step = data.length > 1 ? (width - padX*2)/(data.length-1) : 0;
            const points = data.map((x,i) => ({
                x: padX + i*step,
                y: height-padY-(Number(x.total||0)/max)*(height-padY*2),
                label: String(x.rotulo || '').slice(5).split('-').reverse().join('/'),
                value: Number(x.total||0)
            }));
            const line = points.map(p => `${p.x},${p.y}`).join(' ');
            const area = `${padX},${height-padY} ${line} ${points.at(-1).x},${height-padY}`;
            const labelsEvery = Math.max(1, Math.ceil(points.length/8));
            el.innerHTML = `<svg class="rg-line-svg" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none">
                ${[0,.25,.5,.75,1].map(f=>`<line class="rg-line-grid" x1="${padX}" x2="${width-padX}" y1="${padY+f*(height-padY*2)}" y2="${padY+f*(height-padY*2)}"/>`).join('')}
                <polygon class="rg-line-area" points="${area}"></polygon>
                <polyline class="rg-line-path" points="${line}"></polyline>
                ${points.map(p=>`<circle class="rg-line-dot" cx="${p.x}" cy="${p.y}" r="4"><title>${escapeHtml(p.label)}: ${p.value}</title></circle>`).join('')}
                ${points.map((p,i)=> i%labelsEvery===0 ? `<text class="rg-line-label" x="${p.x}" y="${height-4}" text-anchor="middle">${escapeHtml(p.label)}</text>` : '').join('')}
            </svg>`;
        }
    });
})();