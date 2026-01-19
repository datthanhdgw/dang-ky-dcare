<?php 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['name'], $_POST['phone'], $_POST['address'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $productname = trim($_POST['productname']);
        $center = trim($_POST['center']);
        $centerId = $_POST['center'];         // storeId
        $centerName = $_POST['center_name'];  // storeName
        
                

        if (!preg_match('/^\d{10}$/', $phone)) {
            die("<h4 style='color:red'>❌ Số điện thoại phải đủ 10 chữ số.</h4><a href='index.php'>← Quay lại</a>");
        }

        $file = 'queue_data.json';
        $queue = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

        
        $queue[] = [
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'productname' => $productname,
            'center' => $centerId,
            'centerName' => $centerName,
            'time' => time(),
            'called' => false
        ];


        file_put_contents($file, json_encode($queue));

        // header('Location: thankyou.php');
        $centerId = urlencode($_POST['center']);
        $centerName = urlencode($_POST['center_name']);
        header("Location: thankyounoibo.php?center={$centerId}&center_name={$centerName}");
        exit;
        

        exit;
    } else {
        die("❌ Thiếu thông tin đầu vào.");
    }
} else {
    die("❌ Yêu cầu không hợp lệ.");
}
