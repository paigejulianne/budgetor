<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgetor - Budget Tracker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="app-header">
        <h1>Budgetor</h1>
        <div class="header-actions">
            <span class="user-email"><?= htmlspecialchars($user['email']) ?></span>
            <a href="logout.php" class="btn btn-small">Logout</a>
        </div>
    </header>

    <main class="app-main">
        <div class="toolbar">
            <button id="addDateBtn" class="btn btn-primary">+ Add Date</button>
            <button id="addItemBtn" class="btn btn-primary">+ Add Item</button>
            <button id="showWizardBtn" class="btn">Setup Wizard</button>
            <div class="initial-balance-control">
                <label for="initialBalance">Initial Balance: $</label>
                <input type="number" id="initialBalance" step="0.01"
                       value="<?= number_format((float)$user['initial_balance'], 2, '.', '') ?>">
                <button id="saveInitialBalance" class="btn btn-small">Save</button>
            </div>
        </div>

        <div id="spreadsheet"></div>
    </main>

    <!-- Add Date Modal -->
    <div id="addDateModal" class="modal">
        <div class="modal-content">
            <h3>Add Date Column</h3>
            <div class="form-group">
                <label for="newDate">Date</label>
                <input type="date" id="newDate" required>
            </div>
            <div class="modal-actions">
                <button id="cancelAddDate" class="btn">Cancel</button>
                <button id="confirmAddDate" class="btn btn-primary">Add</button>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="addItemModal" class="modal">
        <div class="modal-content">
            <h3>Add Budget Item</h3>
            <div class="form-group">
                <label for="newItemName">Name</label>
                <input type="text" id="newItemName" required placeholder="e.g., Rent, Power Bill">
            </div>
            <div class="form-group">
                <label for="newItemNotes">Notes (optional)</label>
                <input type="text" id="newItemNotes" placeholder="e.g., Due on 15th">
            </div>
            <div class="modal-actions">
                <button id="cancelAddItem" class="btn">Cancel</button>
                <button id="confirmAddItem" class="btn btn-primary">Add</button>
            </div>
        </div>
    </div>

    <!-- Delete Entry Modal -->
    <div id="deleteEntryModal" class="modal">
        <div class="modal-content">
            <h3>Delete Entry</h3>
            <p>Are you sure you want to delete this entry?</p>
            <div class="delete-info">
                <p><strong>Item:</strong> <span id="deleteItemName"></span></p>
                <p><strong>Date:</strong> <span id="deleteDate"></span></p>
                <p><strong>Amount:</strong> $<span id="deleteAmount"></span></p>
            </div>
            <div class="form-group">
                <label for="deleteNotes">Notes (for history)</label>
                <textarea id="deleteNotes" rows="2" placeholder="Reason for deletion (optional)"></textarea>
            </div>
            <div class="modal-actions">
                <button id="cancelDelete" class="btn">Cancel</button>
                <button id="confirmDelete" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content modal-large">
            <h3>Entry History</h3>
            <div class="history-info">
                <p><strong>Item:</strong> <span id="historyItemName"></span></p>
                <p><strong>Date:</strong> <span id="historyDate"></span></p>
            </div>
            <div id="historyList" class="history-list"></div>
            <div class="modal-actions">
                <button id="closeHistory" class="btn">Close</button>
            </div>
        </div>
    </div>

    <!-- Setup Wizard Modal -->
    <div id="setupWizardModal" class="modal">
        <div class="modal-content">
            <h3>Welcome to Budgetor!</h3>
            <p class="wizard-intro">Let's set up your budget spreadsheet. How many date columns would you like, and how far apart should they be?</p>

            <div class="form-group">
                <label for="wizardStartDate">Start Date</label>
                <input type="date" id="wizardStartDate" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="wizardNumColumns">Number of Columns</label>
                    <input type="number" id="wizardNumColumns" min="1" max="52" value="10">
                </div>
                <div class="form-group">
                    <label for="wizardDaysBetween">Days Between</label>
                    <input type="number" id="wizardDaysBetween" min="1" max="365" value="15">
                </div>
            </div>

            <div class="form-group">
                <label for="wizardInitialBalance">Initial Balance ($)</label>
                <input type="number" id="wizardInitialBalance" step="0.01" value="0.00">
            </div>

            <div class="wizard-preview" id="wizardPreview"></div>

            <div class="modal-actions">
                <button id="skipWizard" class="btn">Skip (Start Empty)</button>
                <button id="confirmWizard" class="btn btn-primary">Create Columns</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
