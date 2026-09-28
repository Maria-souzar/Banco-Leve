<?php

header("Content-Type: application/json; charset=UTF-8");


// =====================================================
// CONTA UTILIZADA PELO SISTEMA
// =====================================================

// Este identificador será utilizado pelos outros PHPs.
// Se futuramente precisarmos trocar a conta,
// alteramos este valor.
$ID_CONTA = 1;


// =====================================================
// CONEXÃO COM O BANCO
// =====================================================

$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "banco_leve";

$conexao = new mysqli(
    $servidor,
    $usuario,
    $senha,
    $banco
);


// Verificar conexão
if ($conexao->connect_error) {

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro ao conectar ao banco de dados."
    ]);

    exit;
}


$conexao->set_charset("utf8mb4");


// =====================================================
// BUSCAR SALDO
// =====================================================

$sql = "
    SELECT saldo
    FROM contas
    WHERE id_conta = ?
";


$stmt = $conexao->prepare($sql);

$stmt->bind_param(
    "i",
    $ID_CONTA
);

$stmt->execute();

$resultado = $stmt->get_result();


// =====================================================
// VERIFICAR CONTA
// =====================================================

if ($resultado->num_rows === 0) {

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Conta não encontrada."
    ]);

    $stmt->close();
    $conexao->close();

    exit;
}


// =====================================================
// RETORNAR SALDO
// =====================================================

$conta = $resultado->fetch_assoc();

$saldo = $conta["saldo"];


echo json_encode([
    "sucesso" => true,
    "saldo" => $saldo
]);


$stmt->close();
$conexao->close();

?>