const FormModule = {
    customerTypesData: [],
    selectedCustomerTypeId: null,
    currentKHType: '',

    // Form elements
    elements: {
        masterForm: null,
        detailSection: null,
        btnSave: null,
        statusEl: null,
        khTypeContainer: null,
        khFormTitle: null,
        btnLookupTax: null,
        lookupStatus: null,
        mstInput: null,
        khachHangInput: null,
        diaChiInput: null,
        soPhieu: null,
        custCode: null,
        email: null,
        ghiChu: null,
        centerSelect: null,
        customerSearch: null,
        // New device & service fields
        serialNo: null,
        model: null,
        productGroup: null,
        serviceName: null,
        status: null,
        completedAt: null
    },

    /**
     * Initialize form module
     */
    init() {
        // Get DOM elements
        this.elements.masterForm = document.getElementById('masterForm');
        this.elements.detailSection = document.getElementById('detailSection');
        this.elements.btnSave = document.getElementById('btnSave');
        this.elements.statusEl = document.getElementById('statusEl');
        this.elements.khTypeContainer = document.getElementById('kh-type-container');
        this.elements.khFormTitle = document.getElementById('kh-form-title');
        this.elements.btnLookupTax = document.getElementById('btn-lookup-tax');
        this.elements.lookupStatus = document.getElementById('lookup-status');
        this.elements.mstInput = document.getElementById('mst');
        this.elements.khachHangInput = document.getElementById('khach_hang');
        this.elements.diaChiInput = document.getElementById('dia_chi');
        this.elements.soPhieu = document.getElementById('so_phieu');
        this.elements.custCode = document.getElementById('cust_code');
        this.elements.email = document.getElementById('email');
        this.elements.ghiChu = document.getElementById('ghi_chu');
        this.elements.centerSelect = document.getElementById('branch');
        this.elements.customerSearch = document.getElementById('customer_search');

        // New device & service fields
        this.elements.serialNo = document.getElementById('serial_no');
        this.elements.model = document.getElementById('model');
        this.elements.productGroup = document.getElementById('product_group');
        this.elements.serviceName = document.getElementById('service_name');
        this.elements.status = document.getElementById('status');
        this.elements.completedAt = document.getElementById('completed_at');

        // Load customer types
        this.loadCustomerTypes();

        // Setup MST lookup
        this.setupMSTLookup();

        // Setup Select2 for customer search
        this.setupCustomerSearch();

        // Lock form initially
        this.lock();
    },

    /**
     * Load customer types from API
     */
    async loadCustomerTypes() {
        try {
            this.customerTypesData = await API.fetchCustomerTypes();
            this.renderCustomerTypes();
        } catch (err) {
            console.error('Error loading customer types:', err);
            this.elements.khTypeContainer.innerHTML = '<span style="color:#e74c3c">Lỗi tải loại KH</span>';
        }
    },

    /**
     * Render customer type radio buttons
     */
    renderCustomerTypes() {
        const container = this.elements.khTypeContainer;

        if (!this.customerTypesData.length) {
            container.innerHTML = '<span style="color:#999">Không có dữ liệu loại KH</span>';
            return;
        }

        container.innerHTML = this.customerTypesData.map((type, index) => {
            const checked = index === 0 ? 'checked' : '';
            return `<label>
                <input type="radio" name="loai_kh" value="${type.id}" ${checked} onchange="FormModule.changeKHType(${type.id}, '${type.type_name}')">
                ${type.type_name}
            </label>`;
        }).join('');

        // Set default selection
        if (this.customerTypesData.length > 0) {
            this.selectedCustomerTypeId = this.customerTypesData[0].id;
            this.currentKHType = this.customerTypesData[0].type_name;
            this.changeKHType(this.customerTypesData[0].id, this.customerTypesData[0].type_name);
        }
    },

    /**
     * Change customer type
     * @param {number} typeId 
     * @param {string} typeName 
     */
    changeKHType(typeId, typeName) {
        typeName = typeName || 'khách hàng';
        this.selectedCustomerTypeId = typeId;
        this.currentKHType = typeName;

        // Reset form data when changing customer type
        this.elements.custCode.value = '';
        this.elements.khachHangInput.value = '';
        this.elements.diaChiInput.value = '';
        this.elements.mstInput.value = '';
        this.elements.email.value = '';
        this.elements.ghiChu.value = '';
        this.elements.lookupStatus.innerText = '';

        // Reset Select2 customer search
        if (this.elements.customerSearch && $(this.elements.customerSearch).data('select2')) {
            $(this.elements.customerSearch).val(null).trigger('change');
        }

        // Reset fields styles
        this.elements.mstInput.style.background = '';
        this.elements.mstInput.placeholder = '';
        this.elements.mstInput.readOnly = false;
        this.elements.khachHangInput.style.background = '';
        this.elements.khachHangInput.readOnly = false;
        this.elements.diaChiInput.style.background = '';
        this.elements.diaChiInput.readOnly = false;
        this.elements.khFormTitle.textContent = 'Thông tin ' + typeName.toLowerCase();
        // Check if it's "KH doanh nghiệp" for MST lookup feature
        if (typeName.toLowerCase().includes('doanh nghiệp')) {
            this.elements.btnLookupTax.style.display = 'inline-block';
            this.elements.lookupStatus.style.display = 'block';
            this.elements.mstInput.placeholder = 'Nhập MST để tìm kiếm';
            this.elements.khachHangInput.style.background = '#f5f5f5';
            this.elements.khachHangInput.readOnly = true;
            this.elements.diaChiInput.style.background = '#f5f5f5';
            this.elements.diaChiInput.readOnly = true;
        } else {
            this.elements.btnLookupTax.style.display = 'none';
            this.elements.lookupStatus.style.display = 'none';
            this.elements.mstInput.style.background = '#f5f5f5';
        }
    },

    /**
     * Set customer type and optionally disable others
     * @param {number} typeId 
     * @param {string} typeName 
     * @param {boolean} disableOthers 
     * @param {boolean} skipReset - If true, don't reset form fields (used when loading existing data)
     */
    setCustomerType(typeId, typeName, disableOthers = false, skipReset = false) {
        this.selectedCustomerTypeId = typeId;
        this.currentKHType = typeName;

        const radios = document.querySelectorAll('input[name="loai_kh"]');
        radios.forEach(radio => {
            const label = radio.parentElement;
            if (radio.value == typeId) {
                radio.checked = true;
                radio.disabled = false;
                label.style.opacity = '1';
            } else if (disableOthers) {
                radio.disabled = true;
                label.style.opacity = '0.4';
            }
        });

        if (!skipReset) {
            this.changeKHType(typeId, typeName);
        } else {
            // Just update styles without resetting form
            this.applyCustomerTypeStyles(typeName);
        }
    },

    /**
     * Apply styles based on customer type without resetting form
     * @param {string} typeName 
     */
    applyCustomerTypeStyles(typeName) {
        // Handle null/undefined typeName
        typeName = typeName || 'khách hàng';

        this.elements.khFormTitle.textContent = 'Thông tin ' + typeName.toLowerCase();

        // Reset field styles first
        this.elements.mstInput.style.background = '';
        this.elements.mstInput.placeholder = '';
        this.elements.mstInput.readOnly = false;
        this.elements.khachHangInput.style.background = '';
        this.elements.khachHangInput.readOnly = false;
        this.elements.diaChiInput.style.background = '';
        this.elements.diaChiInput.readOnly = false;

        if (typeName.toLowerCase().includes('doanh nghiệp')) {
            this.elements.btnLookupTax.style.display = 'inline-block';
            this.elements.lookupStatus.style.display = 'block';
            this.elements.mstInput.placeholder = 'Nhập MST để tìm kiếm';
            this.elements.khachHangInput.style.background = '#f5f5f5';
            this.elements.khachHangInput.readOnly = true;
            this.elements.diaChiInput.style.background = '#f5f5f5';
            this.elements.diaChiInput.readOnly = true;
        } else {
            this.elements.btnLookupTax.style.display = 'none';
            this.elements.lookupStatus.style.display = 'none';
            this.elements.mstInput.style.background = '#f5f5f5';
        }
    },

    /**
     * Enable all customer type radio buttons
     */
    enableAllCustomerTypes() {
        const radios = document.querySelectorAll('input[name="loai_kh"]');
        radios.forEach(radio => {
            radio.disabled = false;
            radio.parentElement.style.opacity = '1';
        });
    },

    /**
     * Setup MST lookup functionality
     */
    setupMSTLookup() {
        // Click button to lookup
        this.elements.btnLookupTax.addEventListener('click', () => this.lookupMST());

        // Enter key in MST input
        this.elements.mstInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && this.currentKHType.toLowerCase().includes('doanh nghiệp')) {
                e.preventDefault();
                this.lookupMST();
            }
        });

        // Only allow numbers in MST input
        this.elements.mstInput.addEventListener('input', function (e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    },

    /**
     * Setup Select2 for customer search with AJAX
     */
    setupCustomerSearch() {
        const self = this;

        // Initialize Select2 with AJAX
        $(this.elements.customerSearch).select2({
            placeholder: 'Nhập mã hoặc tên để tìm...',
            allowClear: true,
            minimumInputLength: 1,
            ajax: {
                url: 'api/search-customers.php',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        term: params.term,
                        page: params.page || 1,
                        type_id: self.selectedCustomerTypeId // Filter by selected customer type
                    };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.results,
                        pagination: {
                            more: data.pagination.more
                        }
                    };
                },
                cache: true
            },
            templateResult: function (data) {
                if (data.loading) return data.text;
                return $('<div class="customer-option">' +
                    '<span class="customer-code">' + (data.customer_id || '') + '</span>' +
                    '<span class="customer-name">' + (data.customer_name || '') + '</span>' +
                    '</div>');
            },
            templateSelection: function (data) {
                if (!data.customer_name) return data.text;
                return data.customer_id + ' - ' + data.customer_name;
            },
            language: {
                inputTooShort: function () {
                    return 'Nhập ít nhất 1 ký tự...';
                },
                noResults: function () {
                    return 'Không tìm thấy khách hàng';
                },
                searching: function () {
                    return 'Đang tìm...';
                }
            }
        });

        // Handle customer selection
        $(this.elements.customerSearch).on('select2:select', function (e) {
            const data = e.params.data;

            // Fill customer data into form fields
            self.elements.custCode.value = data.customer_id || '';
            self.elements.khachHangInput.value = data.customer_name || '';
            self.elements.diaChiInput.value = data.address || '';
            self.elements.mstInput.value = data.mst || '';
            self.elements.email.value = data.email || '';

            // Set fields as readonly when customer is selected (if it's "KH công nợ")
            if (self.currentKHType.toLowerCase().includes('công nợ')) {
                self.elements.khachHangInput.style.background = '#f5f5f5';
                self.elements.khachHangInput.readOnly = true;
                self.elements.diaChiInput.style.background = '#f5f5f5';
                self.elements.diaChiInput.readOnly = true;
            }
        });

        // Handle clear
        $(this.elements.customerSearch).on('select2:clear', function () {
            self.elements.custCode.value = '';
            self.elements.khachHangInput.value = '';
            self.elements.diaChiInput.value = '';
            self.elements.khachHangInput.style.background = '';
            self.elements.khachHangInput.readOnly = false;
            self.elements.diaChiInput.style.background = '';
            self.elements.diaChiInput.readOnly = false;
        });
    },

    /**
     * Lookup MST via VietQR API
     */
    async lookupMST() {
        const mst = this.elements.mstInput.value.trim();

        if (!mst) {
            this.elements.lookupStatus.innerText = 'Vui lòng nhập MST';
            this.elements.lookupStatus.style.color = '#e74c3c';
            return;
        }

        this.elements.lookupStatus.innerText = 'Đang tra cứu...';
        this.elements.lookupStatus.style.color = '#666';

        try {
            const data = await API.lookupMST(mst);

            if (data.code === '00' && data.data) {
                this.elements.khachHangInput.value = data.data.name || '';
                this.elements.diaChiInput.value = data.data.address || '';
                this.elements.lookupStatus.innerText = '✓ Tra cứu thành công';
                this.elements.lookupStatus.style.color = '#27ae60';
            } else {
                this.elements.lookupStatus.innerText = 'Không tìm thấy thông tin MST';
                this.elements.lookupStatus.style.color = '#e74c3c';
                this.elements.khachHangInput.value = '';
                this.elements.diaChiInput.value = '';
            }
        } catch (error) {
            console.error('Lookup error:', error);
            this.elements.lookupStatus.innerText = 'Lỗi kết nối API';
            this.elements.lookupStatus.style.color = '#e74c3c';
        }
    },

    /**
     * Lock form
     */
    lock() {
        this.elements.masterForm.classList.add('disabled');
        this.elements.detailSection.classList.add('disabled');
        this.elements.btnSave.disabled = true;
        if (window.GridModule && window.GridModule.hot) {
            window.GridModule.hot.updateSettings({ readOnly: true });
        }
    },

    /**
     * Unlock form
     */
    unlock() {
        this.elements.masterForm.classList.remove('disabled');
        this.elements.detailSection.classList.remove('disabled');
        this.elements.btnSave.disabled = false;
        if (window.GridModule && window.GridModule.hot) {
            window.GridModule.hot.updateSettings({ readOnly: false });
        }
    },

    /**
     * Clear all form fields
     */
    clear() {
        document.querySelectorAll('#masterForm input, #masterForm textarea').forEach(i => i.value = '');
        this.elements.soPhieu.value = '';
        this.elements.custCode.value = '';

        // Reset dropdowns to default
        if (this.elements.productGroup) this.elements.productGroup.value = '';
        if (this.elements.serviceName) this.elements.serviceName.value = '';
        if (this.elements.status) this.elements.status.value = 'NEW';
    },

    /**
     * Get form data
     * @returns {Object}
     */
    getData() {
        return {
            psc_no: this.elements.soPhieu.value,
            center_id: this.elements.centerSelect.value,
            customer_type_id: this.selectedCustomerTypeId,
            cust_code: this.elements.custCode.value, // Customer code from search
            customer_name: this.elements.khachHangInput.value,
            address: this.elements.diaChiInput.value,
            mst: this.elements.mstInput.value,
            email: this.elements.email.value,
            note: this.elements.ghiChu.value,
            // New device & service fields
            serial_no: this.elements.serialNo.value,
            model: this.elements.model.value,
            product_group: this.elements.productGroup.value,
            service_name: this.elements.serviceName.value,
            status: this.elements.status.value,
            completed_at: this.elements.completedAt.value
        };
    },

    /**
     * Set form data
     * @param {Object} data 
     */
    setData(data) {
        console.log('setData received:', data);

        // Set center first
        if (data.center_id) {
            this.elements.centerSelect.value = data.center_id;
        }

        // Set form values FIRST
        console.log('Setting form values:');
        console.log('- khachHangInput element:', this.elements.khachHangInput);
        console.log('- customer_name value:', data.customer_name);

        this.elements.custCode.value = data.cust_code || '';
        this.elements.khachHangInput.value = data.customer_name || '';
        this.elements.diaChiInput.value = data.address || '';
        this.elements.mstInput.value = data.mst || '';
        this.elements.email.value = data.email || '';
        this.elements.ghiChu.value = data.customer_note || '';

        // Set new device & service fields
        this.elements.serialNo.value = data.serial_no || '';
        this.elements.model.value = data.model || '';
        this.elements.productGroup.value = data.product_group || '';
        this.elements.serviceName.value = data.service_name || '';
        this.elements.status.value = data.status || 'NEW';

        // Format and display completed_at if exists
        if (data.completed_at) {
            // Format datetime to be more readable
            const date = new Date(data.completed_at);
            this.elements.completedAt.value = date.toLocaleString('vi-VN');
        } else {
            this.elements.completedAt.value = '';
        }

        console.log('After setting, khachHangInput.value =', this.elements.khachHangInput.value);

        // Pre-populate Select2 with existing customer data
        if (data.cust_code && data.customer_name) {
            const $select = $(this.elements.customerSearch);
            // Create a new option with the customer data
            const option = new Option(
                data.cust_code + ' - ' + data.customer_name,
                data.customer_id || data.cust_code,
                true, // default selected
                true  // currently selected
            );
            // Add extra data for template
            option.customer_id = data.cust_code;
            option.customer_name = data.customer_name;
            option.address = data.address || '';
            option.mst = data.mst || '';
            option.email = data.email || '';

            $select.append(option).trigger('change');
        }

        // Set customer type with skipReset=true to keep form values
        if (data.customer_type_id) {
            this.setCustomerType(data.customer_type_id, data.customer_type_name, true, true);
        } else {
            this.enableAllCustomerTypes();
        }
    }
};

// Export for use in other modules
window.FormModule = FormModule;
