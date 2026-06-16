<?php

require_once __DIR__ . '/config.php';

function transactionStorageDir(): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'lp-cantinho'
        . DIRECTORY_SEPARATOR
        . 'transactions';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function transactionStoragePath(string $transactionId): string
{
    $safeId = preg_replace('/[^A-Za-z0-9_-]/', '', $transactionId);
    return transactionStorageDir() . DIRECTORY_SEPARATOR . $safeId . '.json';
}

function loadTransactionRecord(string $transactionId): array
{
    $path = transactionStoragePath($transactionId);
    if (!is_file($path)) {
        return [];
    }

    $content = file_get_contents($path);
    $data = json_decode((string) $content, true);

    return is_array($data) ? $data : [];
}

function saveTransactionRecord(string $transactionId, array $data): void
{
    $path = transactionStoragePath($transactionId);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function sanitizeTrackingParameters($input): array
{
    $allowedKeys = [
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

    $result = [];
    foreach ($allowedKeys as $key) {
        $value = is_array($input) ? ($input[$key] ?? null) : null;
        if ($value === null || $value === '') {
            $result[$key] = null;
            continue;
        }

        $result[$key] = substr(trim((string) $value), 0, 255);
    }

    return $result;
}

function clientIpAddress(): ?string
{
    $keys = ['HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }

        $parts = explode(',', (string) $_SERVER[$key]);
        $ip = trim($parts[0]);
        if ($ip !== '') {
            return $ip;
        }
    }

    return null;
}

function rateLimitDir(): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'lp-cantinho'
        . DIRECTORY_SEPARATOR
        . 'ratelimit';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function enforceRateLimit(string $scope, int $maxAttempts, int $windowSeconds): void
{
    $ip = clientIpAddress() ?? 'unknown';
    $safeIp = preg_replace('/[^A-Za-z0-9_.:-]/', '_', $ip);
    $path = rateLimitDir() . DIRECTORY_SEPARATOR . $scope . '_' . $safeIp . '.json';
    $now = time();
    $attempts = [];

    if (is_file($path)) {
        $stored = json_decode((string) file_get_contents($path), true);
        if (is_array($stored)) {
            foreach ($stored as $timestamp) {
                $timestamp = (int) $timestamp;
                if ($timestamp > ($now - $windowSeconds)) {
                    $attempts[] = $timestamp;
                }
            }
        }
    }

    if (count($attempts) >= $maxAttempts) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Muitas tentativas. Aguarde um momento e tente novamente.']);
        exit;
    }

    $attempts[] = $now;
    file_put_contents($path, json_encode($attempts), LOCK_EX);
}

function utmifyStatusForTransaction(string $status): ?string
{
    if ($status === 'COMPLETED') {
        return 'paid';
    }

    if ($status === 'PENDING') {
        return 'waiting_payment';
    }

    return null;
}

function normalizeDateToUtc(?string $dateTime): ?string
{
    if ($dateTime === null || trim($dateTime) === '') {
        return null;
    }

    $value = trim($dateTime);
    $defaultTimezone = new DateTimeZone(date_default_timezone_get());

    $formats = [
        'Y-m-d H:i:s',
        DateTimeInterface::ATOM,
        'Y-m-d\TH:i:s.uP',
        'Y-m-d\TH:i:sP',
        'Y-m-d\TH:i:s\Z',
    ];

    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value, $defaultTimezone);
        if ($date instanceof DateTimeImmutable) {
            return $date
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z');
        }
    }

    try {
        $date = new DateTimeImmutable($value, $defaultTimezone);
        return $date
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    } catch (Exception $e) {
        return null;
    }
}

function buildUtmifyPayload(array $record, string $utmifyStatus): array
{
    $amountInCents = (int) ($record['amountInCents'] ?? 0);
    $createdAt = normalizeDateToUtc((string) ($record['createdAt'] ?? ''));
    if ($createdAt === null) {
        $createdAt = gmdate('Y-m-d\TH:i:s\Z');
    }

    $approvedDate = normalizeDateToUtc($record['approvedDate'] ?? null);
    if ($utmifyStatus === 'waiting_payment') {
        $approvedDate = null;
    }

    $orderId = (string) $record['transactionId'];
    $productName = (string) ($record['product']['name'] ?? 'Deposito');

    return [
        'isTest' => false,
        'orderId' => $orderId,
        'platform' => 'Cantinho das Borboletas',
        'paymentMethod' => 'pix',
        'status' => $utmifyStatus,
        'createdAt' => $createdAt,
        'approvedDate' => $approvedDate,
        'refundedAt' => null,
        'customer' => [
            'name' => (string) ($record['customer']['name'] ?? ''),
            'email' => (string) ($record['customer']['email'] ?? ''),
            'phone' => (string) ($record['customer']['phone'] ?? ''),
            'document' => (string) ($record['customer']['document'] ?? ''),
            'country' => 'BR',
            'ip' => $record['customer']['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        ],
        'products' => [
            [
                'id' => $orderId,
                'name' => $productName,
                'planId' => null,
                'planName' => null,
                'priceInCents' => $amountInCents,
                'quantity' => 1,
            ],
        ],
        'trackingParameters' => sanitizeTrackingParameters($record['trackingParameters'] ?? []),
        'commission' => [
            'totalPriceInCents' => $amountInCents,
            'gatewayFeeInCents' => 0,
            'userCommissionInCents' => $amountInCents,
        ],
    ];
}

function syncTransactionWithUtmify(array $record): array
{
    if (UTMIFY_API_TOKEN === '' || empty($record['transactionId'])) {
        return $record;
    }

    if (!isset($record['utmify']) || !is_array($record['utmify'])) {
        $record['utmify'] = [];
    }

    $utmifyStatus = utmifyStatusForTransaction((string) ($record['status'] ?? ''));
    if ($utmifyStatus === null) {
        return $record;
    }

    if (!empty($record['utmify'][$utmifyStatus])) {
        return $record;
    }

    $payload = buildUtmifyPayload($record, $utmifyStatus);
    error_log('[UTMify] Payload ' . $record['transactionId'] . ': ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    writeAppLog('utmify', 'Payload enviado para UTMify', [
        'transactionId' => $record['transactionId'],
        'status' => $utmifyStatus,
        'payload' => $payload,
    ]);
    $response = utmifyRequest($payload);
    error_log('[UTMify] Response ' . $record['transactionId'] . ': ' . json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    writeAppLog('utmify', 'Resposta recebida da UTMify', [
        'transactionId' => $record['transactionId'],
        'status' => $utmifyStatus,
        'response' => $response,
    ]);

    $record['utmify'][$utmifyStatus] = date('Y-m-d H:i:s');
    error_log('[UTMify] Evento enviado com status ' . $utmifyStatus . ' para ' . $record['transactionId']);

    return $record;
}
