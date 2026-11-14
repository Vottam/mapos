<?php
declare(strict_types=1);

if (! function_exists('dataIsoParaYmd')) {
    function dataIsoParaYmd($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y'] as $formato) {
            $data = DateTimeImmutable::createFromFormat('!' . $formato, $valor);
            $erros = DateTimeImmutable::getLastErrors();
            if ($data && ($erros === false || ($erros['warning_count'] ?? 0) === 0) && ($formato === 'Y-m-d' ? $data->format('Y-m-d') === $valor : $data->format('d/m/Y') === $valor)) {
                return $data->format('Y-m-d');
            }
        }
        return null;
    }
}

require dirname(__DIR__, 2) . '/application/helpers/os_helper.php';

function verificar(bool $condicao, string $mensagem): void
{
    if (! $condicao) {
        fwrite(STDERR, "FAIL {$mensagem}\n");
        exit(1);
    }
}

function igual($esperado, $real, string $mensagem): void
{
    if ($esperado !== $real) {
        fwrite(STDERR, "FAIL {$mensagem}: esperado=" . var_export($esperado, true) . " real=" . var_export($real, true) . "\n");
        exit(1);
    }
}

$agora = new DateTimeImmutable('2026-11-05 12:00:00', new DateTimeZone('America/Sao_Paulo'));

// A: previsão é preservada e a data efetiva é criada na transição.
$prevista = (object) ['status' => 'Em Aberto', 'dataFinal' => '2026-12-20', 'dataEntrega' => null];
$entregue = osDadosDaTransicaoDeEntrega(['status' => 'Entregue', 'dataFinal' => '2026-12-20'], $prevista, $agora);
igual('2026-12-20', $entregue['dataFinal'], 'data prevista preservada na entrega');
igual('2026-11-05', $entregue['dataEntrega'], 'data efetiva automática');

// B: reversão persistente limpa apenas a data efetiva e mantém a previsão.
$revertido = osDadosDaTransicaoDeEntrega(['status' => 'Em Aberto', 'dataFinal' => '2026-12-20'], (object) [
    'status' => 'Entregue', 'dataFinal' => '2026-12-20', 'dataEntrega' => '2026-11-05',
], $agora);
igual(null, $revertido['dataEntrega'], 'reversão remove data efetiva');
igual('2026-12-20', $revertido['dataFinal'], 'reversão recupera a previsão exata');

// C: sem previsão não recebe data inventada, antes ou depois da reversão.
$semPrevisao = osDadosDaTransicaoDeEntrega(['status' => 'Entregue', 'dataFinal' => null], (object) [
    'status' => 'Em Aberto', 'dataFinal' => null, 'dataEntrega' => null,
], $agora);
igual(null, $semPrevisao['dataFinal'], 'entrega sem previsão não inventa dataFinal');
igual('2026-11-05', $semPrevisao['dataEntrega'], 'entrega sem previsão registra data efetiva');
$semPrevisaoRevertida = osDadosDaTransicaoDeEntrega(['status' => 'Em Aberto', 'dataFinal' => null], (object) [
    'status' => 'Entregue', 'dataFinal' => null, 'dataEntrega' => '2026-11-05',
], $agora);
igual(null, $semPrevisaoRevertida['dataFinal'], 'reversão sem previsão continua sem dataFinal');
igual(null, $semPrevisaoRevertida['dataEntrega'], 'reversão sem previsão não inventa data');

// D: nova entrega após reversão é consistente e não cria coleção/duplicata.
$segundaEntrega = osDadosDaTransicaoDeEntrega(['status' => 'Entregue', 'dataFinal' => '2026-12-20'], (object) [
    'status' => 'Em Aberto', 'dataFinal' => '2026-12-20', 'dataEntrega' => null,
], $agora);
igual('2026-11-05', $segundaEntrega['dataEntrega'], 'segunda entrega consistente');
verificar(! is_array($segundaEntrega['dataEntrega']), 'data efetiva permanece escalar');

// E/F: salvar sem mudança e data manual têm precedência explícita.
$semMudanca = osDadosDaTransicaoDeEntrega(['status' => 'Entregue', 'dataFinal' => '2027-01-01'], (object) [
    'status' => 'Entregue', 'dataFinal' => '2026-12-20', 'dataEntrega' => '2026-11-05',
], $agora);
igual('2027-01-01', $semMudanca['dataFinal'], 'data manual não é sobrescrita');
igual('2026-11-05', $semMudanca['dataEntrega'], 'salvamento idempotente preserva efetiva');

// Garantia usa efetiva nova; legado sem dataEntrega continua compatível.
igual('2026-11-15', osVencimentoGarantia('2026-12-20', 10, 'Entregue', '2026-11-05'), 'garantia usa data efetiva');
igual('2026-12-30', osVencimentoGarantia('2026-12-20', 10, 'Entregue', null), 'garantia legada usa fallback');
igual(null, osVencimentoGarantia('2026-12-20', 10, 'Em Aberto', null), 'previsão não inicia garantia');

printf("PASS delivery-date reversibility scenarios A-F and warranty\n");
