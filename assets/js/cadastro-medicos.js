document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('formMedico');
    const pesquisa = document.getElementById('pesquisaMedico');
    const resultados = document.getElementById('resultadosPesquisa');
    const medicoId = document.getElementById('medicoId');
    const modo = document.getElementById('modoCadastro');
    const msg = document.getElementById('mensagemMedico');
    const btnSalvar = document.getElementById('botaoSalvarMedico');
    const textoSalvar = document.getElementById('textoBotaoSalvar');

    const campos = {
        nome: document.getElementById('nome'),
        cpf: document.getElementById('cpf'),
        crm: document.getElementById('crm'),
        uf_crm: document.getElementById('ufCrm'),
        especialidade_id: document.getElementById('especialidade'),
        telefone: document.getElementById('telefone'),
        email: document.getElementById('email'),
        usuario_id: document.getElementById('usuarioId'),
        ativo: document.getElementById('ativo')
    };

    function numeros(v){ return String(v ?? '').replace(/\D/g,''); }
    function cpf(v){
        const n=numeros(v).slice(0,11);
        return n.replace(/^(\d{3})(\d)/,'$1.$2')
                .replace(/^(\d{3})\.(\d{3})(\d)/,'$1.$2.$3')
                .replace(/\.(\d{3})(\d)/,'.$1-$2');
    }
    function esc(v){
        return String(v ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;')
        .replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
    }
    function mensagem(t,tipo='success'){
        msg.hidden=false; msg.className=`doctor-message ${tipo}`; msg.textContent=t;
    }
    function modoEdicao(ativo){
        modo.textContent=ativo?'Alterando cadastro':'Novo cadastro';
        modo.classList.toggle('editing',ativo);
        textoSalvar.textContent=ativo?'Salvar alterações':'Salvar médico';
    }
    function novo(){
        form.reset(); medicoId.value=''; resultados.hidden=true; resultados.innerHTML='';
        modoEdicao(false); msg.hidden=true; campos.ativo.value='1'; campos.nome.focus();
    }
    async function json(resp){
        const texto=await resp.text();
        try{return JSON.parse(texto);}catch{throw new Error('Resposta inválida do servidor.');}
    }
    async function pesquisar(){
        const q=pesquisa.value.trim();
        if(!q){mensagem('Informe nome, CPF ou CRM para pesquisar.','error');return;}
        try{
            const r=await fetch(`/safe-health/modules/cadastros/api/medicos.php?acao=pesquisar&q=${encodeURIComponent(q)}`,{cache:'no-store'});
            const x=await json(r);
            if(!r.ok||!x.sucesso)throw new Error(x.mensagem);
            const lista=x.dados.medicos||[];
            resultados.hidden=false;
            resultados.innerHTML=lista.length?lista.map(m=>`
                <button type="button" class="doctor-result-item" data-id="${Number(m.id)}">
                    <div><strong>${esc(m.nome)}</strong><span>CRM ${esc(m.crm)}/${esc(m.uf_crm)} · ${esc(m.especialidade_nome)}</span></div>
                    <span class="doctor-result-status ${Number(m.ativo)===1?'active':'inactive'}">${Number(m.ativo)===1?'Ativo':'Inativo'}</span>
                </button>`).join(''):'<div class="doctor-result-empty">Nenhum médico encontrado.</div>';
        }catch(e){mensagem(e.message,'error');}
    }
    async function carregar(id){
        try{
            const r=await fetch(`/safe-health/modules/cadastros/api/medicos.php?acao=buscar&id=${id}`,{cache:'no-store'});
            const x=await json(r);
            if(!r.ok||!x.sucesso)throw new Error(x.mensagem);
            const m=x.dados.medico;
            medicoId.value=m.id;
            campos.nome.value=m.nome||'';
            campos.cpf.value=cpf(m.cpf||'');
            campos.crm.value=m.crm||'';
            campos.uf_crm.value=m.uf_crm||'';
            campos.especialidade_id.value=m.especialidade_id||'';
            campos.telefone.value=m.telefone||'';
            campos.email.value=m.email||'';
            campos.usuario_id.value=m.usuario_id||'';
            campos.ativo.value=String(Number(m.ativo));
            modoEdicao(true); resultados.hidden=true;
            mensagem(`Cadastro de ${m.nome} carregado para alteração.`);
        }catch(e){mensagem(e.message,'error');}
    }

    document.getElementById('botaoPesquisarMedico').addEventListener('click',pesquisar);
    document.getElementById('botaoNovoMedico').addEventListener('click',novo);
    document.getElementById('botaoLimpar').addEventListener('click',novo);
    pesquisa.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();pesquisar();}});
    resultados.addEventListener('click',e=>{const b=e.target.closest('.doctor-result-item');if(b)carregar(Number(b.dataset.id));});
    campos.cpf.addEventListener('input',()=>campos.cpf.value=cpf(campos.cpf.value));

    form.addEventListener('submit',async e=>{
        e.preventDefault();
        if(!form.reportValidity())return;
        btnSalvar.disabled=true;
        const dados=Object.fromEntries(new FormData(form).entries());
        dados.medico_id=Number(medicoId.value||0);
        try{
            const r=await fetch('/safe-health/modules/cadastros/api/medicos.php',{
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json'},
                body:JSON.stringify(dados)
            });
            const x=await json(r);
            if(!r.ok||!x.sucesso)throw new Error(x.mensagem);
            medicoId.value=x.dados.medico_id;
            modoEdicao(true);
            mensagem(x.mensagem);
        }catch(err){mensagem(err.message,'error');}
        finally{btnSalvar.disabled=false;}
    });
});
