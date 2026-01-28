const GridModule = {
    hot: null,
    partsCache: {}, // Cache to store parts data from API
    receiptAmountEl: null,
    diffAmountEl: null,

    /**
     * Initialize the Handsontable grid
     */
    init() {
        // Get receipt summary elements
        this.receiptAmountEl = document.getElementById('receipt_amount');
        this.diffAmountEl = document.getElementById('diff_amount');

        const moneyCol = {
            type: 'numeric',
            numericFormat: {
                pattern: '0,0',
                culture: 'vi-VN'
            }
        };

        const self = this;

        this.hot = new Handsontable(document.getElementById('hot'), {
            data: [],
            rowHeaders: true,
            stretchH: 'all',
            minRows: 8,

            colHeaders: [
                'Linh kiện', 'Số lượng', 'Đơn giá bán lẻ', 'Doanh thu',
                'Thuế GTGT (%)', 'Thuế GTGT', 'Thành tiền', 'Ghi chú'
            ],

            columns: [
                {
                    type: 'autocomplete',
                    source: function (query, callback) {
                        if (!query || query.length < 1) {
                            callback([]);
                            return;
                        }

                        fetch('api/search-parts.php?term=' + encodeURIComponent(query))
                            .then(response => response.json())
                            .then(result => {
                                if (result.success) {
                                    // Store parts data in cache for later use
                                    if (result.partsMap) {
                                        Object.assign(self.partsCache, result.partsMap);
                                    }
                                    callback(result.data);
                                } else {
                                    console.error('Error fetching parts:', result.message);
                                    callback([]);
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching parts:', error);
                                callback([]);
                            });
                    },
                    trimDropdown: false,
                    strict: false,
                    width: 190,
                    // Custom renderer for inactive parts
                    renderer: function (instance, td, row, col, prop, value, cellProperties) {
                        Handsontable.renderers.AutocompleteRenderer.apply(this, arguments);

                        // Check if this part is inactive
                        if (value && self.partsCache[value] && self.partsCache[value].is_active === 0) {
                            td.style.color = '#999';
                            td.style.fontStyle = 'italic';
                            td.style.textDecoration = 'line-through';
                        }
                    }
                },
                { type: 'numeric', width: 60 },    // Số lượng
                { ...moneyCol, width: 130 },       // Đơn giá bán lẻ
                { ...moneyCol, readOnly: true, width: 130 }, // Doanh thu
                { type: 'dropdown', source: [0, 8, 10], width: 100 }, // Thuế %
                { ...moneyCol, readOnly: true, width: 110 }, // Thuế GTGT
                { ...moneyCol, readOnly: true, width: 120 }, // Thành tiền
                { type: 'text', width: 200 }       // Ghi chú
            ],

            licenseKey: 'non-commercial-and-evaluation',

            cells: function (row) {
                const last = this.instance.countRows() - 1;
                if (row === last) {
                    return { readOnly: true, className: 'summary-row' };
                }
            },

            beforeKeyDown: function (e) {
                if (e.key !== 'Enter') return;

                const sel = this.getSelectedLast();
                if (!sel) return;

                const [row, col] = sel;
                const lastDataRow = this.countRows() - 2;
                const lastCol = this.countCols() - 1;

                e.preventDefault();
                e.stopImmediatePropagation();

                if (col === lastCol) {
                    if (row < lastDataRow) {
                        this.selectCell(row + 1, 0);
                    }
                    return;
                }
                this.selectCell(row, col + 1);
            },

            afterChange: function (changes, src) {
                if (!changes || src === 'calc' || src === 'summary' || src === 'autofill') return;

                changes.forEach(([r, c, oldVal, newVal]) => {
                    const last = GridModule.hot.countRows() - 1;
                    if (r >= last) return;

                    // If part column changed (column 0), auto-fill other columns
                    if (c === 0 && newVal && newVal !== oldVal) {
                        const partInfo = GridModule.partsCache[newVal];
                        // console.log(partInfo, "partInfo for", newVal);
                        if (partInfo) {
                            // Check if part is inactive - prevent selection
                            if (partInfo.is_active === 0) {
                                alert('Linh kiện này đã ngừng sử dụng, không thể chọn!');
                                GridModule.hot.setDataAtCell(r, 0, oldVal || '', 'autofill');
                                return;
                            }
        
                            // Check if price is expired (>30 days) - prevent selection
                            if (partInfo.price_is_valid === 0) {
                                const days = partInfo.days_since_confirm || 'N/A';
                                alert(`Giá đã hết hiệu lực (${days} ngày kể từ lần confirm cuối).\n\nVui lòng liên hệ quản lý giá để cập nhật trước khi sử dụng linh kiện này.`);
                                GridModule.hot.setDataAtCell(r, 0, oldVal || '', 'autofill');
                                return;
                            }

                            // Lấy giá trị hiện tại
                            let qty = GridModule.hot.getDataAtCell(r, 1);
                            if (!qty || qty === 0) {
                                qty = 1;
                            }

                            const price = partInfo.retail_price || 0;

                            let taxPct = GridModule.hot.getDataAtCell(r, 4);
                            if (!taxPct && taxPct !== 0) {
                                // Use vat_pct from master data
                                taxPct = partInfo.vat_pct !== undefined ? partInfo.vat_pct : 10;
                            }

                            // Tính toán các giá trị
                            const dt = qty * price;
                            const th = Math.round(dt * taxPct / 100 / 5) * 5;
                            const tt = dt + th;

                            // Batch tất cả changes cùng lúc
                            GridModule.hot.setDataAtCell([
                                [r, 1, qty],
                                [r, 2, price],
                                [r, 3, dt],
                                [r, 4, taxPct],
                                [r, 5, th],
                                [r, 6, tt]
                            ], 'autofill');
                        }
                    } else {
                        // User changed other columns (quantity, price, tax, etc.)

                        // Special check for unit price change (column 2)
                        if (c === 2 && newVal !== oldVal) {
                            const partName = GridModule.hot.getDataAtCell(r, 0);
                            const partInfo = GridModule.partsCache[partName];

                            if (partInfo && partInfo.retail_price) {
                                const retailPrice = partInfo.retail_price;
                                const userPrice = parseFloat(newVal) || 0;
                                const threshold = partInfo.max_price_diff_percent || 10;

                                if (userPrice > 0) {
                                    const diff = Math.abs(retailPrice - userPrice);
                                    const diffPercent = (diff / retailPrice) * 100;

                                    if (diffPercent > threshold) {
                                        // Highlight cell with warning
                                        GridModule.hot.setCellMeta(r, 2, 'className', 'price-variance-warning');

                                        // Show warning alert
                                        setTimeout(() => {
                                            alert(
                                                `⚠️ CẢNH BÁO: Giá chênh lệch ${diffPercent.toFixed(1)}% (vượt ngưỡng ${threshold}%)\n\n` +
                                                `Giá bảng: ${retailPrice.toLocaleString('vi-VN')} VNĐ\n` +
                                                `Giá nhập: ${userPrice.toLocaleString('vi-VN')} VNĐ\n` +
                                                `Chênh lệch: ${diff.toLocaleString('vi-VN')} VNĐ\n\n` +
                                                `Cần liên hệ chị Loan để duyệt giá trước khi hoàn thành phiếu.`
                                            );
                                        }, 100);
                                    } else {
                                        // Clear warning if within threshold
                                        GridModule.hot.setCellMeta(r, 2, 'className', '');
                                    }
                                }
                            }
                        }

                        // Tính toán khi thay đổi các cột khác (số lượng, đơn giá, thuế)
                        GridModule.calculateRow(r);
                    }
                });

                // Delay updateSummary để đảm bảo tất cả giá trị đã được commit
                setTimeout(() => GridModule.updateSummary(), 50);
            },

            afterSelection: function (r) {
                const last = GridModule.hot.countRows() - 1;
                if (r < last) {
                    GridModule.hot.setCellMeta(r, 0, 'className', 'active-row');
                    GridModule.hot.render();
                }
            },

            beforePaste: function (data, coords) {
                const numericCols = [1, 2, 3, 4, 5, 6];
                for (let i = 0; i < data.length; i++) {
                    for (let j = 0; j < data[i].length; j++) {
                        const targetCol = coords[0].startCol + j;
                        if (numericCols.includes(targetCol) && data[i][j]) {
                            let val = String(data[i][j]).replace(/,/g, '');
                            data[i][j] = val;
                        }
                    }
                }
            }
        });

        // Add summary row
        this.hot.alter('insert_row_below', this.hot.countRows() - 1);
        this.updateSummary();
    },

    /**
     * Calculate totals for a specific row
     * @param {number} row - Row index
     */
    calculateRow(row) {
        const last = this.hot.countRows() - 1;
        if (row >= last) return;

        let sl = +this.hot.getDataAtCell(row, 1) || 0;
        let dg = +this.hot.getDataAtCell(row, 2) || 0;
        let tax = +this.hot.getDataAtCell(row, 4) || 0;

        let dt = sl * dg;
        let th = Math.round(dt * tax / 100 / 5) * 5; // Làm tròn đến bội số 5
        let tt = dt + th;

        this.hot.setDataAtCell(row, 3, dt, 'calc');
        this.hot.setDataAtCell(row, 5, th, 'calc');
        this.hot.setDataAtCell(row, 6, tt, 'calc');
    },

    /**
     * Calculate totals for a specific row - Direct source data modification
     * @param {number} row - Row index
     */
    calculateRowDirect(row) {
        const last = this.hot.countRows() - 1;
        if (row >= last) return;

        const sourceData = this.hot.getSourceData();
        const rowData = sourceData[row];
        if (!rowData) return;

        let sl = +rowData[1] || 0;
        let dg = +rowData[2] || 0;
        let tax = +rowData[4] || 0;

        let dt = sl * dg;
        let th = Math.round(dt * tax / 100 / 5) * 5; // Làm tròn đến bội số 5
        let tt = dt + th;

        rowData[3] = dt;
        rowData[5] = th;
        rowData[6] = tt;

        this.hot.render();
    },

    /**
     * Update summary row with totals
     */
    updateSummary() {
        const data = this.hot.getData();
        const last = data.length - 1;
        let tDT = 0, tTax = 0, tTT = 0;

        // Dùng getData để lấy giá trị hiện tại
        for (let i = 0; i < last; i++) {
            const row = data[i];
            if (!row || !row[0] || row[0] === 'TỔNG CỘNG') continue;

            const dt = +row[3] || 0;
            const tax = +row[5] || 0;
            const tt = +row[6] || 0;

            tDT += dt;
            tTax += tax;
            tTT += tt;
        }

        console.log('updateSummary:', { tDT, tTax, tTT });

        this.hot.setDataAtCell([
            [last, 0, 'TỔNG CỘNG'],
            [last, 3, tDT],
            [last, 5, tTax],
            [last, 6, tTT]
        ], 'summary');

        // Update receipt summary fields
        this.updateReceiptSummary(tTT);
    },

    /**
     * Update receipt amount and difference display
     * @param {number} totalAmount - Total "Thành tiền" from grid
     */
    updateReceiptSummary(totalAmount) {
        if (!this.receiptAmountEl || !this.diffAmountEl) return;

        // Format number with thousand separators
        const formatted = new Intl.NumberFormat('vi-VN').format(totalAmount);
        this.receiptAmountEl.value = formatted;

        // For now, difference is 0 (Receipt = Total)
        // Later this can be changed if user wants to input a different receipt amount
        const diff = 0;
        this.diffAmountEl.value = new Intl.NumberFormat('vi-VN').format(diff);

        // Update diff color based on value
        if (diff > 0) {
            this.diffAmountEl.style.color = '#27ae60'; // Green for positive
            this.diffAmountEl.style.borderColor = '#27ae60';
        } else if (diff < 0) {
            this.diffAmountEl.style.color = '#e74c3c'; // Red for negative
            this.diffAmountEl.style.borderColor = '#e74c3c';
        } else {
            this.diffAmountEl.style.color = '#95a5a6'; // Gray for zero
            this.diffAmountEl.style.borderColor = '#95a5a6';
        }
    },

    /**
     * Load data into grid
     * @param {Array} data 
     */
    loadData(data) {
        this.hot.loadData(data);
        this.hot.alter('insert_row_below', this.hot.countRows() - 1);

        // Tính toán lại tất cả các dòng sau khi load
        const sourceData = this.hot.getSourceData();
        for (let i = 0; i < sourceData.length; i++) {
            const rowData = sourceData[i];
            if (!rowData || !rowData[0]) continue;

            let sl = +rowData[1] || 0;
            let dg = +rowData[2] || 0;
            let tax = +rowData[4] || 0;

            let dt = sl * dg;
            let th = Math.round(dt * tax / 100 / 5) * 5;
            let tt = dt + th;

            rowData[3] = dt;
            rowData[5] = th;
            rowData[6] = tt;
        }

        this.hot.render();
        this.updateSummary();
    },

    /**
     * Get grid data (excluding summary row)
     * @returns {Array}
     */
    getData() {
        return this.hot.getData().slice(0, -1);
    },

    /**
     * Clear grid data
     */
    clear() {
        this.hot.loadData([]);
        this.hot.alter('insert_row_below', this.hot.countRows() - 1);
        this.updateSummary();
    },

    /**
     * Focus on first cell
     */
    focus() {
        setTimeout(() => this.hot.selectCell(0, 0), 50);
    },

    /**
     * Validate grid data before saving
     * @returns {Object} { valid: boolean, errors: string[] }
     */
    validate() {
        const errors = [];
        const data = this.getData();
        const validTaxValues = [0, 8, 10];
        let validRowCount = 0;
        let hasPriceWarning = false;

        // Clear previous error highlights
        this.clearValidationErrors();

        for (let row = 0; row < data.length; row++) {
            const rowData = data[row];
            const partName = rowData[0];
            const qty = rowData[1];
            const price = rowData[2];
            const tax = rowData[4];
            const note = rowData[7];

            // Check if row has any data
            const hasAnyData = (qty !== null && qty !== '' && qty !== 0) ||
                (price !== null && price !== '' && price !== 0) ||
                (tax !== null && tax !== '' && tax !== 0) ||
                (note !== null && note !== '' && String(note).trim() !== '');

            // Skip completely empty rows (no part name and no other data)
            if ((!partName || partName.trim() === '') && !hasAnyData) continue;

            // If row has data but no part name - this is an error
            if ((!partName || partName.trim() === '') && hasAnyData) {
                errors.push(`Dòng ${row + 1}: Có dữ liệu nhưng thiếu tên linh kiện`);
                this.hot.setCellMeta(row, 0, 'className', 'htInvalid');
                continue; // Don't validate other fields if missing part name
            }

            validRowCount++;

            // Validate quantity - must be positive integer
            if (qty === null || qty === '' || isNaN(qty) || !Number.isInteger(Number(qty)) || Number(qty) <= 0) {
                errors.push(`Dòng ${row + 1}: Số lượng phải là số nguyên dương`);
                this.hot.setCellMeta(row, 1, 'className', 'htInvalid');
            }

            // Validate price - must be positive number
            if (price === null || price === '' || isNaN(price) || Number(price) < 0) {
                errors.push(`Dòng ${row + 1}: Đơn giá phải là số >= 0`);
                this.hot.setCellMeta(row, 2, 'className', 'htInvalid');
            }

            // Validate tax - must be 0, 8, or 10
            if (tax === null || tax === '' || !validTaxValues.includes(Number(tax))) {
                errors.push(`Dòng ${row + 1}: Thuế GTGT phải là 0, 8 hoặc 10`);
                this.hot.setCellMeta(row, 4, 'className', 'htInvalid');
            }

            // Check for price variance warning
            const cellMeta = this.hot.getCellMeta(row, 2);
            if (cellMeta && cellMeta.className && cellMeta.className.includes('price-variance-warning')) {
                hasPriceWarning = true;
                errors.push(`Dòng ${row + 1}: Giá chênh lệch vượt ngưỡng cho phép - cần được duyệt trước khi lưu`);
            }
        }

        // Check if at least one part exists
        if (validRowCount === 0) {
            errors.unshift('Phải có ít nhất 1 linh kiện trong phiếu sửa chữa');
        }

        this.hot.render();

        return {
            valid: errors.length === 0,
            errors: errors,
            hasPriceWarning: hasPriceWarning
        };
    },

    /**
     * Clear validation error highlights (but preserve price warnings)
     */
    clearValidationErrors() {
        const rowCount = this.hot.countRows() - 1; // Exclude summary row
        for (let row = 0; row < rowCount; row++) {
            // Clear htInvalid but preserve price-variance-warning
            const cols = [0, 1, 2, 4];
            cols.forEach(col => {
                const meta = this.hot.getCellMeta(row, col);
                if (meta && meta.className) {
                    // Only keep price-variance-warning, remove htInvalid
                    if (meta.className.includes('price-variance-warning')) {
                        this.hot.setCellMeta(row, col, 'className', 'price-variance-warning');
                    } else {
                        this.hot.setCellMeta(row, col, 'className', '');
                    }
                }
            });
        }
    }
};

// Export for use in other modules
window.GridModule = GridModule;
