<?php
/**
 * Settings API - Update user settings
 * PUT /api/settings.php - Update initial balance
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireApiAuth();

$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = getJsonInput();

if (isset($input['initial_balance'])) {
    $balance = (float)$input['initial_balance'];
    updateInitialBalance($userId, $balance);
    jsonResponse(['success' => true, 'initial_balance' => $balance]);
}

jsonResponse(['error' => 'No valid settings provided'], 400);
