<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow CORS for testing (restrict in production)



// Load Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database configuration from .env file
$host = $_ENV['DB_HOST'];
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];
$database = $_ENV['DB_NAME'];
$valid_api_key = $_ENV['API_KEY'];


// Check API key
$headers = getallheaders();
$api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : '';
if ($api_key !== $valid_api_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or missing API key']);
    exit;
}

// Create database connection
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Parse request
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';
$request = explode('/', trim($endpoint, '/'));
$method = $_SERVER['REQUEST_METHOD'];

// USERS endpoint
if ($request[0] === 'users') {
    if ($method === 'GET') {
        if (isset($request[1]) && is_numeric($request[1])) {
            $id = intval($request[1]);
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo $result->num_rows > 0 ? json_encode($result->fetch_assoc()) : json_encode(['error' => 'User not found']);
            $stmt->close();
        } else {
            $result = $conn->query("SELECT * FROM users");
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            echo json_encode($users);
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['name'], $input['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
        $stmt->bind_param('ss', $input['name'], $input['email']);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to insert user']);
        }
        $stmt->close();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

// VENDOR endpoint
} elseif ($request[0] === 'vendors') {
    if ($method === 'GET') {
        if (isset($request[1]) && is_numeric($request[1])) {
            $id = intval($request[1]);
            $stmt = $conn->prepare("SELECT * FROM VendorDetails WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo $result->num_rows > 0 ? json_encode($result->fetch_assoc()) : json_encode(['error' => 'Vendor not found']);
            $stmt->close();
        } else {
            $result = $conn->query("SELECT * FROM VendorDetails");
            $vendors = [];
            while ($row = $result->fetch_assoc()) {
                $vendors[] = $row;
            }
            echo json_encode($vendors);
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $required = ['caseNumber', 'invoicefilename', 'case_status', 'priority', 'region_code', 'workFlowStatus', 'vendor_name', 'vendor_address', 'vendor_id'];

        foreach ($required as $field) {
            if (!isset($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing field: $field"]);
                exit;
            }
        }

        $stmt = $conn->prepare("INSERT INTO VendorDetails (caseNumber, invoicefilename, createdOn, updatedOn, `case status`, priority, region_code, workFlowStatus, vendor_name, vendor_address, vendor_id) VALUES (?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            'sssssssss',
            $input['caseNumber'],
            $input['invoicefilename'],
            $input['case_status'],
            $input['priority'],
            $input['region_code'],
            $input['workFlowStatus'],
            $input['vendor_name'],
            $input['vendor_address'],
            $input['vendor_id']
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to insert vendor']);
        }
        $stmt->close();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}

$conn->close();
?>
