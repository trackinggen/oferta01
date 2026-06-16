<?php
/**
 * ============================================
 * GERAR PIX - Cria cobranca via Duttyfy
 * ============================================
 * POST /api/pix/create
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/storage.php';

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = [];
}

$utmRaw = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $trackingParameters = [];
    $trackingKeys = [
        'xcod',
        'src',
        'sck',
        'utm_source',
        'utm_campaign',
        'utm_medium',
        'utm_content',
        'utm_term',
        'utm_id',
        'utm_source_platform',
        'utm_creative_format',
        'utm_marketing_tactic',
        'fbclid',
        'fbp',
        'fbc',
        'gclid',
        'gbraid',
        'wbraid',
        'ttclid',
        'msclkid',
    ];

    foreach ($trackingKeys as $key) {
        if (isset($_POST[$key])) {
            $trackingParameters[$key] = $_POST[$key];
        }
    }

    if (!empty($_POST['utm_raw'])) {
        $utmRaw = trim((string) $_POST['utm_raw']);
        $rawTracking = [];
        parse_str($utmRaw, $rawTracking);
        if (is_array($rawTracking)) {
            $trackingParameters = array_merge($rawTracking, $trackingParameters);
        }
    } elseif (!empty($_POST['utm'])) {
        $utmRaw = trim((string) $_POST['utm']);
    }

    $input = [
        'amount' => $_POST['amount'] ?? $_POST['valor'] ?? $_POST['price'] ?? $_POST['valor-doacao'] ?? 0,
        'nome' => $_POST['nome'] ?? $_POST['nome_doador'] ?? '',
        'anonimo' => $_POST['anonimo'] ?? false,
        'mensagem' => $_POST['mensagem'] ?? '',
        'trackingParameters' => $trackingParameters,
        'utm' => $utmRaw,
    ];
}

enforceRateLimit('pix_create', 12, 300);

$amount = $input['amount'] ?? $input['valor'] ?? $input['price'] ?? 0;
$nome = trim((string) ($input['nome'] ?? ''));
$anonimo = (bool) ($input['anonimo'] ?? false);
$mensagem = trim((string) ($input['mensagem'] ?? ''));
$trackingParameters = sanitizeTrackingParameters($input['trackingParameters'] ?? []);

if (!is_numeric($amount) || $amount < 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'O valor minimo para doacao e R$ 5,00']);
    exit;
}

if ($amount > 50000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'O valor maximo para doacao e R$ 50.000,00']);
    exit;
}

$amount = round((float) $amount, 2);
$amountInCents = (int) round($amount * 100);
$fake = gerarDadosFicticios();

$utmRaw = trim((string) ($input['utm'] ?? $input['utm_raw'] ?? $utmRaw));
if ($utmRaw === '' && !empty($trackingParameters)) {
    $filtered = array_filter($trackingParameters, static function ($value) {
        return $value !== null && $value !== '';
    });
    if (!empty($filtered)) {
        $utmRaw = http_build_query($filtered);
    }
}

$body = [
    'amount' => $amountInCents,
    'paymentMethod' => 'PIX',
    'customer' => [
        'name' => $fake['nome'],
        'email' => $fake['email'],
        'phone' => preg_replace('/\D/', '', $fake['telefone']),
        'document' => preg_replace('/\D/', '', $fake['cpf']),
    ],
    'item' => [
        'title' => 'Deposito',
        'price' => $amountInCents,
        'quantity' => 1,
    ],
    'description' => 'Pagamento via Pix',
];

if ($utmRaw !== '') {
    $body['utm'] = $utmRaw;
}

error_log('[PIX] Criando cobranca Duttyfy no valor de R$ ' . number_format($amount, 2, '.', '') . ' url=...' . duttyfyUrlSuffix());

try {
    $result = duttyfyRequest('POST', $body);

    $pixCode = (string) ($result['pixCode'] ?? '');
    $transactionId = (string) ($result['transactionId'] ?? '');
    $status = duttyfyNormalizeStatus((string) ($result['status'] ?? 'PENDING'));

    if ($pixCode === '' || $transactionId === '') {
        throw new Exception('Resposta incompleta ao criar a cobranca PIX.');
    }

    $record = [
        'transactionId' => $transactionId,
        'status' => $status,
        'amount' => $amount,
        'amountInCents' => $amountInCents,
        'createdAt' => date('Y-m-d H:i:s'),
        'approvedDate' => null,
        'customer' => [
            'name' => $fake['nome'],
            'email' => $fake['email'],
            'phone' => preg_replace('/\D/', '', $fake['telefone']),
            'document' => preg_replace('/\D/', '', $fake['cpf']),
            'ip' => clientIpAddress(),
        ],
        'product' => [
            'id' => $transactionId,
            'name' => 'Deposito',
            'quantity' => 1,
        ],
        'trackingParameters' => $trackingParameters,
        'checkout' => [
            'nome' => $nome,
            'anonimo' => $anonimo,
            'mensagem' => $mensagem,
        ],
        'utmify' => [],
    ];

    saveTransactionRecord($transactionId, $record);
    try {
        $record = syncTransactionWithUtmify($record);
        saveTransactionRecord($transactionId, $record);
    } catch (Exception $utmifyError) {
        error_log('[UTMify] Falha ao sincronizar waiting_payment para ' . $transactionId . ': ' . $utmifyError->getMessage());
    }

    error_log('[Duttyfy] Pagamento criado com status ' . $status . ' e id ' . $transactionId);

    echo json_encode([
        'success' => true,
        'paymentId' => $transactionId,
        'transactionId' => $transactionId,
        'status' => $status,
        'qrCodeImage' => '',
        'qrCode' => $pixCode,
        'pixCode' => $pixCode,
        'amount' => $amount,
    ]);
} catch (Exception $e) {
    error_log('[PIX] Erro ao criar cobranca Duttyfy: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage() ?: 'Nao foi possivel gerar o PIX. Tente novamente em instantes.',
    ]);
}
