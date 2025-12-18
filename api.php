<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

function db_connect() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($mysqli->connect_errno) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed', 'detail' => $mysqli->connect_error]);
        exit;
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

$action = $_REQUEST['action'] ?? '';

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

try {
    $mysqli = db_connect();
} catch (Exception $e) {
    json_error('DB connection failed', 500);
}

if ($action === 'get_state') {
    $userid = $_GET['userid'] ?? '';
    // Cards currently in play (state = 2)
    $in_play = [];
    $stmt = $mysqli->prepare("SELECT number, color, userid FROM cards WHERE state = 2");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $in_play[] = $row;
    $stmt->close();

    // Cards in the player's hand (state = 1 and userid matches)
    $in_hand = [];
    if ($userid !== '') {
        $stmt = $mysqli->prepare("SELECT number, color FROM cards WHERE state = 1 AND userid = ?");
        $stmt->bind_param('s', $userid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $in_hand[] = $row;
        $stmt->close();
    }

    // Tricks taken: count of cards with state = 3 and userid matches (assumption)
    $tricks = 0;
    if ($userid !== '') {
        $stmt = $mysqli->prepare("SELECT COUNT(*) as c FROM cards WHERE state = 3 AND userid = ?");
        $stmt->bind_param('s', $userid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $tricks = (int)$row['c'];
        $stmt->close();
    }

    echo json_encode(['in_play' => $in_play, 'in_hand' => $in_hand, 'tricks' => $tricks]);
    exit;
}

if ($action === 'submit_bid') {
    // Required: userid, bid (int); optional: round
    $userid = $_POST['userid'] ?? '';
    $bid = isset($_POST['bid']) ? intval($_POST['bid']) : null;
    $round = isset($_POST['round']) ? intval($_POST['round']) : 1;
    if ($userid === '' || $bid === null) json_error('Missing userid or bid');

    // Ensure bids table exists (safe to run multiple times)
    $create = "CREATE TABLE IF NOT EXISTS bids (
        userid VARCHAR(128) NOT NULL,
        round INT NOT NULL DEFAULT 1,
        bid INT NOT NULL,
        PRIMARY KEY(userid, round)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $mysqli->query($create);

    // Insert or update
    $stmt = $mysqli->prepare("INSERT INTO bids (userid, round, bid) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE bid = VALUES(bid)");
    $stmt->bind_param('sii', $userid, $round, $bid);
    if (!$stmt->execute()) json_error('Failed to store bid: ' . $stmt->error, 500);
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'play_card') {
    // Play a card: POST userid, number, color
    $userid = $_POST['userid'] ?? '';
    $number = $_POST['number'] ?? null;
    $color = $_POST['color'] ?? null;
    if ($userid === '' || $number === null || $color === null) json_error('Missing parameters for play_card');

    // Update the card: set state=2 and userid to the player
    $stmt = $mysqli->prepare("UPDATE cards SET state = 2, userid = ? WHERE number = ? AND color = ? LIMIT 1");
    $stmt->bind_param('sis', $userid, $number, $color);
    if (!$stmt->execute()) json_error('Failed to play card: ' . $stmt->error, 500);
    if ($stmt->affected_rows === 0) {
        // try if card id exists but belongs to another or state different -- still return ok=false
        echo json_encode(['ok' => false, 'message' => 'No card updated (maybe card missing or wrong owner)']);
        exit;
    }
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

json_error('Unknown action', 400);
