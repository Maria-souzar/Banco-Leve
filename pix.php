<?php

header("Content-Type: application/json; charset=UTF-8");


// =====================================================
// IDENTIFICAÇÃO DA CONTA
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
        "mensagem" => "Erro ao conectar ao banco de dados."
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


$chave = trim($dados["chave"] ?? "");

$valor = $dados["valor"] ?? null;


// =====================================================
// VALIDAR CHAVE
// =====================================================

if ($chave === "") {

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Digite uma chave Pix."
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
        "mensagem" => "Digite um valor válido."
    ]);

    $conexao->close();

    exit;
}


$valor = (float)$valor;


if ($valor <= 0) {

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "O valor deve ser maior que zero."
    ]);

    $conexao->close();

    exit;
}


// =====================================================
// INICIAR TRANSAÇÃO DO BANCO
// =====================================================

$conexao->begin_transaction();


try {


    // =================================================
    // VERIFICAR SE A CHAVE PIX EXISTE
    // =================================================

    $sql = "
        SELECT id_chave, id_usuario, tipo_chave, chave
        FROM chaves_pix
        WHERE chave = ?
    ";

    $stmt = $conexao->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "Erro ao consultar a chave Pix."
        );
    }

    $stmt->bind_param(
        "s",
        $chave
    );

    $stmt->execute();

    $resultado = $stmt->get_result();


    if ($resultado->num_rows === 0) {

        throw new Exception(
            "Chave Pix não encontrada."
        );
    }


    $dadosChave = $resultado->fetch_assoc();

    $stmt->close();


    // =================================================
    // BUSCAR SALDO DA CONTA
    // =================================================

    $sql = "
        SELECT saldo
        FROM contas
        WHERE id_conta = ?
        FOR UPDATE
    ";

    $stmt = $conexao->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "Erro ao consultar a conta."
        );
    }

    $stmt->bind_param(
        "i",
        $ID_CONTA
    );

    $stmt->execute();

    $resultado = $stmt->get_result();


    if ($resultado->num_rows === 0) {

        throw new Exception(
            "Conta não encontrada."
        );
    }


    $conta = $resultado->fetch_assoc();

    $saldoAtual = (float)$conta["saldo"];

    $stmt->close();


    // =================================================
    // VERIFICAR SALDO
    // =================================================

    if ($valor > $saldoAtual) {

        throw new Exception(
            "Saldo insuficiente."
        );
    }


    // =================================================
    // ATUALIZAR SALDO
    // =================================================

    $sql = "
        UPDATE contas
        SET saldo = saldo - ?
        WHERE id_conta = ?
    ";

    $stmt = $conexao->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "Erro ao preparar atualização do saldo."
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

    $tipo = "PIX";

    $descricao =
        "Pagamento PIX - Chave: " . $chave;


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


    $stmt = $conexao->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "Erro ao registrar a transação."
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
            "Não foi possível registrar a transação."
        );
    }


    $stmt->close();


    // =================================================
    // CONFIRMAR TUDO
    // =================================================

    $conexao->commit();


    // =================================================
    // BUSCAR NOVO SALDO
    // =================================================

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

    $conta = $resultado->fetch_assoc();

    $novoSaldo = $conta["saldo"];

    $stmt->close();


    // =================================================
    // RESPOSTA
    // =================================================

    echo json_encode([

        "sucesso" => true,

        "mensagem" =>
            "PIX realizado com sucesso.",

        "valor" =>
            number_format(
                $valor,
                2,
                ".",
                ""
            ),

        "saldo_atual" =>
            $novoSaldo

    ]);


} catch (Exception $erro) {


    // =================================================
    // DESFAZER OPERAÇÃO
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