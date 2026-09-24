<?php
// Ensure this is included from api.php
if (!isset($db)) {
    http_response_code(403);
    exit;
}

$user = authenticateToken();
if (!isset($user['role']) || $user['role'] !== 'siswa') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Only siswa role allowed']);
    exit;
}

// Canonical Identity (SERVER AUTHORITATIVE)
$siswa_id = $user['reference_id'] ?? $user['id'];
if (!$siswa_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Invalid student identity']);
    exit;
}

// Helper functions for session ownership
function getSession($db, $session_id, $siswa_id) {
    $stmt = $db->prepare("SELECT * FROM wlc_sessions WHERE id = ? AND siswa_id = ?");
    $stmt->execute([$session_id, $siswa_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ROUTING
if ($uri === '/api/v2/student/session/active' && $method === 'GET') {
    $stmt = $db->prepare("SELECT * FROM wlc_sessions WHERE siswa_id = ? AND UPPER(status) = 'DRAFT' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$siswa_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Send instrument methodology and status
    if ($session) {
        $stmt = $db->prepare("SELECT methodology, status FROM wlc_instruments WHERE id = ?");
        $stmt->execute([$session['instrument_id']]);
        $inst = $stmt->fetch(PDO::FETCH_ASSOC);
        $session['methodology'] = $inst['methodology'];
        $session['instrument_status'] = $inst['status'];
    }
    
    echo json_encode(['data' => $session]);
    exit;
}

if ($uri === '/api/v2/student/session' && $method === 'POST') {
    $methodology = $inputBody['methodology'] ?? 'SMP Self-Report';
    
    // Validate Instrument
    $stmt = $db->prepare("SELECT id FROM wlc_instruments WHERE methodology = ? AND status IN ('active', 'provisional') ORDER BY version DESC LIMIT 1");
    $stmt->execute([$methodology]);
    $instrument = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$instrument) {
        http_response_code(400);
        echo json_encode(['error' => 'No active instrument found for methodology']);
        exit;
    }
    
    $instrument_id = $instrument['id'];

    // Check for existing DRAFT session
    $stmt = $db->prepare("SELECT id FROM wlc_sessions WHERE siswa_id = ? AND instrument_id = ? AND UPPER(status) = 'DRAFT' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$siswa_id, $instrument_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo json_encode(['success' => true, 'id' => $existing['id'], 'message' => 'Resumed existing session']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO wlc_sessions (siswa_id, instrument_id, status) VALUES (?, ?, 'DRAFT')");
    $stmt->execute([$siswa_id, $instrument_id]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Created new session']);
    exit;
}

if (preg_match('/^\/api\/v2\/student\/session\/(\d+)\/items$/', $uri, $matches) && $method === 'GET') {
    $session_id = $matches[1];
    $session = getSession($db, $session_id, $siswa_id);
    
    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
        exit;
    }

    // Get items and current responses
    $stmt = $db->prepare("
        SELECT i.id as item_id, c.name as construct_name, i.text, r.skor
        FROM wlc_items i
        JOIN wlc_constructs c ON i.construct_id = c.id
        LEFT JOIN wlc_responses r ON r.item_id = i.id AND r.session_id = ?
        WHERE c.instrument_id = ?
        ORDER BY c.id, i.id
    ");
    $stmt->execute([$session_id, $session['instrument_id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['data' => $items, 'status' => $session['status']]);
    exit;
}

if (preg_match('/^\/api\/v2\/student\/session\/(\d+)\/response$/', $uri, $matches) && ($method === 'PUT' || $method === 'POST')) {
    $session_id = $matches[1];
    $session = getSession($db, $session_id, $siswa_id);
    
    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
        exit;
    }

    if (strtoupper($session['status']) !== 'DRAFT') {
        http_response_code(403);
        echo json_encode(['error' => 'Session is submitted and locked']);
        exit;
    }

    $item_id = $inputBody['itemId'] ?? null;
    $skor = $inputBody['skor'] ?? null;

    if (!$item_id || $skor === null || $skor < 1 || $skor > 4) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid itemId and skor (1-4) required']);
        exit;
    }

    // Validate item belongs to instrument
    $stmt = $db->prepare("SELECT id FROM wlc_items WHERE id = ? AND construct_id IN (SELECT id FROM wlc_constructs WHERE instrument_id = ?)");
    $stmt->execute([$item_id, $session['instrument_id']]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid itemId for this session']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO wlc_responses (session_id, item_id, skor) VALUES (?, ?, ?) 
                          ON CONFLICT(session_id, item_id) DO UPDATE SET skor = excluded.skor");
    $stmt->execute([$session_id, $item_id, $skor]);

    echo json_encode(['success' => true]);
    exit;
}

if (preg_match('/^\/api\/v2\/student\/session\/(\d+)\/submit$/', $uri, $matches) && $method === 'POST') {
    $session_id = $matches[1];
    $session = getSession($db, $session_id, $siswa_id);
    
    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
        exit;
    }

    if (strtoupper($session['status']) !== 'DRAFT') {
        echo json_encode(['success' => true, 'message' => 'Already submitted']); // Idempotent
        exit;
    }

    // Check for missing responses
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM wlc_items WHERE construct_id IN (SELECT id FROM wlc_constructs WHERE instrument_id = ?)");
    $stmt->execute([$session['instrument_id']]);
    $total_items = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM wlc_responses WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $total_responses = $stmt->fetchColumn();

    if ($total_responses < $total_items) {
        http_response_code(400);
        echo json_encode(['error' => 'Semua pertanyaan harus dijawab (' . $total_responses . '/' . $total_items . ')']);
        exit;
    }

    // Calculate scores
    $stmt = $db->prepare("
        SELECT c.id as construct_id, i.is_reverse, r.skor 
        FROM wlc_responses r
        JOIN wlc_items i ON r.item_id = i.id
        JOIN wlc_constructs c ON i.construct_id = c.id
        WHERE r.session_id = ?
    ");
    $stmt->execute([$session_id]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $construct_scores = [];
    foreach ($responses as $r) {
        $c_id = $r['construct_id'];
        if (!isset($construct_scores[$c_id])) {
            $construct_scores[$c_id] = ['sum' => 0, 'count' => 0];
        }
        
        $skor = $r['skor'];
        if ($r['is_reverse'] == 1) {
            $skor = 5 - $skor;
        }
        
        $construct_scores[$c_id]['sum'] += $skor;
        $construct_scores[$c_id]['count'] += 1;
    }

    $db->beginTransaction();
    try {
        $stmtReport = $db->prepare("INSERT INTO wlc_reports (session_id, construct_id, mean_score, category) VALUES (?, ?, ?, ?)");
        foreach ($construct_scores as $c_id => $data) {
            $mean = $data['sum'] / $data['count'];
            $category = 'Foundational';
            if ($mean >= 3.3) {
                $category = 'Prominent';
            } elseif ($mean >= 2.1) {
                $category = 'Developing';
            }
            $stmtReport->execute([$session_id, $c_id, $mean, $category]);
        }

        $stmt = $db->prepare("UPDATE wlc_sessions SET status = 'SUBMITTED', submitted_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$session_id]);
        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate report']);
    }
    exit;
}

if ($uri === '/api/v2/student/report/latest' && $method === 'GET') {
    $stmt = $db->prepare("SELECT id, submitted_at, instrument_id FROM wlc_sessions WHERE siswa_id = ? AND UPPER(status) = 'SUBMITTED' ORDER BY submitted_at DESC LIMIT 1");
    $stmt->execute([$siswa_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'No submitted session found']);
        exit;
    }

    $stmt = $db->prepare("SELECT status FROM wlc_instruments WHERE id = ?");
    $stmt->execute([$session['instrument_id']]);
    $inst_status = $stmt->fetchColumn();

    // Fetch averages per construct from reports
    $stmt = $db->prepare("
        SELECT c.name, r.mean_score as avg_skor, r.category
        FROM wlc_reports r
        JOIN wlc_constructs c ON r.construct_id = c.id
        WHERE r.session_id = ?
        ORDER BY c.id
    ");
    $stmt->execute([$session['id']]);
    $report = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'data' => $report, 
        'submitted_at' => $session['submitted_at'],
        'content_status' => strtoupper($inst_status)
    ]);
    exit;
}

// Fallback
http_response_code(404);
echo json_encode(['error' => 'Student API Endpoint Not Found']);
exit;
