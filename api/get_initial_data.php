<?php
include_once "db.php";

try {
    // 1. 지점 목록 가져오기 (등록순)
    $branches = $pdo->query("SELECT branch_name FROM cars_branch_list ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. 직원 목록 가져오기 (최신 등록순)
    $users = $pdo->query("SELECT id, user_id, user_pw as pw, name, branch, role FROM cars_employee_list ORDER BY created_at ASC")->fetchAll();
    
    // 3. 업무 차량 목록 가져오기
    $vehicles = $pdo->query("SELECT vehicle_num, model_name, fuel, branch FROM cars_vehicle_list ORDER BY id DESC")->fetchAll();
    
    // 4. 통합 차량 운행 및 정비/지출 대장 가져오기 (가장 최근 활동 순정렬)
    $histories = $pdo->query("SELECT * FROM cars_driving_log ORDER BY COALESCE(end_datetime, CONCAT(expense_date, ' 23:59:59')) DESC")->fetchAll();

    echo json_encode([
        "success" => true,
        "branches" => $branches,
        "users" => $users,
        "vehicles" => $vehicles,
        "histories" => $histories
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "데이터 조회 오류: " . $e->getMessage()]);
}
?>