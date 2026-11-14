<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$tituloPagina = 'Abertura de Atendimento';
$codigoTela = 'atendimento.recepcao';

$senhaId = filter_input(INPUT_GET, 'senha_id', FILTER_VALIDATE_INT);
$pacienteId = filter_input(INPUT_GET, 'paciente_id', FILTER_VALIDATE_INT);

if (
    $senhaId === false ||
    $senhaId === null ||
    $senhaId <= 0 ||
    $pacienteId === false ||
    $pacienteId === null ||
    $pacienteId <= 0
) {
    header('Location: /safe-health/modules/atendimento/recepcao.php');
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
    header('Location: /safe-health/modules/atendimento/recepcao.php');
    exit;
}

if ($senha['status'] !== 'CHAMADA_RECEPCAO') {
    header('Location: /safe-health/modules/atendimento/recepcao.php');
    exit;
}

try {
    $dataNascimento = new DateTimeImmutable(
        (string) $paciente['data_nascimento']
    );
} catch (Throwable) {
    $dataNascimento = null;
}

$idade = $dataNascimento
    ? $dataNascimento->diff(new DateTimeImmutable('today'))->y
    : null;

$tipoAtendimentoPadrao =
    strtoupper((string) $senha['tipo_fila']) === 'URGENCIA'
        ? 'URGENCIA'
        : 'AMBULATORIO';

$origemPadrao = match ((string) $senha['codigo_fila']) {
    'PS_ADULTO' => 'PRONTO_SOCORRO_ADULTO',
    'PS_INFANTIL' => 'PRONTO_SOCORRO_INFANTIL',
    'PS_ORTOPEDIA' => 'PRONTO_SOCORRO_ORTOPEDIA',
    'AGENDAMENTO', 'CONSULTA' => 'CONSULTORIO',
    'EXAMES' => 'EXAMES',
    default => ''
};

$especialidadePadrao = match ((string) $senha['codigo_fila']) {
    'PS_INFANTIL' => 'PEDIATRIA',
    'PS_ORTOPEDIA' => 'ORTOPEDIA',
    'PS_ADULTO' => 'CLINICA MEDICA',
    default => ''
};

// Busca os médicos ativos e a especialidade vinculada no cadastro.
$stmtMedicos = $pdo->prepare(
    "
    SELECT
        m.id,
        m.nome,
        m.crm,
        m.uf_crm,
        m.especialidade_id,
        e.nome AS especialidade_nome
    FROM medicos m
    INNER JOIN especialidades e
        ON e.id = m.especialidade_id
    WHERE m.ativo = 1
    ORDER BY m.nome ASC
    "
);

$stmtMedicos->execute();

$medicos = $stmtMedicos->fetchAll(PDO::FETCH_ASSOC);

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
                    <span class="page-eyebrow">Recepção</span>

                    <h2>Abertura de Atendimento</h2>

                    <p>
                        Confira os dados do paciente e preencha
                        as informações para criar o atendimento.
                    </p>
                </div>

                <div class="opening-references">

                    <div class="reference-card">
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
                        class="reference-card atendimento-reference"
                    >
                        <span>Atendimento</span>

                        <strong id="numeroAtendimentoCriado">
                            Aguardando criação
                        </strong>
                    </div>

                </div>

            </div>

            <form
                id="formAbrirAtendimento"
                class="opening-form"
                novalidate
            >

                <input
                    type="hidden"
                    name="paciente_id"
                    value="<?= (int) $pacienteId ?>"
                >

                <input
                    type="hidden"
                    name="senha_id"
                    value="<?= (int) $senhaId ?>"
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
                                <?= $dataNascimento
                                    ? $dataNascimento->format('d/m/Y')
                                    : 'Não informada' ?>
                            </strong>
                        </div>

                        <div class="info-field">
                            <span>Idade</span>
                            <strong>
                                <?= $idade !== null
                                    ? $idade . ' anos'
                                    : 'Não calculada' ?>
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
                                <option value="">Selecione</option>

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
                            <label for="origem">Origem</label>

                            <select
                                id="origem"
                                name="origem"
                                required
                            >
                                <option value="">Selecione</option>

                                <?php
                                $origens = [
                                    'CLINICA_MEDICA' => 'Clínica Médica',
                                    'CLINICA_CIRURGICA' => 'Clínica Cirúrgica',
                                    'CENTRO_CIRURGICO' => 'Centro Cirúrgico',
                                    'CONSULTORIO' => 'Consultório',
                                    'SALA_DE_PROCEDIMENTO' => 'Sala de Procedimento',
                                    'PRONTO_SOCORRO_ADULTO' => 'Pronto-Socorro Adulto',
                                    'PRONTO_SOCORRO_INFANTIL' => 'Pronto-Socorro Infantil',
                                    'PRONTO_SOCORRO_ORTOPEDIA' => 'Pronto-Socorro Ortopedia',
                                    'AMBULATORIO' => 'Ambulatório',
                                    'UNIDADE_DE_INTERNACAO' => 'Unidade de Internação',
                                    'UTI' => 'UTI',
                                    'EXAMES' => 'Setor de Exames',
                                    'EXTERNO' => 'Atendimento Externo'
                                ];

                                foreach ($origens as $valor => $rotulo):
                                ?>
                                    <option
                                        value="<?= $valor ?>"
                                        <?= $origemPadrao === $valor
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= htmlspecialchars(
                                            $rotulo,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="convenio">Convênio</label>

                            <select
                                id="convenio"
                                name="convenio"
                                required
                            >
                                <option value="">Selecione</option>
                                <option value="SUS">SUS</option>
                                <option value="PARTICULAR">Particular</option>
                                <option value="UNIMED">Unimed</option>
                                <option value="BRADESCO SAUDE">Bradesco Saúde</option>
                                <option value="AMIL">Amil</option>
                                <option value="SULAMERICA">SulAmérica</option>
                                <option value="NOTREDAME INTERMEDICA">NotreDame Intermédica</option>
                                <option value="PORTO SAUDE">Porto Saúde</option>
                                <option value="HAPVIDA">Hapvida</option>
                                <option value="CASSI">Cassi</option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="especialidade">
                                Especialidade do médico
                            </label>

                            <select
                                id="especialidade"
                                name="especialidade"
                                required
                                disabled
                            >
                                <option value="">
                                    Selecione primeiro o médico responsável
                                </option>

                                <?php
                                foreach ($medicos as $medico):
                                    $nomeEspecialidade =
                                        (string) $medico['especialidade_nome'];

                                    $valorEspecialidade =
                                        strtoupper(
                                            strtr(
                                                $nomeEspecialidade,
                                                [
                                                    'Á' => 'A',
                                                    'À' => 'A',
                                                    'Â' => 'A',
                                                    'Ã' => 'A',
                                                    'É' => 'E',
                                                    'Ê' => 'E',
                                                    'Í' => 'I',
                                                    'Ó' => 'O',
                                                    'Ô' => 'O',
                                                    'Õ' => 'O',
                                                    'Ú' => 'U',
                                                    'Ç' => 'C',
                                                    'á' => 'A',
                                                    'à' => 'A',
                                                    'â' => 'A',
                                                    'ã' => 'A',
                                                    'é' => 'E',
                                                    'ê' => 'E',
                                                    'í' => 'I',
                                                    'ó' => 'O',
                                                    'ô' => 'O',
                                                    'õ' => 'O',
                                                    'ú' => 'U',
                                                    'ç' => 'C'
                                                ]
                                            )
                                        );
                                ?>
                                    <option
                                        value="<?= htmlspecialchars(
                                            $valorEspecialidade,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-especialidade-id="<?= (int) $medico['especialidade_id'] ?>"
                                    >
                                        <?= htmlspecialchars(
                                            $nomeEspecialidade,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <small>
                                A especialidade é preenchida automaticamente
                                conforme o cadastro do médico.
                            </small>
                        </div>

                        <div class="form-field">
                            <label for="profissionalResponsavel">
                                Profissional responsável
                            </label>

                            <select
                                id="profissionalResponsavel"
                                name="profissional_id"
                                required
                            >
                                <option value="">
                                    Selecione o médico responsável
                                </option>

                                <?php foreach ($medicos as $medico): ?>
                                    <?php
                                    $nomeEspecialidade =
                                        (string) $medico['especialidade_nome'];

                                    $valorEspecialidade =
                                        strtoupper(
                                            strtr(
                                                $nomeEspecialidade,
                                                [
                                                    'Á' => 'A',
                                                    'À' => 'A',
                                                    'Â' => 'A',
                                                    'Ã' => 'A',
                                                    'É' => 'E',
                                                    'Ê' => 'E',
                                                    'Í' => 'I',
                                                    'Ó' => 'O',
                                                    'Ô' => 'O',
                                                    'Õ' => 'O',
                                                    'Ú' => 'U',
                                                    'Ç' => 'C',
                                                    'á' => 'A',
                                                    'à' => 'A',
                                                    'â' => 'A',
                                                    'ã' => 'A',
                                                    'é' => 'E',
                                                    'ê' => 'E',
                                                    'í' => 'I',
                                                    'ó' => 'O',
                                                    'ô' => 'O',
                                                    'õ' => 'O',
                                                    'ú' => 'U',
                                                    'ç' => 'C'
                                                ]
                                            )
                                        );
                                    ?>
                                    <option
                                        value="<?= (int) $medico['id'] ?>"
                                        data-especialidade-id="<?= (int) $medico['especialidade_id'] ?>"
                                        data-especialidade="<?= htmlspecialchars(
                                            $valorEspecialidade,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                        <?= htmlspecialchars(
                                            $medico['nome']
                                            . ' - CRM '
                                            . $medico['crm']
                                            . '/'
                                            . $medico['uf_crm'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (empty($medicos)): ?>
                                <small>
                                    Nenhum médico ativo cadastrado.
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="form-field">
                            <label>Data e hora de abertura</label>

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
                    role="alert"
                    hidden
                ></div>

                <div class="form-actions">

                    <a
                        href="/safe-health/modules/atendimento/recepcao.php"
                        class="cancel-button"
                        id="btnCancelarAtendimento"
                    >
                        <i class="fa-solid fa-xmark"></i>
                        Cancelar atendimento
                    </a>

                    <button
                        type="submit"
                        class="create-attendance-button"
                        id="btnCriarAtendimento"
                    >
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Criar atendimento</span>
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

<script src="/safe-health/assets/js/abrir-atendimento.js?v=4"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>