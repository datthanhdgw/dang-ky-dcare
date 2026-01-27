const GridModule = {
    hot: null,
    partsCache: {}, // Cache to store parts data from API

    /**
     * Initialize the Handsontable grid
     */
    init() {
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
                },      // Linh kiện
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
                if (!changes || src === 'calc' || src === 'summary') return;

                changes.forEach(([r, c, oldVal, newVal]) => {
                    const last = GridModule.hot.countRows() - 1;
                    if (r >= last) return;

                    // If part column changed (column 0), auto-fill other columns
                    if (c === 0 && newVal && newVal !== oldVal && src !== 'autofill') {
                        const partInfo = GridModule.partsCache[newVal];
                        console.log(partInfo, "partInfo for", newVal);
                        if (partInfo) {
                            // Check if part is inactive - prevent selection
                            if (partInfo.is_active === 0) {
                                alert('Linh kiện này đã ngừng sử dụng, không thể chọn!');
                                GridModule.hot.setDataAtCell(r, 0, oldVal || '', 'autofill');
                                return;
                            }

                            // Set quantity to 1 if empty
                            const currentQty = GridModule.hot.getDataAtCell(r, 1);
                            if (!currentQty || currentQty === 0) {
                                GridModule.hot.setDataAtCell(r, 1, 1, 'autofill');
                            }
                            // Set retail price
                            GridModule.hot.setDataAtCell(r, 2, partInfo.retail_price, 'autofill');
                            // Set tax from max_price_diff_percent (default 10% if not set)
                            const currentTax = GridModule.hot.getDataAtCell(r, 4);
                            if (!currentTax && currentTax !== 0) {
                                const taxValue = partInfo.max_price_diff_percent !== undefined ? partInfo.max_price_diff_percent : 10;
                                GridModule.hot.setDataAtCell(r, 4, taxValue, 'autofill');
                            }
                        }
                    }

                    // Calculate totals for this row
                    GridModule.calculateRow(r);
                });

                GridModule.updateSummary();
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
     * Update summary row with totals
     */
    updateSummary() {
        const d = this.hot.getData();
        let tDT = 0, tTax = 0, tTT = 0;

        for (let i = 0; i < d.length - 1; i++) {
            tDT += +d[i][3] || 0;
            tTax += +d[i][5] || 0;
            tTT += +d[i][6] || 0;
        }

        const last = d.length - 1;
        this.hot.setDataAtCell(last, 0, 'TỔNG CỘNG', 'summary');
        this.hot.setDataAtCell(last, 3, tDT, 'summary');
        this.hot.setDataAtCell(last, 5, tTax, 'summary');
        this.hot.setDataAtCell(last, 6, tTT, 'summary');
    },

    /**
     * Load data into grid
     * @param {Array} data 
     */
    loadData(data) {
        this.hot.loadData(data);
        this.hot.alter('insert_row_below', this.hot.countRows() - 1);
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

        // Clear previous error highlights
        this.clearValidationErrors();

        for (let row = 0; row < data.length; row++) {
            const rowData = data[row];
            const partName = rowData[0];

            // Skip empty rows
            if (!partName || partName.trim() === '') continue;

            validRowCount++;

            const qty = rowData[1];
            const price = rowData[2];
            const tax = rowData[4];

            // Validate quantity - must be positive number
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
        }

        // Check if at least one part exists
        if (validRowCount === 0) {
            errors.unshift('Phải có ít nhất 1 linh kiện trong phiếu sửa chữa');
        }

        this.hot.render();

        return {
            valid: errors.length === 0,
            errors: errors
        };
    },

    /**
     * Clear validation error highlights
     */
    clearValidationErrors() {
        const rowCount = this.hot.countRows() - 1; // Exclude summary row
        for (let row = 0; row < rowCount; row++) {
            this.hot.setCellMeta(row, 1, 'className', '');
            this.hot.setCellMeta(row, 2, 'className', '');
            this.hot.setCellMeta(row, 4, 'className', '');
        }
    }
};

// Export for use in other modules
window.GridModule = GridModule;
