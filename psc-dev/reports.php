<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

// Get report type from URL
$reportType = $_GET['type'] ?? 'all';

// Include header
include __DIR__ . '/views/header.php';

// Report titles
$reportTitles = [
    'monthly' => '📆 Báo cáo theo tháng',
];

$pageTitle = $reportTitles[$reportType] ?? 'Báo cáo';
?>

<div class="wrap">
    <div class="reports-container">
        <div class="reports-header">
            <h2><?php echo $pageTitle; ?></h2>
            <p class="reports-description">
                <?php
                switch($reportType) {
                    case 'daily':
                        echo 'Xem báo cáo chi tiết các giao dịch theo ngày';
                        break;
                    case 'monthly':
                        echo 'Xem tổng hợp báo cáo theo tháng';
                        break;
                    default:
                        echo 'Chọn loại báo cáo để xem chi tiết';
                }
                ?>
            </p>
        </div>

        <!-- Report Filters -->
        <div class="report-filters">
            <div class="filter-group">
                <label>Từ ngày:</label>
                <input type="date" id="fromDate" class="filter-input">
            </div>
            <div class="filter-group">
                <label>Đến ngày:</label>
                <input type="date" id="toDate" class="filter-input">
            </div>
            <div class="filter-group">
                <label>Chi nhánh:</label>
                <select id="branchFilter" class="filter-input">
                    <option value="">Tất cả chi nhánh</option>
                    <option value="CN1_HCM">CN1_HCM - Quận 7</option>
                    <option value="CN1_CT">CN1_CT - Cần Thơ</option>
                    <option value="CN1_DT">CN1_DT - Đồng Tháp</option>
                    <option value="CN1_BT">CN1_BT - Bình Thuận</option>
                </select>
            </div>
            <button class="btn btn-primary" id="btnApplyFilter">
                <span class="btn-icon">🔍</span>
                <span>Lọc dữ liệu</span>
            </button>
            <button class="btn btn-secondary" id="btnExport">
                <span class="btn-icon">📥</span>
                <span>Xuất Excel</span>
            </button>
        </div>

        <!-- Report Content -->
        <div class="report-content">
            <?php if ($reportType === 'all'): ?>
                <!-- Dashboard view with multiple reports -->
                <div class="dashboard-grid">
                    
                    <div class="dashboard-card">
                        <div class="card-icon">📆</div>
                        <h3>Báo cáo tháng</h3>
                        <p>Tổng hợp theo tháng</p>
                        <a href="reports.php?type=monthly" class="card-link">Xem báo cáo →</a>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-icon">👥</div>
                        <h3>Báo cáo khách hàng</h3>
                        <p>Phân tích theo khách hàng</p>
                        <a href="reports.php?type=customer" class="card-link">Xem báo cáo →</a>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-icon">💰</div>
                        <h3>Báo cáo doanh thu</h3>
                        <p>Thống kê doanh thu chi tiết</p>
                        <a href="reports.php?type=revenue" class="card-link">Xem báo cáo →</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Specific report view -->
                <div class="report-table-container">
                    <div class="report-summary">
                        <div class="summary-item">
                            <span class="summary-label">Tổng đơn hàng:</span>
                            <span class="summary-value" id="totalOrders">0</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Tổng doanh thu:</span>
                            <span class="summary-value" id="totalRevenue">0 ₫</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Tổng thuế:</span>
                            <span class="summary-value" id="totalTax">0 ₫</span>
                        </div>
                    </div>
                    
                    <div id="reportTable">
                        <!-- Data table will be loaded here via JavaScript -->
                        <p class="no-data">Chọn khoảng thời gian và nhấn "Lọc dữ liệu" để xem báo cáo</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Reports Page Styles */
.reports-container {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* .reports-header {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid #ff9f43;
} */

/* .reports-header h2 { */
    /* margin: 0 0 8px 0;
    color: #ff9f43;
    font-size: 28px;
} */

.reports-description {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.report-filters {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #333;
}

.filter-input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    min-width: 150px;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

/* .dashboard-card {
    background: linear-gradient(135deg, #fff3e0 0%, #ffffff 100%);
    border: 2px solid #ff9f43;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
} */

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(255, 159, 67, 0.3);
}

.card-icon {
    font-size: 48px;
    margin-bottom: 12px;
}

.dashboard-card h3 {
    margin: 12px 0;
    color: #333;
    font-size: 20px;
}

.dashboard-card p {
    color: #666;
    font-size: 14px;
    margin: 8px 0 16px 0;
}

.card-link {
    display: inline-block;
    /* color: #ff9f43; */
    font-weight: 600;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
}

.card-link:hover {
    /* color: #ff8a1a; */
}

/* Report Summary */
.report-summary {
    display: flex;
    gap: 24px;
    padding: 20px;
    background: #fff3e0;
    border-radius: 8px;
    margin-bottom: 20px;
    /* border: 2px solid #ff9f43; */
}

.summary-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.summary-label {
    font-size: 12px;
    color: #666;
    font-weight: 600;
}

.summary-value {
    font-size: 24px;
    font-weight: 700;
    /* color: #ff9f43; */
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #999;
    font-style: italic;
}
</style>

<script>
// Report functionality
document.addEventListener('DOMContentLoaded', function() {
    const btnApplyFilter = document.getElementById('btnApplyFilter');
    const btnExport = document.getElementById('btnExport');
    
    if (btnApplyFilter) {
        btnApplyFilter.addEventListener('click', loadReportData);
    }
    
    if (btnExport) {
        btnExport.addEventListener('click', exportToExcel);
    }
    
    // Set default dates (last 30 days)
    const toDate = document.getElementById('toDate');
    const fromDate = document.getElementById('fromDate');
    
    if (toDate && fromDate) {
        const today = new Date();
        toDate.value = today.toISOString().split('T')[0];
        
        const lastMonth = new Date();
        lastMonth.setDate(lastMonth.getDate() - 30);
        fromDate.value = lastMonth.toISOString().split('T')[0];
    }
});

function loadReportData() {
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    const branch = document.getElementById('branchFilter').value;
    
    if (!fromDate || !toDate) {
        alert('Vui lòng chọn khoảng thời gian');
        return;
    }
    
    // TODO: Implement API call to fetch report data
    console.log('Loading report data:', { fromDate, toDate, branch });
    
    // Mock data for demonstration
    document.getElementById('totalOrders').textContent = '125';
    document.getElementById('totalRevenue').textContent = '458,750,000 ₫';
    document.getElementById('totalTax').textContent = '45,875,000 ₫';
    
    const reportTable = document.getElementById('reportTable');
    reportTable.innerHTML = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Số PSC</th>
                    <th>Ngày</th>
                    <th>Khách hàng</th>
                    <th>Chi nhánh</th>
                    <th>Doanh thu</th>
                    <th>Thuế</th>
                    <th>Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px;">
                        Dữ liệu mẫu - Kết nối API để hiển thị dữ liệu thực
                    </td>
                </tr>
            </tbody>
        </table>
    `;
}

function exportToExcel() {
    alert('Chức năng xuất Excel đang được phát triển');
    // TODO: Implement Excel export functionality
}
</script>

<?php
// Include footer
include __DIR__ . '/views/footer.php';
?>
