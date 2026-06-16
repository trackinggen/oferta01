<?php
header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(array(
            'success' => false,
            'erro' => 1,
            'erroMsg' => 'Erro interno no gateway: ' . $error['message'],
            'error' => 'Erro interno no gateway: ' . $error['message'],
        ), JSON_UNESCAPED_UNICODE);
    }
});

function responder_json($payload, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function pegar_valor($data, $keys, $default = '') {
    foreach ($keys as $key) {
        if (isset($data[$key]) && $data[$key] !== '') {
            return $data[$key];
        }
    }
    return $default;
}

function gerar_cpf_valido() {
    $cpf = '';
    for ($i = 0; $i < 9; $i++) {
        $cpf .= rand(0, 9);
    }
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += (int) $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        $cpf .= $d;
    }
    return $cpf;
}

function cliente_padrao() {
    $nomes = array('Ana', 'Maria', 'Juliana', 'Fernanda', 'Carlos', 'Joao', 'Pedro', 'Lucas', 'Rafael', 'Marcos');
    $sobrenomes = array('Silva', 'Santos', 'Oliveira', 'Souza', 'Pereira', 'Costa', 'Almeida', 'Rodrigues');
    $nome = $nomes[array_rand($nomes)] . ' ' . $sobrenomes[array_rand($sobrenomes)] . ' ' . $sobrenomes[array_rand($sobrenomes)];
    $emailNome = strtolower(preg_replace('/[^a-z0-9]/i', '', $nome));

    return array(
        'nome' => $nome,
        'email' => $emailNome . rand(100, 9999) . '@gmail.com',
        'telefone' => '119' . rand(10000000, 99999999),
        'cpf' => gerar_cpf_valido(),
    );
}

function normalizar_status($status) {
    $status = strtolower((string) $status);
    if (in_array($status, array('approved', 'completed', 'paid', 'confirmed'), true)) {
        return 'COMPLETED';
    }
    if (in_array($status, array('cancelled', 'canceled', 'failed', 'refused', 'expired'), true)) {
        return 'CANCELLED';
    }
    return 'PENDING';
}

function url_base_site() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443');
    $scheme = $https ? 'https://' : 'http://';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $scriptDir = str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : ''));
    $baseDir = preg_replace('#/pagamento/api$#', '', $scriptDir);
    return rtrim($scheme . $host . $baseDir, '/');
}

function chamar_endpoint_local($path, $method, $data) {
    if (!function_exists('curl_init')) {
        return array('success' => false, 'error' => 'Extensao PHP cURL nao esta habilitada no servidor');
    }

    $url = url_base_site() . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 35);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    }

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        return array('success' => false, 'error' => $curlError, 'httpCode' => $httpCode);
    }

    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        return array(
            'success' => false,
            'error' => 'Resposta invalida do endpoint local. HTTP ' . $httpCode,
            'httpCode' => $httpCode,
            'raw' => substr((string) $raw, 0, 500),
        );
    }

    return $json;
}

function tentar_fallback_criar_pix($entrada, $valor, $nome) {
    $post = array_merge($entrada, array(
        'amount' => $valor,
        'valor' => $valor,
        'valor-doacao' => $valor,
        'nome' => $nome,
        'nome_doador' => $nome,
    ));

    $arquivoLocal = __DIR__ . '/../../api/gerar_pix.php';
    if (is_file($arquivoLocal)) {
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        include $arquivoLocal;
        exit;
    }

    $fallback = chamar_endpoint_local('api/gerar_pix.php', 'POST', $post);
    if (!empty($fallback['success'])) {
        $transactionId = isset($fallback['transactionId']) ? $fallback['transactionId'] : (isset($fallback['paymentId']) ? $fallback['paymentId'] : '');
        $qrCode = isset($fallback['qrCode']) ? $fallback['qrCode'] : (isset($fallback['pixCode']) ? $fallback['pixCode'] : '');

        if ($transactionId !== '' && $qrCode !== '') {
            responder_json(array(
                'ok' => true,
                'success' => true,
                'fallback' => 'api/gerar_pix.php',
                'payment_id' => $transactionId,
                'paymentId' => $transactionId,
                'transactionId' => $transactionId,
                'pixCode' => $qrCode,
                'qrCode' => $qrCode,
                'status' => isset($fallback['status']) ? $fallback['status'] : 'PENDING',
            ));
        }
    }

    return $fallback;
}

function tentar_fallback_verificar_pix($paymentId) {
    $arquivoLocal = __DIR__ . '/../../api/consultar_pix.php';
    if (is_file($arquivoLocal)) {
        $_GET['transactionId'] = $paymentId;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        include $arquivoLocal;
        exit;
    }

    $fallback = chamar_endpoint_local('api/consultar_pix.php?transactionId=' . urlencode($paymentId), 'GET', array());
    if (!empty($fallback['success']) || isset($fallback['status'])) {
        responder_json(array(
            'success' => !empty($fallback['success']),
            'fallback' => 'api/consultar_pix.php',
            'paymentId' => $paymentId,
            'transactionId' => $paymentId,
            'status' => isset($fallback['status']) ? $fallback['status'] : 'PENDING',
            'isPaid' => !empty($fallback['isPaid']),
        ));
    }

    return $fallback;
}

$jsonInput = json_decode(file_get_contents('php://input'), true);
if (!is_array($jsonInput)) {
    $jsonInput = array();
}

$entrada = array_merge($_GET, $_POST, $jsonInput);
$acao = pegar_valor($entrada, array('acao'), '');

if ($acao === '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = 'criar';
    } elseif (pegar_valor($entrada, array('payment_id', 'transactionId', 'id'), '') !== '') {
        $acao = 'verificar';
    }
}

include_once(__DIR__ . '/../../nlo-config.php');

if (empty($gateway_api)) {
    responder_json(array(
        'success' => false,
        'erro' => 1,
        'erroMsg' => 'gateway_api nao configurado em nlo-config.php',
        'error' => 'gateway_api nao configurado em nlo-config.php',
    ), 500);
}

$params = array();
$postfields = null;

if ($acao === 'criar') {
    $cliente = cliente_padrao();

    $valor = pegar_valor($entrada, array('valor', 'amount', 'price', 'valor-doacao'), 20);
    $valor = (float) str_replace(',', '.', (string) $valor);
    if ($valor < 5) {
        responder_json(array(
            'success' => false,
            'erro' => 1,
            'erroMsg' => 'Valor minimo para PIX e R$ 5,00',
            'error' => 'Valor minimo para PIX e R$ 5,00',
        ), 400);
    }

    $valorCentavos = (int) str_replace('.', '', number_format($valor, 2, '.', ''));
    $nome = trim((string) pegar_valor($entrada, array('nome', 'nome_doador'), $cliente['nome']));
    if ($nome === '' || stripos($nome, 'anon') === 0) {
        $nome = $cliente['nome'];
    }

    $email = trim((string) pegar_valor($entrada, array('email'), $cliente['email']));
    if ($email === '') {
        $email = $cliente['email'];
    }

    $telefone = preg_replace('/\D/', '', (string) pegar_valor($entrada, array('telefone', 'phone'), $cliente['telefone']));
    if ($telefone === '') {
        $telefone = $cliente['telefone'];
    }

    $cpf = preg_replace('/\D/', '', (string) pegar_valor($entrada, array('cpf', 'document'), $cliente['cpf']));
    if ($cpf === '') {
        $cpf = $cliente['cpf'];
    }

    $ofertaNome = isset($nome_front) && $nome_front !== '' ? $nome_front : 'Deposito';
    $utm = urldecode((string) pegar_valor($entrada, array('utm', 'utm_raw'), ''));

    $postfields = array(
        'utm' => $utm,
        'item' => array(
            'price' => $valorCentavos,
            'title' => $ofertaNome,
            'quantity' => 1,
        ),
        'amount' => $valorCentavos,
        'customer' => array(
            'name' => $nome,
            'email' => $email,
            'phone' => $telefone,
            'document' => $cpf,
        ),
        'description' => 'Pagamento via Pix',
        'paymentMethod' => 'PIX',
    );
} elseif ($acao === 'verificar') {
    $paymentId = pegar_valor($entrada, array('payment_id', 'transactionId', 'id'), '');
    if ($paymentId === '') {
        responder_json(array(
            'success' => false,
            'erro' => 1,
            'erroMsg' => 'Parametro obrigatorio faltando: payment_id',
            'error' => 'Parametro obrigatorio faltando: payment_id',
        ), 400);
    }
    $params['transactionId'] = $paymentId;
} else {
    responder_json(array(
        'success' => false,
        'erro' => 1,
        'erroMsg' => 'Acao nao encontrada',
        'error' => 'Acao nao encontrada',
    ), 400);
}

$query = http_build_query($params);
$url = rtrim($gateway_api, '?');
if ($query !== '') {
    $url .= '?' . $query;
}

if (!function_exists('curl_init')) {
    responder_json(array(
        'success' => false,
        'erro' => 1,
        'erroMsg' => 'Extensao PHP cURL nao esta habilitada no servidor',
        'error' => 'Extensao PHP cURL nao esta habilitada no servidor',
    ), 500);
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_ENCODING, 'deflate');
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

if ($acao === 'verificar') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
} else {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
}

$raw = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError) {
    if ($acao === 'criar') {
        $fallback = tentar_fallback_criar_pix($entrada, $valor, $nome);
    } else {
        $fallback = tentar_fallback_verificar_pix($paymentId);
    }
    responder_json(array(
        'success' => false,
        'erro' => 1,
        'erroMsg' => $curlError,
        'error' => $curlError,
        'fallbackDetalhes' => isset($fallback) ? $fallback : null,
    ), 500);
}

$result = json_decode((string) $raw, true);
if (!is_array($result)) {
    if ($acao === 'criar') {
        $fallback = tentar_fallback_criar_pix($entrada, $valor, $nome);
    } else {
        $fallback = tentar_fallback_verificar_pix($paymentId);
    }
    responder_json(array(
        'success' => false,
        'erro' => 1,
        'erroMsg' => 'Resposta invalida da API de pagamento. HTTP ' . $httpCode,
        'error' => 'Resposta invalida da API de pagamento. HTTP ' . $httpCode,
        'detalhes' => substr((string) $raw, 0, 500),
        'fallbackDetalhes' => isset($fallback) ? $fallback : null,
    ), 500);
}

if (!empty($result['message']) || !empty($result['error'])) {
    $msg = !empty($result['message']) ? $result['message'] : $result['error'];
    if ($acao === 'criar') {
        $fallback = tentar_fallback_criar_pix($entrada, $valor, $nome);
    } else {
        $fallback = tentar_fallback_verificar_pix($paymentId);
    }
    responder_json(array(
        'success' => false,
        'erro' => 1,
        'erroMsg' => $msg,
        'error' => $msg,
        'detalhes' => $result,
        'fallbackDetalhes' => isset($fallback) ? $fallback : null,
    ), $httpCode >= 400 ? $httpCode : 500);
}

if ($acao === 'criar') {
    $transactionId = isset($result['transactionId']) ? $result['transactionId'] : '';
    $pixCode = isset($result['pixCode']) ? $result['pixCode'] : '';

    if ($transactionId === '' || $pixCode === '') {
        $fallback = tentar_fallback_criar_pix($entrada, $valor, $nome);
        responder_json(array(
            'success' => false,
            'erro' => 1,
            'erroMsg' => 'API nao retornou transactionId ou pixCode',
            'error' => 'API nao retornou transactionId ou pixCode',
            'detalhes' => $result,
            'fallbackDetalhes' => $fallback,
        ), 500);
    }

    responder_json(array(
        'ok' => true,
        'success' => true,
        'payment_id' => $transactionId,
        'paymentId' => $transactionId,
        'transactionId' => $transactionId,
        'pixCode' => $pixCode,
        'qrCode' => $pixCode,
        'status' => isset($result['status']) ? $result['status'] : 'PENDING',
    ));
}

responder_json(array(
    'success' => true,
    'paymentId' => $paymentId,
    'transactionId' => $paymentId,
    'status' => normalizar_status(isset($result['status']) ? $result['status'] : 'PENDING'),
));
?>
