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

// ROLES endpoint
} elseif ($request[0] === 'roles') {
    if ($method === 'GET') {
        if (isset($_GET['email'])) {
            $email = $_GET['email'];
            $stmt = $conn->prepare("SELECT roles FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                echo json_encode(['role' => $row['roles']]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'User not found for provided email']);
            }
            $stmt->close();
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required parameter: email']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

// CASEMASTERASSIGNED endpoint
} elseif (strpos($request[0], 'casemasterassigned=') === 0) {
    if ($method === 'GET') {
        // Extract email from endpoint string
        $email = explode('=', $request[0])[1];

        // Query case_master where Assigned_to matches the email
        $stmt = $conn->prepare("SELECT id, case_number, created_on, duplicate_check, grn_check, order_type, other_keys, po_check, priority, region_code, status, updated_on, userid, vendor_check, work_flow_status, Assigned_to FROM case_master WHERE Assigned_to = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        $cases = [];
        while ($row = $result->fetch_assoc()) {
            $cases[] = $row;
        }

        if (count($cases) > 0) {
            echo json_encode($cases);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No cases found for this email']);
        }

        $stmt->close();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

// CASE_ID endpoint for case_diary lookup
} elseif (strpos($request[0], 'case_id=') === 0) {
    if ($method === 'GET') {
        // Extract case_id from endpoint
        $case_id = intval(explode('=', $request[0])[1]);

        // Query the case_diary table
        $stmt = $conn->prepare("SELECT id, comment, flag_code, flag_level, case_id, approved_by FROM case_diary WHERE case_id = ?");
        $stmt->bind_param('i', $case_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $entries = [];
        while ($row = $result->fetch_assoc()) {
            $entries[] = $row;
        }

        if (count($entries) > 0) {
            echo json_encode($entries);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No records found for the provided case_id']);
        }

        $stmt->close();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

// INVOICEID endpoint for fetching invoice details by case_id
} elseif (strpos($request[0], 'invoiceid=') === 0) {
    if ($method === 'GET') {
        // Extract case_id from endpoint
        $case_id = intval(explode('=', $request[0])[1]);

        // Query the invoice_details table
        $stmt = $conn->prepare("SELECT 
            inv_id, bill_to_party, bill_to_party_address, branch, case_id, company_code,
            gross_amount, total_amount, vendor_name, vendor_address, vendor_bank_acc_no,
            vendor_bank_address, vendor_bank_name, vendor_id, vendor_postal_code, vendor_state
            FROM invoice WHERE inv_id = ?");
        $stmt->bind_param('i', $case_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $invoices = [];
        while ($row = $result->fetch_assoc()) {
            $invoices[] = $row;
        }

        if (count($invoices) > 0) {
            echo json_encode($invoices);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No invoice found for the provided case_id']);
        }

        $stmt->close();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

// INVLINE_ITEM endpoint for fetching line items by inv_id
} elseif (strpos($request[0], 'invline_item=') === 0) {
    if ($method === 'GET') {
        // Extract inv_id from endpoint
        $inv_id = intval(explode('=', $request[0])[1]);

        // Query the table (replace 'invoice_line_items' with actual table name if different)
        $stmt = $conn->prepare("SELECT 
            id, inv_id, description, gross_amount, name, quantity, total_amount, unit_price 
            FROM invoice_line_item WHERE inv_id = ?");
        $stmt->bind_param('i', $inv_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $line_items = [];
        while ($row = $result->fetch_assoc()) {
            $line_items[] = $row;
        }

        if (count($line_items) > 0) {
            echo json_encode($line_items);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No line items found for the provided inv_id']);
        }

        $stmt->close();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

    // ALLCASEMASTER endpoint to fetch all records from case_master table
} elseif ($request[0] === 'allcasemaster') {
    if ($method === 'GET') {
        $query = "SELECT 
            id, case_number, created_on, duplicate_check, grn_check, order_type, other_keys, 
            po_check, priority, region_code, status, updated_on, userid, 
            vendor_check, work_flow_status, Assigned_to 
            FROM case_master";

        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            $cases = [];
            while ($row = $result->fetch_assoc()) {
                $cases[] = $row;
            }
            echo json_encode($cases);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No case master records found']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }


} elseif (strpos($request[0], 'apstudiocases=') === 0) {
    if ($method === 'GET') {
        // Extract case_number from endpoint
        $case_number = explode('=', $request[0])[1];

        // Prepare SQL query
        $stmt = $conn->prepare("
            SELECT 
                cm.id AS case_id,
                cm.case_number,
                cm.created_on,
                cm.duplicate_check,
                cm.grn_check,
                cm.order_type,
                cm.other_keys,
                cm.po_check,
                cm.priority AS cm_priority,
                cm.region_code AS cm_region_code,
                cm.status,
                cm.updated_on,
                cm.userid,
                cm.vendor_check,
                cm.work_flow_status,
                cm.Assigned_to,

                cd.id AS diary_id,
                cd.comment,
                cd.flag_code,
                cd.flag_level,
                cd.case_id AS diary_case_id,
                cd.approved_by,

                vd.id AS vendor_id,
                vd.caseNumber,
                vd.invoicefilename,
                vd.createdOn AS vendor_createdOn,
                vd.updatedOn AS vendor_updatedOn,
                vd.`case status`,
                vd.priority AS vendor_priority,
                vd.region_code AS vendor_region_code,
                vd.workFlowStatus,
                vd.vendor_name,
                vd.vendor_address,
                vd.vendor_id AS vendor_internal_id,

                i.inv_id,
                i.bill_to_party,
                i.bill_to_party_address,
                i.branch,
                i.case_id AS invoice_case_id,
                i.company_code,
                i.gross_amount AS invoice_gross_amount,
                i.total_amount AS invoice_total_amount,
                i.vendor_name AS invoice_vendor_name,
                i.vendor_address,
                i.vendor_bank_acc_no,
                i.vendor_bank_address,
                i.vendor_bank_name,
                i.vendor_id AS invoice_vendor_id,
                i.vendor_postal_code,
                i.vendor_state,

                ili.id AS line_item_id,
                ili.inv_id AS line_item_inv_id,
                ili.description,
                ili.gross_amount AS line_item_gross_amount,
                ili.name,
                ili.quantity,
                ili.total_amount AS line_item_total_amount,
                ili.unit_price

            FROM 
                case_master cm
            LEFT JOIN case_diary cd ON cd.case_id = cm.id
            LEFT JOIN VendorDetails vd ON vd.caseNumber = cm.case_number
            LEFT JOIN invoice i ON i.case_id = cm.id
            LEFT JOIN invoice_line_item ili ON ili.inv_id = i.inv_id

            WHERE cm.case_number = ?
        ");

        $stmt->bind_param('s', $case_number);
        $stmt->execute();
        $result = $stmt->get_result();

        $cases = [];
        while ($row = $result->fetch_assoc()) {
            $cases[] = $row;
        }

        if (count($cases) > 0) {
            echo json_encode($cases);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No case found for the provided case_number']);
        }

        $stmt->close();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }



} elseif ($request[0] === 'update_case') {
    if ($method === 'POST') {
        // Read input JSON body
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate input
        if (!isset($input['case_number'], $input['work_flow_status'], $input['Assigned_to'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields: case_number, work_flow_status, Assigned_to']);
            exit;
        }

        $case_number = $input['case_number'];
        $work_flow_status = $input['work_flow_status'];
        $assigned_to = $input['Assigned_to'];

        // Prepare update query
        $stmt = $conn->prepare("UPDATE case_master 
                                SET work_flow_status = ?, Assigned_to = ?
                                WHERE case_number = ?");
        $stmt->bind_param('sss', $work_flow_status, $assigned_to, $case_number);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => 'Case updated successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'No case found with the provided case_number']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
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
