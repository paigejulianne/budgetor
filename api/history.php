<?php
/**
 * History API - Get change history for a cell
 * GET /api/history.php?item_id=X&date_id=Y
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireApiAuth();

$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$itemId = (int)($_GET['item_id'] ?? 0);
$dateId = (int)($_GET['date_id'] ?? 0);

if (!$itemId || !$dateId) {
    jsonResponse(['error' => 'item_id and date_id required'], 400);
}

$history = getEntryHistory($userId, $itemId, $dateId);

jsonResponse([
    'success' => true,
    'history' => $history
]);
