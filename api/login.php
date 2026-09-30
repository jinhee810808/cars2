<?php
include_once "db.php";

// POST로 들어온 JSON 데이터 파싱
$data = json_decode(file_get_contents("php://input"), true);
$user_id = $data['id'] ?? '';
$user_pw = $data['pw'] ?? '';

if (empty($user_id) || empty($user_pw)) {
    echo json_encode(["success" => false, "message" => "아이디와 비밀번호를 입력해 주세요."]);
    exit;
}

try {
    $sql = "SELECT company_id, name, branch, role FROM cars_employee_list WHERE user_id = ? AND user_pw = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_pw]);
    $user = $stmt->fetch();

    if ($user) {
        echo json_encode([
            "success" => true,
            "name" => $user['name'],
            "branch" => $user['branch'],
            "role" => $user['role'],
            "company_id" => $user['company_id'],
            "debug_sql" => $sql // 👈 F12 Response에서 쿼리문 확인용으로 포함
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            "success" => false, 
            "message" => "아이디 또는 비밀번호가 올바르지 않습니다.",
            "debug_sql" => $sql // 👈 실패 시에도 확인 가능
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "로그인 처리 오류: " . $e->getMessage()]);
}
?>