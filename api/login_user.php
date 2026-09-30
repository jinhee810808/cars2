<?php
// 1. 에러 및 경고 표시 설정
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// 2. DB 연결
require_once 'db.php';

if (!isset($pdo)) {
    echo json_encode([
        "success" => false,
        "message" => "DB 연결 객체(\$pdo)를 찾을 수 없습니다."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. JSON 요청 데이터 수신
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

$user_id = trim($input['user_id'] ?? '');
$user_pw = trim($input['user_pw'] ?? '');

if (!$user_id || !$user_pw) {
    echo json_encode([
        "success" => false,
        "message" => "아이디와 비밀번호를 입력해 주세요."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. SQL 쿼리문 정의 ($sql 변수 초기화)
$sql = "SELECT 
            e.name, 
            e.branch, 
            e.role, 
            e.company_id,
            a.company_name
        FROM cars_employee_list e
        LEFT JOIN cars_admin_list a 
               ON e.company_id COLLATE utf8mb4_unicode_ci = a.company_id COLLATE utf8mb4_unicode_ci
        WHERE e.user_id = ? AND e.user_pw = ?";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_pw]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            "success"   => true,
            "debug_sql" => $sql,
            "params"    => [$user_id, $user_pw],
            "user" => [
                "name"         => $user['name'] ?? '',
                "branch"       => $user['branch'] ?? '',
                "role"         => $user['role'] ?? '',
                "company_id"   => $user['company_id'] ?? '',
                "company_name" => $user['company_name'] ?? ''
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            "success"   => false,
            "debug_sql" => $sql,
            "params"    => [$user_id, $user_pw],
            "message"   => "아이디 또는 비밀번호가 올바르지 않습니다."
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success"   => false,
        "debug_sql" => $sql,
        "message"   => "DB PDO 오류: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        "success"   => false,
        "debug_sql" => $sql,
        "message"   => "일반 오류: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}