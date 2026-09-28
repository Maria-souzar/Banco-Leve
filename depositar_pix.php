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


$chave = trim(
    $dados["chave"] ?? ""
);


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
// INICIAR TRANSAÇÃO
// =====================================================

$conexao->begin_transaction();


try {


    // =================================================
    // DESCOBRIR O USUÁRIO DA CONTA
    // =================================================

    $sql = "
        SELECT id_usuario
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


    $resultado =
        $stmt->get_result();


    if ($resultado->num_rows === 0) {

        throw new Exception(
            "Conta não encontrada."
        );

    }


    $conta =
        $resultado->fetch_assoc();


    $id_usuario =
        $conta["id_usuario"];


    $stmt->close();


    // =================================================
    // VERIFICAR SE A CHAVE JÁ EXISTE
    // =================================================

    $sql = "
        SELECT id_chave
        FROM chaves_pix
        WHERE chave = ?
    ";


    $stmt =
        $conexao->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Erro ao verificar chave Pix."
        );

    }


    $stmt->bind_param(
        "s",
        $chave
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    // =================================================
    // CADASTRAR CHAVE CASO NÃO EXISTA
    // =================================================

    if ($resultado->num_rows === 0) {


        // ---------------------------------------------
        // IDENTIFICAR TIPO DA CHAVE
        // ---------------------------------------------

        if (filter_var($chave, FILTER_VALIDATE_EMAIL)) {

            $tipo_chave = "EMAIL";

        } elseif (
            preg_match(
                '/^[0-9]{11}$/',
                preg_replace('/\D/', '', $chave)
            )
        ) {

            $tipo_chave = "CPF";

        } elseif (
            preg_match(
                '/^[0-9]{10,13}$/',
                preg_replace('/\D/', '', $chave)
            )
        ) {

            $tipo_chave = "CELULAR";

        } else {

            $tipo_chave = "OUTRA";

        }


        // ---------------------------------------------
        // CADASTRAR CHAVE
        // ---------------------------------------------

        $stmt->close();


        $sql = "
            INSERT INTO chaves_pix
            (
                id_usuario,
                tipo_chave,
                chave
            )
            VALUES
            (
                ?,
                ?,
                ?
            )
        ";


        $stmt =
            $conexao->prepare($sql);


        if (!$stmt) {

            throw new Exception(
                "Erro ao preparar cadastro da chave."
            );

        }


        $stmt->bind_param(
            "iss",
            $id_usuario,
            $tipo_chave,
            $chave
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Não foi possível cadastrar a chave Pix."
            );

        }


    } else {

        $stmt->close();

    }


    // =================================================
    // ADICIONAR VALOR AO SALDO
    // =================================================

    $sql = "
        UPDATE contas
        SET saldo = saldo + ?
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

    $tipo = "DEPOSITO_PIX";


    $descricao =
        "Depósito via PIX - Chave: " . $chave;


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
            "Erro ao registrar depósito."
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
            "Não foi possível registrar o depósito."
        );

    }


    $stmt->close();


    // =================================================
    // CONFIRMAR TODAS AS OPERAÇÕES
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
    // RESPOSTA PARA O HTML
    // =================================================

    echo json_encode([

        "sucesso" => true,

        "mensagem" =>
            "Depósito realizado com sucesso!",

        "valor" =>
            $valor,

        "saldo_atual" =>
            $novoSaldo

    ]);


} catch (Exception $erro) {


    // =================================================
    // DESFAZER TUDO SE ALGUMA ETAPA FALHAR
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