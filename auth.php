<?php
session_start();

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';

// Establish database connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize input
    $inputUsername = isset($_POST['username']) ? trim($_POST['username']) : '';
    $inputGamecode = isset($_POST['gamecode']) ? trim($_POST['gamecode']) : '';

    // Basic input validation
    if (empty($inputUsername) || empty($inputGamecode)) {
        die(json_encode(['success' => false, 'message' => 'Username and gamecode are required.']));
    }

    // Prepare the SQL statement to fetch the user
    $stmt = $conn->prepare("SELECT id, username, dispname FROM users WHERE username = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $inputUsername); // Bind the username parameter
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // Fetch the user data
            $user = $result->fetch_assoc();

            $_SESSION['userid'] = $user['id'];
	    $_SESSION['dispname'] = $user['dispname'];
	    $_SESSION['game'] = $inputGamecode;

	    // join game
	    $stmt2 = $conn->prepare("UPDATE users set game=? where id=".$user['id'].";");
            if (!$stmt) {
                $stmt->close();
                die(json_encode(['success' => false, 'message' => 'stmt2 failed to prepare.']));
            }
	    $stmt2->bind_param("d", $inputGamecode);
	    $stmt2->execute();

            echo json_encode(['success' => true, 'redirect' => 'play.html']);
        } else {
            // User not found
            echo json_encode(['success' => false, 'message' => 'Invalid username.']);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare the statement.']);
    }
} else {
    // Invalid request method
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

// Close the database connection
$conn->close();
?>
