<?php
/**
 * Entries API - CRUD for cell values
 * PUT /api/entries.php - Create/update entry
 * DELETE /api/entries.php - Delete entry
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireApiAuth();

$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'PUT':
        $input = getJsonInput();
        $itemId = (int)($input['item_id'] ?? 0);
        $dateId = (int)($input['date_id'] ?? 0);
        $amount = $input['amount'] ?? null;

        if (!$itemId || !$dateId) {
            jsonResponse(['error' => 'item_id and date_id required'], 400);
        }

        if ($amount === null || $amount === '') {
            jsonResponse(['error' => 'amount required'], 400);
        }

        $amount = (float)$amount;
        $result = upsertEntry($userId, $itemId, $dateId, $amount);
        jsonResponse($result);
        break;

    case 'DELETE':
        $input = getJsonInput();
        $itemId = (int)($input['item_id'] ?? 0);
        $dateId = (int)($input['date_id'] ?? 0);
        $notes = $input['notes'] ?? null;

        if (!$itemId || !$dateId) {
            jsonResponse(['error' => 'item_id and date_id required'], 400);
        }

        $result = deleteEntry($userId, $itemId, $dateId, $notes);
        jsonResponse($result, $result['success'] ? 200 : 400);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
