<?php
/**
 * Budget Items API - CRUD for budget items (rows)
 * POST /api/items.php - Create item
 * PUT /api/items.php?id=X - Update item
 * DELETE /api/items.php?id=X - Delete item
 * POST /api/items.php?action=reorder - Reorder items
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

        // Check for reorder action
        if (isset($_GET['action']) && $_GET['action'] === 'reorder') {
            if (!isset($input['item_ids']) || !is_array($input['item_ids'])) {
                jsonResponse(['error' => 'item_ids array required'], 400);
            }
            reorderBudgetItems($userId, $input['item_ids']);
            jsonResponse(['success' => true]);
        }

        // Create new item
        $name = trim($input['name'] ?? '');
        $notes = $input['notes'] ?? null;

        if (empty($name)) {
            jsonResponse(['error' => 'Name is required'], 400);
        }

        $result = createBudgetItem($userId, $name, $notes);
        jsonResponse($result, $result['success'] ? 201 : 400);
        break;

    case 'PUT':
        $itemId = (int)($_GET['id'] ?? 0);
        if (!$itemId) {
            jsonResponse(['error' => 'Item ID required'], 400);
        }

        $input = getJsonInput();
        $name = isset($input['name']) ? trim($input['name']) : null;
        $notes = $input['notes'] ?? null;

        $result = updateBudgetItem($userId, $itemId, $name, $notes);
        jsonResponse($result, $result['success'] ? 200 : 400);
        break;

    case 'DELETE':
        $itemId = (int)($_GET['id'] ?? 0);
        if (!$itemId) {
            jsonResponse(['error' => 'Item ID required'], 400);
        }

        $result = deleteBudgetItem($userId, $itemId);
        jsonResponse($result, $result['success'] ? 200 : 400);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
