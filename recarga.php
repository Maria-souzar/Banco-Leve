<?php

header("Content-Type: application/json; charset=UTF-8");


// =====================================================
// CONTA UTILIZADA PELO SISTEMA
// =====================================================

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


if ($conexao->connect_error) {

    echo json_encode([
        "sucesso" => false,
        "mensagem" =>
            "Erro ao conectar ao banco de dados."
    ]);

    exit;

}


$conexao->set_charset("utf8mb4");


// =====================================================
// RECEBER DADOS DO JAVASCRIPT
// =====================================================

$dados = json_decode(
    file_get_contents("php://input"),
    true
);


$numero =
    trim(
        $dados["numero"] ?? ""
    );


$valor =
    $dados["valor"] ?? null;


// =====================================================
// VALIDAR NÚMERO
// =====================================================

if ($numero === "") {

    echo json_encode([
        "sucesso" => false,
        "mensagem" =>
            "Número do celular não informado."
    ]);

    $conexao->close();

    exit;

}


// =====================================================
// VALIDAR VALOR
// =====================================================

if (!is_numeric($valor)) {

    echo json_encode([
        "sucesso" => false,
        "mensagem" =>
            "Valor de recarga inválido."
    ]);

    $conexao->close();

    exit;

}


$valor = (float)$valor;


// =====================================================
// PERMITIR SOMENTE OS VALORES DO HTML
// =====================================================

$valoresPermitidos = [
    10,
    20,
    30,
    50
];


if (!in_array(
    $valor,
    $valoresPermitidos,
    true
)) {

    echo json_encode([
        "sucesso" => false,
        "mensagem" =>
            "Valor de recarga não permitido."
    ]);

    $conexao->close();

    exit;

}


// =====================================================
// INICIAR TRANSAÇÃO
// =====================================================

$conexao->begin_transaction();


try {


    // =================================================
    // CONSULTAR SALDO
    // =================================================

    $sql = "
        SELECT saldo
        FROM contas
        WHERE id_conta = ?
        FOR UPDATE
    ";


    $stmt =
        $conexao->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Erro ao consultar o saldo."
        );

    }


    $stmt->bind_param(
        "i",
        $ID_CONTA
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    if ($resultado->num_rows === 0) {

        throw new Exception(
            "Conta não encontrada."
        );

    }


    $conta =
        $resultado->fetch_assoc();


    $saldoAtual =
        (float)$conta["saldo"];


    $stmt->close();


    // =================================================
    // VERIFICAR SALDO SUFICIENTE
    // =================================================

    if ($valor > $saldoAtual) {

        throw new Exception(
            "Saldo insuficiente para realizar a recarga."
        );

    }


    // =================================================
    // SUBTRAIR RECARGA DO SALDO
    // =================================================

    $sql = "
        UPDATE contas
        SET saldo = saldo - ?
        WHERE id_conta = ?
    ";


    $stmt =
        $conexao->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Erro ao atualizar o saldo."
        );

    }


    $stmt->bind_param(
        "di",
        $valor,
        $ID_CONTA
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Não foi possível atualizar o saldo."
        );

    }


    $stmt->close();


    // =================================================
    // REGISTRAR RECARGA
    // =================================================

    $tipo =
        "RECARGA";


    $descricao =
        "Recarga de celular - " . $numero;


    $sql = "
        INSERT INTO transacoes
        (
            id_conta,
            tipo,
            descricao,
            valor
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?
        )
    ";


    $stmt =
        $conexao->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Erro ao registrar a recarga."
        );

    }


    $stmt->bind_param(
        "issd",
        $ID_CONTA,
        $tipo,
        $descricao,
        $valor
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Não foi possível registrar a recarga."
        );

    }


    $stmt->close();


    // =================================================
    // CONFIRMAR TRANSAÇÃO
    // =================================================

    $conexao->commit();


    // =================================================
    // CONSULTAR NOVO SALDO
    // =================================================

    $sql = "
        SELECT saldo
        FROM contas
        WHERE id_conta = ?
    ";


    $stmt =
        $conexao->prepare($sql);


    $stmt->bind_param(
        "i",
        $ID_CONTA
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    $conta =
        $resultado->fetch_assoc();


    $novoSaldo =
        $conta["saldo"];


    $stmt->close();


    // =================================================
    // RESPOSTA
    // =================================================

    echo json_encode([

        "sucesso" => true,

        "mensagem" =>
            "Recarga realizada com sucesso!",

        "numero" =>
            $numero,

        "valor" =>
            $valor,

        "saldo_atual" =>
            $novoSaldo

    ]);


} catch (Exception $erro) {


    // =================================================
    // DESFAZER CASO OCORRA ERRO
    // =================================================

    $conexao->rollback();


    echo json_encode([

        "sucesso" => false,

        "mensagem" =>
            $erro->getMessage()

    ]);

}


$conexao->close();

?>