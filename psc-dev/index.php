<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

// Include header
include __DIR__ . '/views/header.php';
?>

<div class="wrap">
    <!-- PSC Search Bar -->
    <div class="psc-bar">
        <span>PSC</span>
        <input id="so_phieu" placeholder="Nhập PSC">
        
        <button type="button" id="btnSearchPSC" class="btn-search">
            🔍 Tìm
        </button>
        
        <button type="button" id="btnResetPSC" class="btn-reset">
            🔄 Reset
        </button>
        
        <span>CHI NHÁNH</span>
        <select id="branch"></select>
        
        <span id="statusEl">Chưa chọn phiếu</span>
    </div>

    <!-- Master Form -->
    <div class="master disabled" id="masterForm">
        <!-- Customer Type Selection -->
        <div class="kh-type-section">
            <span class="form-group-title">Loại khách hàng</span>
            <div class="kh-type-group" id="kh-type-container">
                <span style="color:#999">Đang tải...</span>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div id="kh-form-title" class="form-group-title">Thông tin khách hàng công nợ</div>
        
        <!-- Row 1: Search + Customer Name + Address -->
        <div class="master-section row-divider">
            <div class="field" style="flex: 1.5; min-width: 250px;">
                <label>🔍 Tìm khách hàng (Mã KH hoặc Tên)</label>
                <select id="customer_search" style="width: 100%;"></select>
            </div>
            <div class="field" style="flex: 1.5;">
                <label>Tên khách hàng</label>
                <input id="khach_hang" placeholder="Nhập hoặc tự động điền khi chọn KH"/>
            </div>
            <div class="field" style="flex: 2;">
                <label>Địa chỉ</label>
                <input id="dia_chi" placeholder="Nhập hoặc tự động điền khi chọn KH" />
            </div>
        </div>

        <!-- Hidden fields for data storage -->
        <input type="hidden" id="cust_code" />

        <!-- Row 2: MST + Email + Notes -->
        <div class="master-section">
            <div class="field" id="mst-wrapper" style="position: relative;">
                <label>Mã số thuế</label>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <input id="mst" inputmode="numeric" maxlength="15" style="flex: 1;" placeholder="Nhập MST" />
                    <button type="button" class="btn-lookup" id="btn-lookup-tax" style="display: none;">🔍 Tra cứu</button>
                </div>
                <div class="lookup-status" id="lookup-status" style="display: none;"></div>
            </div>
            <div class="field">
                <label>Email</label>
                <input id="email" placeholder="Nhập email" />
            </div>
            <div class="field grow">
                <label>Ghi chú</label>
                <input id="ghi_chu" placeholder="Nhập ghi chú (nếu có)" />
            </div>
        </div>

        <!-- Device & Service Information -->
        <div class="form-group-title" style="margin-top: 20px;">Thông tin thiết bị & dịch vụ</div>
        
        <!-- Row 3: Serial No + Model + Product Group -->
        <div class="master-section row-divider">
            <div class="field" style="flex: 1;">
                <label>Serial Number / IMEI</label>
                <input id="serial_no" placeholder="Nhập IMEI hoặc Serial Number" maxlength="50" />
            </div>
            <div class="field" style="flex: 1;">
                <label>Model</label>
                <input id="model" placeholder="VD: SM-G998B" maxlength="100" />
            </div>
            <div class="field" style="flex: 1;">
                <label>Nhóm sản phẩm</label>
                <select id="product_group">
                    <option value="">-- Chọn nhóm --</option>
                    <option value="HHP">HHP</option>
                    <option value="DA~CE">DA~CE</option>
                    <option value="AV~CE">AV~CE</option>
                </select>
            </div>
        </div>

        <!-- Row 4: Service Name + Status + Completed At -->
        <div class="master-section">
            <div class="field" style="flex: 1.5;">
                <label>Tên dịch vụ</label>
                <input id="service_name" placeholder="Nhập tên dịch vụ" maxlength="100" />
            </div>
            <div class="field">
                <label>Trạng thái</label>
                <select id="status">
                    <option value="NEW">Mới tạo</option>
                    <option value="PROCESSING">Đang xử lý</option>
                    <option value="COMPLETED">Hoàn thành</option>
                    <option value="DELIVERED">Đã giao</option>
                    <option value="CANCELLED">Đã hủy</option>
                </select>
            </div>
            <div class="field">
                <label>Ngày hoàn thành</label>
                <input id="completed_at" placeholder="Tự động khi hoàn thành" readonly style="background: #f5f5f5;" />
            </div>
        </div>
    </div>

    <!-- Receipt Summary - Above Grid -->
    <div class="receipt-summary" style="margin: 16px 0; display: flex; gap: 20px; align-items: center; justify-content: flex-end; padding: 12px; background: #f8f9fa; border-radius: 4px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <label style="font-weight: 600; margin: 0;">Tiền trên Phiếu thu:</label>
            <input id="receipt_amount" type="text" readonly style="width: 150px; text-align: right; font-weight: bold; background: white; border: 2px solid #3498db; color: #2c3e50;" value="0" />
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <label style="font-weight: 600; margin: 0;">Chênh lệch:</label>
            <input id="diff_amount" type="text" readonly style="width: 150px; text-align: right; font-weight: bold; background: white; border: 2px solid #e74c3c; color: #e74c3c;" value="0" />
        </div>
    </div>

    <!-- Detail Grid -->
    <div class="detail disabled" id="detailSection">
        <div id="hot"></div>
    </div>

    <!-- Sticky Action Bar -->
    <div class="action-bar">
        <div class="action-bar-content">
            <div class="btn-group">
                <button type="button" id="btnNew" class="btn btn-secondary">
                    <span class="btn-icon">📄</span>
                    <span>Tạo mới</span>
                </button>
                <button type="button" id="btnSave" class="btn btn-primary" disabled>
                    <span class="btn-icon">💾</span>
                    <span>Lưu</span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer (with JS includes)
include __DIR__ . '/views/footer.php';
?>