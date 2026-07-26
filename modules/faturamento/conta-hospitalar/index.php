<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permissoes.php';

/*
|--------------------------------------------------------------------------
| Controle de acesso
|--------------------------------------------------------------------------
|
| Apenas usuários que possuem a tela vinculada ao seu papel poderão
| visualizar contas hospitalares.
|
| A recepção não deve possuir esta permissão.
|
*/

exigirPermissao(
    $pdo,
    'FATURAMENTO_CONTAS_HOSPITALARES'
);

/*
|--------------------------------------------------------------------------
| Identificação da conta hospitalar
|--------------------------------------------------------------------------
*/

$contaId = filter_input(
    INPUT_GET,
    'conta_id',
    FILTER_VALIDATE_INT
);

if (
    $contaId === false ||
    $contaId === null ||
    $contaId <= 0
) {
    http_response_code(422);

    exit('Conta hospitalar inválida.');
}

$contaId = filter_input(
    INPUT_GET,
    'conta_id',
    FILTER_VALIDATE_INT
);

if (!$contaId || $contaId <= 0) {
    http_response_code(422);
    exit('Conta hospitalar inválida.');
}

/*
|--------------------------------------------------------------------------
| Busca da conta hospitalar
|--------------------------------------------------------------------------
|
| Estrutura real do Safe Health:
|
| pacientes:
| - id
| - nome
| - cpf
|
| atendimentos:
| - id
| - numero_atendimento
| - paciente_id
| - convenio
| - especialidade
| - profissional_responsavel
| - tipo_atendimento
| - origem
| - data_entrada
|
| contas_hospitalares:
| - atendimento_id
| - paciente_id
| - valores
| - status
|
*/

$stmtConta = $pdo->prepare(
    "
    SELECT
        ch.id,
        ch.atendimento_id,
        ch.paciente_id,
        ch.data_abertura,
        ch.data_fechamento,
        ch.valor_bruto,
        ch.valor_desconto,
        ch.valor_coparticipacao,
        ch.valor_liquido,
        ch.status,

        p.nome AS paciente_nome,
        p.cpf AS paciente_cpf,
        p.data_nascimento,
        p.sexo,

        a.numero_atendimento,
        a.convenio,
        a.origem,
        a.tipo_atendimento,
        a.especialidade,
        a.profissional_responsavel,
        a.data_entrada,
        a.data_alta

    FROM contas_hospitalares ch

    INNER JOIN pacientes p
        ON p.id = ch.paciente_id

    INNER JOIN atendimentos a
        ON a.id = ch.atendimento_id

    WHERE ch.id = :conta_id

    LIMIT 1
    "
);

$stmtConta->execute([
    ':conta_id' => $contaId
]);

$conta = $stmtConta->fetch(PDO::FETCH_ASSOC);

if (!$conta) {
    http_response_code(404);
    exit('Conta hospitalar não encontrada.');
}

/*
|--------------------------------------------------------------------------
| Itens da conta
|--------------------------------------------------------------------------
*/

$stmtItens = $pdo->prepare(
    "
    SELECT
        id,
        conta_id,
        tipo_item,
        item_id,
        descricao,
        quantidade,
        valor_unitario,
        valor_total,
        status_item,
        criado_em

    FROM itens_conta_hospitalar

    WHERE conta_id = :conta_id

    ORDER BY
        CASE tipo_item
            WHEN 'DIARIA' THEN 1
            WHEN 'TAXA' THEN 2
            WHEN 'PROCEDIMENTO' THEN 3
            WHEN 'CIRURGIA' THEN 4
            WHEN 'MEDICAMENTO' THEN 5
            WHEN 'MATERIAL' THEN 6
            WHEN 'EXAME_LABORATORIAL' THEN 7
            WHEN 'EXAME_IMAGEM' THEN 8
            WHEN 'HONORARIO' THEN 9
            ELSE 10
        END,
        criado_em DESC,
        id DESC
    "
);

$stmtItens->execute([
    ':conta_id' => $contaId
]);

$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

$itensAgrupados = [];

foreach ($itens as $item) {
    $itensAgrupados[$item['tipo_item']][] = $item;
}

$categorias = [
    'DIARIA' => [
        'nome' => 'Diárias',
        'icone' => 'fa-bed'
    ],
    'TAXA' => [
        'nome' => 'Taxas hospitalares',
        'icone' => 'fa-receipt'
    ],
    'PROCEDIMENTO' => [
        'nome' => 'Procedimentos',
        'icone' => 'fa-stethoscope'
    ],
    'CIRURGIA' => [
        'nome' => 'Cirurgias',
        'icone' => 'fa-user-doctor'
    ],
    'MEDICAMENTO' => [
        'nome' => 'Medicamentos',
        'icone' => 'fa-pills'
    ],
    'MATERIAL' => [
        'nome' => 'Materiais',
        'icone' => 'fa-box'
    ],
    'EXAME_LABORATORIAL' => [
        'nome' => 'Exames laboratoriais',
        'icone' => 'fa-flask'
    ],
    'EXAME_IMAGEM' => [
        'nome' => 'Exames de imagem',
        'icone' => 'fa-x-ray'
    ],
    'HONORARIO' => [
        'nome' => 'Honorários',
        'icone' => 'fa-file-invoice-dollar'
    ],
    'OUTRO' => [
        'nome' => 'Outros lançamentos',
        'icone' => 'fa-circle-plus'
    ]
];

function formatarMoeda(float|string|null $valor): string
{
    return 'R$ ' . number_format(
        (float) $valor,
        2,
        ',',
        '.'
    );
}

function formatarData(?string $data): string
{
    if (!$data) {
        return 'Não informado';
    }

    return date(
        'd/m/Y H:i',
        strtotime($data)
    );
}

function formatarCpf(?string $cpf): string
{
    $cpf = preg_replace('/\D/', '', (string) $cpf);

    if (strlen($cpf) !== 11) {
        return $cpf ?: 'Não informado';
    }

    return preg_replace(
        '/(\d{3})(\d{3})(\d{3})(\d{2})/',
        '$1.$2.$3-$4',
        $cpf
    );
}

$statusConta = strtoupper(
    (string) $conta['status']
);

$contaBloqueada = in_array(
    $statusConta,
    ['FECHADA', 'FATURADA'],
    true
);

$classesStatusConta = [
    'ABERTA' => 'status-aberta',
    'EM_AUDITORIA' => 'status-auditoria',
    'FECHADA' => 'status-fechada',
    'FATURADA' => 'status-faturada'
];

$classesStatusItem = [
    'PENDENTE' => 'item-pendente',
    'AUDITADO' => 'item-auditado',
    'GLOSADO' => 'item-glosado'
];

$tituloPagina = 'Conta Hospitalar';

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
require_once __DIR__ . '/../../../includes/topbar.php';

?>

<link
    rel="stylesheet"
    href="/safe-health/modules/faturamento/conta-hospitalar/conta-hospitalar.css"
>

<main class="conta-hospitalar-page">

    <div
        id="alertaConta"
        class="alerta-conta"
        hidden
    ></div>

    <section class="cabecalho-conta">

        <div class="cabecalho-conta-topo">

            <div>
                <div class="modulo-identificacao">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    Faturamento hospitalar
                </div>

                <div class="titulo-conta-linha">
                    <h1>
                        Conta Hospitalar
                        <?= str_pad(
                            (string) $conta['id'],
                            8,
                            '0',
                            STR_PAD_LEFT
                        ) ?>
                    </h1>

                    <span
                        class="status-conta <?= htmlspecialchars(
                            $classesStatusConta[$statusConta] ??
                            'status-desconhecido'
                        ) ?>"
                    >
                        <?= htmlspecialchars(
                            str_replace('_', ' ', $statusConta)
                        ) ?>
                    </span>
                </div>

                <p>
                    Gestão dos lançamentos financeiros vinculados ao
                    atendimento hospitalar.
                </p>
            </div>

            <?php if (!$contaBloqueada): ?>
                <button
                    type="button"
                    class="botao botao-primario"
                    id="abrirModalItem"
                >
                    <i class="fa-solid fa-plus"></i>
                    Adicionar item
                </button>
            <?php endif; ?>

        </div>

        <div class="dados-conta-grid">

            <div class="dado-conta destaque">
                <span>Paciente</span>

                <strong>
                    <?= htmlspecialchars(
                        $conta['paciente_nome']
                    ) ?>
                </strong>
            </div>

            <div class="dado-conta">
                <span>CPF</span>

                <strong>
                    <?= htmlspecialchars(
                        formatarCpf($conta['paciente_cpf'])
                    ) ?>
                </strong>
            </div>

            <div class="dado-conta">
                <span>Número do atendimento</span>

                <strong>
                    <?= htmlspecialchars(
                        $conta['numero_atendimento']
                    ) ?>
                </strong>
            </div>

            <div class="dado-conta">
                <span>Convênio</span>

                <strong>
                    <?= htmlspecialchars(
                        $conta['convenio'] ?: 'PARTICULAR'
                    ) ?>
                </strong>
            </div>

            <div class="dado-conta">
                <span>Tipo de atendimento</span>

                <strong>
                    <?= htmlspecialchars(
                        str_replace(
                            '_',
                            ' ',
                            $conta['tipo_atendimento']
                        )
                    ) ?>
                </strong>
            </div>

            <div class="dado-conta">
                <span>Especialidade</span>

                <strong>
                    <?= htmlspecialchars(
                        $conta['especialidade'] ?:
                        'Não informada'
                    ) ?>
                </strong>
            </div>

            <div class="dado-conta">
                <span>Profissional responsável</span>

                <strong>
                    <?= htmlspecialchars(
                        $conta['profissional_responsavel'] ?:
                        'Não informado'
                    ) ?>
                </strong>
            </div>

            <div class="dado-conta">
                <span>Data de entrada</span>

                <strong>
                    <?= htmlspecialchars(
                        formatarData($conta['data_entrada'])
                    ) ?>
                </strong>
            </div>

        </div>

    </section>

    <div class="conteudo-conta-grid">

        <section class="painel-lancamentos">

            <div class="painel-titulo">

                <div>
                    <span class="painel-subtitulo">
                        Lançamentos
                    </span>

                    <h2>Itens da conta hospitalar</h2>
                </div>

                <span class="contador-itens">
                    <?= count($itens) ?>
                    item(ns)
                </span>

            </div>

            <?php if (!$itens): ?>

                <div class="estado-vazio">

                    <div class="estado-vazio-icone">
                        <i class="fa-solid fa-file-circle-plus"></i>
                    </div>

                    <h3>Nenhum item lançado</h3>

                    <p>
                        Esta conta ainda não possui diárias,
                        procedimentos, medicamentos, materiais ou exames.
                    </p>

                    <?php if (!$contaBloqueada): ?>
                        <button
                            type="button"
                            class="botao botao-primario"
                            id="abrirModalItemVazio"
                        >
                            Adicionar primeiro item
                        </button>
                    <?php endif; ?>

                </div>

            <?php else: ?>

                <?php foreach ($itensAgrupados as $tipo => $listaItens): ?>

                    <?php
                    $categoria = $categorias[$tipo] ?? [
                        'nome' => $tipo,
                        'icone' => 'fa-circle'
                    ];
                    ?>

                    <div class="grupo-itens">

                        <div class="grupo-itens-cabecalho">

                            <div class="grupo-titulo">
                                <i class="fa-solid <?= htmlspecialchars(
                                    $categoria['icone']
                                ) ?>"></i>

                                <span>
                                    <?= htmlspecialchars(
                                        $categoria['nome']
                                    ) ?>
                                </span>
                            </div>

                            <span class="grupo-quantidade">
                                <?= count($listaItens) ?>
                            </span>

                        </div>

                        <div class="tabela-responsiva">

                            <table class="tabela-conta">

                                <thead>
                                    <tr>
                                        <th>Descrição</th>
                                        <th>Código</th>
                                        <th class="texto-direita">
                                            Quantidade
                                        </th>
                                        <th class="texto-direita">
                                            Valor unitário
                                        </th>
                                        <th class="texto-direita">
                                            Valor total
                                        </th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($listaItens as $item): ?>

                                        <?php
                                        $statusItem = strtoupper(
                                            $item['status_item']
                                        );
                                        ?>

                                        <tr>

                                            <td>
                                                <strong class="descricao-item">
                                                    <?= htmlspecialchars(
                                                        $item['descricao']
                                                    ) ?>
                                                </strong>

                                                <small>
                                                    Lançado em
                                                    <?= htmlspecialchars(
                                                        formatarData(
                                                            $item['criado_em']
                                                        )
                                                    ) ?>
                                                </small>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $item['item_id'] ?
                                                    (string) $item['item_id'] :
                                                    '-'
                                                ) ?>
                                            </td>

                                            <td class="texto-direita">
                                                <?= number_format(
                                                    (float) $item['quantidade'],
                                                    2,
                                                    ',',
                                                    '.'
                                                ) ?>
                                            </td>

                                            <td class="texto-direita">
                                                <?= formatarMoeda(
                                                    $item['valor_unitario']
                                                ) ?>
                                            </td>

                                            <td class="texto-direita valor-item">
                                                <?= formatarMoeda(
                                                    $item['valor_total']
                                                ) ?>
                                            </td>

                                            <td>
                                                <span
                                                    class="status-item <?= htmlspecialchars(
                                                        $classesStatusItem[
                                                            $statusItem
                                                        ] ?? ''
                                                    ) ?>"
                                                >
                                                    <?= htmlspecialchars(
                                                        $statusItem
                                                    ) ?>
                                                </span>
                                            </td>

                                            <td>

                                                <?php if (!$contaBloqueada): ?>

                                                    <select
                                                        class="seletor-status-item"
                                                        data-item-id="<?= (int) $item['id'] ?>"
                                                        aria-label="Status do item"
                                                    >
                                                        <option
                                                            value="PENDENTE"
                                                            <?= $statusItem === 'PENDENTE'
                                                                ? 'selected'
                                                                : '' ?>
                                                        >
                                                            Pendente
                                                        </option>

                                                        <option
                                                            value="AUDITADO"
                                                            <?= $statusItem === 'AUDITADO'
                                                                ? 'selected'
                                                                : '' ?>
                                                        >
                                                            Auditado
                                                        </option>

                                                        <option
                                                            value="GLOSADO"
                                                            <?= $statusItem === 'GLOSADO'
                                                                ? 'selected'
                                                                : '' ?>
                                                        >
                                                            Glosado
                                                        </option>
                                                    </select>

                                                <?php else: ?>

                                                    <span class="conta-bloqueada">
                                                        Bloqueado
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>

        <aside class="resumo-financeiro">

            <div class="resumo-cabecalho">
                <span>Resumo financeiro</span>
                <h2>Valores da conta</h2>
            </div>

            <div class="resumo-linha">
                <span>Total bruto</span>

                <strong id="valorBruto">
                    <?= formatarMoeda(
                        $conta['valor_bruto']
                    ) ?>
                </strong>
            </div>

            <div class="resumo-linha desconto">
                <span>Descontos e glosas</span>

                <strong id="valorDesconto">
                    <?= formatarMoeda(
                        $conta['valor_desconto']
                    ) ?>
                </strong>
            </div>

            <div class="resumo-linha">
                <span>Coparticipação</span>

                <strong id="valorCoparticipacao">
                    <?= formatarMoeda(
                        $conta['valor_coparticipacao']
                    ) ?>
                </strong>
            </div>

            <div class="resumo-total">
                <span>Total líquido</span>

                <strong id="valorLiquido">
                    <?= formatarMoeda(
                        $conta['valor_liquido']
                    ) ?>
                </strong>
            </div>

            <div class="dados-financeiros-secundarios">

                <div>
                    <span>Abertura</span>

                    <strong>
                        <?= htmlspecialchars(
                            formatarData($conta['data_abertura'])
                        ) ?>
                    </strong>
                </div>

                <div>
                    <span>Fechamento</span>

                    <strong>
                        <?= htmlspecialchars(
                            formatarData($conta['data_fechamento'])
                        ) ?>
                    </strong>
                </div>

            </div>

            <?php if ($statusConta === 'ABERTA'): ?>

                <button
                    type="button"
                    class="botao botao-auditoria botao-largo"
                    id="encaminharAuditoria"
                >
                    <i class="fa-solid fa-magnifying-glass-dollar"></i>
                    Encaminhar para auditoria
                </button>

            <?php endif; ?>

            <?php if (in_array(
                $statusConta,
                ['ABERTA', 'EM_AUDITORIA'],
                true
            )): ?>

                <button
                    type="button"
                    class="botao botao-fechar botao-largo"
                    id="fecharConta"
                >
                    <i class="fa-solid fa-lock"></i>
                    Fechar conta hospitalar
                </button>

            <?php endif; ?>

            <?php if ($statusConta === 'FECHADA'): ?>

                <div class="mensagem-status-final">
                    <i class="fa-solid fa-circle-check"></i>

                    <span>
                        Conta fechada e pronta para faturamento.
                    </span>
                </div>

            <?php endif; ?>

            <?php if ($statusConta === 'FATURADA'): ?>

                <div class="mensagem-status-final faturada">
                    <i class="fa-solid fa-file-invoice"></i>

                    <span>
                        Conta faturada.
                    </span>
                </div>

            <?php endif; ?>

        </aside>

    </div>

</main>

<div
    class="modal-conta"
    id="modalNovoItem"
    hidden
>
    <div class="modal-conta-fundo"></div>

    <div
        class="modal-conta-conteudo"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloModalItem"
    >

        <div class="modal-conta-cabecalho">

            <div>
                <span>Novo lançamento</span>

                <h2 id="tituloModalItem">
                    Adicionar item à conta
                </h2>
            </div>

            <button
                type="button"
                class="fechar-modal"
                id="fecharModalItem"
                aria-label="Fechar"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>

        <form id="formNovoItem">

            <div class="modal-conta-corpo">

                <div class="form-grid">

                    <div class="campo">
                        <label for="tipoItem">
                            Categoria
                        </label>

                        <select
                            id="tipoItem"
                            name="tipo_item"
                            required
                        >
                            <option value="">
                                Selecione
                            </option>

                            <option value="DIARIA">
                                Diária
                            </option>

                            <option value="TAXA">
                                Taxa hospitalar
                            </option>

                            <option value="PROCEDIMENTO">
                                Procedimento
                            </option>

                            <option value="CIRURGIA">
                                Cirurgia
                            </option>

                            <option value="MEDICAMENTO">
                                Medicamento
                            </option>

                            <option value="MATERIAL">
                                Material
                            </option>

                            <option value="EXAME_LABORATORIAL">
                                Exame laboratorial
                            </option>

                            <option value="EXAME_IMAGEM">
                                Exame de imagem
                            </option>

                            <option value="HONORARIO">
                                Honorário
                            </option>

                            <option value="OUTRO">
                                Outro lançamento
                            </option>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="itemId">
                            Código do item
                        </label>

                        <input
                            id="itemId"
                            name="item_id"
                            type="number"
                            min="1"
                            placeholder="Opcional"
                        >
                    </div>

                    <div class="campo campo-largo">
                        <label for="descricaoItem">
                            Descrição
                        </label>

                        <input
                            id="descricaoItem"
                            name="descricao"
                            type="text"
                            maxlength="255"
                            placeholder="Ex.: Diária de enfermaria"
                            required
                        >
                    </div>

                    <div class="campo">
                        <label for="quantidadeItem">
                            Quantidade
                        </label>

                        <input
                            id="quantidadeItem"
                            name="quantidade"
                            type="number"
                            min="0.01"
                            step="0.01"
                            value="1"
                            required
                        >
                    </div>

                    <div class="campo">
                        <label for="valorUnitarioItem">
                            Valor unitário
                        </label>

                        <input
                            id="valorUnitarioItem"
                            name="valor_unitario"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0,00"
                            required
                        >
                    </div>

                    <div class="campo campo-largo total-preview">
                        <span>Valor total do item</span>

                        <strong id="previewValorTotal">
                            R$ 0,00
                        </strong>
                    </div>

                </div>

            </div>

            <div class="modal-conta-rodape">

                <button
                    type="button"
                    class="botao botao-secundario"
                    id="cancelarModalItem"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="botao botao-primario"
                    id="salvarItem"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Salvar item
                </button>

            </div>

        </form>

    </div>
</div>

<script>
    window.SafeHealthConta = {
        contaId: <?= (int) $contaId ?>,
        apiUrl: '/safe-health/modules/faturamento/conta-hospitalar/api.php'
    };
</script>

<script src="/safe-health/modules/faturamento/conta-hospitalar/conta-hospitalar.js"></script>

<?php

require_once __DIR__ . '/../../../includes/footer.php';