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


$valor =
    $dados["valor"] ?? null;


// =====================================================
// VALIDAR VALOR
// =====================================================

if (!is_numeric($valor)) {

    echo json_encode([
        "sucesso" => false,
        "mensagem" =>
            "Digite um valor válido."
    ]);

    $conexao->close();

    exit;
}


$valor = (float)$valor;


if ($valor <= 0) {

    echo json_encode([
        "sucesso" => false,
        "mensagem" =>
            "O valor deve ser maior que zero."
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
    // CONSULTAR SALDO ATUAL
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
            "Erro ao consultar saldo."
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
    // VERIFICAR SALDO
    // =================================================

    if ($valor > $saldoAtual) {

        throw new Exception(
            "Saldo insuficiente para realizar a transferência."
        );

    }


    // =================================================
    // SUBTRAIR O VALOR DO SALDO
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
            "Erro ao atualizar saldo."
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
    // REGISTRAR TRANSAÇÃO
    // =================================================

    $tipo =
        "TRANSFERENCIA";


    $descricao =
        "Transferência bancária";


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
            "Erro ao registrar transferência."
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
            "Não foi possível registrar a transferência."
        );

    }


    $stmt->close();


    // =================================================
    // CONFIRMAR ALTERAÇÕES
    // =================================================

    $conexao->commit();


    // =================================================
    // BUSCAR SALDO ATUALIZADO
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
    // ENVIAR RESPOSTA PARA O JAVASCRIPT
    // =================================================

    echo json_encode([

        "sucesso" => true,

        "mensagem" =>
            "Transferência realizada com sucesso!",

        "valor" =>
            $valor,

        "saldo_atual" =>
            $novoSaldo

    ]);


} catch (Exception $erro) {


    // =================================================
    // SE ALGO DER ERRADO, DESFAZER TUDO
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