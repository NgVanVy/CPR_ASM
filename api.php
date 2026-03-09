<?php
// Cho phép CORS để React có thể gọi API
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit();
}

// Kết nối MySQL XAMPP
$conn = new mysqli("localhost", "root", "", "hitech_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die(json_encode(["error" => "Kết nối CSDL thất bại: " . $conn->connect_error]));
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$input = json_decode(file_get_contents("php://input"), true);

// 1. LẤY TOÀN BỘ DỮ LIỆU LÊN FRONTEND
if ($action == 'get_all') {
    $users = []; $orders = [];
    
    $res1 = $conn->query("SELECT * FROM users");
    if ($res1) {
        while($row = $res1->fetch_assoc()) {
            $row['mfaEnabled'] = (bool)$row['mfaEnabled']; // Ép kiểu boolean cho React
            $users[] = $row;
        }
    }

    $res2 = $conn->query("SELECT * FROM orders ORDER BY id DESC");
    if ($res2) {
        while($row = $res2->fetch_assoc()) {
            $row['items'] = json_decode($row['items']);
            $row['total'] = (int)$row['total'];
            $orders[] = $row;
        }
    }
    echo json_encode(["users" => $users, "orders" => $orders]);
}
// 2. THÊM NGƯỜI DÙNG MỚI (Đăng ký)
elseif ($action == 'add_user') {
    $stmt = $conn->prepare("INSERT INTO users (id, email, password, role, mfaEnabled, phone, fullName) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $mfa = isset($input['mfaEnabled']) && $input['mfaEnabled'] ? 1 : 0;
    $id = strval($input['id']);
    $stmt->bind_param("ssssiss", $id, $input['email'], $input['password'], $input['role'], $mfa, $input['phone'], $input['fullName']);
    if($stmt->execute()) echo json_encode(["success" => true]);
    else echo json_encode(["error" => $stmt->error]);
}
// 3. CẬP NHẬT NGƯỜI DÙNG (Bật/tắt MFA)
elseif ($action == 'update_user') {
    $stmt = $conn->prepare("UPDATE users SET fullName=?, phone=?, password=?, role=?, mfaEnabled=? WHERE email=?");
    $mfa = isset($input['mfaEnabled']) && $input['mfaEnabled'] ? 1 : 0;
    $stmt->bind_param("ssssis", $input['fullName'], $input['phone'], $input['password'], $input['role'], $mfa, $input['email']);
    if($stmt->execute()) echo json_encode(["success" => true]);
    else echo json_encode(["error" => $stmt->error]);
}
// 4. LƯU ĐƠN HÀNG MỚI
elseif ($action == 'add_order') {
    $stmt = $conn->prepare("INSERT INTO orders (id, email, items, total, date, status) VALUES (?, ?, ?, ?, ?, ?)");
    $items_json = json_encode($input['items'], JSON_UNESCAPED_UNICODE);
    $stmt->bind_param("sssdss", $input['id'], $input['email'], $items_json, $input['total'], $input['date'], $input['status']);
    if($stmt->execute()) echo json_encode(["success" => true]);
    else echo json_encode(["error" => $stmt->error]);
}
else {
    echo json_encode(["error" => "Hành động không hợp lệ"]);
}
$conn->close();
?>