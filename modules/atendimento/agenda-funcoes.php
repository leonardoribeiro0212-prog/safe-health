<?php
function horariosAgenda(PDO $pdo,int $medico,string $dia,int $ignorar=0): array {
 $d=DateTimeImmutable::createFromFormat('!Y-m-d',$dia); if(!$d||$d->format('Y-m-d')!==$dia)return [];
 $s=$pdo->prepare('SELECT * FROM agenda_medica WHERE medico_id=? AND dia_semana=? AND ativa=1');$s->execute([$medico,$d->format('N')]);$a=$s->fetch(PDO::FETCH_ASSOC);if(!$a)return [];
 $s=$pdo->prepare('SELECT 1 FROM agenda_bloqueios WHERE medico_id=? AND data_inicio<=? AND data_fim>=?');$s->execute([$medico,$dia,$dia]);if($s->fetchColumn())return [];
 $s=$pdo->prepare('SELECT horario FROM agendamentos_consulta WHERE medico_id=? AND DATE(horario)=? AND ocupado IS NOT NULL AND id<>?');$s->execute([$medico,$dia,$ignorar]);$ocupados=$s->fetchAll(PDO::FETCH_COLUMN);
 $out=[];$inicio=new DateTimeImmutable($dia.' '.$a['inicio']);$fim=new DateTimeImmutable($dia.' '.$a['fim']);
 for($t=$inicio;$t->modify('+30 minutes')<=$fim;$t=$t->modify('+30 minutes')){ $h=$t->format('H:i:s');$end=$t->modify('+30 minutes')->format('H:i:s');if($a['almoco_inicio']&&$h<$a['almoco_fim']&&$end>$a['almoco_inicio'])continue;if($t<=new DateTimeImmutable()||in_array($t->format('Y-m-d H:i:s'),$ocupados,true))continue;$out[]=$t->format('H:i');}return $out;
}
