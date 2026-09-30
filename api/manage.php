<?php
// PHP 에러가 JSON 출력을 깨뜨리지 않도록 방어
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// CORS 사전 검사(Preflight) 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

include_once "db.php";

// JSON Body 및 Query Parameter 수신 통일
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true) ?? [];
$action = trim($_GET['action'] ?? $input['action'] ?? '');

try {
    switch ($action) {

        // ====================================================
        // 1. 운행기록 또는 지출/정비 대장 신규 등록
        // ====================================================
        case 'add_log':
            $company_id   = trim($input['company_id'] ?? '');
            $vehicle_num  = trim($input['vehicle_num'] ?? '');
            $type         = trim($input['type'] ?? '운행');
            $branch_name  = trim($input['branch_name'] ?? '');
            $user_name    = trim($input['user_name'] ?? '');
            $memo         = trim($input['memo'] ?? '');

            if ($type === '운행') {
                $start_datetime = $input['start_datetime'] ?? null;
                $start_km       = intval($input['start_km'] ?? 0);
                $end_datetime   = $input['end_datetime'] ?? null;
                $end_km         = intval($input['end_km'] ?? 0);
                $purpose        = $input['purpose'] ?? '일반업무용';

                $sql = "INSERT INTO cars_driving_log 
                        (company_id, vehicle_num, type, branch_name, user_name, start_datetime, start_km, end_datetime, end_km, purpose, memo) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $company_id, $vehicle_num, $type, $branch_name, $user_name,
                    $start_datetime, $start_km, $end_datetime, $end_km, $purpose, $memo
                ]);
            } else {
                $expense_date = $input['expense_date'] ?? null;
                $expense_type = $input['expense_type'] ?? '기타';
                $amount       = intval($input['amount'] ?? 0);

                $sql = "INSERT INTO cars_driving_log 
                        (company_id, vehicle_num, type, branch_name, user_name, expense_date, expense_type, amount, memo) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $company_id, $vehicle_num, $type, $branch_name, $user_name,
                    $expense_date, $expense_type, $amount, $memo
                ]);
            }

            echo json_encode(["success" => true, "message" => "기록이 등록되었습니다."], JSON_UNESCAPED_UNICODE);
            exit;

        // ====================================================
        // 2. 기록 상세 수정 (UPDATE)
        // ====================================================
        case 'update_log':
            $id        = intval($input['id'] ?? 0);
            $type      = trim($input['type'] ?? '운행');
            $user_name = trim($input['user_name'] ?? '');
            $memo      = trim($input['memo'] ?? '');

            if (!$id) {
                echo json_encode(["success" => false, "message" => "수정할 기록의 ID가 올바르지 않습니다."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($type === '운행') {
                $start_datetime = $input['start_datetime'] ?? null;
                $start_km       = intval($input['start_km'] ?? 0);
                $end_datetime   = $input['end_datetime'] ?? null;
                $end_km         = intval($input['end_km'] ?? 0);
                $purpose        = $input['purpose'] ?? '일반업무용';

                $sql = "UPDATE cars_driving_log 
                        SET user_name = ?, start_datetime = ?, start_km = ?, end_datetime = ?, end_km = ?, purpose = ?, memo = ?
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_name, $start_datetime, $start_km, $end_datetime, $end_km, $purpose, $memo, $id]);
            } else {
                $expense_date = $input['expense_date'] ?? null;
                $expense_type = $input['expense_type'] ?? '기타';
                $amount       = intval($input['amount'] ?? 0);

                $sql = "UPDATE cars_driving_log 
                        SET user_name = ?, expense_date = ?, expense_type = ?, amount = ?, memo = ?
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_name, $expense_date, $expense_type, $amount, $memo, $id]);
            }

            echo json_encode(["success" => true, "message" => "기록이 성공적으로 수정되었습니다."], JSON_UNESCAPED_UNICODE);
            exit;

        // ====================================================
        // 3. 기록 삭제 (DELETE)
        // ====================================================
        case 'delete_log':
            $id = intval($input['id'] ?? 0);

            if (!$id) {
                echo json_encode(["success" => false, "message" => "삭제할 기록의 ID가 올바르지 않습니다."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM cars_driving_log WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(["success" => true, "message" => "기록이 정상적으로 삭제되었습니다."], JSON_UNESCAPED_UNICODE);
            exit;

        // ====================================================
        // 4. 지점 추가
        // ====================================================
        case 'add_branch':
            $company_id  = trim($input['company_id'] ?? '');
            $branch_name = trim($input['branch_name'] ?? '');

            if (empty($branch_name)) {
                echo json_encode(["success" => false, "message" => "지점명이 입력되지 않았습니다."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 중복 검사
            $checkStmt = $pdo->prepare("SELECT id FROM cars_branch_list WHERE company_id = ? AND branch_name = ?");
            $checkStmt->execute([$company_id, $branch_name]);
            if ($checkStmt->fetch()) {
                echo json_encode(["success" => false, "message" => "이미 등록된 지점입니다."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO cars_branch_list (company_id, branch_name) VALUES (?, ?)");
            $stmt->execute([$company_id, $branch_name]);

            echo json_encode(["success" => true, "message" => "지점이 추가되었습니다."], JSON_UNESCAPED_UNICODE);
            exit;

        // ====================================================
        // 5. 지점 삭제
        // ====================================================
        case 'delete_branch':
            $company_id  = trim($input['company_id'] ?? '');
            $branch_name = trim($input['branch_name'] ?? '');

            if (empty($branch_name)) {
                echo json_encode(["success" => false, "message" => "삭제할 지점명이 전달되지 않았습니다."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM cars_branch_list WHERE company_id = ? AND branch_name = ?");
            $stmt->execute([$company_id, $branch_name]);

            echo json_encode(["success" => true, "message" => "지점이 삭제되었습니다."], JSON_UNESCAPED_UNICODE);
            exit;

        // ====================================================
        // 6. 업무 차량 추가
        // ====================================================
        case 'add_car':
            $num        = trim($input['vehicle_num'] ?? '');
            $model      = trim($input['model_name'] ?? '');
            $fuel       = trim($input['fuel'] ?? '');
            $branch     = trim($input['branch'] ?? '');
            $company_id = trim($input['company_id'] ?? '');

            if (empty($num) || empty($model) || empty($branch)) {
                echo json_encode(["success" => false, "message" => "필수 입력 정보(차량번호, 모델명, 지점)가 누락되었습니다."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $checkStmt = $pdo->prepare("SELECT id FROM cars_vehicle_list WHERE vehicle_num = ?");
            $checkStmt->execute([$num]);
            if ($checkStmt->fetch()) {
                echo json_encode(["success" => false, "message" => "이미 등록된 차량 번호입니다."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO cars_vehicle_list (vehicle_num, model_name, fuel, branch, company_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$num, $model, $fuel, $branch, $company_id]);

            echo json_encode(["success" => true, "message" => "차량이 정상적으로 등록되었습니다."], JSON_UNESCAPED_UNICODE);
            exit;

        // ====================================================
        // 7. 업무 차량 제외 (삭제)
        // ====================================================
        case 'delete_car':
            $num = trim($input['vehicle_num'] ?? '');

            if (empty($num)) {
                echo json_encode(["success" => false, "message" => "삭제할 차량 번호가 지정되지 않았습니다."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM cars_vehicle_list WHERE vehicle_num = ?");
            $stmt->execute([$num]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "차량이 정상적으로 삭제되었습니다."], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(["success" => false, "message" => "해당 차량을 찾을 수 없거나 이미 삭제되었습니다."], JSON_UNESCAPED_UNICODE);
            }
            exit;

        // ====================================================
        // 8. 신규 사원 계정 추가
        // ====================================================
        case 'add_staff':
            $id         = trim($input['id'] ?? '');
            $pw         = trim($input['pw'] ?? '');
            $name       = trim($input['name'] ?? '');
            $branch     = trim($input['branch'] ?? '');
            $role       = trim($input['role'] ?? 'general');
            $company_id = trim($input['company_id'] ?? '');

            if (empty($id) || empty($pw) || empty($name) || empty($branch) || empty($company_id)) {
                echo json_encode(["success" => false, "message" => "필수 입력 항목이 누락되었습니다."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $checkStmt = $pdo->prepare("SELECT id FROM cars_employee_list WHERE user_id = ?");
            $checkStmt->execute([$id]);
            if ($checkStmt->fetch()) {
                echo json_encode(["success" => false, "message" => "이미 존재하는 ID입니다."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO cars_employee_list (user_id, user_pw, name, branch, role, company_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $pw, $name, $branch, $role, $company_id]);

            echo json_encode(["success" => true, "message" => "직원 계정이 정상적으로 생성되었습니다."], JSON_UNESCAPED_UNICODE);
            exit;

        // ====================================================
        // 9. 사원 계정 삭제
        // ====================================================
        case 'delete_staff':
            $id         = trim($input['id'] ?? '');
            $company_id = trim($input['company_id'] ?? '');

            if (empty($id)) {
                echo json_encode(["success" => false, "message" => "삭제할 ID가 전달되지 않았습니다."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (!empty($company_id)) {
                $stmt = is_numeric($id) 
                    ? $pdo->prepare("DELETE FROM cars_employee_list WHERE id = ? AND company_id = ?")
                    : $pdo->prepare("DELETE FROM cars_employee_list WHERE user_id = ? AND company_id = ?");
                $stmt->execute([$id, $company_id]);
            } else {
                $stmt = is_numeric($id)
                    ? $pdo->prepare("DELETE FROM cars_employee_list WHERE id = ?")
                    : $pdo->prepare("DELETE FROM cars_employee_list WHERE user_id = ?");
                $stmt->execute([$id]);
            }

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "직원 계정이 정상 삭제되었습니다."], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(["success" => false, "message" => "해당 ID({$id})의 계정을 찾을 수 없거나 이미 삭제되었습니다."], JSON_UNESCAPED_UNICODE);
            }
            exit;

        // ====================================================
        // ⚠️ 액션 불일치 (기본 에러)
        // ====================================================
        default:
            echo json_encode([
                "success" => false, 
                "message" => "정의되지 않은 액션 요청입니다. (action: " . htmlspecialchars($action) . ")"
            ], JSON_UNESCAPED_UNICODE);
            exit;
    }

} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => "서버 처리 에러: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>