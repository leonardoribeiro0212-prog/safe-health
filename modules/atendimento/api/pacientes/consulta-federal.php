<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/cpf.php';

function respostaConsulta(int $status, bool $sucesso, string $codigo, string $mensagem, array $extras = []): void {
    http_response_code($status);
    echo json_encode(array_merge([
        'sucesso' => $sucesso,
        'codigo' => $codigo,
        'mensagem' => $mensagem,
        'fonte' => 'NAO_VERIFICADO',
        'consulta_oficial' => false
    ], $extras), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respostaConsulta(405, false, 'METODO_INVALIDO', 'Método não permitido.');
}
if (empty($_SESSION['usuario_id']) && empty($_SESSION['id_usuario'])) {
    respostaConsulta(401, false, 'NAO_AUTENTICADO', 'Entre no sistema para consultar.');
}
$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    respostaConsulta(400, false, 'REQUISICAO_INVALIDA', 'Dados inválidos.');
}
$cpf = normalizarCpf((string) ($entrada['cpf'] ?? ''));
$nascimento = (string) ($entrada['data_nascimento'] ?? '');
if (!validarCpf($cpf)) {
    respostaConsulta(422, false, 'CPF_INVALIDO', 'CPF matematicamente inválido.');
}
$data = DateTimeImmutable::createFromFormat('!Y-m-d', $nascimento);
if (!$data || $data->format('Y-m-d') !== $nascimento || $data > new DateTimeImmutable('today')) {
    respostaConsulta(422, false, 'NASCIMENTO_INVALIDO', 'Informe uma data de nascimento válida.');
}

/*
 * AMBIENTE DE TESTES LOCAL — NÃO consulta Receita Federal / SERPRO.
 * Habilitar SOMENTE para demonstração, com variável de ambiente:
 * SAFE_HEALTH_CPF_MODE=demo
 *
 * Nenhum CPF/data é enviado a serviços externos.
 * Nunca use respostas DEMO como prova de situação cadastral.
 */
$modo = strtolower(trim((string) (getenv('SAFE_HEALTH_CPF_MODE') ?: 'disabled')));
if ($modo !== 'demo') {
    respostaConsulta(503, false, 'INTEGRACAO_NAO_CONFIGURADA',
        'Consulta oficial ainda não configurada. O cadastro pode continuar sem verificação federal.');
}
if ($cpf === '52998224725' && $nascimento === '2000-01-01') {
    respostaConsulta(200, true, 'DEMO_REGULAR',
        'SIMULAÇÃO: dados de teste correspondentes. NÃO é uma consulta à Receita Federal.', [
            'fonte' => 'SIMULACAO_LOCAL',
            'situacao' => 'REGULAR_SIMULADA',
            'ambiente' => 'DEMONSTRACAO'
        ]);
}
if ($cpf === '52998224725') {
    respostaConsulta(200, false, 'DEMO_DIVERGENCIA',
        'SIMULAÇÃO: data de nascimento divergente. NÃO é uma consulta à Receita Federal.', [
            'fonte' => 'SIMULACAO_LOCAL',
            'situacao' => 'DIVERGENCIA_SIMULADA',
            'ambiente' => 'DEMONSTRACAO'
        ]);
}
respostaConsulta(200, false, 'DEMO_SEM_CENARIO',
    'SIMULAÇÃO: não há cenário configurado para este CPF. Cadastro permitido sem verificação federal.', [
        'fonte' => 'SIMULACAO_LOCAL',
        'ambiente' => 'DEMONSTRACAO'
    ]);
