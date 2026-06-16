<?php
/**
 * ============================================
 * CONFIGURACAO DA API PIX
 * ============================================
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Referrer-Policy: strict-origin-when-cross-origin');

applyCorsPolicy();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('DUTTYFY_CREATE_TIMEOUT_SECONDS', 15);
define('DUTTYFY_STATUS_TIMEOUT_SECONDS', 10);
define('UTMIFY_API_TOKEN', '1WibVmsMBtKopqQT6bmb6eva91S3mzbYWINn');
define('UTMIFY_ORDERS_URL', 'https://api.utmify.com.br/api-credentials/orders');

function duttyfyPixUrlEncrypted(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $fromEnv = getenv('DUTTYFY_PIX_URL_ENCRYPTED');
    if (is_string($fromEnv) && trim($fromEnv) !== '') {
        $cached = trim($fromEnv);
        return $cached;
    }

    $configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'nlo-config.php';
    if (is_file($configPath)) {
        $gateway_api = null;
        include $configPath;
        if (!empty($gateway_api) && is_string($gateway_api)) {
            $cached = trim($gateway_api);
            return $cached;
        }
    }

    return '';
}

function appLogDir(): string
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function appLogPath(string $channel = 'app'): string
{
    $safeChannel = preg_replace('/[^A-Za-z0-9_-]/', '_', $channel);
    return appLogDir() . DIRECTORY_SEPARATOR . $safeChannel . '.log';
}

function writeAppLog(string $channel, string $message, ?array $context = null): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context !== null) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $line .= PHP_EOL;

    file_put_contents(appLogPath($channel), $line, FILE_APPEND | LOCK_EX);
}

function applyCorsPolicy(): void
{
    if (empty($_SERVER['HTTP_ORIGIN'])) {
        return;
    }

    $origin = (string) $_SERVER['HTTP_ORIGIN'];
    $allowedOrigin = requestBaseOrigin();

    if ($allowedOrigin !== null && hash_equals($allowedOrigin, $origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        return;
    }

    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Origem nao autorizada']);
    exit;
}

function requestBaseOrigin(): ?string
{
    if (empty($_SERVER['HTTP_HOST'])) {
        return null;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $isHttps ? 'https://' : 'http://';

    return $scheme . $_SERVER['HTTP_HOST'];
}

function duttyfyUrlSuffix(): string
{
    $url = duttyfyPixUrlEncrypted();
    return $url !== '' ? substr($url, -8) : '(vazio)';
}

function duttyfyRequestOnce(string $method, string $url, ?array $body, int $timeoutSeconds): array
{
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; lp-cantinho/1.0; PHP)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error !== '') {
        return [
            'ok' => false,
            'httpCode' => 0,
            'networkError' => $error,
            'data' => [],
            'raw' => '',
        ];
    }

    $rawResponse = (string) $response;
    $data = json_decode($rawResponse, true);
    if (!is_array($data)) {
        $data = [];
    }

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'httpCode' => $httpCode,
        'networkError' => null,
        'data' => $data,
        'raw' => $rawResponse,
    ];
}

function duttyfyRequest(string $method, ?array $body = null, ?string $transactionId = null): array
{
    $baseUrl = duttyfyPixUrlEncrypted();
    if ($baseUrl === '') {
        throw new Exception('DUTTYFY_PIX_URL_ENCRYPTED nao configurada.');
    }

    $url = rtrim($baseUrl, '?');
    if ($transactionId !== null && $transactionId !== '') {
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . 'transactionId=' . rawurlencode($transactionId);
    }

    $timeout = strtoupper($method) === 'GET'
        ? DUTTYFY_STATUS_TIMEOUT_SECONDS
        : DUTTYFY_CREATE_TIMEOUT_SECONDS;
    $retryDelays = [0, 1, 2, 4];
    $maxAttempts = count($retryDelays);
    $lastResult = null;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        if ($retryDelays[$attempt] > 0) {
            sleep($retryDelays[$attempt]);
        }

        $lastResult = duttyfyRequestOnce($method, $url, $body, $timeout);
        $httpCode = (int) ($lastResult['httpCode'] ?? 0);

        if (!empty($lastResult['networkError'])) {
            if ($attempt < $maxAttempts - 1) {
                error_log('[Duttyfy] Erro de rede (tentativa ' . ($attempt + 1) . ') url=...' . duttyfyUrlSuffix() . ': ' . $lastResult['networkError']);
                continue;
            }
            throw new Exception('Erro de conexao: ' . $lastResult['networkError']);
        }

        if ($httpCode >= 400 && $httpCode < 500) {
            $data = $lastResult['data'] ?? [];
            $msg = $data['error'] ?? $data['message'] ?? ('Duttyfy retornou status ' . $httpCode);
            error_log('[Duttyfy] Erro HTTP ' . $httpCode . ' url=...' . duttyfyUrlSuffix() . ': ' . ($lastResult['raw'] ?? ''));
            throw new Exception($msg);
        }

        if ($httpCode >= 500) {
            if ($attempt < $maxAttempts - 1) {
                error_log('[Duttyfy] Erro HTTP ' . $httpCode . ' (tentativa ' . ($attempt + 1) . ') url=...' . duttyfyUrlSuffix());
                continue;
            }
            $data = $lastResult['data'] ?? [];
            $msg = $data['error'] ?? $data['message'] ?? ('Duttyfy retornou status ' . $httpCode);
            throw new Exception($msg);
        }

        if (empty($lastResult['ok'])) {
            throw new Exception('Resposta invalida do gateway Duttyfy.');
        }

        return $lastResult['data'];
    }

    throw new Exception('Nao foi possivel comunicar com o gateway Duttyfy.');
}

function duttyfyNormalizeStatus(string $status): string
{
    $status = strtoupper(trim($status));
    if ($status === 'COMPLETED') {
        return 'COMPLETED';
    }
    if (in_array($status, ['CANCELLED', 'CANCELED', 'FAILED', 'EXPIRED', 'REFUSED'], true)) {
        return 'CANCELLED';
    }

    return 'PENDING';
}

function utmifyRequest(array $body): array
{
    if (UTMIFY_API_TOKEN === '') {
        throw new Exception('UTMIFY_API_TOKEN nao configurado.');
    }

    $ch = curl_init(UTMIFY_ORDERS_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-token: ' . UTMIFY_API_TOKEN,
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        writeAppLog('utmify', 'Erro de conexao com a UTMify', [
            'error' => $error,
            'url' => UTMIFY_ORDERS_URL,
        ]);
        throw new Exception('Erro de conexao com a UTMify: ' . $error);
    }

    $data = json_decode((string) $response, true);
    if (!is_array($data)) {
        $data = [];
    }

    writeAppLog('utmify', 'Resposta HTTP da UTMify', [
        'statusCode' => $httpCode,
        'response' => $data,
    ]);

    if ($httpCode >= 400) {
        $msg = $data['message'] ?? $data['error'] ?? ('UTMify retornou status ' . $httpCode);
        error_log('[UTMify] Erro HTTP ' . $httpCode . ': ' . $msg);
        writeAppLog('utmify', 'Erro HTTP da UTMify', [
            'statusCode' => $httpCode,
            'message' => $msg,
            'response' => $data,
        ]);
        throw new Exception($msg);
    }

    return $data;
}
