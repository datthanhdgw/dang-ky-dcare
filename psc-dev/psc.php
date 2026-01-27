


<?php
if (isset($_GET['action']) || $_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0);
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

/* ===== LOAD ===== */
if (($_GET['action'] ?? '') === 'load') {
    $so = $_GET['so_phieu'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM psc_master WHERE so_phieu_psc=?");
    $stmt->execute([$so]);
    $m = $stmt->fetch();

    if (!$m) {
        echo json_encode(['exists'=>false]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM psc_detail WHERE master_id=?");
    $stmt->execute([$m['id']]);

    $details=[];
    while($r=$stmt->fetch()){
        $details[]=[
            $r['linh_kien'],$r['so_luong'],$r['don_gia'],
            $r['doanh_thu'],$r['thue_suat'],$r['thue_gtgt'],
            $r['thanh_tien'],$r['tien_phieu_thu'],
            $r['chenhlech'],$r['ghi_chu']
        ];
    }

    echo json_encode(['exists'=>true,'master'=>$m,'details'=>$details]);
    exit;
}

/* ===== SAVE ===== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json; charset=utf-8');

    try {

        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data) {
            throw new Exception('Payload JSON không hợp lệ');
        }

        $m = $data['master'] ?? null;
        $rows = $data['details'] ?? [];

        if (!$m || !isset($m['so_phieu'])) {
            throw new Exception('Thiếu dữ liệu master');
        }

        $pdo->beginTransaction();

        // ===== CHECK MASTER =====
        $stmt = $pdo->prepare("SELECT id FROM psc_master WHERE so_phieu_psc=?");
        $stmt->execute([$m['so_phieu']]);
        $id = $stmt->fetchColumn();


        // Chuẩn hoá ngày giao hàng
        $ngayGoodDelivery = null;
        if (!empty($m['ngay'])) {
            $ngayGoodDelivery = $m['ngay']; // yyyy-mm-dd
        }

        if ($id) {
            // UPDATE
            $pdo->prepare("
                UPDATE psc_master SET
                    branch_code=?, branch_name=?,
                    ngay_good_delivery=?, ten_khach_hang=?, mst=?, email_nhan_hd=?, ghi_chu=?,
                    tong_doanh_thu=?, tong_thue=?, tong_thanh_tien=?
                WHERE id=?
            ")->execute([
                $m['branch_code'],
                $m['branch_name'],
                $ngayGoodDelivery,
                $m['khach_hang'],
                $m['mst'],
                $m['email'],
                $m['ghi_chu'],
                (float)$m['tong_doanh_thu'],
                (float)$m['tong_thue'],
                (float)$m['tong_thanh_tien'],
                $id
            ]);

            $pdo->prepare("DELETE FROM psc_detail WHERE master_id=?")->execute([$id]);
            $masterId = $id;

        } else {
            // INSERT
            $pdo->prepare("
                INSERT INTO psc_master
                (branch_code, branch_name, so_phieu_psc,
                 ngay_good_delivery, ten_khach_hang, mst, email_nhan_hd, ghi_chu,
                 tong_doanh_thu, tong_thue, tong_thanh_tien)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $m['branch_code'],
                $m['branch_name'],
                $m['so_phieu'],
                $ngayGoodDelivery,
                $m['khach_hang'],
                $m['mst'],
                $m['email'],
                $m['ghi_chu'],
                (float)$m['tong_doanh_thu'],
                (float)$m['tong_thue'],
                (float)$m['tong_thanh_tien']
            ]);

            $masterId = $pdo->lastInsertId();
        }

        // ===== INSERT DETAIL =====
        $stmtDetail = $pdo->prepare("
            INSERT INTO psc_detail
            (master_id, linh_kien, so_luong, don_gia, doanh_thu,
             thue_suat, thue_gtgt, thanh_tien, tien_phieu_thu, chenhlech, ghi_chu)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");

        $insertedRows = 0;

        foreach ($rows as $idx => $r) {

            // ✅ FIX 2: KHÔNG CÓ MÃ HÀNG → BỎ
            if (!isset($r[0]) || trim((string)$r[0]) === '') {
                continue;
            }

            $stmtDetail->execute([
                $masterId,
                $r[0],                     // linh_kien
                (int)($r[1] ?? 0),
                (float)($r[2] ?? 0),
                (float)($r[3] ?? 0),
                (float)($r[4] ?? 0),
                (float)($r[5] ?? 0),
                (float)($r[6] ?? 0),
                (float)($r[7] ?? 0),
                (float)($r[8] ?? 0),
                $r[9] ?? ''
            ]);

            $insertedRows++;
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'ok',
            'master_id' => $masterId,
            'detail_inserted' => $insertedRows
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
    background:#fff;margin-top:8px;padding:8px;border-radius:8px;
    display:flex;gap:10px;align-items:flex-end
}
.master .field{display:flex;flex-direction:column}
.master .field.grow{flex:1}
label{font-size:12px;font-weight:600}
input,textarea{padding:6px;border-radius:5px;border:1px solid #ddd;font-size:13px}
textarea{min-height:32px}

.detail{background:#fff;margin-top:8px;padding:6px;border-radius:8px}
#hot{width:100%;min-height:380px}

.footer{
    margin-top:10px;
    display:flex;justify-content:space-between
}
button{
    background:#ff9f43;color:white;border:none;
    padding:8px 20px;border-radius:20px;font-size:14px
}
button.secondary{background:#999}
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

    <span>CỬA HÀNG</span>
    <select id="branch"></select>

    <span id="status">Chưa chọn phiếu</span>
</div>


<div class="master disabled" id="masterForm">
    <div class="field"><label>Ngày</label><input type="date" id="ngay"></div>
    <div class="field grow"><label>Khách hàng</label><input id="khach_hang"></div>
    <div class="field"><label>MST</label><input id="mst"></div>
    <div class="field"><label>Email</label><input id="email"></div>
    <div class="field grow"><label>Ghi chú</label><input id="ghi_chu"></div>
</div>

<div class="detail disabled" id="detailSection">
    <div id="hot"></div>
</div>

<div class="footer">
    <button id="btnNew" onclick="newDoc()">➕ Tạo mới</button>
    <button id="btnSave" onclick="saveData()" disabled>💾 Lưu</button>
</div>

</div>

<script>
/* ===== BRANCH ===== */
const BRANCHES={
  "CN1_HCM":"B2X_QUAN 7_HO CHI MINH",
  "CN1_CT":"B2X_NINH KIEU_CAN THO",
  "CN1_DT":"B2X_CAO LANH_DONG THAP",
  "CN1_BT":"B2X_PHAN THIET_BINH THUAN",
  "CN1_BRVT":"B2X_VUNG TAU_BA RIA VUNG TAU",
  "CN2_BRVT":"B2X_BA RIA_BA RIA VUNG TAU",
  "CN1_HN":"B2X_TAY HO_HA NOI"
};
const branchSelect=document.getElementById('branch');
Object.keys(BRANCHES).forEach(c=>{
  const o=document.createElement('option');
  o.value=c;o.textContent=`${c} - ${BRANCHES[c]}`;
  branchSelect.appendChild(o);
});
branchSelect.value=new URLSearchParams(location.search).get('branch')
    || localStorage.getItem('LAST_BRANCH') || 'CN1_HCM';
localStorage.setItem('LAST_BRANCH',branchSelect.value);
branchSelect.onchange=()=>localStorage.setItem('LAST_BRANCH',branchSelect.value);

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
        // 'Tiền trên Phiếu thu','Chênh lệch','Ghi chú'
    ],
    
columns: [
        { type:'text', width:220 },      // Linh kiện
        { type:'numeric', width:90 },    // Số lượng
    
        { ...moneyCol, width:130 },      // Đơn giá bán lẻ
        { ...moneyCol, readOnly:true, width:130 }, // Doanh thu
    
        { type:'dropdown', source:[0,8,10], width:110 }, // Thuế %
    
        { ...moneyCol, readOnly:true, width:130 }, // Thuế GTGT
        { ...moneyCol, readOnly:true, width:140 }, // Thành tiền
        // { ...moneyCol, width:160 },      // Tiền trên Phiếu thu
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
    status.innerText='Chưa chọn phiếu';
    lockForm();
}

function saveData(){
    const rows=hot.getData().slice(0,-1);
    let dt=0,th=0,tt=0;
    rows.forEach(r=>{dt+=+r[3]||0;th+=+r[5]||0;tt+=+r[6]||0});
    fetch('',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
            master:{
                so_phieu:so_phieu.value,
                branch_code:branchSelect.value,
                branch_name:BRANCHES[branchSelect.value],
                ngay:ngay.value,
                khach_hang:khach_hang.value,
                mst:mst.value,
                email:email.value,
                ghi_chu:ghi_chu.value,
                tong_doanh_thu:dt,
                tong_thue:th,
                tong_thanh_tien:tt
            },
            details:rows
        })
    }).then(()=>alert('Đã lưu'));
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
 

function loadPSC(soPhieu) {

    status.innerText = 'Đang load phiếu';

    fetch(`?action=load&so_phieu=${encodeURIComponent(soPhieu)}`)
        .then(r => r.json())
        .then(res => {

            unlockForm();
            so_phieu.disabled = true;

            if (!res.exists) {
                status.innerText = 'Phiếu mới';
                hot.loadData([]);
                hot.alter('insert_row_below', hot.countRows() - 1);
                updateSummary();
                setTimeout(() => hot.selectCell(0,0), 50);
                return;
            }

            status.innerText = 'Đang sửa phiếu';

            ngay.value = res.master.ngay_good_delivery;
            khach_hang.value = res.master.ten_khach_hang;
            mst.value = res.master.mst;
            email.value = res.master.email_nhan_hd;
            ghi_chu.value = res.master.ghi_chu;
            branchSelect.value = res.master.branch_code; 

            hot.loadData(res.details);
            hot.alter('insert_row_below', hot.countRows() - 1);
            updateSummary();
            setTimeout(() => hot.selectCell(0,0), 50);
        });
}

</script>
</body>
</html>
