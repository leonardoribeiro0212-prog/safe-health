<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/permissoes.php';
$tituloPagina = 'Alteração de Atendimento';
$codigoTela = 'atendimento.alteracao';
exigirPermissao($pdo,$codigoTela);
function aa($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function aaNorm(string $v): string { return strtoupper(strtr($v,['Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ú'=>'U','Ç'=>'C','á'=>'A','à'=>'A','â'=>'A','ã'=>'A','é'=>'E','ê'=>'E','í'=>'I','ó'=>'O','ô'=>'O','õ'=>'O','ú'=>'U','ç'=>'C'])); }
function aaEditavel(array $a): bool { return in_array($a['status'],['RECEPCAO','AGUARDANDO_TRIAGEM','AGUARDANDO_MEDICO'],true) && !$a['data_alta']; }
$tipos=['URGENCIA'=>'Urgência','AMBULATORIO'=>'Ambulatório','INTERNACAO'=>'Internação','EXTERNO'=>'Externo'];
$origens=['CLINICA_MEDICA','CLINICA_CIRURGICA','CENTRO_CIRURGICO','CONSULTORIO','SALA_DE_PROCEDIMENTO','PRONTO_SOCORRO_ADULTO','PRONTO_SOCORRO_INFANTIL','PRONTO_SOCORRO_ORTOPEDIA','AMBULATORIO','UNIDADE_DE_INTERNACAO','UTI','EXAMES','EXTERNO'];
$medicos=$pdo->query('SELECT m.id,m.nome,m.crm,m.uf_crm,e.nome AS especialidade FROM medicos m JOIN especialidades e ON e.id=m.especialidade_id WHERE m.ativo=1 AND e.ativa=1 ORDER BY m.nome')->fetchAll(PDO::FETCH_ASSOC);
$convenios=$pdo->query('SELECT id,nome FROM convenios WHERE ativo=1 ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
$_SESSION['aa_csrf'] ??= bin2hex(random_bytes(32));
$id=(int)($_POST['id']??$_GET['id']??0); $erro=''; $ok='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  if (!hash_equals($_SESSION['aa_csrf'],(string)($_POST['csrf']??''))) throw new DomainException('Sessão do formulário inválida. Recarregue a página.');
  $motivo=trim((string)($_POST['motivo']??''));
  if (mb_strlen($motivo)<5 || mb_strlen($motivo)>400) throw new DomainException('Informe uma justificativa de 5 a 400 caracteres.');
  $pdo->beginTransaction();
  $s=$pdo->prepare('SELECT * FROM atendimentos WHERE id=? FOR UPDATE'); $s->execute([$id]); $a=$s->fetch(PDO::FETCH_ASSOC);
  if (!$a || !aaEditavel($a)) throw new DomainException('Atendimento indisponível para alteração.');
  $campos=['tipo_atendimento','origem','convenio','profissional_id','profissional_responsavel','especialidade'];
  $antes=array_intersect_key($a,array_flip($campos));
  if (!hash_equals(hash('sha256',json_encode($antes)),(string)($_POST['versao']??''))) throw new DomainException('O atendimento mudou. Confira os dados e tente novamente.');
  $tipo=(string)($_POST['tipo']??''); $origem=(string)($_POST['origem']??'');
  if (!isset($tipos[$tipo]) || !in_array($origem,$origens,true)) throw new DomainException('Tipo ou origem inválidos.');
  // Não mudar o fluxo assistencial de uma senha já vinculada.
  if ($a['senha_id'] && $tipo!==$a['tipo_atendimento']) throw new DomainException('Não é possível mudar o tipo de um atendimento vinculado a uma senha.');
  $mid=(int)($_POST['medico']??0); $m=null; foreach($medicos as $x) if ((int)$x['id']===$mid) $m=$x;
  if (!$m) throw new DomainException('Selecione um médico ativo.');
  $cid=(int)($_POST['convenio']??0); $c=null; foreach($convenios as $x) if ((int)$x['id']===$cid) $c=$x;
  if (!$c && $cid!==0) throw new DomainException('Convênio inválido.');
  $nome=$c ? aaNorm($c['nome']) : (string)$a['convenio'];
  if (mb_strlen($nome)>100 || mb_strlen(aaNorm($m['especialidade']))>100) throw new DomainException('Nome do cadastro excede o limite do atendimento.');
  $depois=['tipo_atendimento'=>$tipo,'origem'=>$origem,'convenio'=>$nome,'profissional_id'=>$mid,'profissional_responsavel'=>$m['nome'],'especialidade'=>aaNorm($m['especialidade'])];
  if ($nome!==$a['convenio']) {
   $s=$pdo->prepare('SELECT id,status FROM contas_hospitalares WHERE atendimento_id=? FOR UPDATE'); $s->execute([$id]);
   foreach($s->fetchAll(PDO::FETCH_ASSOC) as $conta) {
    if ($conta['status']!=='ABERTA') throw new DomainException('A conta não está aberta.');
    $q=$pdo->prepare('SELECT (SELECT COUNT(*) FROM conta_hospitalar_itens WHERE conta_hospitalar_id=?) + (SELECT COUNT(*) FROM itens_conta_hospitalar WHERE conta_id=?)'); $q->execute([$conta['id'],$conta['id']]);
    if ((int)$q->fetchColumn()>0) throw new DomainException('A conta possui lançamentos. Solicite a revisão do faturamento.');
    $q=$pdo->prepare('UPDATE contas_hospitalares SET convenio_id=? WHERE id=?'); $q->execute([$cid,$conta['id']]);
   }
  }
  $s=$pdo->prepare('UPDATE atendimentos SET tipo_atendimento=?,origem=?,convenio=?,profissional_id=?,profissional_responsavel=?,especialidade=? WHERE id=?'); $s->execute([...array_values($depois),$id]);
  $s=$pdo->prepare("INSERT INTO auditoria_sistema (usuario_id,acao,modulo,tela,entidade,registro_id,descricao,ip,metodo_http,url,dados_anteriores,dados_novos) VALUES (?,'ALTERACAO_ATENDIMENTO','Atendimento','Alteração de Atendimento','atendimentos',?,?,?,'POST',?,?,?)");
  $s->execute([(int)$_SESSION['usuario_id'],(string)$id,'Motivo: '.$motivo,$_SERVER['REMOTE_ADDR']??null,$_SERVER['SCRIPT_NAME'],json_encode($antes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($depois,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
  $pdo->commit(); $_SESSION['aa_ok']='Atendimento alterado com sucesso.'; header('Location: alteracao-atendimento.php?id='.$id); exit;
 } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); $erro=$e instanceof DomainException?$e->getMessage():'Não foi possível salvar. Nenhuma alteração foi confirmada.'; if(!($e instanceof DomainException)) error_log($e->getMessage()); }
}
$ok=$_SESSION['aa_ok']??''; unset($_SESSION['aa_ok']);
$a=null; if($id>0){$s=$pdo->prepare('SELECT a.*,p.nome AS paciente FROM atendimentos a JOIN pacientes p ON p.id=a.paciente_id WHERE a.id=?');$s->execute([$id]);$a=$s->fetch(PDO::FETCH_ASSOC);}
$q=trim((string)($_GET['q']??'')); $lista=[];
if($q!==''){ $s=$pdo->prepare('SELECT a.id,a.numero_atendimento,a.status,p.nome FROM atendimentos a JOIN pacientes p ON p.id=a.paciente_id WHERE a.numero_atendimento=? OR p.cpf=? OR p.nome LIKE ? ORDER BY a.id DESC LIMIT 50');$s->execute([$q,preg_replace('/\D/','',$q),'%'.$q.'%']);$lista=$s->fetchAll(PDO::FETCH_ASSOC); }
require __DIR__.'/../../includes/header.php'; require __DIR__.'/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/abrir-atendimento.css">
<style>
.aa-page .aa-search{display:flex;align-items:flex-end;gap:16px}.aa-page .aa-search .form-field{flex:1;min-width:0}.aa-page .aa-search button{flex-shrink:0}.aa-page .aa-summary{margin-bottom:24px}.aa-page .aa-section{padding-top:20px;border-top:1px solid #e2e8f0}.aa-page .aa-wide{grid-column:1/-1}.aa-page .aa-reason{margin-top:22px}.aa-page textarea{width:100%;box-sizing:border-box;min-height:100px;padding:12px 14px;border:1px solid #cbd5e1;border-radius:11px;font:inherit;color:#0f172a;background:#fff;resize:vertical;outline:none}.aa-page textarea:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}.aa-page .aa-reason small{color:#64748b;font-size:.82rem}.aa-page .aa-required{color:#dc2626}.aa-page .aa-status{display:inline-block;background:#eff6ff;color:#1d4ed8;border-radius:20px;padding:5px 10px;font-size:.85rem}.aa-page .form-actions{margin-top:24px;padding-top:18px;border-top:1px solid #e2e8f0}.aa-page .create-attendance-button{display:inline-flex;align-items:center;justify-content:center;gap:8px}.aa-page .form-card>h2{font-size:1.15rem;margin-bottom:14px}.aa-page .form-card>p{padding:10px 0;border-bottom:1px solid #f1f5f9}.aa-page .form-card>p:last-child{border:0}.aa-page .form-card a{color:#2563eb;font-weight:600;text-decoration:none}.aa-page .form-card a:hover{text-decoration:underline}@media(max-width:700px){.aa-page .aa-search{flex-direction:column;align-items:stretch}.aa-page .aa-search .form-field{width:100%}.aa-page .patient-grid,.aa-page .form-grid{grid-template-columns:1fr}.aa-page .form-actions button{width:100%}}
</style><div class="app-content"><?php require __DIR__.'/../../includes/topbar.php'; ?><main class="main-content"><section class="opening-page aa-page">
<div class="page-heading"><div><span class="page-eyebrow">Atendimento</span><h2>Alteração de Atendimento</h2><p>Corrija informações administrativas antes do atendimento médico.</p></div></div>
<?php if($erro): ?><p role="alert" style="color:#b91c1c"><?=aa($erro)?></p><?php endif; ?>
<?php if($ok): ?><p role="status" style="color:#15803d"><?=aa($ok)?></p><?php endif; ?>
<form method="get" class="form-card"><div class="card-title"><div class="card-title-icon"><i class="fa-solid fa-magnifying-glass"></i></div><div><span>Localização</span><h3>Pesquisar atendimento</h3></div></div><div class="aa-search"><div class="form-field"><label for="q">Número do atendimento, CPF ou nome</label><input id="q" name="q" value="<?=aa($q)?>" required></div><button class="create-attendance-button"><i class="fa-solid fa-magnifying-glass"></i> Pesquisar</button></div></form>
<?php if($q!==''): ?><section class="form-card"><h2>Resultados</h2><?php if(!$lista): ?><p>Nenhum atendimento encontrado.</p><?php endif; ?><?php foreach($lista as $r): ?><p><a href="?id=<?=(int)$r['id']?>"><?=aa($r['numero_atendimento'])?> — <?=aa($r['nome'])?></a> · <?=aa($r['status'])?></p><?php endforeach; ?></section><?php endif; ?>
<?php if($a): ?><section class="form-card"><div class="card-title"><div class="card-title-icon"><i class="fa-solid fa-clipboard-list"></i></div><div><span>Atendimento selecionado</span><h3><?=aa($a['paciente'])?></h3></div></div><div class="patient-grid aa-summary"><div class="info-field"><span>Número do atendimento</span><strong><?=aa($a['numero_atendimento'])?></strong></div><div class="info-field"><span>Situação</span><strong class="aa-status"><?=aa(ucfirst(strtolower(str_replace('_',' ',$a['status']))))?></strong></div><div class="info-field"><span>Data de entrada</span><strong><?=aa(date('d/m/Y H:i',strtotime($a['data_entrada'])))?></strong></div></div>
<?php if(!aaEditavel($a)): ?><p>Este atendimento não pode ser alterado nesta tela.</p><?php else: ?>
<form method="post"><input type="hidden" name="id" value="<?=(int)$a['id']?>"><input type="hidden" name="csrf" value="<?=aa($_SESSION['aa_csrf'])?>"><input type="hidden" name="versao" value="<?=aa(hash('sha256',json_encode(array_intersect_key($a,array_flip(['tipo_atendimento','origem','convenio','profissional_id','profissional_responsavel','especialidade'])))))?>">
<div class="section-heading aa-section"><span>Informações administrativas</span><h3>Dados do atendimento</h3></div><div class="form-grid"><div class="form-field"><label>Tipo</label><select name="tipo"><?php foreach($tipos as $k=>$v): ?><option value="<?=aa($k)?>" <?=$k===$a['tipo_atendimento']?'selected':''?>><?=aa($v)?></option><?php endforeach; ?></select></div>
<div class="form-field"><label>Origem</label><select name="origem"><?php foreach(array_unique(array_merge([$a['origem']],$origens)) as $v): ?><option value="<?=aa($v)?>" <?=$v===$a['origem']?'selected':''?>><?=aa($v)?></option><?php endforeach; ?></select></div>
<div class="form-field"><label>Convênio</label><select name="convenio"><option value="0">Manter: <?=aa($a['convenio'])?></option><?php foreach($convenios as $c): ?><option value="<?=(int)$c['id']?>"><?=aa($c['nome'])?></option><?php endforeach; ?></select></div>
<div class="form-field aa-wide"><label>Médico e especialidade</label><select name="medico" required><option value="">Selecione</option><?php foreach($medicos as $m): ?><option value="<?=(int)$m['id']?>" <?=(int)$m['id']===(int)$a['profissional_id']?'selected':''?>><?=aa($m['nome'].' — '.$m['especialidade'].' (CRM '.$m['crm'].'/'.$m['uf_crm'].')')?></option><?php endforeach; ?></select></div></div>
<div class="form-field aa-reason"><label for="motivo">Motivo da alteração <span class="aa-required">*</span></label><textarea id="motivo" name="motivo" required minlength="5" maxlength="400" rows="3" placeholder="Descreva o motivo da correção..."></textarea><small>Obrigatório: de 5 a 400 caracteres. A justificativa será registrada na auditoria.</small></div><div class="form-actions"><button class="create-attendance-button"><i class="fa-solid fa-floppy-disk"></i> Salvar alteração</button></div></form><?php endif; ?></section><?php elseif($id): ?><p>Atendimento não encontrado.</p><?php endif; ?>
</section></main></div><?php require __DIR__.'/../../includes/footer.php'; ?>
