<?php
$c = file_get_contents('api_v2_observer.php');
$c = str_replace('/api/v2/student/', '/api/v2/observer/', $c);
$c = str_replace('$stmt->execute([$siswa_id]);', '$stmt->execute([$siswa_id, $observer_id]);', $c);
$c = str_replace('siswa_id = ? AND UPPER(status)', 'siswa_id = ? AND observer_id = ? AND UPPER(status)', $c);
$c = str_replace('WHERE siswa_id = ? AND instrument_id = ? AND UPPER(status)', 'WHERE siswa_id = ? AND observer_id = ? AND instrument_id = ? AND UPPER(status)', $c);
$c = str_replace('$stmt->execute([$siswa_id, $instrument_id]);', '$stmt->execute([$siswa_id, $observer_id, $instrument_id]);', $c);
$c = str_replace("(siswa_id, instrument_id, status) VALUES (?, ?, 'DRAFT')", "(siswa_id, observer_id, respondent_type, instrument_id, status) VALUES (?, ?, 'OBSERVER', ?, 'DRAFT')", $c);
$c = preg_replace("/(\\\$uri === '\/api\/v2\/observer\/session\/active' && \\\$method === 'GET')/", "$1; \$siswa_id = \$_GET['siswa_id'] ?? null; if (!\$siswa_id) { http_response_code(400); echo json_encode(['error'=>'siswa_id required']); exit; }", $c);
$c = preg_replace("/(\\\$uri === '\/api\/v2\/observer\/session' && \\\$method === 'POST')/", "$1; \$siswa_id = \$inputBody['siswa_id'] ?? null; if (!\$siswa_id) { http_response_code(400); echo json_encode(['error'=>'siswa_id required']); exit; }", $c);
file_put_contents('api_v2_observer.php', $c);
echo "OK\n";
?>
