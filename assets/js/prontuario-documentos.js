document.addEventListener('DOMContentLoaded', function () {
    const page=document.querySelector('.docs-page');
    if(!page)return;

    const aid=Number(page.dataset.atendimentoId||0);
    const did=Number(page.dataset.documentoId||0);
    const tipo=page.dataset.tipoDocumento||'RECEITUARIO';
    const apiUrl='/safe-health/modules/prontuario/api/documentos.php';
    const feedback=document.getElementById('docFeedback');

    async function api(payload){
        const r=await fetch(apiUrl,{
            method:'POST',
            headers:{'Content-Type':'application/json','Accept':'application/json'},
            body:JSON.stringify(payload)
        });
        const x=await r.json();
        if(!r.ok||!x.sucesso)throw new Error(x.mensagem||'Falha na operação.');
        return x;
    }

    function meds(){
        return Array.from(document.querySelectorAll('.docs-med-item')).map(el=>({
            medicamento_id:Number(el.dataset.medicamentoId||0),
            nome:el.dataset.nome||'',
            posologia:el.querySelector('.doc-med-posologia')?.value.trim()||'',
            orientacao:el.querySelector('.doc-med-orientacao')?.value.trim()||''
        }));
    }

    function conteudo(){
        if(tipo==='RECEITUARIO')return{
            medicamentos:meds(),
            orientacoes_gerais:document.getElementById('docOrientacoesGerais')?.value.trim()||''
        };
        if(tipo==='ATESTADO')return{
            dias_afastamento:document.getElementById('docDiasAfastamento')?.value||'',
            data_inicio:document.getElementById('docDataInicio')?.value||'',
            texto:document.getElementById('docTextoAtestado')?.value.trim()||''
        };
        if(tipo==='DECLARACAO')return{
            hora_entrada:document.getElementById('docHoraEntrada')?.value||'',
            hora_saida:document.getElementById('docHoraSaida')?.value||'',
            texto:document.getElementById('docTextoDeclaracao')?.value.trim()||''
        };
        if(tipo==='RELATORIO')return{
            titulo:document.getElementById('docTituloRelatorio')?.value.trim()||'',
            texto:document.getElementById('docTextoRelatorio')?.value.trim()||''
        };
        const especialidadeSelect =
            document.getElementById('docEspecialidadeDestino');
        const especialidadeOption =
            especialidadeSelect?.options[
                especialidadeSelect.selectedIndex
            ];

        return{
            especialidade_id:
                Number(especialidadeSelect?.value || 0),
            destino:
                especialidadeOption?.dataset.nome || '',
            prioridade:
                document.getElementById('docPrioridade')?.value
                || 'ROTINA',
            justificativa:
                document.getElementById('docJustificativa')
                    ?.value.trim() || ''
        };
    }

    function url(id=0){
        return '/safe-health/modules/prontuario/index.php?aba=documentos'
            +'&atendimento_id='+encodeURIComponent(aid)
            +'&tipo_documento='+encodeURIComponent(tipo)
            +(id?'&documento_id='+encodeURIComponent(id):'');
    }

    document.querySelectorAll('#btnNovoDocumento').forEach(btn=>{
        btn.addEventListener('click',async()=>{
            btn.disabled=true;
            try{
                const x=await api({acao:'novo',atendimento_id:aid,tipo});
                location.href=url(Number(x.dados.documento_id));
            }catch(e){alert(e.message);btn.disabled=false;}
        });
    });

    document.getElementById('btnSalvarDocumento')?.addEventListener('click',async function(){
        this.disabled=true;
        try{
            const x=await api({acao:'salvar',atendimento_id:aid,documento_id:did,tipo,conteudo:conteudo()});
            if(feedback){feedback.textContent=x.mensagem;feedback.className='allergy-form-feedback success';feedback.hidden=false;}
        }catch(e){
            if(feedback){feedback.textContent=e.message;feedback.className='allergy-form-feedback error';feedback.hidden=false;}
        }finally{this.disabled=false;}
    });

    document.getElementById('btnExcluirDocumento')?.addEventListener('click',async function(){
        if(!confirm('Excluir este documento em rascunho?'))return;
        this.disabled=true;
        try{
            const x=await api({acao:'excluir',atendimento_id:aid,documento_id:did,tipo});
            alert(x.mensagem);location.href=url();
        }catch(e){alert(e.message);this.disabled=false;}
    });

    document.getElementById('btnAssinarDocumento')?.addEventListener('click',async function(){
        if(!confirm('Fechar e assinar este documento?\n\nApós a assinatura ele não poderá ser editado diretamente.'))return;
        this.disabled=true;
        try{
            const x=await api({acao:'finalizar',atendimento_id:aid,documento_id:did,tipo,conteudo:conteudo()});
            alert(x.mensagem);location.href=url();
        }catch(e){alert(e.message);this.disabled=false;}
    });

    const busca=document.getElementById('docMedBusca');
    const resultados=document.getElementById('docMedResultados');
    const lista=document.getElementById('docMedicamentos');
    let timer;

    function esc(v){const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML;}
    function bindRemove(){
        document.querySelectorAll('.docs-med-remove').forEach(b=>{
            b.onclick=()=>b.closest('.docs-med-item')?.remove();
        });
    }
    function addMed(m){
        if(document.querySelector('.docs-med-item[data-medicamento-id="'+Number(m.id)+'"]'))return;
        const el=document.createElement('div');
        el.className='docs-med-item';
        el.dataset.medicamentoId=String(m.id);
        el.dataset.nome=m.nome;
        el.innerHTML='<div class="docs-med-title"><strong>'+esc(m.nome)+'</strong>'
            +'<button type="button" class="docs-med-remove"><i class="fa-solid fa-xmark"></i></button></div>'
            +'<div class="docs-med-fields"><label>Posologia<input type="text" class="doc-med-posologia" maxlength="500" placeholder="Ex.: Tomar 1 comprimido a cada 8 horas por 5 dias"></label>'
            +'<label>Orientação<input type="text" class="doc-med-orientacao" maxlength="500" placeholder="Ex.: Tomar após as refeições"></label></div>';
        lista?.appendChild(el);bindRemove();
    }

    busca?.addEventListener('input',()=>{
        clearTimeout(timer);
        const q=busca.value.trim();
        if(q.length<2){if(resultados)resultados.hidden=true;return;}
        timer=setTimeout(async()=>{
            try{
                const r=await fetch('/safe-health/modules/prontuario/api/receituario-medicamentos.php?q='+encodeURIComponent(q));
                const x=await r.json();
                resultados.innerHTML='';
                x.dados.forEach(m=>{
                    const b=document.createElement('button');b.type='button';b.className='docs-med-result';
                    b.innerHTML='<strong>'+esc(m.nome)+'</strong><span>'+esc([m.concentracao,m.apresentacao,m.principio_ativo].filter(Boolean).join(' • '))+'</span>';
                    b.onclick=()=>{addMed(m);busca.value='';resultados.hidden=true;};
                    resultados.appendChild(b);
                });
                if(!x.dados.length)resultados.innerHTML='<div class="cid-empty">Nenhum medicamento encontrado.</div>';
                resultados.hidden=false;
            }catch(e){console.error(e);}
        },250);
    });
    bindRemove();
});
