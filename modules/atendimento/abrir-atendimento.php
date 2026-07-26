<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config/database.php';

$tituloPagina = 'Abertura de Atendimento';

$senhaId = (int) ($_GET['senha_id'] ?? 0);
$pacienteId = (int) ($_GET['paciente_id'] ?? 0);

if ($senhaId <= 0 || $pacienteId <= 0) {
    header(
        'Location: /safe-health/modules/atendimento/recepcao.php'
    );
    exit;
}

$stmtPaciente = $pdo->prepare(
    "
    SELECT
        id,
        nome,
        cpf,
        data_nascimento,
        sexo,
        telefone,
        cartao_sus
    FROM pacientes
    WHERE id = :id
    LIMIT 1
    "
);

$stmtPaciente->execute([
    ':id' => $pacienteId
]);

$paciente = $stmtPaciente->fetch(PDO::FETCH_ASSOC);

$stmtSenha = $pdo->prepare(
    "
    SELECT
        s.id,
        s.numero,
        s.status,
        s.data_emissao,
        s.emitida_em,
        f.nome AS nome_fila,
        f.codigo AS codigo_fila,
        f.tipo AS tipo_fila
    FROM senhas_atendimento s
    INNER JOIN filas f
        ON f.id = s.fila_id
    WHERE s.id = :id
    LIMIT 1
    "
);

$stmtSenha->execute([
    ':id' => $senhaId
]);

$senha = $stmtSenha->fetch(PDO::FETCH_ASSOC);

if (!$paciente || !$senha) {
    header(
        'Location: /safe-health/modules/atendimento/recepcao.php'
    );
    exit;
}

$dataNascimento = new DateTime(
    (string) $paciente['data_nascimento']
);

$hoje = new DateTime();

$idade = $dataNascimento->diff($hoje)->y;

$tipoAtendimentoPadrao =
    $senha['tipo_fila'] === 'URGENCIA'
        ? 'URGENCIA'
        : 'AMBULATORIO';

$origemPadrao = match ($senha['codigo_fila']) {
    'PS_ADULTO' => 'PRONTO_SOCORRO_ADULTO',
    'PS_INFANTIL' => 'PRONTO_SOCORRO_INFANTIL',
    'PS_ORTOPEDIA' => 'PRONTO_SOCORRO_ORTOPEDIA',
    'AGENDAMENTO',
    'CONSULTA' => 'CONSULTORIO',
    'EXAMES' => 'EXAMES',
    default => ''
};

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<link
    rel="stylesheet"
    href="/safe-health/assets/css/abrir-atendimento.css"
>

<div class="app-content">

    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="main-content">

        <section class="opening-page">

            <div class="page-heading">

                <div>
                    <span class="page-eyebrow">
                        Recepção
                    </span>

                    <h2>Abertura de Atendimento</h2>

                    <p>
                        Confira os dados do paciente e preencha
                        as informações do atendimento.
                    </p>
                </div>

                <div class="opening-reference">

    <div>
        <span>Senha vinculada</span>

        <strong>
            #<?= htmlspecialchars(
                (string) $senha['numero'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </div>

    <div
    id="referenciaAtendimento"
    class="referencia-atendimento"
    hidden
>
    <span>Atendimento criado</span>

    <strong id="numeroAtendimentoCriado">
        —
    </strong>
</div>
</div>

            </div>

            <form
                id="formAbrirAtendimento"
                class="opening-form"
            >

                <input
                    type="hidden"
                    name="paciente_id"
                    value="<?= $pacienteId ?>"
                >

                <input
                    type="hidden"
                    name="senha_id"
                    value="<?= $senhaId ?>"
                >

                <section class="form-card">

                    <div class="card-title">

                        <div class="card-title-icon">
                            <i class="fa-solid fa-user"></i>
                        </div>

                        <div>
                            <span>Paciente</span>

                            <h3>
                                <?= htmlspecialchars(
                                    (string) $paciente['nome'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h3>
                        </div>

                    </div>

                    <div class="patient-grid">

                        <div class="info-field">
                            <span>CPF</span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $paciente['cpf'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>
                        </div>

                        <div class="info-field">
                            <span>Data de nascimento</span>

                            <strong>
                                <?= $dataNascimento->format('d/m/Y') ?>
                            </strong>
                        </div>

                        <div class="info-field">
                            <span>Idade</span>

                            <strong>
                                <?= $idade ?> anos
                            </strong>
                        </div>

                        <div class="info-field">
                            <span>Sexo</span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $paciente['sexo'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>
                        </div>

                        <div class="info-field">
                            <span>Telefone</span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) (
                                        $paciente['telefone']
                                        ?: 'Não informado'
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>
                        </div>

                        <div class="info-field">
                            <span>Cartão SUS</span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) (
                                        $paciente['cartao_sus']
                                        ?: 'Não informado'
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>
                        </div>

                    </div>

                </section>

                <section class="form-card">

                    <div class="section-heading">
                        <span>Dados assistenciais</span>
                        <h3>Informações do atendimento</h3>
                    </div>

                    <div class="form-grid">

                        <div class="form-field">
                            <label for="tipoAtendimento">
                                Tipo de atendimento
                            </label>

                            <select
                                id="tipoAtendimento"
                                name="tipo_atendimento"
                                required
                            >
                                <option value="">
                                    Selecione
                                </option>

                                <option
                                    value="URGENCIA"
                                    <?= $tipoAtendimentoPadrao === 'URGENCIA'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Urgência
                                </option>

                                <option
                                    value="AMBULATORIO"
                                    <?= $tipoAtendimentoPadrao === 'AMBULATORIO'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Ambulatório
                                </option>

                                <option value="INTERNACAO">
                                    Internação
                                </option>

                                <option value="EXTERNO">
                                    Externo
                                </option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="origem">
                                Origem
                            </label>

                            <select
                                id="origem"
                                name="origem"
                                required
                            >
                                <option value="">
                                    Selecione
                                </option>

                                <option
                                    value="CLINICA_MEDICA"
                                    <?= $origemPadrao === 'CLINICA_MEDICA'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Clínica Médica
                                </option>

                                <option
                                    value="CLINICA_CIRURGICA"
                                    <?= $origemPadrao === 'CLINICA_CIRURGICA'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Clínica Cirúrgica
                                </option>

                                <option
                                    value="CENTRO_CIRURGICO"
                                    <?= $origemPadrao === 'CENTRO_CIRURGICO'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Centro Cirúrgico
                                </option>

                                <option
                                    value="CONSULTORIO"
                                    <?= $origemPadrao === 'CONSULTORIO'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Consultório
                                </option>

                                <option
                                    value="SALA_DE_PROCEDIMENTO"
                                    <?= $origemPadrao === 'SALA_DE_PROCEDIMENTO'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Sala de Procedimento
                                </option>

                                <option
                                    value="PRONTO_SOCORRO_ADULTO"
                                    <?= $origemPadrao === 'PRONTO_SOCORRO_ADULTO'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Pronto-Socorro Adulto
                                </option>

                                <option
                                    value="PRONTO_SOCORRO_INFANTIL"
                                    <?= $origemPadrao === 'PRONTO_SOCORRO_INFANTIL'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Pronto-Socorro Infantil
                                </option>

                                <option
                                    value="PRONTO_SOCORRO_ORTOPEDIA"
                                    <?= $origemPadrao === 'PRONTO_SOCORRO_ORTOPEDIA'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Pronto-Socorro Ortopedia
                                </option>

                                <option
                                    value="AMBULATORIO"
                                    <?= $origemPadrao === 'AMBULATORIO'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Ambulatório
                                </option>

                                <option
                                    value="UNIDADE_DE_INTERNACAO"
                                    <?= $origemPadrao === 'UNIDADE_DE_INTERNACAO'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Unidade de Internação
                                </option>

                                <option
                                    value="UTI"
                                    <?= $origemPadrao === 'UTI'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    UTI
                                </option>

                                <option
                                    value="EXAMES"
                                    <?= $origemPadrao === 'EXAMES'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Setor de Exames
                                </option>

                                <option
                                    value="EXTERNO"
                                    <?= $origemPadrao === 'EXTERNO'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Atendimento Externo
                                </option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="convenio">
                                Convênio
                            </label>

                            <select
                                id="convenio"
                                name="convenio"
                                required
                            >
                                <option value="">
                                    Selecione
                                </option>

                                <option value="SUS">SUS</option>
                                <option value="PARTICULAR">Particular</option>
                                <option value="UNIMED">Unimed</option>
                                <option value="BRADESCO SAUDE">
                                    Bradesco Saúde
                                </option>
                                <option value="AMIL">Amil</option>
                                <option value="SULAMERICA">
                                    SulAmérica
                                </option>
                                <option value="NOTREDAME INTERMEDICA">
                                    NotreDame Intermédica
                                </option>
                                <option value="PORTO SAUDE">
                                    Porto Saúde
                                </option>
                                <option value="HAPVIDA">Hapvida</option>
                                <option value="CASSI">Cassi</option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="especialidade">
                                Especialidade
                            </label>

                            <select
                                id="especialidade"
                                name="especialidade"
                                required
                            >
                                <option value="">
                                    Selecione
                                </option>

                                <option value="CLINICA MEDICA">
                                    Clínica Médica
                                </option>

                                <option value="PEDIATRIA">
                                    Pediatria
                                </option>

                                <option value="ORTOPEDIA">
                                    Ortopedia
                                </option>

                                <option value="CARDIOLOGIA">
                                    Cardiologia
                                </option>

                                <option value="NEUROLOGIA">
                                    Neurologia
                                </option>

                                <option value="GINECOLOGIA">
                                    Ginecologia
                                </option>

                                <option value="CIRURGIA GERAL">
                                    Cirurgia Geral
                                </option>

                                <option value="OFTALMOLOGIA">
                                    Oftalmologia
                                </option>

                                <option value="OTORRINOLARINGOLOGIA">
                                    Otorrinolaringologia
                                </option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="profissionalResponsavel">
                                Profissional responsável
                            </label>

                            <input
                                type="text"
                                id="profissionalResponsavel"
                                name="profissional_responsavel"
                                placeholder="Nome do profissional"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label>
                                Data e hora de abertura
                            </label>

                            <input
                                type="text"
                                value="<?= date('d/m/Y H:i') ?>"
                                readonly
                            >
                        </div>

                    </div>

                </section>

                <div
                    id="mensagemAtendimento"
                    class="form-message"
                    hidden
                ></div>

                <div class="form-actions">

                    <a
                        href="/safe-health/modules/atendimento/recepcao.php"
                        class="cancel-button"
                    >
                        <i class="fa-solid fa-xmark"></i>
                        Cancelar atendimento
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="btnCriarAtendimento"
                    >
                        <i class="fa-solid fa-circle-check"></i>
                        Criar atendimento
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

<script src="/safe-health/assets/js/abrir-atendimento.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>