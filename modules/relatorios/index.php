<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissoes.php';

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);

if ($usuarioId <= 0) {
    header('Location: /safe-health/login.php');
    exit;
}

function rgDataValida(?string $data): bool
{
    if (!$data) return false;
    $d = DateTime::createFromFormat('Y-m-d', $data);
    return $d && $d->format('Y-m-d') === $data;
}

function rgMoeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function rgNumero(int|float $valor, int $casas = 0): string
{
    return number_format((float) $valor, $casas, ',', '.');
}

function rgQuery(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function rgIn(array $valores, string $prefixo, array &$params): string
{
    $partes = [];
    foreach (array_values($valores) as $i => $valor) {
        $chave = ':' . $prefixo . $i;
        $partes[] = $chave;
        $params[$chave] = $valor;
    }
    return implode(',', $partes);
}

$hoje = new DateTimeImmutable('today');
$dataFim = rgDataValida($_GET['data_fim'] ?? null)
    ? new DateTimeImmutable($_GET['data_fim'])
    : $hoje;
$dataInicio = rgDataValida($_GET['data_inicio'] ?? null)
    ? new DateTimeImmutable($_GET['data_inicio'])
    : $dataFim->modify('first day of this month');

if ($dataInicio > $dataFim) {
    [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
}

$tipo = trim((string) ($_GET['tipo'] ?? ''));
$convenio = trim((string) ($_GET['convenio'] ?? ''));
$especialidade = trim((string) ($_GET['especialidade'] ?? ''));

$paramsAt = [
    ':inicio' => $dataInicio->format('Y-m-d') . ' 00:00:00',
    ':fim' => $dataFim->format('Y-m-d') . ' 23:59:59',
];

$whereAt = ['a.data_entrada BETWEEN :inicio AND :fim'];

if ($tipo !== '') {
    $whereAt[] = 'a.tipo_atendimento = :tipo';
    $paramsAt[':tipo'] = $tipo;
}
if ($convenio !== '') {
    $whereAt[] = 'a.convenio = :convenio';
    $paramsAt[':convenio'] = $convenio;
}
if ($especialidade !== '') {
    $whereAt[] = 'a.especialidade = :especialidade';
    $paramsAt[':especialidade'] = $especialidade;
}

$whereSql = implode(' AND ', $whereAt);

/* Filtros */
$tipos = $pdo->query("
    SELECT DISTINCT tipo_atendimento
    FROM atendimentos
    WHERE tipo_atendimento IS NOT NULL AND tipo_atendimento <> ''
    ORDER BY tipo_atendimento
")->fetchAll(PDO::FETCH_COLUMN);

$convenios = $pdo->query("
    SELECT DISTINCT convenio
    FROM atendimentos
    WHERE convenio IS NOT NULL AND convenio <> ''
    ORDER BY convenio
")->fetchAll(PDO::FETCH_COLUMN);

$especialidades = $pdo->query("
    SELECT DISTINCT especialidade
    FROM atendimentos
    WHERE especialidade IS NOT NULL AND especialidade <> ''
    ORDER BY especialidade
")->fetchAll(PDO::FETCH_COLUMN);

/* Indicadores de atendimento */
$resumoAt = rgQuery($pdo, "
    SELECT
        COUNT(*) AS atendimentos,
        COUNT(DISTINCT a.paciente_id) AS pacientes,
        SUM(CASE WHEN a.status = 'FINALIZADO' THEN 1 ELSE 0 END) AS finalizados,
        AVG(
            CASE
                WHEN a.data_alta IS NOT NULL
                THEN TIMESTAMPDIFF(MINUTE, a.data_entrada, a.data_alta)
                ELSE NULL
            END
        ) AS tempo_medio_min
    FROM atendimentos a
    WHERE {$whereSql}
", $paramsAt)->fetch(PDO::FETCH_ASSOC) ?: [];

$totalAtendimentos = (int) ($resumoAt['atendimentos'] ?? 0);
$totalPacientes = (int) ($resumoAt['pacientes'] ?? 0);
$totalFinalizados = (int) ($resumoAt['finalizados'] ?? 0);
$tempoMedioMin = (float) ($resumoAt['tempo_medio_min'] ?? 0);

if ($tempoMedioMin >= 60) {
    $horas = intdiv((int) round($tempoMedioMin), 60);
    $minutos = (int) round($tempoMedioMin) % 60;
    $tempoMedioTexto = $horas . 'h ' . str_pad((string) $minutos, 2, '0', STR_PAD_LEFT) . 'min';
} elseif ($tempoMedioMin > 0) {
    $tempoMedioTexto = (int) round($tempoMedioMin) . ' min';
} else {
    $tempoMedioTexto = '—';
}

/* IDs de atendimentos filtrados: usados para integrar os demais módulos */
$idsAtendimentos = rgQuery(
    $pdo,
    "SELECT a.id FROM atendimentos a WHERE {$whereSql}",
    $paramsAt
)->fetchAll(PDO::FETCH_COLUMN);

$faturamento = 0.0;
$ticketMedio = 0.0;
$contasAbertas = 0;
$contasFechadas = 0;
$contasAuditoria = 0;
$itensPendentesPreco = 0;
$prescricoes = 0;
$medicamentosPrescritos = 0;
$examesLab = 0;
$examesImagem = 0;
$altas = 0;

if ($idsAtendimentos) {
    $paramsIds = [];
    $inIds = rgIn($idsAtendimentos, 'at', $paramsIds);

    $resumoFat = rgQuery($pdo, "
        SELECT
            COALESCE(SUM(ch.valor_liquido), 0) AS faturamento,
            SUM(CASE WHEN ch.status = 'ABERTA' THEN 1 ELSE 0 END) AS abertas,
            SUM(CASE WHEN ch.status IN ('FECHADA','FATURADA') THEN 1 ELSE 0 END) AS fechadas,
            SUM(CASE WHEN ch.status = 'EM_AUDITORIA' THEN 1 ELSE 0 END) AS auditoria
        FROM contas_hospitalares ch
        WHERE ch.atendimento_id IN ({$inIds})
    ", $paramsIds)->fetch(PDO::FETCH_ASSOC) ?: [];

    $faturamento = (float) ($resumoFat['faturamento'] ?? 0);
    $contasAbertas = (int) ($resumoFat['abertas'] ?? 0);
    $contasFechadas = (int) ($resumoFat['fechadas'] ?? 0);
    $contasAuditoria = (int) ($resumoFat['auditoria'] ?? 0);
    $ticketMedio = $totalAtendimentos > 0 ? $faturamento / $totalAtendimentos : 0;

    $itensPendentesPreco = (int) rgQuery($pdo, "
        SELECT COUNT(*)
        FROM itens_conta_hospitalar i
        INNER JOIN contas_hospitalares ch ON ch.id = i.conta_id
        WHERE ch.atendimento_id IN ({$inIds})
          AND (
              i.status_item = 'PENDENTE_PRECO'
              OR (i.valor_unitario = 0 AND i.valor_total = 0)
          )
    ", $paramsIds)->fetchColumn();

    $prescricoes = (int) rgQuery($pdo, "
        SELECT COUNT(*)
        FROM prescricoes_medicas pm
        WHERE pm.atendimento_id IN ({$inIds})
    ", $paramsIds)->fetchColumn();

    $medicamentosPrescritos = (int) rgQuery($pdo, "
        SELECT COUNT(*)
        FROM prescricao_medicamentos pmed
        INNER JOIN prescricoes_medicas pm ON pm.id = pmed.prescricao_id
        WHERE pm.atendimento_id IN ({$inIds})
    ", $paramsIds)->fetchColumn();

    $solicitacoes = rgQuery($pdo, "
        SELECT
            SUM(CASE WHEN ps.tipo_item = 'EXAME_LABORATORIAL' THEN 1 ELSE 0 END) AS lab,
            SUM(CASE WHEN ps.tipo_item = 'EXAME_IMAGEM' THEN 1 ELSE 0 END) AS imagem
        FROM prescricao_solicitacoes ps
        INNER JOIN prescricoes_medicas pm ON pm.id = ps.prescricao_id
        WHERE pm.atendimento_id IN ({$inIds})
    ", $paramsIds)->fetch(PDO::FETCH_ASSOC) ?: [];

    $examesLab = (int) ($solicitacoes['lab'] ?? 0);
    $examesImagem = (int) ($solicitacoes['imagem'] ?? 0);

    $altas = (int) rgQuery($pdo, "
        SELECT COUNT(*)
        FROM altas_medicas am
        WHERE am.atendimento_id IN ({$inIds})
          AND am.status = 'FINALIZADA'
    ", $paramsIds)->fetchColumn();
}

/* Séries dos gráficos */
$atendimentosPeriodo = rgQuery($pdo, "
    SELECT DATE(a.data_entrada) AS rotulo, COUNT(*) AS total
    FROM atendimentos a
    WHERE {$whereSql}
    GROUP BY DATE(a.data_entrada)
    ORDER BY DATE(a.data_entrada)
", $paramsAt)->fetchAll(PDO::FETCH_ASSOC);

$porTipo = rgQuery($pdo, "
    SELECT COALESCE(NULLIF(a.tipo_atendimento,''), 'NÃO INFORMADO') AS rotulo, COUNT(*) AS total
    FROM atendimentos a
    WHERE {$whereSql}
    GROUP BY a.tipo_atendimento
    ORDER BY total DESC
", $paramsAt)->fetchAll(PDO::FETCH_ASSOC);

$porEspecialidade = rgQuery($pdo, "
    SELECT COALESCE(NULLIF(a.especialidade,''), 'Não informada') AS rotulo, COUNT(*) AS total
    FROM atendimentos a
    WHERE {$whereSql}
    GROUP BY a.especialidade
    ORDER BY total DESC
    LIMIT 8
", $paramsAt)->fetchAll(PDO::FETCH_ASSOC);

$porConvenio = rgQuery($pdo, "
    SELECT COALESCE(NULLIF(a.convenio,''), 'Particular / não informado') AS rotulo, COUNT(*) AS total
    FROM atendimentos a
    WHERE {$whereSql}
    GROUP BY a.convenio
    ORDER BY total DESC
    LIMIT 8
", $paramsAt)->fetchAll(PDO::FETCH_ASSOC);

/* Faturamento por convênio */
$faturamentoConvenio = rgQuery($pdo, "
    SELECT
        COALESCE(NULLIF(a.convenio,''), 'Particular / não informado') AS rotulo,
        COALESCE(SUM(ch.valor_liquido),0) AS total
    FROM atendimentos a
    INNER JOIN contas_hospitalares ch ON ch.atendimento_id = a.id
    WHERE {$whereSql}
    GROUP BY a.convenio
    ORDER BY total DESC
    LIMIT 8
", $paramsAt)->fetchAll(PDO::FETCH_ASSOC);

/* Segurança e auditoria: período, independente dos filtros clínicos */
$paramsAudit = [
    ':audit_inicio' => $dataInicio->format('Y-m-d') . ' 00:00:00',
    ':audit_fim' => $dataFim->format('Y-m-d') . ' 23:59:59',
];

$resumoAudit = rgQuery($pdo, "
    SELECT
        COUNT(*) AS acoes,
        COUNT(DISTINCT usuario_id) AS usuarios,
        SUM(CASE WHEN modulo = 'Prontuário' OR modulo = 'PRONTUARIO' THEN 1 ELSE 0 END) AS prontuario
    FROM auditoria_sistema
    WHERE criado_em BETWEEN :audit_inicio AND :audit_fim
", $paramsAudit)->fetch(PDO::FETCH_ASSOC) ?: [];

$acoesAuditadas = (int) ($resumoAudit['acoes'] ?? 0);
$usuariosAuditados = (int) ($resumoAudit['usuarios'] ?? 0);
$acessosProntuario = (int) ($resumoAudit['prontuario'] ?? 0);

$usuariosAtivos = (int) $pdo->query("
    SELECT COUNT(*) FROM usuarios WHERE ativo = 1
")->fetchColumn();

$ultimasAuditorias = rgQuery($pdo, "
    SELECT
        au.criado_em,
        COALESCE(u.nome, 'Sistema') AS usuario,
        au.acao,
        au.modulo,
        au.tela,
        au.ip
    FROM auditoria_sistema au
    LEFT JOIN usuarios u ON u.id = au.usuario_id
    WHERE au.criado_em BETWEEN :audit_inicio AND :audit_fim
    ORDER BY au.criado_em DESC
    LIMIT 8
", $paramsAudit)->fetchAll(PDO::FETCH_ASSOC);

$tituloPagina = 'Relatórios Gerenciais';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/safe-health/assets/css/relatorios-gerenciais.css">

<div class="app-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content rg-page">
        <section class="rg-heading">
            <div>
                <span class="rg-kicker">Inteligência hospitalar</span>
                <h1>Relatórios Gerenciais</h1>
                <p>Visão consolidada dos indicadores assistenciais, operacionais, financeiros e de segurança do Safe Health.</p>
            </div>
            <button type="button" class="rg-print" onclick="window.print()">
                <i class="fa-solid fa-file-pdf"></i>
                Exportar / Imprimir
            </button>
        </section>

        <form class="rg-filters" method="get">
            <label>
                <span>Data inicial</span>
                <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio->format('Y-m-d')) ?>">
            </label>
            <label>
                <span>Data final</span>
                <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim->format('Y-m-d')) ?>">
            </label>
            <label>
                <span>Tipo de atendimento</span>
                <select name="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $item): ?>
                        <option value="<?= htmlspecialchars((string) $item) ?>" <?= $tipo === (string) $item ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', (string) $item)))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Convênio</span>
                <select name="convenio">
                    <option value="">Todos</option>
                    <?php foreach ($convenios as $item): ?>
                        <option value="<?= htmlspecialchars((string) $item) ?>" <?= $convenio === (string) $item ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Especialidade</span>
                <select name="especialidade">
                    <option value="">Todas</option>
                    <?php foreach ($especialidades as $item): ?>
                        <option value="<?= htmlspecialchars((string) $item) ?>" <?= $especialidade === (string) $item ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="rg-filter-actions">
                <button class="rg-primary" type="submit"><i class="fa-solid fa-filter"></i> Aplicar</button>
                <a class="rg-secondary" href="/safe-health/modules/relatorios/index.php">Limpar</a>
            </div>
        </form>

        <section class="rg-cards">
            <article class="rg-card">
                <div class="rg-card-icon"><i class="fa-solid fa-hospital-user"></i></div>
                <div><span>Atendimentos</span><strong><?= rgNumero($totalAtendimentos) ?></strong><small>No período selecionado</small></div>
            </article>
            <article class="rg-card">
                <div class="rg-card-icon"><i class="fa-solid fa-users"></i></div>
                <div><span>Pacientes atendidos</span><strong><?= rgNumero($totalPacientes) ?></strong><small>Pacientes distintos</small></div>
            </article>
            <article class="rg-card">
                <div class="rg-card-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div><span>Finalizados</span><strong><?= rgNumero($totalFinalizados) ?></strong><small><?= $totalAtendimentos ? rgNumero(($totalFinalizados / $totalAtendimentos) * 100, 1) . '% do total' : 'Sem atendimentos' ?></small></div>
            </article>
            <article class="rg-card">
                <div class="rg-card-icon"><i class="fa-solid fa-clock"></i></div>
                <div><span>Tempo médio</span><strong><?= htmlspecialchars($tempoMedioTexto) ?></strong><small>Entrada até alta</small></div>
            </article>
            <article class="rg-card rg-card-wide">
                <div class="rg-card-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div><span>Faturamento</span><strong><?= rgMoeda($faturamento) ?></strong><small>Valor líquido das contas</small></div>
            </article>
            <article class="rg-card rg-card-wide">
                <div class="rg-card-icon"><i class="fa-solid fa-receipt"></i></div>
                <div><span>Ticket médio</span><strong><?= rgMoeda($ticketMedio) ?></strong><small>Por atendimento</small></div>
            </article>
        </section>

        <section class="rg-grid">
            <article class="rg-panel rg-panel-large">
                <header><div><h2>Atendimentos por período</h2><p>Evolução diária no intervalo selecionado</p></div></header>
                <div class="rg-chart" data-chart="line" data-values='<?= htmlspecialchars(json_encode($atendimentosPeriodo, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
            </article>
            <article class="rg-panel">
                <header><div><h2>Tipo de atendimento</h2><p>Distribuição da demanda</p></div></header>
                <div class="rg-chart" data-chart="donut" data-values='<?= htmlspecialchars(json_encode($porTipo, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
            </article>
        </section>

        <section class="rg-grid">
            <article class="rg-panel">
                <header><div><h2>Por especialidade</h2><p>Especialidades com maior volume</p></div></header>
                <div class="rg-chart" data-chart="bars" data-values='<?= htmlspecialchars(json_encode($porEspecialidade, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
            </article>
            <article class="rg-panel">
                <header><div><h2>Por convênio</h2><p>Atendimentos por operadora</p></div></header>
                <div class="rg-chart" data-chart="bars" data-values='<?= htmlspecialchars(json_encode($porConvenio, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
            </article>
        </section>

        <section class="rg-section-title">
            <div><span>Assistencial</span><h2>Indicadores clínicos</h2></div>
        </section>

        <section class="rg-mini-cards">
            <article><i class="fa-solid fa-file-prescription"></i><span>Prescrições</span><strong><?= rgNumero($prescricoes) ?></strong></article>
            <article><i class="fa-solid fa-pills"></i><span>Medicamentos prescritos</span><strong><?= rgNumero($medicamentosPrescritos) ?></strong></article>
            <article><i class="fa-solid fa-flask-vial"></i><span>Exames laboratoriais</span><strong><?= rgNumero($examesLab) ?></strong></article>
            <article><i class="fa-solid fa-x-ray"></i><span>Exames de imagem</span><strong><?= rgNumero($examesImagem) ?></strong></article>
            <article><i class="fa-solid fa-person-walking-arrow-right"></i><span>Altas finalizadas</span><strong><?= rgNumero($altas) ?></strong></article>
        </section>

        <section class="rg-section-title">
            <div><span>Financeiro</span><h2>Faturamento e contas hospitalares</h2></div>
        </section>

        <section class="rg-grid">
            <article class="rg-panel">
                <header><div><h2>Faturamento por convênio</h2><p>Valor líquido acumulado no período</p></div></header>
                <div class="rg-chart" data-chart="money-bars" data-values='<?= htmlspecialchars(json_encode($faturamentoConvenio, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
            </article>
            <article class="rg-panel">
                <header><div><h2>Situação das contas</h2><p>Acompanhamento do ciclo financeiro</p></div></header>
                <div class="rg-status-list">
                    <div><span><i class="fa-solid fa-folder-open"></i> Contas abertas</span><strong><?= rgNumero($contasAbertas) ?></strong></div>
                    <div><span><i class="fa-solid fa-magnifying-glass-dollar"></i> Em auditoria</span><strong><?= rgNumero($contasAuditoria) ?></strong></div>
                    <div><span><i class="fa-solid fa-file-circle-check"></i> Fechadas / faturadas</span><strong><?= rgNumero($contasFechadas) ?></strong></div>
                    <div class="<?= $itensPendentesPreco > 0 ? 'rg-warning' : '' ?>"><span><i class="fa-solid fa-triangle-exclamation"></i> Itens pendentes de preço</span><strong><?= rgNumero($itensPendentesPreco) ?></strong></div>
                </div>
            </article>
        </section>

        <section class="rg-section-title">
            <div><span>Segurança</span><h2>Auditoria e rastreabilidade</h2></div>
        </section>

        <section class="rg-mini-cards rg-security">
            <article><i class="fa-solid fa-shield-halved"></i><span>Ações auditadas</span><strong><?= rgNumero($acoesAuditadas) ?></strong></article>
            <article><i class="fa-solid fa-file-shield"></i><span>Registros no Prontuário</span><strong><?= rgNumero($acessosProntuario) ?></strong></article>
            <article><i class="fa-solid fa-user-check"></i><span>Usuários ativos</span><strong><?= rgNumero($usuariosAtivos) ?></strong></article>
            <article><i class="fa-solid fa-users-gear"></i><span>Usuários com atividade</span><strong><?= rgNumero($usuariosAuditados) ?></strong></article>
        </section>

        <section class="rg-panel rg-audit-table">
            <header><div><h2>Atividades recentes de auditoria</h2><p>Últimos registros dentro do período selecionado</p></div></header>
            <div class="rg-table-wrap">
                <table>
                    <thead><tr><th>Data / hora</th><th>Usuário</th><th>Ação</th><th>Módulo</th><th>Tela</th><th>IP</th></tr></thead>
                    <tbody>
                    <?php if (!$ultimasAuditorias): ?>
                        <tr><td colspan="6" class="rg-empty">Nenhum registro de auditoria no período.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ultimasAuditorias as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $log['criado_em']))) ?></td>
                                <td><?= htmlspecialchars((string) $log['usuario']) ?></td>
                                <td><span class="rg-badge"><?= htmlspecialchars((string) $log['acao']) ?></span></td>
                                <td><?= htmlspecialchars((string) ($log['modulo'] ?: '—')) ?></td>
                                <td><?= htmlspecialchars((string) ($log['tela'] ?: '—')) ?></td>
                                <td><?= htmlspecialchars((string) ($log['ip'] ?: '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="rg-report-footer">
            Relatório gerado em <?= date('d/m/Y H:i') ?> • Safe Health
        </footer>
    </main>
</div>

<script src="/safe-health/assets/js/relatorios-gerenciais.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
