<?php
/**
 * ============================================
 * WEBHOOK PIX - Nao utilizado pelo Duttyfy
 * ============================================
 * O gateway Duttyfy confirma pagamentos via polling (consultar_pix.php).
 * Este endpoint permanece apenas por compatibilidade de rota.
 */

require_once __DIR__ . '/config.php';

http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => 'Webhook nao suportado. Use consultar_pix.php para verificar o status.',
]);
