/**
 * Budgetor - Main Application JavaScript
 */

(function() {
    'use strict';

    // State
    let hot = null;
    let budgetData = {
        initial_balance: 0,
        items: [],
        dates: [],
        entries: {}
    };

    // DOM Elements
    const spreadsheetEl = document.getElementById('spreadsheet');
    const addDateBtn = document.getElementById('addDateBtn');
    const addItemBtn = document.getElementById('addItemBtn');
    const initialBalanceInput = document.getElementById('initialBalance');
    const saveInitialBalanceBtn = document.getElementById('saveInitialBalance');

    // Modals
    const addDateModal = document.getElementById('addDateModal');
    const addItemModal = document.getElementById('addItemModal');
    const deleteEntryModal = document.getElementById('deleteEntryModal');
    const historyModal = document.getElementById('historyModal');
    const setupWizardModal = document.getElementById('setupWizardModal');

    // Pending delete info
    let pendingDelete = null;

    /**
     * Initialize application
     */
    async function init() {
        console.log('Budgetor: Initializing...');
        await loadBudgetData();
        console.log('Budgetor: Data loaded, dates count:', budgetData.dates.length);
        initEventListeners();

        // Show setup wizard if no dates exist
        if (budgetData.dates.length === 0) {
            console.log('Budgetor: Showing setup wizard');
            showSetupWizard();
        } else {
            console.log('Budgetor: Initializing spreadsheet');
            initSpreadsheet();
        }
    }

    /**
     * Show setup wizard for new users
     */
    function showSetupWizard() {
        console.log('Budgetor: showSetupWizard called');
        console.log('Budgetor: setupWizardModal element:', setupWizardModal);

        if (!setupWizardModal) {
            console.error('Budgetor: Setup wizard modal not found!');
            initSpreadsheet();
            return;
        }

        // Set default values
        const today = new Date();
        document.getElementById('wizardStartDate').value = today.toISOString().split('T')[0];
        document.getElementById('wizardNumColumns').value = '10';
        document.getElementById('wizardDaysBetween').value = '15';
        document.getElementById('wizardInitialBalance').value = '0.00';
        setupWizardModal.classList.add('active');
        console.log('Budgetor: Wizard modal should now be visible');
    }

    /**
     * Run setup wizard - create date columns
     */
    async function runSetupWizard() {
        const startDate = new Date(document.getElementById('wizardStartDate').value + 'T00:00:00');
        const numColumns = parseInt(document.getElementById('wizardNumColumns').value) || 10;
        const daysBetween = parseInt(document.getElementById('wizardDaysBetween').value) || 15;
        const initialBalance = parseFloat(document.getElementById('wizardInitialBalance').value) || 0;

        if (isNaN(startDate.getTime())) {
            alert('Please enter a valid start date');
            return;
        }

        // Save initial balance
        try {
            await fetch('api/settings.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ initial_balance: initialBalance })
            });
            budgetData.initial_balance = initialBalance;
            initialBalanceInput.value = initialBalance.toFixed(2);
        } catch (error) {
            console.error('Failed to save initial balance:', error);
        }

        // Create date columns
        const dates = [];
        for (let i = 0; i < numColumns; i++) {
            const date = new Date(startDate);
            date.setDate(date.getDate() + (i * daysBetween));
            dates.push(date.toISOString().split('T')[0]);
        }

        // Create all dates via API
        for (const dateStr of dates) {
            try {
                const response = await fetch('api/dates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ date: dateStr })
                });
                const result = await response.json();
                if (result.success) {
                    budgetData.dates.push({
                        id: result.id,
                        date_value: result.date_value,
                        sort_order: result.sort_order
                    });
                }
            } catch (error) {
                console.error('Failed to create date:', error);
            }
        }

        setupWizardModal.classList.remove('active');
        initSpreadsheet();
    }

    /**
     * Load budget data from API
     */
    async function loadBudgetData() {
        try {
            console.log('Budgetor: Fetching budget data...');
            const response = await fetch('api/budget.php');
            console.log('Budgetor: Response status:', response.status);
            const result = await response.json();
            console.log('Budgetor: API result:', result);
            if (result.success) {
                budgetData = result.data;
                initialBalanceInput.value = budgetData.initial_balance.toFixed(2);
                console.log('Budgetor: Budget data loaded successfully');
            } else {
                console.error('Budgetor: API returned error:', result.error);
            }
        } catch (error) {
            console.error('Failed to load budget data:', error);
            alert('Failed to load budget data. Please refresh the page.');
        }
    }

    /**
     * Build spreadsheet data from budget data
     */
    function buildSpreadsheetData() {
        const dates = budgetData.dates;
        const items = budgetData.items;
        const entries = budgetData.entries;

        // Build header row (dates)
        const headerRow = ['Item', 'Notes'];
        dates.forEach(date => {
            headerRow.push(formatDate(date.date_value));
        });

        // Build starting balance row
        const startingRow = ['Starting Balance', ''];
        let runningBalance = budgetData.initial_balance;
        const startingBalances = [runningBalance];

        // Calculate starting balances for each column
        dates.forEach((date, colIndex) => {
            if (colIndex === 0) {
                startingRow.push(runningBalance);
            } else {
                // Calculate from previous column's ending balance
                let prevTotal = startingBalances[colIndex - 1] || budgetData.initial_balance;
                items.forEach(item => {
                    const key = item.id + '_' + dates[colIndex - 1].id;
                    if (entries[key] !== undefined) {
                        prevTotal += parseFloat(entries[key]);
                    }
                });
                startingBalances.push(prevTotal);
                startingRow.push(prevTotal);
            }
        });

        // Build item rows
        const itemRows = items.map(item => {
            const row = [item.name, item.notes || ''];
            dates.forEach(date => {
                const key = item.id + '_' + date.id;
                row.push(entries[key] !== undefined ? parseFloat(entries[key]) : null);
            });
            return row;
        });

        // Build subtotal row (sum of items only, NOT including starting balance)
        const subtotalRow = ['Subtotal', ''];
        dates.forEach((date, colIndex) => {
            let itemsTotal = 0;
            items.forEach(item => {
                const key = item.id + '_' + date.id;
                if (entries[key] !== undefined) {
                    itemsTotal += parseFloat(entries[key]);
                }
            });
            subtotalRow.push(itemsTotal);
        });

        // Build ending balance row (starting balance + subtotal)
        const endingRow = ['Ending Balance', ''];
        dates.forEach((date, colIndex) => {
            let itemsTotal = 0;
            items.forEach(item => {
                const key = item.id + '_' + date.id;
                if (entries[key] !== undefined) {
                    itemsTotal += parseFloat(entries[key]);
                }
            });
            endingRow.push(startingBalances[colIndex] + itemsTotal);
        });

        return {
            data: [headerRow, startingRow, ...itemRows, subtotalRow, endingRow],
            startingBalances
        };
    }

    /**
     * Initialize Handsontable spreadsheet
     */
    function initSpreadsheet() {
        const { data, startingBalances } = buildSpreadsheetData();

        const numRows = data.length;
        const numCols = data[0].length;
        const numItems = budgetData.items.length;

        hot = new Handsontable(spreadsheetEl, {
            data: data,
            rowHeaders: false,
            colHeaders: false,
            width: '100%',
            height: 'auto',
            stretchH: 'all',
            manualColumnResize: true,
            manualRowResize: true,
            colWidths: function(index) {
                if (index === 0) return 150;  // Item name column
                if (index === 1) return 120;  // Notes column
                return 110;  // Date columns
            },
            licenseKey: 'non-commercial-and-evaluation',
            contextMenu: {
                items: {
                    'view_history': {
                        name: 'View History',
                        callback: function(key, selection) {
                            const row = selection[0].start.row;
                            const col = selection[0].start.col;
                            showHistory(row, col);
                        },
                        disabled: function() {
                            const sel = hot.getSelectedLast();
                            if (!sel) return true;
                            const row = sel[0];
                            const col = sel[1];
                            return !isDataCell(row, col);
                        }
                    },
                    'separator': '---------',
                    'delete_row': {
                        name: 'Delete Item',
                        callback: function(key, selection) {
                            const row = selection[0].start.row;
                            deleteItem(row);
                        },
                        disabled: function() {
                            const sel = hot.getSelectedLast();
                            if (!sel) return true;
                            const row = sel[0];
                            return !isItemRow(row);
                        }
                    },
                    'delete_col': {
                        name: 'Delete Date Column',
                        callback: function(key, selection) {
                            const col = selection[0].start.col;
                            deleteDateColumn(col);
                        },
                        disabled: function() {
                            const sel = hot.getSelectedLast();
                            if (!sel) return true;
                            const col = sel[1];
                            return col < 2;
                        }
                    }
                }
            },
            cells: function(row, col) {
                const cellProperties = {};

                // Header row
                if (row === 0) {
                    cellProperties.readOnly = true;
                    cellProperties.className = 'header-cell';
                }
                // Starting balance row
                else if (row === 1) {
                    cellProperties.className = 'starting-balance-row readonly-cell';
                    cellProperties.readOnly = true;
                    if (col >= 2) {
                        cellProperties.type = 'numeric';
                        cellProperties.numericFormat = {
                            pattern: '$0,0.00',
                            culture: 'en-US'
                        };
                    }
                }
                // Item rows
                else if (row >= 2 && row < numRows - 2) {
                    if (col === 0) {
                        cellProperties.className = 'item-name-cell';
                    } else if (col === 1) {
                        cellProperties.className = 'notes-cell';
                    } else {
                        cellProperties.type = 'numeric';
                        cellProperties.numericFormat = {
                            pattern: '$0,0.00',
                            culture: 'en-US'
                        };
                    }
                }
                // Subtotal row
                else if (row === numRows - 2) {
                    cellProperties.readOnly = true;
                    cellProperties.className = 'subtotal-row readonly-cell';
                    if (col >= 2) {
                        cellProperties.type = 'numeric';
                        cellProperties.numericFormat = {
                            pattern: '$0,0.00',
                            culture: 'en-US'
                        };
                    }
                }
                // Ending balance row
                else if (row === numRows - 1) {
                    cellProperties.readOnly = true;
                    cellProperties.className = 'ending-balance-row readonly-cell';
                    if (col >= 2) {
                        cellProperties.type = 'numeric';
                        cellProperties.numericFormat = {
                            pattern: '$0,0.00',
                            culture: 'en-US'
                        };
                    }
                }

                // First two columns in special rows are read-only
                if ((row === 0 || row === 1 || row === numRows - 2 || row === numRows - 1) && col < 2) {
                    cellProperties.readOnly = true;
                }

                return cellProperties;
            },
            afterChange: function(changes, source) {
                if (source === 'loadData' || !changes) return;

                changes.forEach(([row, col, oldVal, newVal]) => {
                    handleCellChange(row, col, oldVal, newVal);
                });
            },
            afterRenderer: function(td, row, col, prop, value, cellProperties) {
                // Add color classes for amounts
                if (col >= 2 && row >= 1 && typeof value === 'number') {
                    if (value < 0) {
                        td.classList.add('negative-amount');
                    } else if (value > 0) {
                        td.classList.add('positive-amount');
                    }
                }
            }
        });
    }

    /**
     * Check if row is an item row (not header, starting balance, subtotal, or ending)
     */
    function isItemRow(row) {
        const numRows = hot.countRows();
        return row >= 2 && row < numRows - 2;
    }

    /**
     * Check if cell is a data cell (item amount)
     */
    function isDataCell(row, col) {
        return isItemRow(row) && col >= 2;
    }

    /**
     * Handle cell change
     */
    async function handleCellChange(row, col, oldVal, newVal) {
        const numRows = hot.countRows();
        const itemIndex = row - 2;

        // Item name change
        if (isItemRow(row) && col === 0) {
            const item = budgetData.items[itemIndex];
            if (item && newVal !== oldVal) {
                await updateItem(item.id, { name: newVal });
            }
        }
        // Item notes change
        else if (isItemRow(row) && col === 1) {
            const item = budgetData.items[itemIndex];
            if (item && newVal !== oldVal) {
                await updateItem(item.id, { notes: newVal });
            }
        }
        // Amount change
        else if (isDataCell(row, col)) {
            const item = budgetData.items[itemIndex];
            const dateIndex = col - 2;
            const date = budgetData.dates[dateIndex];

            if (item && date) {
                if (newVal === null || newVal === '' || newVal === undefined) {
                    // Deletion - show modal
                    if (oldVal !== null && oldVal !== '' && oldVal !== undefined) {
                        pendingDelete = { row, col, itemIndex, dateIndex, oldVal };
                        showDeleteModal(item, date, oldVal);
                        // Restore old value until confirmed
                        hot.setDataAtCell(row, col, oldVal, 'revert');
                    }
                } else {
                    // Update or create
                    await saveEntry(item.id, date.id, parseFloat(newVal));
                    recalculateBalances();
                }
            }
        }
    }

    /**
     * Save entry to API
     */
    async function saveEntry(itemId, dateId, amount) {
        try {
            const response = await fetch('api/entries.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_id: itemId, date_id: dateId, amount })
            });
            const result = await response.json();
            if (result.success) {
                budgetData.entries[itemId + '_' + dateId] = amount;
            }
        } catch (error) {
            console.error('Failed to save entry:', error);
            alert('Failed to save entry. Please try again.');
        }
    }

    /**
     * Delete entry via API
     */
    async function deleteEntryApi(itemId, dateId, notes) {
        try {
            const response = await fetch('api/entries.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_id: itemId, date_id: dateId, notes })
            });
            const result = await response.json();
            if (result.success) {
                delete budgetData.entries[itemId + '_' + dateId];
                return true;
            }
        } catch (error) {
            console.error('Failed to delete entry:', error);
            alert('Failed to delete entry. Please try again.');
        }
        return false;
    }

    /**
     * Recalculate all balances after a change
     */
    function recalculateBalances() {
        const dates = budgetData.dates;
        const items = budgetData.items;
        const entries = budgetData.entries;
        const numRows = hot.countRows();

        let runningBalance = budgetData.initial_balance;

        dates.forEach((date, colIndex) => {
            const col = colIndex + 2;

            // Starting balance
            hot.setDataAtCell(1, col, runningBalance, 'recalc');

            // Calculate sum of items only (subtotal)
            let itemsTotal = 0;
            items.forEach(item => {
                const key = item.id + '_' + date.id;
                if (entries[key] !== undefined) {
                    itemsTotal += parseFloat(entries[key]);
                }
            });

            // Subtotal (sum of items only)
            hot.setDataAtCell(numRows - 2, col, itemsTotal, 'recalc');

            // Ending balance (starting balance + subtotal)
            const endingBalance = runningBalance + itemsTotal;
            hot.setDataAtCell(numRows - 1, col, endingBalance, 'recalc');

            // Set next column's starting balance
            runningBalance = endingBalance;
        });

        hot.render();
    }

    /**
     * Update item via API
     */
    async function updateItem(itemId, data) {
        try {
            const response = await fetch('api/items.php?id=' + itemId, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) {
                const item = budgetData.items.find(i => i.id == itemId);
                if (item) {
                    if (data.name !== undefined) item.name = data.name;
                    if (data.notes !== undefined) item.notes = data.notes;
                }
            }
        } catch (error) {
            console.error('Failed to update item:', error);
        }
    }

    /**
     * Delete item
     */
    async function deleteItem(row) {
        const itemIndex = row - 2;
        const item = budgetData.items[itemIndex];
        if (!item) return;

        if (!confirm(`Are you sure you want to delete "${item.name}"? All entries for this item will be deleted.`)) {
            return;
        }

        try {
            const response = await fetch('api/items.php?id=' + item.id, {
                method: 'DELETE'
            });
            const result = await response.json();
            if (result.success) {
                // Remove from local data
                budgetData.items.splice(itemIndex, 1);
                // Remove entries
                Object.keys(budgetData.entries).forEach(key => {
                    if (key.startsWith(item.id + '_')) {
                        delete budgetData.entries[key];
                    }
                });
                // Rebuild spreadsheet
                refreshSpreadsheet();
            }
        } catch (error) {
            console.error('Failed to delete item:', error);
            alert('Failed to delete item. Please try again.');
        }
    }

    /**
     * Delete date column
     */
    async function deleteDateColumn(col) {
        const dateIndex = col - 2;
        const date = budgetData.dates[dateIndex];
        if (!date) return;

        if (!confirm(`Are you sure you want to delete the column for ${formatDate(date.date_value)}? All entries for this date will be deleted.`)) {
            return;
        }

        try {
            const response = await fetch('api/dates.php?id=' + date.id, {
                method: 'DELETE'
            });
            const result = await response.json();
            if (result.success) {
                // Remove from local data
                budgetData.dates.splice(dateIndex, 1);
                // Remove entries
                Object.keys(budgetData.entries).forEach(key => {
                    if (key.endsWith('_' + date.id)) {
                        delete budgetData.entries[key];
                    }
                });
                // Rebuild spreadsheet
                refreshSpreadsheet();
            }
        } catch (error) {
            console.error('Failed to delete date column:', error);
            alert('Failed to delete column. Please try again.');
        }
    }

    /**
     * Refresh spreadsheet with current data
     */
    function refreshSpreadsheet() {
        hot.destroy();
        initSpreadsheet();
    }

    /**
     * Format date for display
     */
    function formatDate(dateStr) {
        const date = new Date(dateStr + 'T00:00:00');
        return date.toLocaleDateString('en-US', {
            month: '2-digit',
            day: '2-digit',
            year: 'numeric'
        });
    }

    /**
     * Show delete confirmation modal
     */
    function showDeleteModal(item, date, amount) {
        document.getElementById('deleteItemName').textContent = item.name;
        document.getElementById('deleteDate').textContent = formatDate(date.date_value);
        document.getElementById('deleteAmount').textContent = Math.abs(amount).toFixed(2);
        document.getElementById('deleteNotes').value = '';
        deleteEntryModal.classList.add('active');
    }

    /**
     * Show history modal
     */
    async function showHistory(row, col) {
        const itemIndex = row - 2;
        const dateIndex = col - 2;
        const item = budgetData.items[itemIndex];
        const date = budgetData.dates[dateIndex];

        if (!item || !date) return;

        document.getElementById('historyItemName').textContent = item.name;
        document.getElementById('historyDate').textContent = formatDate(date.date_value);

        const historyList = document.getElementById('historyList');
        historyList.innerHTML = '<div class="no-history">Loading...</div>';
        historyModal.classList.add('active');

        try {
            const response = await fetch(`api/history.php?item_id=${item.id}&date_id=${date.id}`);
            const result = await response.json();

            if (result.success && result.history.length > 0) {
                historyList.innerHTML = result.history.map(h => `
                    <div class="history-item">
                        <span class="action ${h.action}">${h.action}</span>
                        <span class="amount">$${h.amount !== null ? Math.abs(h.amount).toFixed(2) : 'N/A'}</span>
                        <span class="timestamp">${new Date(h.recorded_at).toLocaleString()}</span>
                        ${h.notes ? `<div class="notes">${escapeHtml(h.notes)}</div>` : ''}
                    </div>
                `).join('');
            } else {
                historyList.innerHTML = '<div class="no-history">No history for this cell</div>';
            }
        } catch (error) {
            console.error('Failed to load history:', error);
            historyList.innerHTML = '<div class="no-history">Failed to load history</div>';
        }
    }

    /**
     * Escape HTML
     */
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Initialize event listeners
     */
    function initEventListeners() {
        // Add Date
        addDateBtn.addEventListener('click', () => {
            document.getElementById('newDate').value = '';
            addDateModal.classList.add('active');
        });

        document.getElementById('cancelAddDate').addEventListener('click', () => {
            addDateModal.classList.remove('active');
        });

        document.getElementById('confirmAddDate').addEventListener('click', async () => {
            const dateValue = document.getElementById('newDate').value;
            if (!dateValue) {
                alert('Please select a date');
                return;
            }

            try {
                const response = await fetch('api/dates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ date: dateValue })
                });
                const result = await response.json();
                if (result.success) {
                    budgetData.dates.push({
                        id: result.id,
                        date_value: result.date_value,
                        sort_order: result.sort_order
                    });
                    // Sort dates by date_value
                    budgetData.dates.sort((a, b) => a.date_value.localeCompare(b.date_value));
                    refreshSpreadsheet();
                    addDateModal.classList.remove('active');
                } else {
                    alert(result.error || 'Failed to add date');
                }
            } catch (error) {
                console.error('Failed to add date:', error);
                alert('Failed to add date. Please try again.');
            }
        });

        // Add Item
        addItemBtn.addEventListener('click', () => {
            document.getElementById('newItemName').value = '';
            document.getElementById('newItemNotes').value = '';
            addItemModal.classList.add('active');
        });

        document.getElementById('cancelAddItem').addEventListener('click', () => {
            addItemModal.classList.remove('active');
        });

        document.getElementById('confirmAddItem').addEventListener('click', async () => {
            const name = document.getElementById('newItemName').value.trim();
            const notes = document.getElementById('newItemNotes').value.trim();

            if (!name) {
                alert('Please enter an item name');
                return;
            }

            try {
                const response = await fetch('api/items.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, notes: notes || null })
                });
                const result = await response.json();
                if (result.success) {
                    budgetData.items.push({
                        id: result.id,
                        name: result.name,
                        notes: result.notes,
                        sort_order: result.sort_order
                    });
                    refreshSpreadsheet();
                    addItemModal.classList.remove('active');
                } else {
                    alert(result.error || 'Failed to add item');
                }
            } catch (error) {
                console.error('Failed to add item:', error);
                alert('Failed to add item. Please try again.');
            }
        });

        // Delete Entry Modal
        document.getElementById('cancelDelete').addEventListener('click', () => {
            pendingDelete = null;
            deleteEntryModal.classList.remove('active');
        });

        document.getElementById('confirmDelete').addEventListener('click', async () => {
            if (!pendingDelete) return;

            const { row, col, itemIndex, dateIndex, oldVal } = pendingDelete;
            const item = budgetData.items[itemIndex];
            const date = budgetData.dates[dateIndex];
            const notes = document.getElementById('deleteNotes').value.trim();

            const success = await deleteEntryApi(item.id, date.id, notes || null);
            if (success) {
                hot.setDataAtCell(row, col, null, 'delete');
                recalculateBalances();
            }

            pendingDelete = null;
            deleteEntryModal.classList.remove('active');
        });

        // History Modal
        document.getElementById('closeHistory').addEventListener('click', () => {
            historyModal.classList.remove('active');
        });

        // Initial Balance
        saveInitialBalanceBtn.addEventListener('click', async () => {
            const balance = parseFloat(initialBalanceInput.value) || 0;

            try {
                const response = await fetch('api/settings.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ initial_balance: balance })
                });
                const result = await response.json();
                if (result.success) {
                    budgetData.initial_balance = balance;
                    recalculateBalances();
                    alert('Initial balance saved!');
                }
            } catch (error) {
                console.error('Failed to save initial balance:', error);
                alert('Failed to save initial balance. Please try again.');
            }
        });

        // Close modals on backdrop click
        [addDateModal, addItemModal, deleteEntryModal, historyModal].forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                    pendingDelete = null;
                }
            });
        });

        // Close modals on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                addDateModal.classList.remove('active');
                addItemModal.classList.remove('active');
                deleteEntryModal.classList.remove('active');
                historyModal.classList.remove('active');
                pendingDelete = null;
            }
        });

        // Setup Wizard
        document.getElementById('confirmWizard').addEventListener('click', runSetupWizard);
        document.getElementById('skipWizard').addEventListener('click', () => {
            setupWizardModal.classList.remove('active');
            initSpreadsheet();
        });

        // Manual wizard trigger button
        document.getElementById('showWizardBtn').addEventListener('click', () => {
            showSetupWizard();
        });
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', init);
})();
