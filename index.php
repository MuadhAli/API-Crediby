<?php
// Database configuration
$host = 'srv1022.hstgr.io';
$username = 'u180778967_noviagent';
$password = 'Novi@agent1';
$database = 'u180778967_noviagent';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query to fetch all users
$sql = "SELECT * FROM users"; // Adjust table name if different
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users List</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h2>Users List</h2>
    <?php if ($result->num_rows > 0): ?>
        <table>
            <tr>
                <?php
                // Dynamically generate table headers based on column names
                $fields = $result->fetch_fields();
                foreach ($fields as $field) {
                    echo "<th>" . htmlspecialchars($field->name) . "</th>";
                }
                ?>
            </tr>
            <?php
            // Output data of each row
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
                echo "</tr>";
            }
            ?>
        </table>
    <?php else: ?>
        <p>No users found in the database.</p>
    <?php endif; ?>

    <?php
    // Close connection
    $conn->close();
    ?>
</body>
</html>