/**
 * Grid Module
 * Handsontable configuration and logic
 */

const GridModule = {
    hot: null,

    /**
     * Initialize the Handsontable grid
     */
    init() {
        const moneyCol = {
            type: 'numeric',
            numericFormat: {
                pattern: '0,0',
                culture: 'en-US'
            }
        };

        this.hot = new Handsontable(document.getElementById('hot'), {
            data: [],
            rowHeaders: true,
            stretchH: 'all',
            minRows: 8,

            colHeaders: [
                'Linh kiện', 'Số lượng', 'Đơn giá bán lẻ', 'Doanh thu',
                'Thuế GTGT (%)', 'Thuế GTGT', 'Thành tiền',
                'Tiền trên Phiếu thu', 'Chênh lệch', 'Ghi chú'
            ],

            columns: [
                { type: 'text', width: 190 },      // Linh kiện
                { type: 'numeric', width: 60 },    // Số lượng
                { ...moneyCol, width: 130 },       // Đơn giá bán lẻ
                { ...moneyCol, readOnly: true, width: 130 }, // Doanh thu
                { type: 'dropdown', source: [0, 8, 10], width: 100 }, // Thuế %
                { ...moneyCol, readOnly: true, width: 110 }, // Thuế GTGT
                { ...moneyCol, readOnly: true, width: 120 }, // Thành tiền
                { ...moneyCol, width: 160 },       // Tiền trên Phiếu thu
                { ...moneyCol, readOnly: true, width: 120 }, // Chênh lệch
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

                changes.forEach(([r]) => {
                    const last = GridModule.hot.countRows() - 1;
                    if (r >= last) return;

                    let sl = +GridModule.hot.getDataAtCell(r, 1) || 0;
                    let dg = +GridModule.hot.getDataAtCell(r, 2) || 0;
                    let tax = +GridModule.hot.getDataAtCell(r, 4) || 0;
                    let thu = +GridModule.hot.getDataAtCell(r, 7) || 0;

                    let dt = sl * dg;
                    let th = dt * tax / 100;
                    let tt = dt + th;

                    GridModule.hot.setDataAtCell(r, 3, dt, 'calc');
                    GridModule.hot.setDataAtCell(r, 5, th, 'calc');
                    GridModule.hot.setDataAtCell(r, 6, tt, 'calc');
                    GridModule.hot.setDataAtCell(r, 8, thu - tt, 'calc');
                });

                GridModule.updateSummary();
            },

            afterSelection: function (r) {
                const last = GridModule.hot.countRows() - 1;
                if (r < last) {
                    GridModule.hot.setCellMeta(r, 0, 'className', 'active-row');
                    GridModule.hot.render();
                }
            }
        });

        // Add summary row
        this.hot.alter('insert_row_below', this.hot.countRows() - 1);
        this.updateSummary();
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
    }
};

// Export for use in other modules
window.GridModule = GridModule;
