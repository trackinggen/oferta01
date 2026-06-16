<?php
/**
 * ============================================
 * CONSULTAR PIX - Verifica status do pagamento
 * ============================================
 * GET /api/pix/status?id={paymentId}
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/storage.php';

$paymentId = $_GET['id'] ?? $_GET['transactionId'] ?? null;

if (!$paymentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID do pagamento nao informado']);
    exit;
}

try {
    $result = duttyfyRequest('GET', null, (string) $paymentId);
    $status = duttyfyNormalizeStatus((string) ($result['status'] ?? 'PENDING'));
    $isPaid = $status === 'COMPLETED';
    $paidAt = $isPaid ? ($result['paidAt'] ?? null) : null;

    $record = loadTransactionRecord((string) $paymentId);

    if (!empty($record)) {
        $record['status'] = $status;
        if ($isPaid && empty($record['approvedDate'])) {
            $record['approvedDate'] = !empty($paidAt)
                ? (string) $paidAt
                : date('Y-m-d H:i:s');
        }

        try {
            $record = syncTransactionWithUtmify($record);
        } catch (Exception $utmifyError) {
            error_log('[UTMify] Falha ao sincronizar status para ' . $paymentId . ': ' . $utmifyError->getMessage());
        }

        saveTransactionRecord((string) $paymentId, $record);
    }

    echo json_encode([
        'success' => true,
        'paymentId' => $paymentId,
        'status' => $status,
        'isPaid' => $isPaid,
        'paidAt' => $paidAt,
    ]);
} catch (Exception $e) {
    error_log('[PIX] Erro ao consultar Duttyfy: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Nao foi possivel consultar o status do pagamento.',
    ]);
}
