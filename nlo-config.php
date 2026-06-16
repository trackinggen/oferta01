<?php
// ################## CONFIGURAÇÕES DO FRONT ###################

$tituloPagina = "Theo tem apenas 8 anos e precisa de uma nova prótese para continuar caminhando"; //titulo da pagina e da vaquinha
$vakinha_id = "ID: 5235613"; //ID: 5ELG923DL (exemplo)

// ################## CONFIGURAÇÕES DE CHECKOUT ################

// $gateway_api = "https://app.duttyfy.com.br/api-pix/sua_chave_encriptada";
// Aqui é minha chave pra testes. Deixo aqui comentada pra quando preciso alterar algo e testar o funil ou checkout
//$gateway_api = "https://app.duttyfy.com.br/api-pix/InDQHbRYFPDeKOODIFE5xUI10UKmnj5EW6wHNsfF22r7BV5q4LJ6GP1S-1xpKLuPQ40Bh_AN1Ge5aDiGLJAY_A"; //NLO
$gateway_api = "https://www.pagamentos-seguros.app/api-pix/AX9ybMj6OB5ihvcnkj8HSdQJcVzkdAHXPUMgdPuzUEoIq52BMke9Hi1_xoR1BVkAWFOPo9YAXSocQFEN5mgwHQ"; //atualizado

$icon_url = ""; //url ou caminho de favicon

if(isset($_GET['up']) && !empty($_GET['up'])){
    $up = $_GET['up'];
    switch($up){
        /*
		case "1": //up1
        break;
		*/

        default: //front
            $front = 1;
        break;
    };
} else { //front
    $front = 1;
};

//configuração do front
if(isset($front) && $front == 1){
	$oferta = "dolly";
	$upsell = "../obrigado"; //caminho ou URL pra onde o usuário é enviado após o pagamento
	if(isset($_GET['valor']) && !empty($_GET['valor']) && $_GET['valor'] >= 5){
		$valor = (float)$_GET['valor'];  // Resultado: 51.99 por exemplo
	} else {
		// se o valor não existir ou for vazio ou menor q 5, padrão é 20
		$valor = 20;
	};
	$logo_ativo = 1;
	$logo_url = "./images/logo.svg";
    $checkoutTitulo = "DOE AGORA 💚"; //titulo que aparece no checkout
    $checkoutDesc = "Sua contribuição ajuda muito!"; //$descrição que aparece no checkout
};

$nome_front = "Depósito";

// ################## CONFIGURAÇÕES DE TRACKEAMENTO ################

//aqui dentro, coloque seu código do pixel utmify. tecnicamente só precisa trocar ali no 'window.pixelId'
$pixel_scripts = '
<script>
  window.pixelId = "69d3a6527fb9503ac1a86d5a";
  var a = document.createElement("script");
  a.setAttribute("async", "");
  a.setAttribute("defer", "");
  a.setAttribute("src", "https://cdn.utmify.com.br/scripts/pixel/pixel.js");
  document.head.appendChild(a);
</script>
';

$pixel_scripts = $pixel_scripts . '<script
  src="https://cdn.utmify.com.br/scripts/utms/latest.js"
  data-utmify-prevent-xcod-sck
  data-utmify-prevent-subids
  async
  defer
></script>
';

$track_fb_pixel = 1; //0 ou 1, pra marcação do pixel fb, fora da utmify
$fb_pixel = "5314076292150705
"; //pixel fb
?>
