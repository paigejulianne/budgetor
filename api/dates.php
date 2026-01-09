<?php
/**
 * Date Columns API - CRUD for date columns
 * POST /api/dates.php - Create date column
 * DELETE /api/dates.php?id=X - Delete date column
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireApiAuth();

$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        $input = getJsonInput();
        $dateValue = trim($input['date'] ?? '');

        if (empty($dateValue)) {
            jsonResponse(['error' => 'Date is required'], 400);
        }

        $result = createDateColumn($userId, $dateValue);
        jsonResponse($result, $result['success'] ? 201 : 400);
        break;

    case 'DELETE':
        $dateId = (int)($_GET['id'] ?? 0);
        if (!$dateId) {
            jsonResponse(['error' => 'Date ID required'], 400);
        }

        $result = deleteDateColumn($userId, $dateId);
        jsonResponse($result, $result['success'] ? 200 : 400);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
