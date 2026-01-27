const App = {
    /**
     * Initialize application
     */
    async init() {
        // Initialize modules
        GridModule.init();
        FormModule.init();
        await this.loadCenters();

        // Setup event listeners
        this.setupEventListeners();
    },

    /**
     * Load centers from API
     */
    async loadCenters() {
        try {
            const centers = await API.fetchCenters();
            const centerSelect = document.getElementById('branch');

            centers.forEach(c => {
                const option = document.createElement('option');
                option.value = c.center_id;
                option.textContent = `${c.center_name}${c.zone ? ' - ' + c.zone : ''}`;
                centerSelect.appendChild(option);
            });

            // Set default from URL or localStorage
            const savedCenter = new URLSearchParams(location.search).get('center')
                || localStorage.getItem('LAST_CENTER');

            if (savedCenter && centers.find(c => c.center_id == savedCenter)) {
                centerSelect.value = savedCenter;
            } else if (centers.length > 0) {
                centerSelect.value = centers[0].center_id;
            }

            localStorage.setItem('LAST_CENTER', centerSelect.value);

            // Save on change
            centerSelect.onchange = () => {
                localStorage.setItem('LAST_CENTER', centerSelect.value);
            };
        } catch (err) {
            console.error('Error loading centers:', err);
        }
    },

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        const pscInput = document.getElementById('so_phieu');
        const btnSearch = document.getElementById('btnSearchPSC');
        const btnReset = document.getElementById('btnResetPSC');
        const btnNew = document.getElementById('btnNew');
        const btnSave = document.getElementById('btnSave');

        // PSC search
        pscInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.searchPSC();
            }
        });

        btnSearch.addEventListener('click', () => this.searchPSC());
        btnReset.addEventListener('click', () => this.resetPSC());
        btnNew.addEventListener('click', () => this.newDoc());
        btnSave.addEventListener('click', () => this.savePSC());
    },

    /**
     * Search for PSC
     */
    searchPSC() {
        const pscNo = document.getElementById('so_phieu').value.trim();
        if (!pscNo) return;
        this.loadPSC(pscNo);
    },

    /**
     * Load PSC data
     * @param {string} pscNo 
     */
    async loadPSC(pscNo) {
        const statusEl = document.getElementById('statusEl');
        const soPhieu = document.getElementById('so_phieu');

        statusEl.innerText = 'Đang load phiếu';

        try {
            const res = await API.loadPSC(pscNo);

            FormModule.unlock();
            soPhieu.disabled = true;

            if (!res.exists) {
                statusEl.innerText = 'Phiếu mới';
                GridModule.clear();
                GridModule.focus();
                return;
            }

            statusEl.innerText = 'Đang sửa phiếu';
            console.log('Load result:', res);

            // Load form data
            FormModule.setData(res.master);

            // Load grid data
            GridModule.loadData(res.details);
            GridModule.focus();

        } catch (err) {
            console.error('Load error:', err);
            statusEl.innerText = 'Lỗi load phiếu';
            alert('Lỗi: ' + err.message);
        }
    },

    /**
     * Reset PSC form
     */
    resetPSC() {
        // Clear all inputs
        FormModule.clear();

        // Reset grid data
        GridModule.clear();

        // Enable PSC input and focus
        const soPhieu = document.getElementById('so_phieu');
        soPhieu.disabled = false;
        soPhieu.focus();

        // Reset status
        document.getElementById('statusEl').innerText = 'Chưa chọn phiếu';

        // Lock form until user enters new PSC
        FormModule.lock();

        // Reset KH type to default and enable all
        FormModule.enableAllCustomerTypes();
        if (FormModule.customerTypesData.length > 0) {
            const firstType = FormModule.customerTypesData[0];
            document.querySelector(`input[name="loai_kh"][value="${firstType.id}"]`).checked = true;
            FormModule.changeKHType(firstType.id, firstType.type_name);
        }
    },

    /**
     * Create new document
     */
    newDoc() {
        // Clear all inputs
        document.querySelectorAll('input').forEach(i => i.value = '');

        // Clear grid
        GridModule.clear();

        // Enable PSC input and focus
        const soPhieu = document.getElementById('so_phieu');
        soPhieu.disabled = false;
        soPhieu.focus();

        // Reset status
        document.getElementById('statusEl').innerText = 'Chưa chọn phiếu';

        // Lock form
        FormModule.lock();
    },

    /**
     * Save PSC data
     */
    async savePSC() {
        // Validate grid data before saving
        const validation = GridModule.validate();
        if (!validation.valid) {
            alert('Dữ liệu không hợp lệ:\n\n' + validation.errors.join('\n'));
            return;
        }

        const masterData = FormModule.getData();
        const detailsData = GridModule.getData();

        console.log('Saving:', { master: masterData, details: detailsData });

        try {
            const result = await API.savePSC(masterData, detailsData);

            if (result.status === 'ok') {
                alert('Đã lưu thành công!');
            } else {
                alert('Lỗi: ' + (result.message || 'Không xác định'));
            }
        } catch (err) {
            alert('Lỗi kết nối: ' + err.message);
        }
    }
};

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});

// Export for global access
window.App = App;
