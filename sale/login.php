<?php
header("Content-Type: application/json");

function getAuthorizationHeader() {
    if (isset($_SERVER['Authorization'])) return trim($_SERVER["Authorization"]);
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) return trim($_SERVER["HTTP_AUTHORIZATION"]);
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) return trim($headers['Authorization']);
    }
    return null;
}

try {
    $auth = getAuthorizationHeader();

    // Body mặc định cho API login
    $body = json_encode([
        "S_BUKRS" => ["1000", "1100", "1200"],
        "S_MATNR" => ["LOGIN"],
        "S_MATKL" => [],
        "S_BRAND" => [],
        "S_SUBBR" => [],
        "S_GROUP" => [],
        "S_SUBGR" => []
    ]);

    if (!$auth) {
        throw new Exception("Thiếu Authorization header");
    }

    $url = "https://vhdgwps4ap01.sap.dgw.vn:44300/sap/zmis_api/ecotv-zwm11?sap-client=100";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $auth",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("SAP trả về lỗi HTTP $httpCode - $error - Phản hồi: $response");
    }

    echo $response;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage()
    ]);
}
