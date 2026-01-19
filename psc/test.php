<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>PSC Enter Fix</title>
<style>
body {
    font-family: Arial;
    background: #f4f6f9;
    padding: 30px;
}

/* PSC BAR – TUYỆT ĐỐI KHÔNG DISABLE */
.psc-bar {
    background: #fff3e0;
    border: 2px solid #ff9f43;
    padding: 12px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* INPUT PSC */
#so_phieu {
    font-size: 18px;
    padding: 8px 12px;
    width: 260px;
    border: 2px solid #ff9f43;
    border-radius: 6px;
}

/* BLOCK KHÁC (GIẢ LẬP FORM) */
.block {
    margin-top: 20px;
    padding: 15px;
    background: white;
    border-radius: 8px;
}

.disabled {
    opacity: 0.4;
    pointer-events: none; /* CHỈ ÁP CHO FORM, KHÔNG ÁP PSC */
}
</style>
</head>

<body>

<h2>TEST ENTER – SỐ PHIẾU PSC</h2>

<!-- PSC luôn sống -->
<div class="psc-bar">
    <strong>SỐ PHIẾU PSC</strong>
    <input id="so_phieu" placeholder="Gõ PSC rồi Enter">
    <span id="status">Chưa Enter</span>
</div>

<!-- Giả lập form -->
<div id="formBlock" class="block disabled">
    <p>Form sửa chữa (chỉ mở sau Enter)</p>
</div>

<script>
// CHỜ DOM READY – CỰC KỲ QUAN TRỌNG
document.addEventListener('DOMContentLoaded', () => {

    const pscInput = document.getElementById('so_phieu');
    const status = document.getElementById('status');
    const formBlock = document.getElementById('formBlock');

    // GẮN EVENT BẰNG addEventListener (KHÔNG DÙNG onkeydown)
    pscInput.addEventListener('keydown', function (e) {

        if (e.key === 'Enter') {
            e.preventDefault();

            const value = this.value.trim();

            console.log('ENTER PSC:', value);

            if (!value) {
                status.textContent = 'PSC trống';
                return;
            }

            // GIẢ LẬP LOAD PHIẾU
            status.textContent = 'Đang load phiếu: ' + value;

            // MỞ FORM
            formBlock.classList.remove('disabled');

            // KHÓA PSC SAU ENTER
            this.disabled = true;
        }
    });

});
</script>

</body>
</html>
