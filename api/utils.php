<?php
/**
 * ============================================
 * UTILITÁRIOS - GERADOR DE DADOS FICTÍCIOS
 * ============================================
 */

function randomItem(array $arr): string {
    return $arr[array_rand($arr)];
}

/**
 * Gera um CPF válido com dígitos verificadores.
 */
function gerarCPF(): string {
    $n = [];
    for ($i = 0; $i < 9; $i++) {
        $n[] = rand(0, 9);
    }

    // Dígito 1
    $d1 = 0;
    for ($i = 0; $i < 9; $i++) {
        $d1 += $n[$i] * (10 - $i);
    }
    $d1 = 11 - ($d1 % 11);
    if ($d1 >= 10) $d1 = 0;
    $n[] = $d1;

    // Dígito 2
    $d2 = 0;
    for ($i = 0; $i < 10; $i++) {
        $d2 += $n[$i] * (11 - $i);
    }
    $d2 = 11 - ($d2 % 11);
    if ($d2 >= 10) $d2 = 0;
    $n[] = $d2;

    return sprintf('%d%d%d.%d%d%d.%d%d%d-%d%d', ...$n);
}

/**
 * Gera um telefone brasileiro com DDD real.
 */
function gerarTelefone(): string {
    $ddds = [11,21,31,41,51,61,71,81,85,27,48,47,19,15,12,13,14,16,17,18,62,65,67,68,69,82,83,84,86,87,88,91,92,93,94,95,96,97,98,99];
    $ddd = randomItem($ddds);
    $num = rand(10000000, 99999999);
    $numStr = (string) $num;
    return sprintf('(%s) 9%s-%s', $ddd, substr($numStr, 0, 4), substr($numStr, 4));
}

/**
 * Gera dados fictícios completos (nome, email, cpf, telefone).
 */
function gerarDadosFicticios(): array {
    $primeirosNomes = ["Ana","Joao","Maria","Carlos","Lucas","Sofia","Pedro","Fernanda","Eduardo","Isabela","Gustavo","Beatriz","Ricardo","Patricia","Roberto","Juliana","Felipe","Larissa","Thiago","Camila","Bruno","Amanda","Rafael","Mariana","Vinicius","Gabriel","Leticia","Mateus","Aline","Diego","Renata","Leonardo","Bianca","Anderson","Daniela","Murilo","Natalia","Vitor","Vanessa","Caio","Priscila","Andre","Tatiane","Henrique","Julia","Marcelo","Yasmin","Leandro","Paula","Fabio"];

    $sobrenomes = ["Silva","Santos","Oliveira","Souza","Rodrigues","Ferreira","Almeida","Costa","Gomes","Martins","Araujo","Melo","Barbosa","Ribeiro","Carvalho","Lima","Pereira","Alves","Monteiro","Cardoso","Dias","Teixeira","Correia","Nogueira","Campos","Rocha","Rezende","Castro","Moura","Freitas","Batista","Moreira","Pinto","Cavalcanti","Machado","Vieira","Farias","Miranda","Mendes","Borges","Nunes","Tavares","Moraes","Duarte","Peixoto","Coelho","Lopes","Marques","Sales","Fonseca"];

    $nome = randomItem($primeirosNomes);
    $sobrenome = randomItem($sobrenomes);
    $digitos = rand(100, 999);
    $email = strtolower($nome) . strtolower($sobrenome) . $digitos . '@gmail.com';

    return [
        'nome' => "$nome $sobrenome",
        'email' => $email,
        'cpf' => gerarCPF(),
        'telefone' => gerarTelefone(),
    ];
}
