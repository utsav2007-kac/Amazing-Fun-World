<?php
// Helper to initialize rates table and values
function ensureRatesTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS rates (
            key TEXT PRIMARY KEY,
            price INTEGER NOT NULL
        )
    ");
    $count = $db->query("SELECT COUNT(*) FROM rates")->fetchColumn();
    if ($count == 0) {
        $db->exec("INSERT INTO rates (key, price) VALUES ('themes', 400)");
        $db->exec("INSERT INTO rates (key, price) VALUES ('rides', 400)");
        $db->exec("INSERT INTO rates (key, price) VALUES ('combo', 600)");
        $db->exec("INSERT INTO rates (key, price) VALUES ('rooms', 2500)");
    }
}

// Serve public rates lookup
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['action']) && $_GET['action'] === 'get_rates') {
    header("Content-Type: application/json");
    $dbPath = __DIR__ . '/inquiries.db';
    try {
        $db = new PDO("sqlite:" . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        ensureRatesTable($db);

        $stmt = $db->query("SELECT * FROM rates");
        $rates = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rates[$row['key']] = intval($row['price']);
        }
        echo json_encode(["success" => true, "rates" => $rates]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
    exit;
}

header("Content-Type: application/json");

// Ensure the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

// Read raw JSON input
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

if (!$input) {
    echo json_encode(["success" => false, "message" => "Invalid JSON payload."]);
    exit;
}

// Validate required fields
$required = ["name", "booking_date", "contact", "email", "persons"];
foreach ($required as $field) {
    if (empty($input[$field])) {
        echo json_encode(["success" => false, "message" => "Field '$field' is required."]);
        exit;
    }
}

$dbPath = __DIR__ . '/inquiries.db';

try {
    // Open SQLite database
    $db = new PDO("sqlite:" . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table if not exists
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS inquiries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            booking_date TEXT NOT NULL,
            city TEXT,
            contact TEXT NOT NULL,
            email TEXT NOT NULL,
            persons INTEGER NOT NULL,
            message TEXT,
            themes INTEGER DEFAULT 0,
            rides INTEGER DEFAULT 0,
            combo INTEGER DEFAULT 0,
            rooms INTEGER DEFAULT 0,
            total_amount INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ";
    $db->exec($createTableQuery);

    // Insert statement
    $stmt = $db->prepare("
        INSERT INTO inquiries (name, booking_date, city, contact, email, persons, message, themes, rides, combo, rooms, total_amount)
        VALUES (:name, :booking_date, :city, :contact, :email, :persons, :message, :themes, :rides, :combo, :rooms, :total_amount)
    ");

    $stmt->execute([
        ':name'         => $input['name'],
        ':booking_date' => $input['booking_date'],
        ':city'         => isset($input['city']) ? $input['city'] : '',
        ':contact'      => $input['contact'],
        ':email'        => $input['email'],
        ':persons'      => intval($input['persons']),
        ':message'      => isset($input['message']) ? $input['message'] : '',
        ':themes'       => intval($input['themes'] ?? 0),
        ':rides'        => intval($input['rides'] ?? 0),
        ':combo'        => intval($input['combo'] ?? 0),
        ':rooms'        => intval($input['rooms'] ?? 0),
        ':total_amount' => intval($input['total_amount'] ?? 0)
    ]);

    echo json_encode(["success" => true, "message" => "Inquiry saved successfully."]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
