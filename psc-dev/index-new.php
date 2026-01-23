<?php
/**
 * PSC Management System - Main Page
 * Refactored version with proper separation of concerns
 */

// Require authentication
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
        <div class="master-section row-divider">
            <div class="field">
                <label>Mã KH</label>
                <input id="cust_code" style="width: 120px;" readonly />
            </div>
            <div class="field grow">
                <label>Tên khách hàng</label>
                <input id="khach_hang" />
            </div>
            <div class="field grow">
                <label>Địa chỉ</label>
                <input id="dia_chi" />
            </div>
            <div class="field" id="mst-wrapper" style="position: relative;">
                <label>Mã số thuế</label>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <input id="mst" inputmode="numeric" maxlength="15" style="flex: 1;" />
                    <button type="button" class="btn-lookup" id="btn-lookup-tax" style="display: none;">🔍 Tra cứu</button>
                </div>
                <div class="lookup-status" id="lookup-status" style="display: none;"></div>
            </div>
        </div>
        <div class="master-section">
            <div class="field">
                <label>Email</label>
                <input id="email" />
            </div>
            <div class="field grow">
                <label>Ghi chú</label>
                <input id="ghi_chu" />
            </div>
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
