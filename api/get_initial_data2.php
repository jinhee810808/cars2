<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

include_once "db.php";

// GET, POST 또는 JSON Body에서 company_id 추출
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
$company_id = trim($_GET['company_id'] ?? $_POST['company_id'] ?? $input['company_id'] ?? '');

// company_id가 없을 경우 조회 차단
if (!$company_id) {
    echo json_encode([
        "success" => false,
        "message" => "company_id 파라미터가 누락되었습니다."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. 지점 목록 가져오기 (해당 회사 소속)
    $stmt1 = $pdo->prepare("SELECT branch_name FROM cars_branch_list WHERE company_id = ? ORDER BY id ASC");
    $stmt1->execute([$company_id]);
    $branches = $stmt1->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. 직원 목록 가져오기 (해당 회사 소속)
    $stmt2 = $pdo->prepare("SELECT id, user_id, user_pw as pw, name, branch, role, company_id FROM cars_employee_list WHERE company_id = ? ORDER BY created_at ASC");
    $stmt2->execute([$company_id]);
    $users = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. 업무 차량 목록 가져오기 (해당 회사 소속)
    $stmt3 = $pdo->prepare("SELECT id, vehicle_num, model_name, fuel, branch, company_id FROM cars_vehicle_list WHERE company_id = ? ORDER BY id DESC");
    $stmt3->execute([$company_id]);
    $vehicles = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. 통합 차량 운행 및 정비/지출 대장 가져오기 (해당 회사 소속)
    $stmt4 = $pdo->prepare("SELECT * FROM cars_driving_log WHERE company_id = ? ORDER BY COALESCE(end_datetime, CONCAT(expense_date, ' 23:59:59')) DESC");
    $stmt4->execute([$company_id]);
    $histories = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "branches" => $branches,
        "users" => $users,
        "vehicles" => $vehicles,
        "histories" => $histories
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => "데이터 조회 오류: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>