<?php
session_start();
header('Content-Type: application/json');

if (!isset($_GET['cpf'])) {
    echo json_encode(["status" => 400, "message" => "CPF é obrigatório."]);
    exit;
}

$cpf = preg_replace('/\D/', '', $_GET['cpf']);
$cep = isset($_GET['cep']) ? preg_replace('/\D/', '', $_GET['cep']) : null;

// Nova API de CPF
$api_cpf_url = "https://api.bluenext.site/api/v1/consult/$cpf";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_cpf_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer cd92b4a851d94a2ff9d9f63148e80aa6765c8f079044c373d679fa7c98484635'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Ignorar verificação SSL (apenas para testes)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$cpf_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($cpf_response === false || $http_code !== 200) {
    $error = curl_error($ch);
    curl_close($ch);
    echo json_encode(["status" => 500, "message" => "Erro ao buscar dados do CPF: $error"]);
    exit;
}

curl_close($ch);

$cpf_data = json_decode($cpf_response, true);

if (isset($cpf_data['success']) && $cpf_data['success'] === false) {
    echo json_encode(["status" => 404, "message" => "CPF não encontrado."]);
    exit;
}

$_SESSION['dadosBasicos'] = [
    "nome" => $cpf_data['NOME'] ?? "Não informado",
    "cpf" => $cpf_data['CPF'] ?? "Não informado",
    "nascimento" => $cpf_data['NASC'] ?? "Não informado",
    "sexo" => $cpf_data['SEXO'] ?? "Não informado",
];

if ($cep) {
    $api_cep_url = "http://opencep.com/v1/$cep.json";
    $cep_response = file_get_contents($api_cep_url);

    if ($cep_response !== false) {
        $cep_data = json_decode($cep_response, true);
        if (!isset($cep_data['erro'])) {
            $_SESSION['dadosBasicos'] += [
                "cep" => $cep_data['cep'] ?? "Não informado",
                "logradouro" => $cep_data['logradouro'] ?? "Não informado",
                "bairro" => $cep_data['bairro'] ?? "Não informado",
                "municipio" => $cep_data['localidade'] ?? "Não informado",
                "uf" => $cep_data['uf'] ?? "Não informado"
            ];
        }
    }
}

echo json_encode(["status" => 200, "message" => "Dados salvos."]);