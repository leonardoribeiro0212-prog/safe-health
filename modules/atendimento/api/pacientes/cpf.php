<?php

declare(strict_types=1);

/**
 * Normaliza um CPF, mantendo somente os 11 algarismos.
 */
function normalizarCpf(string $cpf): string
{
    return preg_replace('/\D/', '', $cpf) ?? '';
}


function validarCpf(string $cpf): bool
{
    $cpf = normalizarCpf($cpf);

    if (strlen($cpf) !== 11) {
        return false;
    }

    // CPFs formados pelo mesmo algarismo não são válidos.
    if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
        return false;
    }

    for ($posicao = 9; $posicao <= 10; $posicao++) {
        $soma = 0;
        $peso = $posicao + 1;

        for ($i = 0; $i < $posicao; $i++, $peso--) {
            $soma += ((int) $cpf[$i]) * $peso;
        }

        $resto = $soma % 11;
        $digito = $resto < 2 ? 0 : 11 - $resto;

        if ((int) $cpf[$posicao] !== $digito) {
            return false;
        }
    }

    return true;
}
