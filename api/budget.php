<?php
/**
 * Budget API - Get all budget data for current user
 * GET /api/budget.php
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireApiAuth();

$userId = getCurrentUserId();
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$items = getBudgetItems($userId);
$dates = getDateColumns($userId);
$entries = getEntries($userId);

// Convert entries to a map for easy lookup
$entriesMap = [];
foreach ($entries as $entry) {
    $key = $entry['budget_item_id'] . '_' . $entry['date_column_id'];
    $entriesMap[$key] = $entry['amount'];
}

jsonResponse([
    'success' => true,
    'data' => [
        'initial_balance' => (float)$user['initial_balance'],
        'items' => $items,
        'dates' => $dates,
        'entries' => $entriesMap
    ]
]);
