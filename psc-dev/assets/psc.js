let linhKienList = [];
let linhKienMap  = {};

/* ---------- Utils ---------- */
function formatMoney(val) {
  if (val === null || val === '' || isNaN(val)) return '';
  return Number(val).toLocaleString('vi-VN', { maximumFractionDigits: 0 });
}

function parseMoney(val) {
  if (val === null || val === '') return 0;
  return Number(String(val).replace(/[^\d]/g, '')) || 0;
}

/* ---------- Load danh mục linh kiện ---------- */
fetch('linh_kien_list.php')
  .then(r => r.json())
  .then(res => {
    if (!res.success) {
      alert(res.message || 'Không load được danh mục linh kiện');
      return;
    }

    res.data.forEach(it => {
      linhKienList.push(it.ma_linh_kien);
      linhKienMap[it.ma_linh_kien] = it;
    });
  });

/* ---------- Renderer tiền ---------- */
function moneyRenderer(instance, td, row, col, prop, value) {
  td.style.textAlign = 'right';
  td.innerText = formatMoney(value);
}

/* ---------- Tính tiền 1 dòng ---------- */
function calcRow(row) {
  let sl   = parseMoney(hot.getDataAtRowProp(row, 'so_luong'));
  let dg   = parseMoney(hot.getDataAtRowProp(row, 'don_gia'));
  let tax  = parseMoney(hot.getDataAtRowProp(row, 'thue_pct'));

  let tien = sl * dg;
  let tien_thue = Math.round(tien * tax / 100);
  let tong = tien + tien_thue;

  hot.setDataAtRowProp(row, 'tien_thue', tien_thue, 'calc');
  hot.setDataAtRowProp(row, 'thanh_tien', tong, 'calc');
}

/* ---------- Tổng cộng ---------- */
function calcTotal() {
  let total = 0;
  hot.getData().forEach(r => {
    if (r && r.linh_kien) {
      total += parseMoney(r.thanh_tien);
    }
  });
  const el = document.getElementById('tong_cong');
  if (el) el.innerText = formatMoney(total);
}

/* =========================
 * Handsontable init
 * ========================= */

const container = document.getElementById('hot');

const hot = new Handsontable(container, {
  data: [],
  stretchH: 'all',
  rowHeaders: true,
  colHeaders: true,
  minSpareRows: 1,
  licenseKey: 'non-commercial-and-evaluation',

  columns: [
    {
      data: 'linh_kien',
      type: 'autocomplete',
      source: () => linhKienList,
      strict: true,
      allowInvalid: false
    },
    { data: 'ten_linh_kien', readOnly: true },

    { data: 'so_luong', type: 'numeric' },

    {
      data: 'don_gia',
      type: 'numeric',
      renderer: moneyRenderer
    },

    { data: 'thue_pct', type: 'numeric' },

    {
      data: 'tien_thue',
      type: 'numeric',
      readOnly: true,
      renderer: moneyRenderer
    },

    {
      data: 'thanh_tien',
      type: 'numeric',
      readOnly: true,
      renderer: moneyRenderer
    },

    { data: 'ghi_chu' }
  ],

  afterChange: function (changes, source) {
    if (!changes || source === 'loadData') return;

    changes.forEach(([row, prop, oldVal, newVal]) => {

      /* chọn mã linh kiện → fill từ danh mục */
      if (prop === 'linh_kien' && linhKienMap[newVal]) {
        const lk = linhKienMap[newVal];

        hot.setDataAtRowProp(row, 'ten_linh_kien', lk.ten_linh_kien);
        hot.setDataAtRowProp(row, 'don_gia', lk.gia_ban);
        hot.setDataAtRowProp(row, 'thue_pct', lk.thue_pct);

        if (!hot.getDataAtRowProp(row, 'so_luong')) {
          hot.setDataAtRowProp(row, 'so_luong', 1);
        }
      }

      /* sửa SL / giá / thuế → tính lại */
      if (['so_luong', 'don_gia', 'thue_pct'].includes(prop)) {
        calcRow(row);
      }
    });

    calcTotal();
  }
});

/* ---------- focus tiện nhập ---------- */
window.focusFirstRow = function () {
  setTimeout(() => hot.selectCell(0, 0), 100);
};
