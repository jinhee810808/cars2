<?php
header('Content-Type: text/html; charset=utf-8');
include_once 'db.php'; // 기존 PDO DB 연결 파일 포함

// 💡 [핵심 해결책] 다양한 OS/엑셀 환경의 CSV 줄바꿈(CRLF/LF) 자동 감지 설정
ini_set("auto_detect_line_endings", true);

// 한글 깨짐 방지 설정
setlocale(LC_ALL, 'ko_KR.UTF-8');

// 읽어올 CSV 파일 경로
$csv_file = __DIR__.'/csv/2697.csv';
echo "현재 탐색 중인 전체 경로: " . $csv_file . "<br>";

if (!file_exists($csv_file)) {
    die("❌ CSV 파일을 찾을 수 없습니다: " . $csv_file);
}

// CSV 파일 열기
$handle = fopen($csv_file, "r");

if ($handle === FALSE) {
    die("❌ CSV 파일을 열 수 없습니다.");
}

// 💡 첫 번째 줄(헤더/제목행) 건너뛰기
$header = fgetcsv($handle, 1000, ",");

// DB Insert용 Prepared Statement 미리 준비 (속도 및 보안 최적화)
$sql = "INSERT INTO cars_driving_log 
        (vehicle_num, type, branch_name, user_name, start_datetime, start_km, end_datetime, end_km, purpose, memo, expense_date, expense_type, amount) 
        VALUES 
        (:vehicle_num, :type, :branch_name, :user_name, :start_datetime, :start_km, :end_datetime, :end_km, :purpose, :memo, :expense_date, :expense_type, :amount)";

$stmt = $pdo->prepare($sql);

// 💡 [SQL 1] 원본 준비 쿼리문(queryString) 상단 출력
echo "<h4>📄 실행 준비된 SQL 원본 (queryString):</h4>";
echo "<pre style='background:#f4f4f4; padding:8px; font-family:monospace;'>" . htmlspecialchars($stmt->queryString) . "</pre>";
echo "<hr>";

$success_count = 0;
$fail_count = 0;

echo "<h3>🚚 CSV 데이터 DB 등록 시작...</h3>";
echo "<ul>";

// 💡 CSV 행을 한 줄씩 읽는 반복문 (Loop)
while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
    
    // 첫 열의 UTF-8 BOM 기호 제거 (필요시)
    if ($success_count == 0 && $fail_count == 0) {
        $row[0] = preg_replace('/\x{EF}\xBB\xBF/', '', $row[0]);
    }

    // CSV 열 순서 매칭 (0부터 시작)
    $vehicle_num    = trim($row[0] ?? '');
    $type           = trim($row[1] ?? '운행');
    $branch_name    = trim($row[2] ?? '');
    $user_name      = trim($row[3] ?? '');
    $start_datetime = !empty($row[4]) ? trim($row[4]) : null;
    $start_km       = !empty($row[6]) ? intval($row[6]) : 0;
    $end_datetime   = !empty($row[5]) ? trim($row[5]) : null;
    $end_km         = !empty($row[7]) ? intval($row[7]) : 0;
    $purpose        = trim($row[8] ?? '');
    $memo           = trim($row[9] ?? '');
    $expense_date   = !empty($row[10]) ? trim($row[10]) : null;
    $expense_type   = trim($row[11] ?? '');
    $amount         = !empty($row[12]) ? intval($row[12]) : 0;

    // 차량번호가 비어있으면 건너뛰되, 사유 출력
    if (empty($vehicle_num)) {
        echo "<li style='color:gray;'>⚠️ 차량번호(1열)가 비어있는 행이 있어 건너뛰었습니다.</li>";
        continue;
    }

    try {
        // 파라미터 바인딩 및 쿼리 실행
        $stmt->execute([
            ':vehicle_num'    => $vehicle_num,
            ':type'           => $type,
            ':branch_name'    => $branch_name,
            ':user_name'      => $user_name,
            ':start_datetime' => $start_datetime,
            ':start_km'       => $start_km,
            ':end_datetime'   => $end_datetime,
            ':end_km'         => $end_km,
            ':purpose'        => $purpose,
            ':memo'           => $memo,
            ':expense_date'   => $expense_date,
            ':expense_type'   => $expense_type,
            ':amount'         => $amount
        ]);

        $success_count++;

        // 💡 [SQL 2] 바인딩된 값이 채워진 형태의 완성형 SQL 디버깅 문자열 조합
        $debug_sql = "INSERT INTO cars_driving_log VALUES (" .
            "'" . addslashes($vehicle_num) . "', " .
            "'" . addslashes($type) . "', " .
            "'" . addslashes($branch_name) . "', " .
            "'" . addslashes($user_name) . "', " .
            ($start_datetime ? "'{$start_datetime}'" : "NULL") . ", {$start_km}, " .
            ($end_datetime ? "'{$end_datetime}'" : "NULL") . ", {$end_km}, " .
            "'" . addslashes($purpose) . "', '" . addslashes($memo) . "', " .
            ($expense_date ? "'{$expense_date}'" : "NULL") . ", '" . addslashes($expense_type) . "', {$amount}" .
            ");";

        echo "<li>✅ 등록 성공: {$vehicle_num} ({$user_name} - {$type})<br>";
        echo "<code style='color:#2b6cb0; font-size:12px;'>🔍 SQL: " . htmlspecialchars($debug_sql) . "</code></li><br>";

    } catch (PDOException $e) {
        $fail_count++;
        echo "<li style='color:red;'>❌ 등록 실패 ({$vehicle_num}): " . $e->getMessage() . "</li>";
    }
}

fclose($handle);

echo "</ul>";
echo "<hr>";
echo "<b>🎉 처리 완료! 성공: {$success_count}건 / 실패: {$fail_count}건</b>";
?>