<?php
header('Content-Type: application/json');

// Captura os parâmetros da URL
$nome = $_GET['nome'] ?? '';
$cpf = $_GET['cpf'] ?? '';

// Formata o payload
$payload = json_encode([
    "nome" => $nome,
    "cpf" => $cpf,
    "valor" => "67.56",
    "descricao" => "FRONT",
    "utm" => "SOE"
]);

// Inicia a requisição para o gateway
$ch = curl_init("https://seupedidorastreado.site/api/v1/generate-pixmoon.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Apikey: fredkrueger_1414701096"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$response = curl_exec($ch);
curl_close($ch);

// Retorna a resposta original da API
echo $response;
