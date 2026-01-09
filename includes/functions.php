<?php
/**
 * Utility functions
 */

require_once __DIR__ . '/db.php';

/**
 * Send JSON response
 */
function jsonResponse(mixed $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get JSON input from request body
 */
function getJsonInput(): array {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

/**
 * Sanitize string input
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate date format (YYYY-MM-DD)
 */
function isValidDate(string $date): bool {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Format amount for display
 */
function formatAmount(float $amount): string {
    return number_format($amount, 2);
}

/**
 * Get all budget items for a user
 */
function getBudgetItems(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, name, notes, sort_order
        FROM budget_items
        WHERE user_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get all date columns for a user
 */
function getDateColumns(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, date_value, sort_order
        FROM date_columns
        WHERE user_id = ?
        ORDER BY date_value ASC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get all entries for a user
 */
function getEntries(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, budget_item_id, date_column_id, amount
        FROM entries
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get entry by item and date
 */
function getEntry(int $userId, int $itemId, int $dateId): ?array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, amount, created_at, updated_at
        FROM entries
        WHERE user_id = ? AND budget_item_id = ? AND date_column_id = ?
    ");
    $stmt->execute([$userId, $itemId, $dateId]);
    return $stmt->fetch() ?: null;
}

/**
 * Create or update an entry
 */
function upsertEntry(int $userId, int $itemId, int $dateId, float $amount): array {
    $db = getDB();

    // Check if entry exists
    $existing = getEntry($userId, $itemId, $dateId);

    if ($existing) {
        // Update existing entry
        $stmt = $db->prepare("
            UPDATE entries
            SET amount = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$amount, $existing['id']]);

        // Log history
        logEntryHistory($userId, $existing['id'], $itemId, $dateId, $amount, 'updated');

        return ['success' => true, 'id' => $existing['id'], 'action' => 'updated'];
    } else {
        // Create new entry
        $stmt = $db->prepare("
            INSERT INTO entries (user_id, budget_item_id, date_column_id, amount)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $itemId, $dateId, $amount]);
        $entryId = (int)$db->lastInsertId();

        // Log history
        logEntryHistory($userId, $entryId, $itemId, $dateId, $amount, 'created');

        return ['success' => true, 'id' => $entryId, 'action' => 'created'];
    }
}

/**
 * Delete an entry
 */
function deleteEntry(int $userId, int $itemId, int $dateId, ?string $notes = null): array {
    $db = getDB();

    $existing = getEntry($userId, $itemId, $dateId);
    if (!$existing) {
        return ['success' => false, 'error' => 'Entry not found'];
    }

    // Log history before deletion
    logEntryHistory($userId, $existing['id'], $itemId, $dateId, $existing['amount'], 'deleted', $notes);

    // Delete entry
    $stmt = $db->prepare("DELETE FROM entries WHERE id = ?");
    $stmt->execute([$existing['id']]);

    return ['success' => true];
}

/**
 * Log entry history
 */
function logEntryHistory(int $userId, ?int $entryId, int $itemId, int $dateId, ?float $amount, string $action, ?string $notes = null): void {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO entry_history (entry_id, user_id, budget_item_id, date_column_id, amount, action, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$entryId, $userId, $itemId, $dateId, $amount, $action, $notes]);
}

/**
 * Get entry history for a specific cell
 */
function getEntryHistory(int $userId, int $itemId, int $dateId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, amount, action, notes, recorded_at
        FROM entry_history
        WHERE user_id = ? AND budget_item_id = ? AND date_column_id = ?
        ORDER BY recorded_at DESC
    ");
    $stmt->execute([$userId, $itemId, $dateId]);
    return $stmt->fetchAll();
}

/**
 * Create a budget item
 */
function createBudgetItem(int $userId, string $name, ?string $notes = null): array {
    $db = getDB();

    // Get max sort order
    $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM budget_items WHERE user_id = ?");
    $stmt->execute([$userId]);
    $nextOrder = (int)$stmt->fetch()['next_order'];

    $stmt = $db->prepare("INSERT INTO budget_items (user_id, name, notes, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $name, $notes, $nextOrder]);

    return [
        'success' => true,
        'id' => (int)$db->lastInsertId(),
        'name' => $name,
        'notes' => $notes,
        'sort_order' => $nextOrder
    ];
}

/**
 * Update a budget item
 */
function updateBudgetItem(int $userId, int $itemId, ?string $name = null, ?string $notes = null): array {
    $db = getDB();

    // Verify ownership
    $stmt = $db->prepare("SELECT id FROM budget_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$itemId, $userId]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'error' => 'Item not found'];
    }

    $updates = [];
    $params = [];

    if ($name !== null) {
        $updates[] = "name = ?";
        $params[] = $name;
    }
    if ($notes !== null) {
        $updates[] = "notes = ?";
        $params[] = $notes;
    }

    if (empty($updates)) {
        return ['success' => false, 'error' => 'Nothing to update'];
    }

    $params[] = $itemId;
    $stmt = $db->prepare("UPDATE budget_items SET " . implode(", ", $updates) . " WHERE id = ?");
    $stmt->execute($params);

    return ['success' => true];
}

/**
 * Delete a budget item
 */
function deleteBudgetItem(int $userId, int $itemId): array {
    $db = getDB();

    // Verify ownership
    $stmt = $db->prepare("SELECT id FROM budget_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$itemId, $userId]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'error' => 'Item not found'];
    }

    // Delete (cascades to entries)
    $stmt = $db->prepare("DELETE FROM budget_items WHERE id = ?");
    $stmt->execute([$itemId]);

    return ['success' => true];
}

/**
 * Create a date column
 */
function createDateColumn(int $userId, string $dateValue): array {
    if (!isValidDate($dateValue)) {
        return ['success' => false, 'error' => 'Invalid date format'];
    }

    $db = getDB();

    // Check for duplicate
    $stmt = $db->prepare("SELECT id FROM date_columns WHERE user_id = ? AND date_value = ?");
    $stmt->execute([$userId, $dateValue]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Date already exists'];
    }

    // Get sort order based on date
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM date_columns WHERE user_id = ? AND date_value < ?");
    $stmt->execute([$userId, $dateValue]);
    $sortOrder = (int)$stmt->fetch()['cnt'];

    $stmt = $db->prepare("INSERT INTO date_columns (user_id, date_value, sort_order) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $dateValue, $sortOrder]);

    return [
        'success' => true,
        'id' => (int)$db->lastInsertId(),
        'date_value' => $dateValue,
        'sort_order' => $sortOrder
    ];
}

/**
 * Delete a date column
 */
function deleteDateColumn(int $userId, int $dateId): array {
    $db = getDB();

    // Verify ownership
    $stmt = $db->prepare("SELECT id FROM date_columns WHERE id = ? AND user_id = ?");
    $stmt->execute([$dateId, $userId]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'error' => 'Date column not found'];
    }

    // Delete (cascades to entries)
    $stmt = $db->prepare("DELETE FROM date_columns WHERE id = ?");
    $stmt->execute([$dateId]);

    return ['success' => true];
}

/**
 * Reorder budget items
 */
function reorderBudgetItems(int $userId, array $itemIds): bool {
    $db = getDB();

    foreach ($itemIds as $order => $itemId) {
        $stmt = $db->prepare("UPDATE budget_items SET sort_order = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$order, $itemId, $userId]);
    }

    return true;
}
