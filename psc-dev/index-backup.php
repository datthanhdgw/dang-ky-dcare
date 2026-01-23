<?php
if (isset($_GET['action']) || $_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

$pdo = new PDO(
    "mysql:host=localhost;dbname=b2x-dev;charset=utf8mb4",
    "root","root",
    [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]
);

/* ===== LOAD CENTERS ===== */
if (($_GET['action'] ?? '') === 'centers') {
    $stmt = $pdo->query("SELECT center_id, center_name, zone FROM center ORDER BY center_name");
    $centers = $stmt->fetchAll();
    echo json_encode(['centers' => $centers]);
    exit;
}

/* ===== LOAD CUSTOMER TYPES ===== */
if (($_GET['action'] ?? '') === 'customer_types') {
    $stmt = $pdo->query("SELECT id, type_name, note FROM customer_type ORDER BY id");
    $types = $stmt->fetchAll();
    echo json_encode(['types' => $types]);
    exit;
}

/* ===== LOAD PSC ===== */
if (($_GET['action'] ?? '') === 'load') {
    $pscNo = $_GET['psc_no'] ?? '';
    
    // Load master with customer and center info
    $stmt = $pdo->prepare("
        SELECT m.*, 
               c.center_name, c.zone,
               cu.customer_id as cust_code, cu.customer_name, cu.address, cu.mst, cu.email, cu.note as customer_note,
               ctm.type_id as customer_type_id,
               ct.type_name as customer_type_name
        FROM psc_masters m
        LEFT JOIN center c ON m.center_id = c.center_id
        LEFT JOIN customer cu ON m.customer_id = cu.id
        LEFT JOIN customer_type_mapping ctm ON cu.id = ctm.customer_id
        LEFT JOIN customer_type ct ON ctm.type_id = ct.id
        WHERE m.psc_no = ?
    ");
    $stmt->execute([$pscNo]);
    $m = $stmt->fetch();

    if (!$m) {
        echo json_encode(['exists'=>false]);
        exit;
    }

    // Load parts
    $stmt = $pdo->prepare("SELECT * FROM pcs_part WHERE psc_id = ? ORDER BY id");
    $stmt->execute([$m['id']]);

    $details = [];
    while($r = $stmt->fetch()){
        $details[] = [
            $r['part_name'],
            $r['quantity'],
            $r['unit_price'],
            $r['revenue'],
            $r['vat_pct'],
            $r['vat_amt'],
            $r['total_amt'],
            $r['receipt_amt'],
            $r['diff_amt'],
            $r['note']
        ];
    }

    echo json_encode(['exists'=>true, 'master'=>$m, 'details'=>$details]);
    exit;
}

/* ===== SAVE PSC ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json; charset=utf-8');

    try {
        
        $rawInput = file_get_contents("php://input");
        $data = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error: ' . json_last_error_msg() . ' | Raw input: ' . substr($rawInput, 0, 200));
        }
        
        if (!$data || !is_array($data)) {
            throw new Exception('Payload JSON không hợp lệ hoặc rỗng');
        }

        $m = $data['master'] ?? null;
        $rows = $data['details'] ?? [];

        if (!$m || !isset($m['psc_no'])) {
            throw new Exception('Thiếu dữ liệu master');
        }

        $pdo->beginTransaction();

        // ===== CHECK EXISTING PSC ===== 
        $stmt = $pdo->prepare("SELECT id, customer_id FROM psc_masters WHERE psc_no = ?");
        $stmt->execute([$m['psc_no']]);
        $existing = $stmt->fetch();
        $masterId = $existing ? $existing['id'] : null;
        $customerId = $existing ? $existing['customer_id'] : null;

        // ===== UPSERT CUSTOMER =====
        if ($customerId) {
            // Update existing customer
            $pdo->prepare("
                UPDATE customer SET 
                    customer_name = ?, address = ?, mst = ?, email = ?, note = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $m['customer_name'],
                $m['address'],
                $m['mst'] ?? '',
                $m['email'] ?? '',
                $m['note'] ?? '',
                $customerId
            ]);
        } else {
            // Create new customer
            $emailValue = !empty($m['email']) ? $m['email'] : 'no-email-' . uniqid() . '@placeholder.local';
            $pdo->prepare("
                INSERT INTO customer (customer_id, customer_name, address, mst, email, note, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                'CUS_' . uniqid(),
                $m['customer_name'],
                $m['address'],
                $m['mst'] ?? '',
                $emailValue,
                $m['note'] ?? ''
            ]);
            $customerId = $pdo->lastInsertId();

            // Insert customer type mapping
            $customerTypeId = $m['customer_type_id'] ?? null;
            if ($customerTypeId) {
                $pdo->prepare("
                    INSERT INTO customer_type_mapping (customer_id, type_id, created_at)
                    VALUES (?, ?, NOW())
                ")->execute([$customerId, $customerTypeId]);
            }
        }

        // ===== UPSERT PSC MASTER =====
        if ($masterId) {
            // UPDATE
            $pdo->prepare("
                UPDATE psc_masters SET
                    center_id = ?, customer_id = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $m['center_id'],
                $customerId,
                $masterId
            ]);

            // Delete old parts
            $pdo->prepare("DELETE FROM pcs_part WHERE psc_id = ?")->execute([$masterId]);

        } else {
            // INSERT
            $pdo->prepare("
                INSERT INTO psc_masters (psc_no, center_id, customer_id, created_at)
                VALUES (?, ?, ?, NOW())
            ")->execute([
                $m['psc_no'],
                $m['center_id'],
                $customerId
            ]);

            $masterId = $pdo->lastInsertId();
        }

        // ===== INSERT PARTS =====
        $stmtPart = $pdo->prepare("
            INSERT INTO pcs_part 
            (psc_id, part_name, quantity, unit_price, revenue, vat_pct, vat_amt, total_amt, receipt_amt, diff_amt, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $insertedRows = 0;

        foreach ($rows as $r) {
            // Skip empty rows
            if (!isset($r[0]) || trim((string)$r[0]) === '') {
                continue;
            }

            $stmtPart->execute([
                $masterId,
                $r[0],                     // part_name
                (int)($r[1] ?? 0),         // quantity
                (float)($r[2] ?? 0),       // unit_price
                (float)($r[3] ?? 0),       // revenue
                (int)($r[4] ?? 0),         // vat_pct
                (float)($r[5] ?? 0),       // vat_amt
                (float)($r[6] ?? 0),       // total_amt
                (float)($r[7] ?? 0),       // receipt_amt
                (float)($r[8] ?? 0),       // diff_amt
                $r[9] ?? ''                // note
            ]);

            $insertedRows++;
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'ok',
            'master_id' => $masterId,
            'customer_id' => $customerId,
            'parts_inserted' => $insertedRows
        ]);
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(500);

        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>PSC</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable@13/dist/handsontable.full.min.css">
<script src="https://cdn.jsdelivr.net/npm/handsontable@13/dist/handsontable.full.min.js"></script>

<style>
body{font-family:Segoe UI;background:#f4f6f9;margin:0}
.wrap{max-width:1400px;margin:auto;padding:10px}
.psc-bar{
    background:#fff3e0;border:2px solid #ff9f43;
    padding:8px;border-radius:8px;
    display:flex;align-items:center;gap:12px
}
.psc-bar input,.psc-bar select{
    padding:6px 8px;font-size:14px;
    border:2px solid #ff9f43;border-radius:6px
}
.psc-bar span{font-weight:600;color:#ff9f43}

.master{
    background:#fff;margin-top:8px;padding:12px;border-radius:8px;
}
.master .field{display:flex;flex-direction:column}
.master .field.grow{flex:1}
label{font-size:12px;font-weight:600}
input,textarea{padding:6px;border-radius:5px;border:1px solid #ddd;font-size:13px}
textarea{min-height:32px}

/* KH Type Selection */
.kh-type-section {
    background: #fff3e0;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 12px;
    border: 2px solid #ff9f43;
}
.kh-type-section .form-group-title {
    font-weight: 700;
    color: #ff9f43;
    margin-right: 15px;
    display: inline-block;
}
.kh-type-group {
    display: inline-flex;
    gap: 20px;
}
.kh-type-group label {
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    font-weight: 500;
}
.kh-type-group input[type="radio"] {
    accent-color: #ff9f43;
}

/* Form sections */
.form-group-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    font-size: 14px;
}
.master-section {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.master-section.row-divider {
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

/* Lookup MST wrapper */
.lookup-wrapper {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    flex: 1;
}
.lookup-wrapper .field {
    flex: 1;
}
.lookup-status {
    font-size: 11px;
    color: #666;
    position: absolute;
    bottom: -16px;
    left: 0;
    white-space: nowrap;
}
.btn-lookup {
    background: #ff9f43;
    color: #fff;
    border: none;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    white-space: nowrap;
    height: fit-content;
}
.btn-lookup:hover {
    background: #ff8a1a;
}

.detail{background:#fff;margin-top:8px;padding:6px;border-radius:8px;overflow-x:auto}
#hot{width:100%;min-height:380px;overflow:hidden}

/* Action Bar - Sticky Footer */
.action-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #fff6f6ff;
    border-top: 1px solid #f7ebebff;
    padding: 12px 20px;
    z-index: 1000;
    display: flex;
    justify-content: center;
}

.action-bar-content {
    max-width: 1400px;
    width: 100%;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Button Group */
.btn-group {
    display: flex;
    gap: 10px;
}

/* Base Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

/* Secondary Button - Tạo mới */
.btn-secondary {
    background: #ff9f43;
    color: #ffffffff;
    border: 1px solid #ccc;
}

.btn-secondary:hover {
    background: #ff9f43;
}

/* Primary Button - Lưu */
.btn-primary {
    background: #ff9f43;
    color: #ffffffff;
}

.btn-primary:hover {
    background: #ff8a1a;
}

.btn-primary:disabled {
    background: #ccc;
    cursor: not-allowed;
}

/* Padding for sticky footer */
.wrap {
    padding-bottom: 70px;
}

.disabled{opacity:.5;pointer-events:none}

.htCore .active-row td{background:#fffaf3!important}
.htCore .summary-row td{
    background:#fff3e0!important;
    font-weight:bold;
    border-top:2px solid #ff9f43;
}

.btn-search {
    background: #ff9f43;
    color: #fff;
    border: none;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
}
.btn-search:hover {
    background: #ff8a1a;
}

.btn-reset {
    background: #6c757d;
    color: #fff;
    border: none;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
}
.btn-reset:hover {
    background: #5a6268;
}

</style>
</head>

<body>
<div class="wrap">

 
<div class="psc-bar">
    <span>PSC</span>

    <input id="so_phieu" placeholder="Nhập PSC">

    <button type="button" id="btnSearchPSC" class="btn-search">
        🔍 Tìm
    </button>

    <button type="button" id="btnResetPSC" class="btn-reset" onclick="resetPSC()">
        🔄 Reset
    </button>

    <span>CHI NHÁNH</span>
    <select id="branch"></select>

    <span id="statusEl">Chưa chọn phiếu</span>
</div>

<div class="master disabled" id="masterForm">
    <!-- Loại khách hàng - Section nổi bật trên cùng -->
    <div class="kh-type-section">
        <span class="form-group-title">Loại khách hàng</span>
        <div class="kh-type-group" id="kh-type-container">
            <!-- Sẽ được render bằng JavaScript từ database -->
            <span style="color:#999">Đang tải...</span>
        </div>
    </div>
    
    <!-- Thông tin khách hàng - Layout thống nhất cho tất cả loại KH -->
    <div id="kh-form-title" class="form-group-title">Thông tin khách hàng công nợ</div>
    <div class="master-section row-divider">
        <div class="field"><label>Mã KH</label><input id="cust_code" style="width: 120px;" readonly /></div>
        <div class="field grow"><label>Tên khách hàng</label><input id="khach_hang" /></div>
        <div class="field grow"><label>Địa chỉ</label><input id="dia_chi" /></div>
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
        <div class="field"><label>Email</label><input id="email" /></div>
        <div class="field grow"><label>Ghi chú</label><input id="ghi_chu" /></div>
    </div>
</div>

<div class="detail disabled" id="detailSection">
    <div id="hot"></div>
</div>

<!-- Sticky Action Bar -->
<div class="action-bar">
    <div class="action-bar-content">
        <div class="btn-group">
            <button type="button" id="btnNew" class="btn btn-secondary" onclick="newDoc()">
                <span class="btn-icon">📄</span>
                <span>Tạo mới</span>
            </button>
            <button type="button" id="btnSave" class="btn btn-primary" onclick="saveData()" disabled>
                <span class="btn-icon">💾</span>
                <span>Lưu</span>
            </button>
        </div>
    </div>
</div>

</div>

<script>
/* ===== CUSTOMER TYPES (Load from DB) ===== */
let customerTypesData = [];
let selectedCustomerTypeId = null;
let currentKHType = '';

// Load customer types from database
fetch('?action=customer_types')
    .then(r => r.json())
    .then(res => {
        customerTypesData = res.types || [];
        renderCustomerTypes();
    })
    .catch(err => {
        console.error('Error loading customer types:', err);
        document.getElementById('kh-type-container').innerHTML = '<span style="color:#e74c3c">Lỗi tải loại KH</span>';
    });

function renderCustomerTypes() {
    const container = document.getElementById('kh-type-container');
    if (!customerTypesData.length) {
        container.innerHTML = '<span style="color:#999">Không có dữ liệu loại KH</span>';
        return;
    }
    
    container.innerHTML = customerTypesData.map((type, index) => {
        const checked = index === 0 ? 'checked' : '';
        return `<label>
            <input type="radio" name="loai_kh" value="${type.id}" ${checked} onchange="changeKHType(${type.id}, '${type.type_name}')">
            ${type.type_name}
        </label>`;
    }).join('');
    
    // Set default selection
    if (customerTypesData.length > 0) {
        selectedCustomerTypeId = customerTypesData[0].id;
        currentKHType = customerTypesData[0].type_name;
        changeKHType(customerTypesData[0].id, customerTypesData[0].type_name);
    }
}

function changeKHType(typeId, typeName) {
    selectedCustomerTypeId = typeId;
    currentKHType = typeName;
    
    const titleEl = document.getElementById('kh-form-title');
    const btnLookup = document.getElementById('btn-lookup-tax');
    const lookupStatus = document.getElementById('lookup-status');
    const mstInput = document.getElementById('mst');
    const khachHangInput = document.getElementById('khach_hang');
    const diaChiInput = document.getElementById('dia_chi');
    
    // Reset fields styles
    mstInput.style.background = '';
    mstInput.placeholder = '';
    mstInput.readOnly = false;
    khachHangInput.style.background = '';
    khachHangInput.readOnly = false;
    diaChiInput.style.background = '';
    diaChiInput.readOnly = false;
    
    // Update title based on type name
    titleEl.textContent = 'Thông tin ' + typeName.toLowerCase();
    
    // Check if it's "KH doanh nghiệp" for MST lookup feature
    if (typeName.toLowerCase().includes('doanh nghiệp')) {
        btnLookup.style.display = 'inline-block';
        lookupStatus.style.display = 'block';
        mstInput.placeholder = 'Nhập MST để tìm kiếm';
        khachHangInput.style.background = '#f5f5f5';
        khachHangInput.readOnly = true;
        diaChiInput.style.background = '#f5f5f5';
        diaChiInput.readOnly = true;
    } else {
        btnLookup.style.display = 'none';
        lookupStatus.style.display = 'none';
        mstInput.style.background = '#f5f5f5';
    }
}

// Set customer type and optionally disable other radio buttons
function setCustomerType(typeId, typeName, disableOthers = false) {
    selectedCustomerTypeId = typeId;
    currentKHType = typeName;
    
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
    
    // Trigger the change handler
    changeKHType(typeId, typeName);
}

// Enable all customer type radio buttons
function enableAllCustomerTypes() {
    const radios = document.querySelectorAll('input[name="loai_kh"]');
    radios.forEach(radio => {
        radio.disabled = false;
        radio.parentElement.style.opacity = '1';
    });
}

/* ===== CENTERS (Load from DB) ===== */
const centerSelect = document.getElementById('branch');
let centersData = [];

// Load centers from database
fetch('?action=centers')
    .then(r => r.json())
    .then(res => {
        centersData = res.centers || [];
        centersData.forEach(c => {
            const o = document.createElement('option');
            o.value = c.center_id;
            o.textContent = `${c.center_name}${c.zone ? ' - ' + c.zone : ''}`;
            centerSelect.appendChild(o);
        });
        // Set default from URL or localStorage
        const savedCenter = new URLSearchParams(location.search).get('center') 
            || localStorage.getItem('LAST_CENTER');
        if (savedCenter && centersData.find(c => c.center_id == savedCenter)) {
            centerSelect.value = savedCenter;
        } else if (centersData.length > 0) {
            centerSelect.value = centersData[0].center_id;
        }
        localStorage.setItem('LAST_CENTER', centerSelect.value);
    });

centerSelect.onchange = () => localStorage.setItem('LAST_CENTER', centerSelect.value);

/* ===== GRID ===== */
const moneyCol = {
    type: 'numeric',
    numericFormat: {
        pattern: '0,0',
        culture: 'en-US'
    }
};
const hot=new Handsontable(document.getElementById('hot'),{
    data:[],
    rowHeaders:true,
    stretchH:'all',
    minRows:8,

    colHeaders:[
        'Linh kiện','Số lượng','Đơn giá bán lẻ','Doanh thu',
        'Thuế GTGT (%)','Thuế GTGT','Thành tiền',
        'Tiền trên Phiếu thu','Chênh lệch','Ghi chú'
    ],
    
columns: [
        { type:'text', width:190 },      // Linh kiện
        { type:'numeric', width:60 },    // Số lượng
        { ...moneyCol, width:130 },      // Đơn giá bán lẻ
        { ...moneyCol, readOnly:true, width:130 }, // Doanh thu
        { type:'dropdown', source:[0,8,10], width:100 }, // Thuế %
        { ...moneyCol, readOnly:true, width:110 }, // Thuế GTGT
        { ...moneyCol, readOnly:true, width:120 }, // Thành tiền
        { ...moneyCol, width:160 },      // Tiền trên Phiếu thu
        { ...moneyCol, readOnly:true, width:120 }, // Chênh lệch
        { type:'text', width:200 }       // Ghi chú
    ],

    licenseKey:'non-commercial-and-evaluation',

    cells:function(row){
        const last=this.instance.countRows()-1;
        if(row===last){
            return{readOnly:true,className:'summary-row'};
        }
    },

    beforeKeyDown:function(e){
        if(e.key!=='Enter')return;

        const sel=this.getSelectedLast();
        if(!sel)return;

        const[row,col]=sel;
        const lastDataRow=this.countRows()-2;
        const lastCol=this.countCols()-1;

        e.preventDefault();
        e.stopImmediatePropagation();

        if(col===lastCol){
            if(row<lastDataRow){
                this.selectCell(row+1,0);
            }
            return;
        }
        this.selectCell(row,col+1);
    },

    afterChange:function(changes,src){
        if(!changes||src==='calc'||src==='summary')return;
        changes.forEach(([r])=>{
            const last=hot.countRows()-1;
            if(r>=last)return;

            let sl=+hot.getDataAtCell(r,1)||0;
            let dg=+hot.getDataAtCell(r,2)||0;
            let tax=+hot.getDataAtCell(r,4)||0;
            let thu=+hot.getDataAtCell(r,7)||0;

            let dt=sl*dg;
            let th=dt*tax/100;
            let tt=dt+th;

            hot.setDataAtCell(r,3,dt,'calc');
            hot.setDataAtCell(r,5,th,'calc');
            hot.setDataAtCell(r,6,tt,'calc');
            hot.setDataAtCell(r,8,thu-tt,'calc');
        });
        updateSummary();
    },

    afterSelection:function(r){
        const last=hot.countRows()-1;
        if(r<last){
            hot.setCellMeta(r,0,'className','active-row');
            hot.render();
        }
    }
});

/* ===== SUMMARY ===== */
hot.alter('insert_row_below', hot.countRows() - 1);
updateSummary();

function updateSummary(){
    const d=hot.getData();
    let tDT=0,tTax=0,tTT=0;
    for(let i=0;i<d.length-1;i++){
        tDT+=+d[i][3]||0;
        tTax+=+d[i][5]||0;
        tTT+=+d[i][6]||0;
    }
    const last=d.length-1;
    hot.setDataAtCell(last,0,'TỔNG CỘNG','summary');
    hot.setDataAtCell(last,3,tDT,'summary');
    hot.setDataAtCell(last,5,tTax,'summary');
    hot.setDataAtCell(last,6,tTT,'summary');
}

/* ===== FORM FLOW ===== */
function lockForm(){
    masterForm.classList.add('disabled');
    detailSection.classList.add('disabled');
    btnSave.disabled=true;
    hot.updateSettings({readOnly:true});
}
function unlockForm(){
    masterForm.classList.remove('disabled');
    detailSection.classList.remove('disabled');
    btnSave.disabled=false;
    hot.updateSettings({readOnly:false});
}
lockForm();



function newDoc(){
    document.querySelectorAll('input').forEach(i=>i.value='');
    hot.loadData([]);
    hot.alter('insert_row_below', hot.countRows() - 1);
    updateSummary();
    so_phieu.disabled=false;
    so_phieu.focus();
    statusEl.innerText='Chưa chọn phiếu';
    lockForm();
}

function resetPSC(){
    // Clear all inputs including PSC number
    document.querySelectorAll('#masterForm input, #masterForm textarea').forEach(i => i.value = '');
    document.getElementById('so_phieu').value = '';
    document.getElementById('cust_code').value = '';
    
    // Reset grid data
    hot.loadData([]);
    hot.alter('insert_row_below', hot.countRows() - 1);
    updateSummary();
    
    // Enable PSC input and focus
    so_phieu.disabled = false;
    so_phieu.focus();
    
    // Reset status
    statusEl.innerText = 'Chưa chọn phiếu';
    
    // Lock form until user enters new PSC
    lockForm();
    
    // Reset KH type to default (first type in the list) and enable all
    enableAllCustomerTypes();
    if (customerTypesData.length > 0) {
        const firstType = customerTypesData[0];
        document.querySelector(`input[name="loai_kh"][value="${firstType.id}"]`).checked = true;
        changeKHType(firstType.id, firstType.type_name);
    }
}

function saveData(){
    const rows = hot.getData().slice(0,-1);
    console.log('Saving rows:', rows);

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            master: {
                psc_no: so_phieu.value,
                center_id: centerSelect.value,
                customer_type_id: selectedCustomerTypeId,
                customer_name: khach_hang.value,
                address: dia_chi.value,
                mst: mst.value,
                email: email.value,
                note: ghi_chu.value
            },
            details: rows
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'ok') {
            alert('Đã lưu thành công!');
        } else {
            alert('Lỗi: ' + (res.message || 'Không xác định'));
        }
    })
    .catch(err => {
        alert('Lỗi kết nối: ' + err.message);
    });
}
/* ===== FIX ENTER CHO SỐ PHIẾU PSC ===== */

document.addEventListener('DOMContentLoaded', () => {

    const pscInput = document.getElementById('so_phieu');
    const btnSearch = document.getElementById('btnSearchPSC');

    function triggerSearch() {
        const soPhieu = pscInput.value.trim();
        if (!soPhieu) return;
        loadPSC(soPhieu);
    }

    // Enter trong input PSC
    pscInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            triggerSearch();
        }
    });

    // Click nút Tìm
    btnSearch.addEventListener('click', function () {
        triggerSearch();
    });

});
 

/* ===== LOOKUP MST via VietQR API ===== */
document.addEventListener('DOMContentLoaded', () => {
    const mstInput = document.getElementById('mst');
    const btnLookup = document.getElementById('btn-lookup-tax');
    const lookupStatus = document.getElementById('lookup-status');

    async function lookupMST() {
        const mst = mstInput.value.trim();
        if (!mst) {
            lookupStatus.innerText = 'Vui lòng nhập MST';
            lookupStatus.style.color = '#e74c3c';
            return;
        }

        lookupStatus.innerText = 'Đang tra cứu...';
        lookupStatus.style.color = '#666';

        try {
            const response = await fetch(`https://api.vietqr.io/v2/business/${encodeURIComponent(mst)}`);
            const data = await response.json();

            if (data.code === '00' && data.data) {
                // Điền thông tin vào form
                document.getElementById('khach_hang').value = data.data.name || '';
                document.getElementById('dia_chi').value = data.data.address || '';
                lookupStatus.innerText = '✓ Tra cứu thành công';
                lookupStatus.style.color = '#27ae60';
            } else {
                lookupStatus.innerText = 'Không tìm thấy thông tin MST';
                lookupStatus.style.color = '#e74c3c';
                document.getElementById('khach_hang').value = '';
                document.getElementById('dia_chi').value = '';
            }
        } catch (error) {
            console.error('Lookup error:', error);
            lookupStatus.innerText = 'Lỗi kết nối API';
            lookupStatus.style.color = '#e74c3c';
        }
    }

    // Click nút Tra cứu
    btnLookup.addEventListener('click', lookupMST);

    // Enter trong input MST
    mstInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && currentKHType === 'vanglai-doanh-nghiep') {
            e.preventDefault();
            lookupMST();
        }
    });

    // Chỉ cho phép nhập số
    mstInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});

function loadPSC(pscNo) {

    statusEl.innerText = 'Đang load phiếu';

    fetch(`?action=load&psc_no=${encodeURIComponent(pscNo)}`)
        .then(r => r.json())
        .then(res => {

            unlockForm();
            so_phieu.disabled = true;
            if (!res.exists) {
                statusEl.innerText = 'Phiếu mới';
                hot.loadData([]);
                hot.alter('insert_row_below', hot.countRows() - 1);
                updateSummary();
                setTimeout(() => hot.selectCell(0,0), 50);
                return;
            }

            statusEl.innerText = 'Đang sửa phiếu';
            console.log('Load result:', res);
            // Load customer info from new schema
            cust_code.value = res.master.cust_code || '';
            khach_hang.value = res.master.customer_name || '';
            dia_chi.value = res.master.address || '';
            mst.value = res.master.mst || '';
            email.value = res.master.email || '';
            ghi_chu.value = res.master.customer_note || '';
            
            // Set center dropdown
            if (res.master.center_id) {
                centerSelect.value = res.master.center_id;
            }

            // Set customer type and disable other options
            if (res.master.customer_type_id) {
                setCustomerType(res.master.customer_type_id, res.master.customer_type_name, true);
            } else {
                // Enable all radio buttons if no type set
                enableAllCustomerTypes();
            }

            hot.loadData(res.details);
            hot.alter('insert_row_below', hot.countRows() - 1);
            updateSummary();
            setTimeout(() => hot.selectCell(0,0), 50);
        })
        .catch(err => {
            console.error('Load error:', err);
            statusEl.innerText = 'Lỗi load phiếu';
        });
}

</script>
</body>
</html>