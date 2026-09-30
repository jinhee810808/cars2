<?php
// 에러가 출력되어 JSON 형식을 깨뜨리는 것 방지
// 예비용임 manage.php 이거가 맞음
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

include_once "db.php";

// JSON 및 POST 데이터 수신
$raw_input = file_get_contents('php://input');
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

$data = json_decode(file_get_contents("php://input"), true);

try {
    // A. 운행기록 또는 지출/정비 대장 등록
    if ($action === 'add_log') {
        $sql = "INSERT INTO cars_driving_log (company_id, vehicle_num, type, branch_name, user_name, start_datetime, end_datetime, start_km, end_km, purpose, expense_date, expense_type, amount, memo) 
                VALUES (:company_id, :vehicle_num, :type, :branch_name, :user_name, :start_datetime, :end_datetime, :start_km, :end_km, :purpose, :expense_date, :expense_type, :amount, :memo)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':company_id' => $data['company_id'],
            ':vehicle_num' => $data['vehicle_num'],
            ':type' => $data['type'],
            ':branch_name' => $data['branch_name'],
            ':user_name' => $data['user_name'],
            ':start_datetime' => $data['start_datetime'] ?? null,
            ':end_datetime' => $data['end_datetime'] ?? null,
            ':start_km' => $data['start_km'] ?? null,
            ':end_km' => $data['end_km'] ?? null,
            ':purpose' => $data['purpose'] ?? null,
            ':expense_date' => $data['expense_date'] ?? null,
            ':expense_type' => $data['expense_type'] ?? null,
            ':amount' => $data['amount'] ?? null,
            ':memo' => $data['memo'] ?? null
        ]);
        echo json_encode(["success" => true]);
        exit;
        
    // B. 지점 추가
    } elseif ($action === 'add_branch') {
            $company_id  = trim($input['company_id'] ?? '');
            $branch_name = trim($input['branch_name'] ?? '');

            if (empty($branch_name)) {
                echo json_encode(["success" => false, "message" => "지점명이 입력되지 않았습니다."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 중복 검사 (동일 회사 내 지점 중복 방지)
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
        
    // C. 지점 삭제
    } elseif ($action === 'delete_branch') {
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
        
    // D. 업무 차량 추가
    } elseif ($action === 'add_car') {
        $num = trim($input['vehicle_num'] ?? '');
        $model = trim($input['model_name'] ?? '');
        $fuel = trim($input['fuel'] ?? '');
        $branch = trim($input['branch'] ?? '');
        $company_id = trim($input['company_id'] ?? '');

        if (empty($num) || empty($model) || empty($branch)) {
            echo json_encode(["success" => false, "message" => "필수 입력 정보(차량번호, 모델명, 지점)가 누락되었습니다."]);
            exit;
        }

        try {
            // 1. 차량 번호 중복 체크
            $checkStmt = $pdo->prepare("SELECT id FROM cars_vehicle_list WHERE vehicle_num = ?");
            $checkStmt->execute([$num]);
            if ($checkStmt->fetch()) {
                echo json_encode(["success" => false, "message" => "이미 인가된 차량 번호입니다."]);
                exit;
            }

            // 2. 신규 차량 등록 (테이블명: cars_vehicle_list 또는 vehicle_list 환경에 맞춰 사용)
            $stmt = $pdo->prepare("INSERT INTO cars_vehicle_list (vehicle_num, model_name, fuel, branch, company_id) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute([$num, $model, $fuel, $branch, $company_id]);

            if ($result) {
                echo json_encode(["success" => true, "message" => "차량이 정상적으로 등록되었습니다."]);
            } else {
                echo json_encode(["success" => false, "message" => "DB 차량 등록 실패"]);
            }
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "DB 오류: " . $e->getMessage()]);
        }
        exit;
        
    // E. 업무 차량 제외
    } elseif ($action === 'delete_car') {
        $num = trim($input['vehicle_num'] ?? '');

        if (empty($num)) {
            echo json_encode(["success" => false, "message" => "삭제할 차량 번호가 지정되지 않았습니다."]);
            exit;
        }

        try {
            // DB DELETE 실행 (테이블명: cars_vehicle_list 또는 vehicle_list)
            $stmt = $pdo->prepare("DELETE FROM cars_vehicle_list WHERE vehicle_num = ?");
            $result = $stmt->execute([$num]);

            if ($result && $stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "차량이 정상적으로 삭제되었습니다."]);
            } else {
                echo json_encode(["success" => false, "message" => "해당 차량을 찾을 수 없거나 이미 삭제되었습니다."]);
            }
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "DB 오류: " . $e->getMessage()]);
        }
        exit;
        
    // F. 신규 사원 계정 추가
    } elseif ($action === 'add_staff') {
        $id = trim($input['id'] ?? '');
        $pw = trim($input['pw'] ?? '');
        $name = trim($input['name'] ?? '');
        $branch = trim($input['branch'] ?? '');
        $role = trim($input['role'] ?? 'general');
        $company_id = trim($input['company_id'] ?? '');

        if (empty($id) || empty($pw) || empty($name) || empty($branch) || empty($company_id)) {
            echo json_encode(["success" => false, "message" => "필수 입력 항목이 누락되었습니다."]);
            exit;
        }

        try {
            // 1. ID 중복 체크 (테이블명: cars_employee_list 또는 users)
            $checkStmt = $pdo->prepare("SELECT id FROM cars_employee_list WHERE user_id = ?");
            $checkStmt->execute([$id]);
            if ($checkStmt->fetch()) {
                echo json_encode(["success" => false, "message" => "이미 존재하는 ID입니다."]);
                exit;
            }

            // 2. 비밀번호 암호화 (선택 사항: password_hash 사용 시 $pwHash = password_hash($pw, PASSWORD_DEFAULT);)
            // 3. 신규 계정 INSERT
            $stmt = $pdo->prepare("INSERT INTO cars_employee_list (user_id, user_pw, name, branch, role, company_id) VALUES (?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$id, $pw, $name, $branch, $role, $company_id]);

            if ($result) {
                echo json_encode(["success" => true, "message" => "직원 계정이 정상적으로 생성되었습니다."]);
            } else {
                echo json_encode(["success" => false, "message" => "DB 계정 생성 실패"]);
            }
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "DB 오류: " . $e->getMessage()]);
        }
        exit;
        
    // G. 사원 계정 삭제
    } elseif ($action === 'delete_staff') {
        $id = trim($input['id'] ?? '');
        $company_id = trim($input['company_id'] ?? '');

        if (empty($id)) {
            echo json_encode(["success" => false, "message" => "삭제할 ID가 전달되지 않았습니다."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            // company_id가 함께 전달된 경우 회사 격리 삭제, 없을 경우 ID로만 삭제
            if (!empty($company_id)) {
                if (is_numeric($id)) {
                    $stmt = $pdo->prepare("DELETE FROM cars_employee_list WHERE id = ? AND company_id = ?");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM cars_employee_list WHERE user_id = ? AND company_id = ?");
                }
                $stmt->execute([$id, $company_id]);
            } else {
                if (is_numeric($id)) {
                    $stmt = $pdo->prepare("DELETE FROM cars_employee_list WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM cars_employee_list WHERE user_id = ?");
                }
                $stmt->execute([$id]);
            }

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "직원 계정이 정상 삭제되었습니다."], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(["success" => false, "message" => "해당 ID({$id})의 계정을 찾을 수 없거나 이미 삭제되었습니다."], JSON_UNESCAPED_UNICODE);
            }
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "DB 오류: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    } else {
        echo json_encode(["success" => false, "message" => "정의되지 않은 액션 요청입니다."]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "서버 처리 에러: " . $e->getMessage()]);
}


// 1) 기록 수정
if ($action === 'update_log') {
    $id = intval($input['id'] ?? 0);
    $type = trim($input['type'] ?? '운행');
    $user_name = trim($input['user_name'] ?? '');
    $memo = trim($input['memo'] ?? '');

    try {
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

        echo json_encode(["success" => true, "message" => "수정 완료"]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "수정 오류: " . $e->getMessage()]);
        exit;
    }
}

// 2) 기록 삭제
if ($action === 'delete_log') {
    $id = intval($input['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("DELETE FROM cars_driving_log WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["success" => true, "message" => "삭제 완료"]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "삭제 오류: " . $e->getMessage()]);
        exit;
    }
}

// ⚠️ 정의되지 않은 요청 (기본 응답)
echo json_encode(["success" => false, "message" => "유효하지 않은 요청입니다. (action: " . htmlspecialchars($action) . ")"]);
exit;
?>