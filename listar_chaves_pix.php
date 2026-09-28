<?php

header("Content-Type: application/json; charset=UTF-8");


$ID_CONTA = 1;


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


if ($conexao->connect_error) {

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro ao conectar ao banco."
    ]);

    exit;
}


$conexao->set_charset("utf8mb4");


// Descobrir usuário da conta

$sql = "
    SELECT id_usuario
    FROM contas
    WHERE id_conta = ?
";


$stmt = $conexao->prepare($sql);

$stmt->bind_param(
    "i",
    $ID_CONTA
);

$stmt->execute();

$resultado =
    $stmt->get_result();


if ($resultado->num_rows === 0) {

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Conta não encontrada."
    ]);

    exit;
}


$conta =
    $resultado->fetch_assoc();

$id_usuario =
    $conta["id_usuario"];

$stmt->close();


// Buscar chaves

$sql = "
    SELECT
        id_chave,
        tipo_chave,
        chave,
        data_cadastro
    FROM chaves_pix
    WHERE id_usuario = ?
    ORDER BY data_cadastro DESC
";


$stmt =
    $conexao->prepare($sql);


$stmt->bind_param(
    "i",
    $id_usuario
);


$stmt->execute();


$resultado =
    $stmt->get_result();


$chaves = [];


while ($linha = $resultado->fetch_assoc()) {

    $chaves[] = $linha;

}


echo json_encode([

    "sucesso" => true,

    "chaves" => $chaves

]);


$stmt->close();
$conexao->close();

?>