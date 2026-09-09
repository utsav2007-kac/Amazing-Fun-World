<?php
// Simple credentials definition
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin123');
define('JWT_SECRET', 'ThrillQuestAdminSecretKey2026!');

// JWT helper functions
function base64UrlEncode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

function base64UrlDecode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $data .= str_repeat('=', $padlen);
    }
    return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
}

function generateJWT($payload) {
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $base64UrlHeader = base64UrlEncode($header);
    $base64UrlPayload = base64UrlEncode(json_encode($payload));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = base64UrlEncode($signature);
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function verifyJWT($token) {
    if (!$token) return false;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
    
    $signature = base64UrlDecode($base64UrlSignature);
    $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    
    if (!hash_equals($signature, $expectedSignature)) return false;
    
    $payload = json_decode(base64UrlDecode($base64UrlPayload), true);
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false; // Expired
    }
    return $payload;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    setcookie("admin_token", "", time() - 3600, "/");
    header("Location: index.php");
    exit;
}

// Handle login submission
$login_error = '';
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        $jwt = generateJWT([
            'user' => 'admin',
            'exp' => time() + 86400 // 1 day
        ]);
        setcookie("admin_token", $jwt, time() + 86400, "/");
        header("Location: index.php");
        exit;
    } else {
        $login_error = "Invalid username or password.";
    }
}

// Verify token from Cookie
$is_logged_in = false;
if (isset($_COOKIE['admin_token'])) {
    $payload = verifyJWT($_COOKIE['admin_token']);
    if ($payload && isset($payload['user']) && $payload['user'] === ADMIN_USER) {
        $is_logged_in = true;
    }
}

$currentPageName = $_GET['page'] ?? 'dashboard';

// Open database connection if logged in
$dbPath = __DIR__ . '/inquiries.db';
$db = null;

if ($is_logged_in) {
    if (file_exists($dbPath)) {
        try {
            $db = new PDO("sqlite:" . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Handle AJAX Status Update POST
            if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_GET['action']) && $_GET['action'] === 'update_status') {
                header("Content-Type: application/json");
                $rawInput = file_get_contents("php://input");
                $data = json_decode($rawInput, true);
                
                $id = intval($data['id'] ?? 0);
                $status = $data['status'] ?? 'New';
                
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE inquiries SET status = :status WHERE id = :id");
                    $stmt->execute([':status' => $status, ':id' => $id]);
                    echo json_encode(["success" => true]);
                } else {
                    echo json_encode(["success" => false, "message" => "Invalid ID."]);
                }
                exit;
            }

            // Handle AJAX Rates Update POST
            if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_GET['action']) && $_GET['action'] === 'update_rates') {
                header("Content-Type: application/json");
                $rawInput = file_get_contents("php://input");
                $data = json_decode($rawInput, true);
                
                try {
                    $db->beginTransaction();
                    $stmt = $db->prepare("UPDATE rates SET price = :price WHERE key = :key");
                    foreach ($data['rates'] as $key => $price) {
                        $stmt->execute([':price' => intval($price), ':key' => $key]);
                    }
                    $db->commit();
                    echo json_encode(["success" => true]);
                } catch (PDOException $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    echo json_encode(["success" => false, "message" => $e->getMessage()]);
                }
                exit;
            }

        } catch (PDOException $e) {
            $error = $e->getMessage();
        }
    }
}

// Define ensureRatesTable if not already declared
if (!function_exists('ensureRatesTable')) {
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
}

// Fetch inquiries & stats & rates if logged in
$inquiries = [];
$rates = [];
$stats = [
    'total_count'   => 0,
    'total_persons' => 0,
    'total_rooms'   => 0,
    'total_themes'  => 0,
    'total_rides'   => 0,
    'total_combo'   => 0,
    'total_amount'  => 0,
    'new_count'     => 0
];

if ($is_logged_in && $db) {
    try {
        // Ensure rates table and fetch rates
        ensureRatesTable($db);
        $ratesStmt = $db->query("SELECT * FROM rates");
        while ($rRow = $ratesStmt->fetch(PDO::FETCH_ASSOC)) {
            $rates[$rRow['key']] = intval($rRow['price']);
        }

        // Fetch inquiries
        $stmt = $db->query("SELECT * FROM inquiries ORDER BY CASE status WHEN 'New' THEN 1 WHEN 'Connected' THEN 2 WHEN 'Confirm Booked' THEN 3 WHEN 'Cancelled' THEN 4 ELSE 5 END ASC, id DESC");
        $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch stats
        $statsStmt = $db->query("
            SELECT 
                COUNT(*) as total_count,
                SUM(persons) as total_persons,
                SUM(rooms) as total_rooms,
                SUM(themes) as total_themes,
                SUM(rides) as total_rides,
                SUM(combo) as total_combo,
                SUM(total_amount) as total_amount,
                SUM(CASE WHEN status = 'New' OR status IS NULL THEN 1 ELSE 0 END) as new_count
            FROM inquiries
        ");
        $fetchedStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        if ($fetchedStats) {
            $stats['total_count']   = intval($fetchedStats['total_count'] ?? 0);
            $stats['total_persons'] = intval($fetchedStats['total_persons'] ?? 0);
            $stats['total_rooms']   = intval($fetchedStats['total_rooms'] ?? 0);
            $stats['total_themes']  = intval($fetchedStats['total_themes'] ?? 0);
            $stats['total_rides']   = intval($fetchedStats['total_rides'] ?? 0);
            $stats['total_combo']   = intval($fetchedStats['total_combo'] ?? 0);
            $stats['total_amount']  = intval($fetchedStats['total_amount'] ?? 0);
            $stats['new_count']     = intval($fetchedStats['new_count'] ?? 0);
        }
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// Status Badges CSS Helper
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Confirm Booked':
            return 'bg-green-100 text-green-700 border-green-200';
        case 'Connected':
            return 'bg-amber-100 text-amber-700 border-amber-200';
        case 'Cancelled':
            return 'bg-rose-100 text-rose-700 border-rose-200';
        case 'New':
        default:
            return 'bg-blue-100 text-blue-700 border-blue-200';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <title>Admin Panel | ThrillQuest Inquiry Portal</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <!-- Tailwind Config -->
  <script src="../assets/js/tailwind-config.js"></script>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Atma:wght@400;600;700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
  <!-- Shared CSS -->
  <link href="../assets/css/style.css" rel="stylesheet" />
</head>
<body class="bg-surface-container-low text-on-surface font-body-md min-h-screen flex flex-col">

  <?php if (!$is_logged_in): ?>
    <!-- Login Screen -->
    <div class="flex-grow flex items-center justify-center px-gutter py-12">
      <div class="bg-white rounded-[32px] border border-outline-variant/30 shadow-2xl p-8 md:p-12 w-full max-w-md space-y-8 hover-lift">
        <div class="text-center space-y-3">
          <img alt="Logo" class="h-16 w-auto mx-auto object-contain" src="../assets/images/logo.png" />
          <h1 class="font-atma text-3xl font-bold text-on-surface">Admin Dashboard</h1>
          <p class="text-xs text-on-surface-variant uppercase tracking-widest font-bold">Please log in to continue</p>
        </div>

        <?php if (!empty($login_error)): ?>
          <div class="bg-error/10 border border-error/20 text-error px-4 py-3 rounded-xl text-sm font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-base">error</span>
            <?= htmlspecialchars($login_error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-6">
          <input type="hidden" name="login" value="1" />
          <div class="space-y-2">
            <label class="block font-label-md text-on-surface-variant font-bold">Username</label>
            <input name="username" required type="text" class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-surface-soft focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all font-medium" placeholder="admin" />
          </div>
          <div class="space-y-2">
            <label class="block font-label-md text-on-surface-variant font-bold">Password</label>
            <input name="password" required type="password" class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-surface-soft focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all font-medium" placeholder="••••••••" />
          </div>
          <button type="submit" class="w-full py-4 bg-primary text-white font-bold rounded-xl hover:scale-105 active:scale-95 transition-all shadow-lg shadow-primary/20">
            Sign In
          </button>
        </form>
      </div>
    </div>
  <?php else: ?>
    <!-- Dashboard Screen -->
    <!-- Header -->
    <header class="bg-white border-b border-outline-variant/30 py-4 px-gutter shadow-sm sticky top-0 z-40">
      <div class="max-w-container-max mx-auto flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <div class="flex items-center justify-between md:justify-start gap-3">
          <div class="flex items-center gap-3">
            <img alt="Logo" class="h-12 w-auto object-contain" src="../assets/images/logo.png" />
            <div>
              <h1 class="font-headline-md text-lg text-primary font-bold font-atma">ThrillQuest Admin</h1>
              <p class="text-xs text-on-surface-variant">Booking Inquiry Management Panel</p>
            </div>
          </div>
          <!-- Mobile Logout -->
          <a href="?action=logout" class="md:hidden flex items-center justify-center bg-error/10 hover:bg-error/20 text-error w-10 h-10 rounded-xl font-bold transition-all active:scale-95">
            <span class="material-symbols-outlined text-base">logout</span>
          </a>
        </div>
        
        <div class="flex items-center justify-between md:justify-end gap-6 w-full md:w-auto">
          <nav class="flex items-center gap-1 bg-white/40 backdrop-blur-md border border-white/25 shadow-sm p-1 rounded-2xl w-full md:w-auto overflow-x-auto md:bg-surface-container-lowest md:backdrop-blur-none md:border-outline-variant/30 md:shadow-none">
            <a href="?page=dashboard" class="flex-1 md:flex-initial text-center px-4 py-2 rounded-xl text-xs font-bold uppercase transition-all flex items-center justify-center gap-1.5 <?= $currentPageName === 'dashboard' ? 'bg-primary text-white shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' ?>">
              <span class="material-symbols-outlined text-sm">dashboard</span>
              Dashboard
            </a>
            <a href="?page=inquiries" class="flex-1 md:flex-initial text-center px-4 py-2 rounded-xl text-xs font-bold uppercase transition-all flex items-center justify-center gap-1.5 <?= $currentPageName === 'inquiries' ? 'bg-primary text-white shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' ?>">
              <span class="material-symbols-outlined text-sm">receipt_long</span>
              Inquiries
            </a>
            <a href="?page=rates" class="flex-1 md:flex-initial text-center px-4 py-2 rounded-xl text-xs font-bold uppercase transition-all flex items-center justify-center gap-1.5 <?= $currentPageName === 'rates' ? 'bg-primary text-white shadow-sm' : 'text-on-surface-variant hover:bg-surface-container' ?>">
              <span class="material-symbols-outlined text-sm">sell</span>
              Rates
            </a>
          </nav>
          
          <a href="?action=logout" class="hidden md:flex items-center gap-2 bg-error/10 hover:bg-error/20 text-error px-4 py-2.5 rounded-xl font-bold transition-all text-sm active:scale-95">
            <span class="material-symbols-outlined text-sm">logout</span>
            Logout
          </a>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow max-w-container-max w-full mx-auto px-gutter py-8 space-y-8">
      
      <?php if ($currentPageName === 'dashboard'): ?>
        <!-- Dashboard Screen -->
        <!-- Stats Cards -->
        <section class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
          <!-- Total Inquiries -->
          <div class="bg-white p-4 rounded-2xl border border-outline-variant/30 shadow-sm flex items-center gap-3.5 hover-lift">
            <div class="w-11 h-11 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
              <span class="material-symbols-outlined text-xl">receipt_long</span>
            </div>
            <div>
              <span class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider block leading-none">Total Inquiries</span>
              <span class="text-xl font-extrabold text-on-surface font-atma mt-0.5 block leading-none"><?= $stats['total_count'] ?></span>
            </div>
          </div>
          <!-- New Inquiries -->
          <div class="bg-white p-4 rounded-2xl border border-outline-variant/30 shadow-sm flex items-center gap-3.5 hover-lift">
            <div class="w-11 h-11 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 shrink-0">
              <span class="material-symbols-outlined text-xl">fiber_new</span>
            </div>
            <div>
              <span class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider block leading-none">New Inquiries</span>
              <span class="text-xl font-extrabold text-on-surface font-atma mt-0.5 block leading-none"><?= $stats['new_count'] ?></span>
            </div>
          </div>
          <!-- Total Guests -->
          <div class="bg-white p-4 rounded-2xl border border-outline-variant/30 shadow-sm flex items-center gap-3.5 hover-lift">
            <div class="w-11 h-11 rounded-xl bg-secondary-container/10 flex items-center justify-center text-secondary-container shrink-0">
              <span class="material-symbols-outlined text-xl">groups</span>
            </div>
            <div>
              <span class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider block leading-none">Total Persons</span>
              <span class="text-xl font-extrabold text-on-surface font-atma mt-0.5 block leading-none"><?= $stats['total_persons'] ?></span>
            </div>
          </div>
          <!-- Total Rooms -->
          <div class="bg-white p-4 rounded-2xl border border-outline-variant/30 shadow-sm flex items-center gap-3.5 hover-lift">
            <div class="w-11 h-11 rounded-xl bg-orange-100 flex items-center justify-center text-orange-600 shrink-0">
              <span class="material-symbols-outlined text-xl">hotel</span>
            </div>
            <div>
              <span class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider block leading-none">Rooms Booked</span>
              <span class="text-xl font-extrabold text-on-surface font-atma mt-0.5 block leading-none"><?= $stats['total_rooms'] ?></span>
            </div>
          </div>
          <!-- Total Revenue Value -->
          <div class="bg-white p-4 rounded-2xl border border-outline-variant/30 shadow-sm flex items-center gap-3.5 hover-lift">
            <div class="w-11 h-11 rounded-xl bg-green-100 flex items-center justify-center text-green-700 shrink-0">
              <span class="material-symbols-outlined text-xl">payments</span>
            </div>
            <div>
              <span class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider block leading-none">Total Value</span>
              <span class="text-xl font-extrabold text-on-surface font-atma mt-0.5 block leading-none">₹ <?= number_format($stats['total_amount'], 0, '.', ',') ?></span>
            </div>
          </div>
        </section>

        <!-- Dashboard Layout Split -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
          <!-- Recent Inquiries (Left Column) -->
          <div class="lg:col-span-8 bg-white rounded-3xl border border-outline-variant/30 shadow-sm p-6 space-y-6">
            <div class="flex justify-between items-center">
              <div>
                <h3 class="font-atma text-2xl font-bold text-on-surface">Recent Submissions</h3>
                <p class="text-xs text-on-surface-variant mt-0.5">Quick overview of the latest visitor inquiry forms.</p>
              </div>
              <a href="?page=inquiries" class="text-xs font-bold uppercase tracking-wider text-primary hover:underline flex items-center gap-1">
                View All <span class="material-symbols-outlined text-sm">arrow_forward</span>
              </a>
            </div>
            <div class="overflow-x-auto w-full">
              <table class="w-full text-left border-collapse table-fixed">
                <thead>
                  <tr class="bg-surface-container text-on-surface-variant font-label-md text-xs uppercase tracking-wider border-b border-outline-variant/20">
                    <th class="py-3.5 px-4 font-bold w-14 text-center">ID</th>
                    <th class="py-3.5 px-4 font-bold w-auto">Customer</th>
                    <th class="py-3.5 px-4 font-bold w-36">Booking Date</th>
                    <th class="py-3.5 px-4 font-bold w-32 text-center">Status</th>
                    <th class="py-3.5 px-4 font-bold w-28 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/20 font-body-md text-sm">
                  <?php 
                  $recentCount = 0;
                  foreach ($inquiries as $row): 
                    if ($recentCount >= 3) break;
                    $recentCount++;
                  ?>
                    <tr class="hover:bg-surface-container-lowest/50 transition-colors">
                      <td class="py-3.5 px-4 font-bold text-on-surface-variant text-center">#<?= $row['id'] ?></td>
                      <td class="py-3.5 px-4 font-extrabold text-on-surface truncate"><?= htmlspecialchars($row['name']) ?></td>
                      <td class="py-3.5 px-4 font-bold text-primary whitespace-nowrap">
                        <?= date("d M Y", strtotime($row['booking_date'])) ?>
                      </td>
                      <td class="py-3.5 px-4 text-center whitespace-nowrap">
                        <span id="badge-<?= $row['id'] ?>" onclick="cycleStatus(<?= $row['id'] ?>, event)" title="Click to cycle status" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase border cursor-pointer select-none transition-all hover:scale-105 active:scale-95 <?= getStatusBadgeClass($row['status']) ?>">
                          <?= htmlspecialchars($row['status']) ?>
                        </span>
                      </td>
                      <td class="py-3.5 px-4 text-center">
                        <button onclick="openDetails(<?= $row['id'] ?>)" class="bg-primary/10 hover:bg-primary text-primary hover:text-white font-bold p-1.5 rounded-lg transition-all active:scale-95 mx-auto flex items-center justify-center">
                          <span class="material-symbols-outlined text-[16px]">visibility</span>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($recentCount === 0): ?>
                    <tr>
                      <td colspan="5" class="py-8 text-center text-xs italic text-on-surface-variant">No inquiries received yet.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Popular Ticket Types (Right Column) -->
          <div class="lg:col-span-4 bg-white rounded-3xl border border-outline-variant/30 shadow-sm p-6 space-y-6">
            <div>
              <h3 class="font-atma text-2xl font-bold text-on-surface">Bookings Breakdown</h3>
              <p class="text-xs text-on-surface-variant mt-0.5">Total quantities of items requested across all inquiries.</p>
            </div>
            
            <div class="space-y-4">
              <!-- Themes -->
              <div class="space-y-1.5">
                <div class="flex justify-between text-xs font-bold text-on-surface">
                  <span class="flex items-center gap-1">🎟️ Themes Tickets</span>
                  <span><?= $stats['total_themes'] ?></span>
                </div>
                <div class="w-full bg-surface-container rounded-full h-2">
                  <?php 
                  $maxVal = max($stats['total_themes'], $stats['total_rides'], $stats['total_combo'], $stats['total_rooms'], 1);
                  $pctThemes = ($stats['total_themes'] / $maxVal) * 100;
                  ?>
                  <div class="bg-secondary-container h-2 rounded-full transition-all duration-500" style="width: <?= $pctThemes ?>%"></div>
                </div>
              </div>
              <!-- Rides -->
              <div class="space-y-1.5">
                <div class="flex justify-between text-xs font-bold text-on-surface">
                  <span class="flex items-center gap-1">🎢 Rides Tickets</span>
                  <span><?= $stats['total_rides'] ?></span>
                </div>
                <div class="w-full bg-surface-container rounded-full h-2">
                  <?php $pctRides = ($stats['total_rides'] / $maxVal) * 100; ?>
                  <div class="bg-primary h-2 rounded-full transition-all duration-500" style="width: <?= $pctRides ?>%"></div>
                </div>
              </div>
              <!-- Combo -->
              <div class="space-y-1.5">
                <div class="flex justify-between text-xs font-bold text-on-surface">
                  <span class="flex items-center gap-1">🍿 Combo Bookings</span>
                  <span><?= $stats['total_combo'] ?></span>
                </div>
                <div class="w-full bg-surface-container rounded-full h-2">
                  <?php $pctCombo = ($stats['total_combo'] / $maxVal) * 100; ?>
                  <div class="bg-green-600 h-2 rounded-full transition-all duration-500" style="width: <?= $pctCombo ?>%"></div>
                </div>
              </div>
              <!-- Rooms -->
              <div class="space-y-1.5">
                <div class="flex justify-between text-xs font-bold text-on-surface">
                  <span class="flex items-center gap-1">🛏️ Rooms Booked</span>
                  <span><?= $stats['total_rooms'] ?></span>
                </div>
                <div class="w-full bg-surface-container rounded-full h-2">
                  <?php $pctRooms = ($stats['total_rooms'] / $maxVal) * 100; ?>
                  <div class="bg-orange-500 h-2 rounded-full transition-all duration-500" style="width: <?= $pctRooms ?>%"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

      <?php elseif ($currentPageName === 'rates'): ?>
        <!-- Rates Manager Screen -->
        <section class="bg-white rounded-3xl border border-outline-variant/30 shadow-sm max-w-2xl mx-auto overflow-hidden">
          <div class="p-6 border-b border-outline-variant/30 bg-surface-soft/20">
            <h2 class="font-atma text-2xl text-on-surface font-bold">Rates & Pricing Manager</h2>
            <p class="text-sm text-on-surface-variant mt-1">Manage ticket prices and room rates updated in real-time on the booking page.</p>
          </div>
          <form id="rates-form" onsubmit="saveRates(event)" class="p-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <!-- Themes Rate -->
              <div class="space-y-2">
                <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider">🎟️ Themes Ticket Price (₹)</label>
                <input id="rate-themes" type="number" min="0" value="<?= $rates['themes'] ?? 400 ?>" class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-surface-soft focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all font-bold text-on-surface" required />
              </div>
              <!-- Rides Rate -->
              <div class="space-y-2">
                <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider">🎢 Rides Ticket Price (₹)</label>
                <input id="rate-rides" type="number" min="0" value="<?= $rates['rides'] ?? 400 ?>" class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-surface-soft focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all font-bold text-on-surface" required />
              </div>
              <!-- Combo Rate -->
              <div class="space-y-2">
                <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider">🍿 Combo Ticket Price (₹)</label>
                <input id="rate-combo" type="number" min="0" value="<?= $rates['combo'] ?? 600 ?>" class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-surface-soft focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all font-bold text-on-surface" required />
              </div>
              <!-- Rooms Rate -->
              <div class="space-y-2">
                <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider">🛏️ Rooms Nightly Rate (₹)</label>
                <input id="rate-rooms" type="number" min="0" value="<?= $rates['rooms'] ?? 2500 ?>" class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-surface-soft focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all font-bold text-on-surface" required />
              </div>
            </div>
            
            <button type="submit" class="w-full py-4 bg-primary hover:bg-primary/95 text-white font-bold rounded-xl transition-all shadow-lg active:scale-[0.98] flex items-center justify-center gap-2">
              <span class="material-symbols-outlined text-base">save</span>
              Save and Update Rates
            </button>
          </form>
        </section>

      <?php else: ?>
        <!-- Booking Inquiries Screen -->
        <!-- Table Section -->
        <section class="bg-white rounded-3xl border border-outline-variant/30 shadow-sm overflow-hidden flex flex-col">
          <div class="p-6 border-b border-outline-variant/30 flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
              <div>
                <h2 class="font-atma text-2xl text-on-surface font-bold">Submitted Booking Inquiries</h2>
                <p class="text-sm text-on-surface-variant mt-1">Real-time submissions from the ticket and room inquiry page.</p>
              </div>
              <button onclick="window.location.reload();" class="flex items-center gap-2 bg-surface hover:bg-surface-container text-on-surface border border-outline-variant px-4 py-2 rounded-xl transition-all active:scale-95 text-sm font-bold">
                <span class="material-symbols-outlined text-sm">refresh</span>
                Refresh Table
              </button>
            </div>
            <!-- Status Filter Tabs -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-2 w-full">
              <div class="flex flex-wrap gap-2" id="filter-container">
                <button onclick="setFilter('All')" class="filter-btn px-4 py-2 rounded-xl text-xs font-bold uppercase transition-all border-2 border-primary bg-primary text-white" data-filter="All">All</button>
                <button onclick="setFilter('New')" class="filter-btn px-4 py-2 rounded-xl text-xs font-bold uppercase transition-all border-2 border-outline-variant/20 text-on-surface-variant hover:bg-surface-container" data-filter="New">New</button>
                <button onclick="setFilter('Connected')" class="filter-btn px-4 py-2 rounded-xl text-xs font-bold uppercase transition-all border-2 border-outline-variant/20 text-on-surface-variant hover:bg-surface-container" data-filter="Connected">Connected</button>
                <button onclick="setFilter('Confirm Booked')" class="filter-btn px-4 py-2 rounded-xl text-xs font-bold uppercase transition-all border-2 border-outline-variant/20 text-on-surface-variant hover:bg-surface-container" data-filter="Confirm Booked">Confirm Booked</button>
                <button onclick="setFilter('Cancelled')" class="filter-btn px-4 py-2 rounded-xl text-xs font-bold uppercase transition-all border-2 border-outline-variant/20 text-on-surface-variant hover:bg-surface-container" data-filter="Cancelled">Cancelled</button>
              </div>
              <span id="filter-count" class="text-xs text-on-surface-variant font-bold uppercase tracking-wider bg-surface-container px-3 py-2 rounded-xl border border-outline-variant/30 self-start sm:self-center">
                Showing <?= count($inquiries) ?> of <?= count($inquiries) ?> Inquiries
              </span>
            </div>
          </div>

          <div class="overflow-x-auto w-full">
            <?php if (isset($error)): ?>
              <div class="p-8 text-center text-error font-bold">
                <span class="material-symbols-outlined text-4xl block mb-2">error</span>
                Failed to query inquiries: <?= htmlspecialchars($error) ?>
              </div>
            <?php elseif (empty($inquiries)): ?>
              <div class="p-16 text-center text-on-surface-variant">
                <span class="material-symbols-outlined text-6xl block mb-4 text-outline/30">drafts</span>
                <p class="text-lg font-bold">No Inquiries Found</p>
                <p class="text-sm mt-1">Submit inquiries from the booking page to see them list here.</p>
              </div>
            <?php else: ?>
              <table class="w-full text-left border-collapse min-w-[1000px] table-fixed">
                <thead>
                  <tr class="bg-surface-container text-on-surface-variant font-label-md text-xs uppercase tracking-wider border-b border-outline-variant/20">
                    <th class="py-4 px-6 font-bold w-16 text-center">ID</th>
                    <th class="py-4 px-6 font-bold w-auto">Customer Details</th>
                    <th class="py-4 px-6 font-bold w-48">Booking Details</th>
                    <th class="py-4 px-6 font-bold w-80">Requested Items</th>
                    <th class="py-4 px-6 font-bold w-36">Total Amount</th>
                    <th class="py-4 px-6 font-bold w-40 text-center">Status</th>
                    <th class="py-4 px-6 font-bold w-44 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/20 font-body-md text-sm">
                  <?php foreach ($inquiries as $row): ?>
                    <tr class="hover:bg-surface-container-lowest/50 transition-colors">
                      <td class="py-4 px-6 font-bold text-on-surface-variant text-center">#<?= $row['id'] ?></td>
                      <td class="py-4 px-6 space-y-1 truncate">
                        <div class="font-extrabold text-on-surface text-base truncate"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="text-xs text-on-surface-variant font-semibold flex items-center gap-1 truncate">
                          <span class="material-symbols-outlined text-[14px]">mail</span>
                          <?= htmlspecialchars($row['email']) ?>
                        </div>
                        <div class="text-xs text-on-surface-variant font-semibold flex items-center gap-1">
                          <span class="material-symbols-outlined text-[14px]">phone</span>
                          <?= htmlspecialchars($row['contact']) ?>
                        </div>
                      </td>
                      <td class="py-4 px-6 space-y-1 whitespace-nowrap">
                        <div class="font-bold flex items-center gap-1.5 text-primary">
                          <span class="material-symbols-outlined text-sm">calendar_month</span>
                          <?= date("d M Y", strtotime($row['booking_date'])) ?>
                        </div>
                        <div class="text-xs text-on-surface-variant font-bold">
                          👥 <?= $row['persons'] ?> Person(s)
                        </div>
                      </td>
                      <td class="py-4 px-6">
                        <div class="flex flex-wrap gap-1 max-w-[280px]">
                          <?php if ($row['themes'] > 0): ?>
                            <span class="bg-secondary-container/10 text-secondary-container px-2 py-0.5 rounded text-[11px] font-bold uppercase">
                              🎟️ Themes (<?= $row['themes'] ?>)
                            </span>
                          <?php endif; ?>
                          <?php if ($row['rides'] > 0): ?>
                            <span class="bg-primary/10 text-primary px-2 py-0.5 rounded text-[11px] font-bold uppercase">
                              🎢 Rides (<?= $row['rides'] ?>)
                            </span>
                          <?php endif; ?>
                          <?php if ($row['combo'] > 0): ?>
                            <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-[11px] font-bold uppercase">
                              🍿 Combo (<?= $row['combo'] ?>)
                            </span>
                          <?php endif; ?>
                          <?php if ($row['rooms'] > 0): ?>
                            <span class="bg-orange-100 text-orange-700 px-2 py-0.5 rounded text-[11px] font-bold uppercase">
                              🛏️ Rooms (<?= $row['rooms'] ?>)
                            </span>
                          <?php endif; ?>
                          <?php if (intval($row['themes'] + $row['rides'] + $row['combo'] + $row['rooms']) === 0): ?>
                            <span class="text-xs text-on-surface-variant italic">None</span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="py-4 px-6 whitespace-nowrap">
                        <span class="text-green-700 font-extrabold text-base">₹ <?= number_format($row['total_amount'], 0, '.', ',') ?></span>
                      </td>
                      <td class="py-4 px-6 text-center whitespace-nowrap">
                        <span id="badge-<?= $row['id'] ?>" onclick="cycleStatus(<?= $row['id'] ?>, event)" title="Click to cycle status" class="px-3 py-1 rounded-full text-xs font-bold uppercase border cursor-pointer select-none transition-all hover:scale-105 active:scale-95 <?= getStatusBadgeClass($row['status']) ?>">
                          <?= htmlspecialchars($row['status']) ?>
                        </span>
                      </td>
                      <td class="py-4 px-6 text-center">
                        <button onclick="openDetails(<?= $row['id'] ?>)" class="bg-primary hover:bg-primary/90 text-white font-bold px-4 py-2 rounded-xl transition-all active:scale-95 text-xs flex items-center gap-1.5 mx-auto">
                          <span class="material-symbols-outlined text-xs">visibility</span>
                          View Details
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
          <!-- Pagination Controls -->
          <div class="p-4 bg-surface-container-lowest border-t border-outline-variant/20 flex items-center justify-center gap-2" id="pagination-container"></div>
        </section>
      <?php endif; ?>

    </main>

    <!-- Details View Modal -->
    <div id="details-modal" class="hidden fixed inset-0 z-50 bg-black/55 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl md:rounded-[32px] border border-outline-variant/30 shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col animate-float" style="animation-duration: 3s;">
        <!-- Modal Header -->
        <div class="bg-primary text-white p-5 md:p-6 flex justify-between items-center">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-2xl">receipt_long</span>
            <h3 class="font-atma text-xl md:text-2xl font-bold">Inquiry Details <span id="modal-id">#0</span></h3>
          </div>
          <button onclick="closeDetails()" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/35 flex items-center justify-center text-white transition-all">
            <span class="material-symbols-outlined text-lg">close</span>
          </button>
        </div>
        <!-- Modal Body -->
        <div class="p-6 md:p-8 overflow-y-auto max-h-[85vh]">
          <div class="grid grid-cols-1 md:grid-cols-12 gap-6 md:gap-8">
            <!-- Left Panel (Customer info & note) -->
            <div class="md:col-span-7 space-y-6">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 bg-surface-container-low p-5 md:p-6 rounded-2xl border border-outline-variant/10">
                <div>
                  <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider block mb-1">Customer Name</span>
                  <span id="modal-name" class="font-extrabold text-base text-on-surface">-</span>
                </div>
                <div>
                  <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider block mb-1">Booking Date</span>
                  <span id="modal-date" class="font-bold text-primary flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">calendar_month</span> -
                  </span>
                </div>
                <div>
                  <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider block mb-1">Contact Email</span>
                  <span id="modal-email" class="font-semibold text-on-surface text-sm truncate block">-</span>
                </div>
                <div>
                  <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider block mb-1">Contact Number</span>
                  <span id="modal-contact" class="font-semibold text-on-surface text-sm">-</span>
                </div>
                <div>
                  <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider block mb-1">City</span>
                  <span id="modal-city" class="font-semibold text-on-surface text-sm">-</span>
                </div>
                <div>
                  <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider block mb-1">Total Persons</span>
                  <span id="modal-persons" class="font-semibold text-on-surface text-sm">-</span>
                </div>
              </div>

              <!-- Message -->
              <div>
                <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider block mb-1.5">Customer Note / Message</span>
                <div id="modal-message" class="bg-surface-soft p-4 rounded-xl border border-outline-variant/35 text-on-surface-variant text-sm font-medium whitespace-pre-wrap min-h-[80px] max-h-[140px] overflow-y-auto">
                  -
                </div>
              </div>
            </div>

            <!-- Right Panel (Requested items & status actions) -->
            <div class="md:col-span-5 flex flex-col justify-between space-y-6 pt-6 border-t border-outline-variant/20 md:border-t-0 md:pt-0 md:border-l md:border-outline-variant/20 md:pl-8">
              <!-- Items Ordered -->
              <div class="space-y-3">
                <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider block">Requested Items</span>
                <div id="modal-items-container" class="grid grid-cols-2 gap-3">
                  <!-- Cards dynamically inserted here -->
                </div>
              </div>

              <!-- Total & Status Manager -->
              <div class="space-y-6 pt-6 border-t border-outline-variant/20">
                <div>
                  <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider block mb-1">Total Pricing</span>
                  <span id="modal-total" class="text-3xl font-extrabold text-green-700 block">-</span>
                </div>
                <div class="flex flex-col gap-2">
                  <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider block">Update Status</span>
                  <div class="grid grid-cols-2 gap-2" id="status-pills">
                    <button onclick="setStatus('New')" class="status-btn flex items-center justify-center gap-1 px-3 py-2 border-2 rounded-xl text-xs font-bold uppercase transition-all duration-200 active:scale-95" data-status="New">
                      <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> New
                    </button>
                    <button onclick="setStatus('Connected')" class="status-btn flex items-center justify-center gap-1 px-3 py-2 border-2 rounded-xl text-xs font-bold uppercase transition-all duration-200 active:scale-95" data-status="Connected">
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Connect
                    </button>
                    <button onclick="setStatus('Confirm Booked')" class="status-btn flex items-center justify-center gap-1 px-3 py-2 border-2 rounded-xl text-xs font-bold uppercase transition-all duration-200 active:scale-95" data-status="Confirm Booked">
                      <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Confirm
                    </button>
                    <button onclick="setStatus('Cancelled')" class="status-btn flex items-center justify-center gap-1 px-3 py-2 border-2 rounded-xl text-xs font-bold uppercase transition-all duration-200 active:scale-95" data-status="Cancelled">
                      <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Cancel
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
        </div>
      </div>
    </div>

    <script>
      const inquiries = <?php echo json_encode($inquiries); ?>;
      const statusSequence = ["New", "Connected", "Confirm Booked", "Cancelled"];
      let activeInquiryId = null;
      let currentFilter = "All";
      let currentPage = 1;
      let itemsPerPage = 5;

      function openDetails(id) {
        const item = inquiries.find(x => x.id == id);
        if (!item) return;

        activeInquiryId = id;
        document.getElementById("modal-id").innerText = "#" + item.id;
        document.getElementById("modal-name").innerText = item.name;
        document.getElementById("modal-email").innerText = item.email;
        document.getElementById("modal-contact").innerText = item.contact;
        document.getElementById("modal-city").innerText = item.city || "N/A";
        document.getElementById("modal-persons").innerText = item.persons;
        document.getElementById("modal-message").innerText = item.message ? item.message : "No message provided.";
        document.getElementById("modal-date").innerText = item.booking_date;
        document.getElementById("modal-total").innerText = "₹ " + parseInt(item.total_amount).toLocaleString("en-IN");
        
        highlightStatusPill(item.status);

        // Build item blocks
        const container = document.getElementById("modal-items-container");
        container.innerHTML = "";

        const fields = [
          { name: "Themes", qty: parseInt(item.themes), color: "border-secondary-container bg-secondary-container/5 text-secondary-container" },
          { name: "Rides", qty: parseInt(item.rides), color: "border-primary bg-primary/5 text-primary" },
          { name: "Combo", qty: parseInt(item.combo), color: "border-green-600 bg-green-50 text-green-700" },
          { name: "Rooms", qty: parseInt(item.rooms), color: "border-orange-500 bg-orange-50 text-orange-700" }
        ];

        fields.forEach(f => {
          if (f.qty > 0) {
            container.innerHTML += `
              <div class="border-2 ${f.color} p-3.5 rounded-xl text-center">
                <span class="text-xs uppercase tracking-wider font-extrabold block">${f.name}</span>
                <span class="text-xl font-extrabold block mt-0.5">${f.qty}</span>
              </div>
            `;
          }
        });

        if (container.innerHTML === "") {
          container.innerHTML = `<span class="col-span-4 text-sm italic text-on-surface-variant">No items selected</span>`;
        }

        document.getElementById("details-modal").classList.remove("hidden");
      }

      function closeDetails() {
        document.getElementById("details-modal").classList.add("hidden");
        activeInquiryId = null;
      }

      function highlightStatusPill(status) {
        document.querySelectorAll(".status-btn").forEach(btn => {
          const btnStatus = btn.getAttribute("data-status");
          btn.className = "status-btn flex items-center justify-center gap-1.5 px-3 py-2 border-2 rounded-xl text-xs font-bold uppercase transition-all duration-200 active:scale-95";
          
          if (btnStatus === status) {
            if (status === "New") {
              btn.classList.add("border-blue-500", "bg-blue-50", "text-blue-700", "shadow-sm", "scale-105");
            } else if (status === "Connected") {
              btn.classList.add("border-amber-500", "bg-amber-50", "text-amber-700", "shadow-sm", "scale-105");
            } else if (status === "Confirm Booked") {
              btn.classList.add("border-green-500", "bg-green-50", "text-green-700", "shadow-sm", "scale-105");
            } else if (status === "Cancelled") {
              btn.classList.add("border-rose-500", "bg-rose-50", "text-rose-700", "shadow-sm", "scale-105");
            }
          } else {
            btn.classList.add("border-outline-variant/20", "text-on-surface-variant", "hover:bg-surface-container");
          }
        });
      }

      function setStatus(newStatus) {
        if (!activeInquiryId) return;

        fetch("index.php?action=update_status", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id: activeInquiryId, status: newStatus })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            // Update modal buttons
            highlightStatusPill(newStatus);

            // Update table badge class dynamically
            updateTableBadge(activeInquiryId, newStatus);

            // Sync original client-side array
            const idx = inquiries.findIndex(x => x.id == activeInquiryId);
            if (idx !== -1) inquiries[idx].status = newStatus;

            // Re-apply filter
            applyFilter(currentFilter);
          } else {
            alert("Error updating status: " + data.message);
          }
        })
        .catch(err => alert("Error: " + err.message));
      }

      function updateTableBadge(id, status) {
        const badge = document.getElementById("badge-" + id);
        if (!badge) return;
        badge.innerText = status;
        badge.className = "px-3 py-1 rounded-full text-xs font-bold uppercase border cursor-pointer select-none transition-all hover:scale-105 active:scale-95";
        if (status === "Confirm Booked") {
          badge.classList.add("bg-green-100", "text-green-700", "border-green-200");
        } else if (status === "Connected") {
          badge.classList.add("bg-amber-100", "text-amber-700", "border-amber-200");
        } else if (status === "Cancelled") {
          badge.classList.add("bg-rose-100", "text-rose-700", "border-rose-200");
        } else {
          badge.classList.add("bg-blue-100", "text-blue-700", "border-blue-200");
        }
      }

      // Cycle status click handler on the table badge directly
      function cycleStatus(id, event) {
        event.stopPropagation(); // Stop row click triggers
        const item = inquiries.find(x => x.id == id);
        if (!item) return;

        const currentIndex = statusSequence.indexOf(item.status);
        const nextIndex = (currentIndex + 1) % statusSequence.length;
        const nextStatus = statusSequence[nextIndex];

        fetch("index.php?action=update_status", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id: id, status: nextStatus })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            item.status = nextStatus;
            updateTableBadge(id, nextStatus);
            applyFilter(currentFilter);
          } else {
            alert("Error cycling status: " + data.message);
          }
        })
        .catch(err => alert("Error: " + err.message));
      }

      // Set and apply client-side status filters
      function setFilter(status) {
        currentFilter = status;
        currentPage = 1; // Reset to page 1 on filter switch
        document.querySelectorAll(".filter-btn").forEach(btn => {
          const btnFilter = btn.getAttribute("data-filter");
          if (btnFilter === status) {
            btn.className = "filter-btn px-4 py-2 rounded-xl text-xs font-bold uppercase transition-all border-2 border-primary bg-primary text-white";
          } else {
            btn.className = "filter-btn px-4 py-2 rounded-xl text-xs font-bold uppercase transition-all border-2 border-outline-variant/20 text-on-surface-variant hover:bg-surface-container";
          }
        });
        applyFilter(status);
      }

      function applyFilter(status) {
        if (!document.getElementById("filter-count")) return;
        let matchCount = 0;
        let totalMatches = 0;

        // First pass: count total matches for pagination calculation
        document.querySelectorAll("tbody tr").forEach(row => {
          const rowId = row.querySelector("td").innerText.replace("#", "").trim();
          const item = inquiries.find(x => x.id == rowId);
          if (item && (status === "All" || item.status === status)) {
            totalMatches++;
          }
        });

        const totalPages = Math.ceil(totalMatches / itemsPerPage) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = currentPage * itemsPerPage;

        // Second pass: hide/show based on index matching
        document.querySelectorAll("tbody tr").forEach(row => {
          const rowId = row.querySelector("td").innerText.replace("#", "").trim();
          const item = inquiries.find(x => x.id == rowId);
          if (!item) return;

          if (status === "All" || item.status === status) {
            if (matchCount >= startIndex && matchCount < endIndex) {
              row.classList.remove("hidden");
            } else {
              row.classList.add("hidden");
            }
            matchCount++;
          } else {
            row.classList.add("hidden");
          }
        });

        // Update badge count
        document.getElementById("filter-count").innerText = `Showing ${matchCount} of ${inquiries.length} Inquiries`;

        // Update pagination bar
        updatePaginationBar(totalMatches, totalPages);
      }

      function updatePaginationBar(totalItems, totalPages) {
        const container = document.getElementById("pagination-container");
        if (!container) return;

        const hasNavigation = totalItems > itemsPerPage;

        let selectHtml = `
          <div class="flex items-center gap-2">
            <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider select-none">Show:</span>
            <select onchange="changeLimit(parseInt(this.value))" class="bg-white border border-outline-variant/30 rounded-xl px-3 py-1.5 text-xs font-bold text-on-surface focus:ring-primary focus:border-primary cursor-pointer">
              <option value="5" ${itemsPerPage === 5 ? 'selected' : ''}>5</option>
              <option value="10" ${itemsPerPage === 10 ? 'selected' : ''}>10</option>
              <option value="25" ${itemsPerPage === 25 ? 'selected' : ''}>25</option>
              <option value="50" ${itemsPerPage === 50 ? 'selected' : ''}>50</option>
              <option value="100" ${itemsPerPage === 100 ? 'selected' : ''}>100</option>
            </select>
          </div>
        `;

        let navHtml = "";
        if (hasNavigation) {
          navHtml = `
            <div class="flex items-center gap-2">
              <button onclick="changePage(1)" ${currentPage === 1 ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-outline-variant/30 text-xs font-bold uppercase transition-all hover:bg-surface-container active:scale-95 disabled:opacity-40 disabled:pointer-events-none flex items-center justify-center bg-white text-on-surface">
                <span class="material-symbols-outlined text-[16px]">first_page</span>
              </button>
              <button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-outline-variant/30 text-xs font-bold uppercase transition-all hover:bg-surface-container active:scale-95 disabled:opacity-40 disabled:pointer-events-none flex items-center justify-center bg-white text-on-surface">
                <span class="material-symbols-outlined text-[16px]">chevron_left</span>
              </button>
              <span class="text-xs font-bold text-on-surface-variant px-3 select-none">Page ${currentPage} of ${totalPages}</span>
              <button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-outline-variant/30 text-xs font-bold uppercase transition-all hover:bg-surface-container active:scale-95 disabled:opacity-40 disabled:pointer-events-none flex items-center justify-center bg-white text-on-surface">
                <span class="material-symbols-outlined text-[16px]">chevron_right</span>
              </button>
              <button onclick="changePage(${totalPages})" ${currentPage === totalPages ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-outline-variant/30 text-xs font-bold uppercase transition-all hover:bg-surface-container active:scale-95 disabled:opacity-40 disabled:pointer-events-none flex items-center justify-center bg-white text-on-surface">
                <span class="material-symbols-outlined text-[16px]">last_page</span>
              </button>
            </div>
          `;
        }

        container.className = "p-4 bg-surface-container-lowest border-t border-outline-variant/20 flex flex-col sm:flex-row items-center justify-between gap-4";
        container.innerHTML = selectHtml + navHtml;
      }

      function changePage(page) {
        currentPage = page;
        applyFilter(currentFilter);
      }

      function changeLimit(limit) {
        itemsPerPage = limit;
        currentPage = 1;
        applyFilter(currentFilter);
      }

      function saveRates(e) {
        e.preventDefault();
        const themes = document.getElementById("rate-themes").value;
        const rides = document.getElementById("rate-rides").value;
        const combo = document.getElementById("rate-combo").value;
        const rooms = document.getElementById("rate-rooms").value;

        fetch("index.php?action=update_rates", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            rates: { themes, rides, combo, rooms }
          })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            alert("Rates updated successfully!");
            window.location.reload();
          } else {
            alert("Error updating rates: " + data.message);
          }
        })
        .catch(err => alert("Error: " + err.message));
      }

      // Close modal on background click
      const detailsModal = document.getElementById("details-modal");
      if (detailsModal) {
        detailsModal.addEventListener("click", (e) => {
          if (e.target === detailsModal) closeDetails();
        });
      }

      // Initialize table and counts on startup if on inquiries page
      if (document.getElementById("pagination-container")) {
        setFilter('All');
      }
    </script>
  <?php endif; ?>

  <!-- Footer -->
  <footer class="bg-inverse-surface py-6 text-center text-xs text-surface-container-lowest font-bold mt-auto border-t border-outline-variant/10">
    <p>© 2024 ThrillQuest Admin Panel. All rights reserved.</p>
  </footer>
</body>
</html>
