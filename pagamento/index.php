<?php
/* Observação:
esse checkout é uma demo visual e 100% funcional pra Dutty,
mas o valor está sendo setado aqui no front lá em formData, no JS.
o ideal é colocar isso apenas no back-end, e no front só colocar multiplicadores (qntd. de titulos, por exemplo)
e então multiplicar no back a quantidade pelo valor da unidade.

edit: este checkout foi adaptado. nem todas as opções de personalização são funcionais ainda; é quase um beta do meu checkout próprio.
*/

include('../nlo-config.php');

$valor_exibicao = "R$ " . number_format($valor, 2, ',', '.');

// Arrays de nomes e sobrenomes
$primeirosNomes = [
    "Ana", "João", "Maria", "Carlos", "Lucas", "Sofia", "Pedro", "Fernanda", "Eduardo", 
    "Isabela", "Gustavo", "Beatriz", "Ricardo", "Patrícia", "Roberto", "Juliana", 
    "Felipe", "Larissa", "Thiago", "Julio", "Cláudia", "Vitor", "Bruna", "Renato", "Vanessa"
];
$sobrenomes = [
    "Silva", "Santos", "Oliveira", "Costa", "Pereira", "Almeida", "Martins", "Rodrigues", 
    "Melo", "Dias", "Souza", "Nascimento", "Barbosa", "Araujo", "Cavalcanti", "Campos", 
    "Pinto", "Lima", "Carvalho", "Gomes", "Ferreira", "Ribeiro", "Castro", "Mendes", 
    "Azevedo", "Fernandes", "Morais", "Vieira", "Faria", "Pimentel"
];
$terceirosNomes = [
    "Lima", "Gomes", "Ribeiro", "Ferreira", "Mendes", "Azevedo", "Carvalho", "Fernandes", 
    "Figueiredo", "Moura", "Rocha", "Teixeira", "Silveira", "Lopes", "Santana", "Pereira", 
    "Alves", "Sá", "Castro", "Machado", "Fontes", "Mello", "Pimentel", "Tavares", "Barreto", 
    "Assis", "Leal", "Cunha", "Rezende", "Borges"
];

// Gerar nome aleatório
$primeiroNome = $primeirosNomes[array_rand($primeirosNomes)];
$sobrenome = $sobrenomes[array_rand($sobrenomes)];

// Garantir que o terceiro nome não seja igual ao sobrenome
do {
    $terceiroNome = $terceirosNomes[array_rand($terceirosNomes)];
} while ($terceiroNome == $sobrenome);

$nomeCompleto = $primeiroNome . " " . $sobrenome . " " . $terceiroNome;

// Gerar e-mail
$nomeFormatado = strtolower(preg_replace('/\s+/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nomeCompleto)));
$dataNascimento = str_pad(rand(1, 31), 2, '0', STR_PAD_LEFT) . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
$emailDomains = ["@gmail.com", "@hotmail.com", "@outlook.com", "@yahoo.com", "@icloud.com"];
$dominio = $emailDomains[array_rand($emailDomains)];

$email = $nomeFormatado . $dataNascimento . $dominio;

// Gera o CPF
$cpf = "";

// Gera os 9 primeiros dígitos aleatórios
for ($i = 0; $i < 9; $i++) {
    $cpf .= rand(0, 9);
};

// Calcula os 2 dígitos verificadores
$cpf .= calcularDigitoVerificador($cpf, 1);  // Calcula o 1º dígito verificador
$cpf .= calcularDigitoVerificador($cpf, 2);  // Calcula o 2º dígito verificador

// Função para calcular o dígito verificador
function calcularDigitoVerificador($cpf, $digito) {
    $soma = 0;
    $multiplicador = ($digito === 1) ? 10 : 11;
    for ($i = 0; $i < strlen($cpf); $i++) {
        $soma += (int)$cpf[$i] * ($multiplicador - $i);
    }

    $resto = $soma % 11;
    $digitoVerificador = ($resto < 2) ? 0 : 11 - $resto;

    return $digitoVerificador;
};

// Gerar um DDD aleatório entre 11 e 99
$ddd = rand(11, 99);

// Decidir se o número terá o dígito 9 ou não (50% de chance)
$comDigito9 = rand(0, 1) === 1;

// Se o número tiver o dígito 9, gerar o número com 9 + 8 dígitos
if ($comDigito9) {
    $telefone = $ddd . "9" . rand(10000000, 99999999); // 8 dígitos + 9
} else {
    $telefone = $ddd . rand(10000000, 99999999); // 8 dígitos sem 9
};

//$upsell = $upsell . "?cpf=" . $cpf . "&nome=" . $nome . "&telefone=" . $telefone;
?>

<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Checkout</title>
	<meta name="description" content="Plataforma de pagamentos" />
	<link rel="apple-touch-icon" href="<?php echo $icon_url; ?>">
	<link rel="icon" href="<?php echo $icon_url; ?>">
    <link rel="stylesheet" href="css/styles.css" />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <?php
    echo $pixel_scripts;
    
    if($track_fb_pixel == 1){ ?>
    <!-- Meta Pixel Code -->
    <script>
      !function(f,b,e,v,n,t,s)
      {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};
      if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
      n.queue=[];t=b.createElement(e);t.async=!0;
      t.src='https://connect.facebook.net/en_US/fbevents.js';
      s=b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t,s)}(window, document,'script');
    
      fbq('init', '<?php echo $fb_pixel; ?>'); // Substitua com o ID do seu pixel
      fbq('track', 'PageView');
    </script>
    <noscript>
      <img height="1" width="1" style="display:none"
           src="https://www.facebook.com/tr?id=<?php echo $fb_pixel; ?>&ev=PageView&noscript=1"/>
    </noscript>
    <!-- End Meta Pixel Code -->
    <?php }; ?>
	<style>
    /* outros visuais */
    .contrib h3 {margin: 1rem 0; font-weight:500}
    .contrib span {margin: 1rem 0; font-weight:500}
    .valor-total h3 {margin: 1rem 0; font-weight:500;}
    .valor-total span {color: green; font-weight:600}
	</style>
	<body>
    <main>
      <!-- Tela do PIX (inicialmente oculta) -->
      <div id="pixScreen" style="display: none">

		<div class="pix-container">
        <img src="<?php echo $logo_url; ?>" alt="Logo" style="
                  display: block;
                  margin: auto;
                  padding: 0;
                  width: auto;
                  height: 32px;
				  margin-bottom:1rem;
                ">
            <div class="contrib">
                <h3 style="
                    margin: 10px 0 5px;
                    font-size: 1.2rem;
                    color: #484848;
                    text-transform: uppercase;
                    font-weight: bold;
                ">
                    <?php echo $checkoutTitulo; ?>
                </h3>
                <span style="
                    margin: 0 0 20px;
                    font-size: 0.8rem;
                    color: rgba(72, 72, 72, 0.6);
                    font-family: Arial, sans-serif
                ">
                    <?php echo $checkoutDesc; ?>
                </span>
            </div>
			<div class="qr-code-container">
			  <div id="qrcode"></div>
			</div>

			<div class="valor-total"><h3>Valor total: <span id="total-exibicao"><?php echo $valor_exibicao; ?></span></h3></div>
			<p class="pix-copy-text">
			  Copie o código Pix abaixo e cole em seu app para finalizar o pagamento.
			</p>

			<div class="pix-code-container">
			  <input type="text" id="pixCode" readonly />
			  <button id="copyPixButton" class="copy-button">
				<svg viewBox="0 0 24 24" width="24" height="24">
				  <path
					d="M20 2H10c-1.103 0-2 .897-2 2v4H4c-1.103 0-2 .897-2 2v10c0 1.103.897 2 2 2h10c1.103 0 2-.897 2-2v-4h4c1.103 0 2-.897 2-2V4c0-1.103-.897-2-2-2zM4 20V10h10l.002 10H4zm16-6h-4v-4c0-1.103-.897-2-2-2h-4V4h10v10z"
				  ></path>
				</svg>
				COPIAR CÓDIGO
			  </button>
			</div>

			<div class="payment-instructions">
			  <h3 style="text-align:center">Como pagar?</h3>
			  <div class="instruction-step">
				<div class="step-icon">
				  <i class="ph ph-bank"></i>
				</div>
				<p>Abra o app do seu banco e entre no ambiente Pix</p>
			  </div>
			  <div class="instruction-step">
				<div class="step-icon">
				  <i class="ph ph-qr-code"></i>
				</div>
				<p>
				  Escolha Pagar com QR Code e aponte a câmera para o código ao lado.
				</p>
			  </div>
			  <div class="instruction-step">
				<div class="step-icon">
				  <i class="ph ph-check-circle"></i>
				</div>
				<p>Confirme as informações e finalize seu pagamento.</p>
			  </div>
			</div>
		</div>
        <footer>
          <button class="security-button">
            <svg viewBox="0 0 24 24" class="security-icon">
              <path
                d="M11.488 21.754c.294.157.663.156.957-.001 8.012-4.304 8.581-12.713 8.574-15.104a.988.988 0 0 0-.596-.903l-8.05-3.566a1.005 1.005 0 0 0-.813.001L3.566 5.747a.99.99 0 0 0-.592.892c-.034 2.379.445 10.806 8.514 15.115zM8.674 10.293l2.293 2.293 4.293-4.293 1.414 1.414-5.707 5.707-3.707-3.707 1.414-1.414z"
              ></path>
            </svg>
            Ambiente seguro
          </button>
        </footer>
      </div>

      <!-- Adicione após o form e antes da tela do PIX -->
      <div id="loadingScreen" class="loading-screen" style="display: none">
        <div class="loading-content">
          <div class="loading-spinner"></div>
          <h2>Gerando seu PIX...</h2>
          <p>Aguarde um momento</p>
        </div>
      </div>
    </main>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
	<script>
		const upsellUrl = "<?php echo $upsell; ?>";
		const oferta = "<?php echo $oferta; ?>";
		let valorTotal = <?php echo $valor; ?>; // Preço base (PHP)
        let id_transacao = null;

		// Dados predefinidos para gerar o PIX automaticamente
		const nomePreDefinido = "<?php echo $nomeCompleto; ?>"; // Nome do cliente
		const emailPreDefinido = "<?php echo $email; ?>";
		const cpfPreDefinido = "<?php echo $cpf; ?>";
		const telefonePreDefinido = "<?php echo $telefone; ?>"; // Telefone do cliente (sem máscara)
		const valorPreDefinido = <?php echo $valor; ?>; // Valor do PIX - Formato (int) xx.xx, separados por ponto

	// Adiciona os listeners para os campos
	document.addEventListener("DOMContentLoaded", function () {
		// Mostra a tela de loading
		document.getElementById("loadingScreen").style.display = "flex";

		// Puxar parâmetros
		const params = new URLSearchParams(window.location.search);

		// Remover parâmetros específicos do envio de UTM
		params.delete("valor");
		//params.delete("email");
		//params.delete("nome");
		//params.delete("cpf");
		//params.delete("telefone");
		//params.delete("titulos");

		// Garantir que algo será enviado, mesmo que vazio
		const utmString = params.toString() || "utm_source=direct";

		const formData = {
			acao: "criar",
			oferta: oferta,
			valor: valorTotal, // Valor do PIX
			nome: nomePreDefinido,
			email: emailPreDefinido,
			cpf: cpfPreDefinido,     // CPF do cliente
			telefone: telefonePreDefinido,
			utm: encodeURIComponent(utmString)
		};

			const url = new URL("api/gateway.php", window.location);
			Object.keys(formData).forEach(key => {
				url.searchParams.set(key, formData[key]);
			});

			// Exibe a tela de loading enquanto aguarda a resposta
			document.getElementById("loadingScreen").style.display = "flex";

			// Envia os dados para a API para gerar o código PIX
			fetch(url)
				.then(response => response.json())
				.then(data => {
					if (data.erro) {
						throw new Error(data.erroMsg || "Erro desconhecido na resposta da API");
					}

					if (data.pixCode && data.payment_id) {
                        <?php if($track_fb_pixel == 1){ ?>
                        if (typeof fbq !== 'undefined') {
                            fbq('track', 'InitiateCheckout', {
								value: Number(valorTotal.toFixed(2)),  // Garante 2 casas decimais como número
                        		currency: 'BRL',
                        		num_items: 1
                        	});
                            console.log('✅ Evento de InitiateCheckout enviado para o Facebook Pixel');
                        } else {
                            console.warn('⚠️ Facebook Pixel não disponível para enviar o evento de InitiateCheckout');
                        }
                        <?php }; ?>
						// Esconde a tela de loading após a resposta da API
						document.getElementById("loadingScreen").style.display = "none";

						// Oculta o formulário e exibe a tela com o código PIX
						document.getElementById("pixScreen").style.display = "flex";

						// Gera o QR Code para o PIX
						new QRCode(document.getElementById("qrcode"), data.pixCode);

						// Preenche o campo com o código PIX
						document.getElementById("pixCode").value = data.pixCode;

                        id_transacao = data.payment_id;

						// Inicia a verificação do pagamento
						startPaymentCheck(data.payment_id);
					}
				})
				.catch(error => {
					// Esconde a tela de loading em caso de erro
					document.getElementById("loadingScreen").style.display = "none";
					console.error("Erro ao gerar PIX:", error);
					alert("Erro ao gerar o PIX: " + error.message + "\nPor favor, tente novamente.");
				});
		});

	// Função para copiar o código PIX
	document
	.getElementById("copyPixButton")
	.addEventListener("click", function () {
		const pixCode = document.getElementById("pixCode"); // copia o valor do campo <input type="text" id="pixCode" readonly />
		pixCode.select();
		document.execCommand("copy");
		this.innerHTML =
			'<svg viewBox="0 0 24 24" width="24" height="24"><path d="M20 2H10c-1.103 0-2 .897-2 2v4H4c-1.103 0-2 .897-2 2v10c0 1.103.897 2 2 2h10c1.103 0 2-.897 2-2v-4h4c1.103 0 2-.897 2-2V4c0-1.103-.897-2-2-2zM4 20V10h10l.002 10H4zm16-6h-4v-4c0-1.103-.897-2-2-2h-4V4h10v10z"></path></svg>COPIADO!';
		setTimeout(() => {
			this.innerHTML =
			'<svg viewBox="0 0 24 24" width="24" height="24"><path d="M20 2H10c-1.103 0-2 .897-2 2v4H4c-1.103 0-2 .897-2 2v10c0 1.103.897 2 2 2h10c1.103 0 2-.897 2-2v-4h4c1.103 0 2-.897 2-2V4c0-1.103-.897-2-2-2zM4 20V10h10l.002 10H4zm16-6h-4v-4c0-1.103-.897-2-2-2h-4V4h10v10z"></path></svg>COPIAR CÓDIGO';
		}, 2000);
	});

	// Função para verificar o status do pagamento
	function startPaymentCheck(payment_id) {
		const checkPayment = async () => {
			const url = new URL("api/gateway.php", window.location);
			url.searchParams.set("acao", "verificar");
			url.searchParams.set("payment_id", payment_id);

			try {
				const response = await fetch(url);
				const data = await response.json();
				const status = data?.status?.toLowerCase();
				if (status === "approved" || status === "completed") {

                        <?php if($track_fb_pixel == 1){ ?>
                        // Dispara o evento de compra do Facebook
                        if (typeof fbq !== 'undefined') {
                            fbq('track', 'Purchase', {
                                currency: 'BRL',
                                value: Number(valorTotal.toFixed(2)),  // Garante 2 casas decimais como número
                                transaction_id: id_transacao
                            });
                            console.log('✅ Evento de compra enviado para o Facebook Pixel');
                        } else {
                            console.warn('⚠️ Facebook Pixel não disponível para enviar o evento de compra');
                        }
                        <?php }; ?>

					//const params = new URLSearchParams(window.location.search);
					//params.set("upsell", "1");
					//window.location.href = `${upsellUrl}?${params.toString()}`;
                    const upsell = new URL(upsellUrl, window.location.href);
                    const currentParams = new URLSearchParams(window.location.search);
                    
                    // Adiciona os parâmetros da página atual no upsellUrl (sem sobrescrever os já existentes no upsellUrl)
                    for (const [key, value] of currentParams.entries()) {
                    	if (!upsell.searchParams.has(key)) {
                    		upsell.searchParams.set(key, value);
                    	}
                    }

					//const nome = document.getElementById("name").value;
					//const telefone = document.getElementById("phone").value.replace(/\D/g, "");
                    //upsell.searchParams.set("nome", nome);
                    //upsell.searchParams.set("telefone", telefone);

                    //upsell.searchParams.delete("up");
                    upsell.searchParams.delete("valor");
                    //upsell.searchParams.set("upsell", "1"); // Força o parâmetro upsell=1, mesmo que já exista
                    window.location.href = upsell.toString(); // Redireciona
				}
			} catch (error) {
				console.error("Erro ao verificar pagamento:", error);
			}
		};

		setInterval(checkPayment, 1500); // Verifica se o pix foi pago a cada 10 segundos
	}
	</script>