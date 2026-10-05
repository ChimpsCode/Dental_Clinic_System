<?php
// Run each endpoint in a separate process because production handlers exit after responding:
// php tests/account_registration_endpoint_test.php patient_register phone
// php tests/account_registration_endpoint_test.php process_registration email
// Uses SQLite memory only; no live records or messages are created.
if (PHP_SAPI !== 'cli') exit('CLI only');
$endpoint = $argv[1] ?? 'patient_register';
$method = $argv[2] ?? 'phone';
$scenario = $argv[3] ?? 'success';
if (!in_array($endpoint, ['patient_register', 'process_registration', 'login'], true)
    || !in_array($method, ['email', 'phone'], true)
    || !in_array($scenario, ['success', 'duplicate', 'expired', 'wrong', 'reuse'], true)) exit(1);
class RegistrationFixturePDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (strpos($query, 'information_schema.columns') !== false) {
            $query = 'SELECT 1 FROM fixture_columns WHERE table_name = ? AND column_name = ? LIMIT 1';
        }
        $query = str_replace('DATE_SUB(NOW(), INTERVAL 5 SECOND)', "datetime('now', '-5 seconds')", $query);
        return parent::prepare($query, $options);
    }
}
$pdo = new RegistrationFixturePDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE fixture_columns (table_name TEXT, column_name TEXT)');
$pdo->exec('CREATE TABLE settings (setting_key TEXT, setting_value TEXT)');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, password TEXT, email TEXT, phone TEXT, role TEXT, patient_id INTEGER, first_name TEXT, middle_name TEXT, last_name TEXT, gender TEXT, date_of_birth TEXT, is_archived INTEGER DEFAULT 0)');
$pdo->exec('CREATE TABLE patients (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT, middle_name TEXT, last_name TEXT, phone TEXT, email TEXT, gender TEXT, date_of_birth TEXT, street_address TEXT, emergency_contact_name TEXT, emergency_contact_phone TEXT, emergency_contact_relationship TEXT, senior_citizen_id TEXT, senior_id_type TEXT, pwd_id TEXT, is_archived INTEGER DEFAULT 0, in_patient_log INTEGER DEFAULT 0)');
foreach (['users', 'patients'] as $table) {
    foreach ($pdo->query("PRAGMA table_info($table)")->fetchAll() as $column) {
        $pdo->prepare('INSERT INTO fixture_columns VALUES (?, ?)')->execute([$table, $column['name']]);
    }
}
define('TEXTBEE_API_KEY', '');
define('TEXTBEE_DEVICE_ID', '');
ini_set('session.use_cookies', '0');
session_set_save_handler(fn() => true, fn() => true, fn() => '', fn() => true, fn() => true, fn() => 0);
session_start();
$_SESSION = [];
$data = [
    'first_name' => 'Sample', 'middle_name' => '', 'last_name' => 'Patient',
    'contact_method' => $method, 'email' => $method === 'email' ? 'sample@example.com' : '',
    'phone' => '09123456789', 'gender' => 'female', 'date_of_birth' => '1995-01-01',
    'address' => '', 'emergency_contact_name' => 'Sample Guardian',
    'emergency_contact_phone' => '09987654321', 'emergency_contact_relationship' => 'Parent',
    'password' => 'SamplePass123!',
];
if ($endpoint === 'process_registration') {
    $data += ['senior_citizen_id' => null, 'senior_id_type' => null, 'pwd_id' => null];
}
$_SESSION['reg_data'] = $data;
$_SESSION['reg_otp'] = '123456';
$_SESSION['reg_otp_expires'] = time() + ($scenario === 'expired' ? -1 : 180);
$_SESSION['reg_otp_attempts'] = 0;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['action' => 'verify_otp', 'otp' => $scenario === 'wrong' ? '654321' : '123456'];
if ($scenario === 'reuse') {
    $_POST = $data + ['confirm_password' => $data['password'], 'action' => 'register'];
    $_SESSION['reg_otp_sent_at'] = time();
}
if ($scenario === 'duplicate') {
    $pdo->prepare('INSERT INTO users (username, email, phone) VALUES (?, ?, ?)')
        ->execute([$method === 'email' ? $data['email'] : $data['phone'], $data['email'] ?: null, $data['phone']]);
}
if ($endpoint === 'login') {
    $pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY, username TEXT, user_id INTEGER, action_type TEXT, created_at TEXT)');
    $pdo->prepare("INSERT INTO audit_logs VALUES (1, ?, 1, 'login', datetime('now'))")
        ->execute([$method === 'phone' ? $data['phone'] : $data['email']]);
    $pdo->prepare('INSERT INTO patients (first_name, last_name, phone, email) VALUES (?, ?, ?, ?)')
        ->execute([$data['first_name'], $data['last_name'], $data['phone'], $data['email'] ?: null]);
    $pdo->prepare('INSERT INTO users (username, password, role, patient_id, email, phone, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$method === 'phone' ? $data['phone'] : $data['email'], password_hash($data['password'], PASSWORD_DEFAULT), 'Patient', 1, $data['email'] ?: null, $data['phone'], $data['first_name'], $data['last_name']]);
    $_SESSION = ['captcha_code' => '123456', 'captcha_time' => time()];
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_POST = ['username' => $method === 'phone' ? '+639123456789' : $data['email'], 'password' => $data['password'], 'captcha' => '123456'];
}
ob_start();
register_shutdown_function(function () use ($pdo, $endpoint, $method, $scenario, $data) {
    $response = json_decode(ob_get_clean(), true);
    $users = $pdo->query('SELECT * FROM users')->fetchAll();
    $patients = $pdo->query('SELECT * FROM patients')->fetchAll();
    $valid = is_array($response) && ($response['success'] ?? false) === in_array($scenario, ['success', 'reuse'], true);
    if ($scenario === 'success') {
        $valid = $valid && count($users) === 1 && count($patients) === 1
            && $users[0]['username'] === ($method === 'phone' ? $data['phone'] : $data['email'])
            && ($users[0]['email'] ?? '') === $data['email']
            && (int)$users[0]['patient_id'] === (int)$patients[0]['id']
            && password_verify($data['password'], $users[0]['password']);
    } else {
        $valid = $valid && count($patients) === 0 && count($users) === ($scenario === 'duplicate' ? 1 : 0);
    }
    if ($scenario === 'reuse') {
        $valid = $valid && ($response['destination'] ?? '') === ($method === 'phone' ? $data['phone'] : $data['email']);
    }
    if ($endpoint === 'login') {
        $valid = $valid && (int)($_SESSION['patient_id'] ?? 0) === 1 && (int)($_SESSION['user_id'] ?? 0) === 1;
    }
    session_destroy();
    echo ($valid ? 'PASS' : 'FAIL') . " $endpoint / $method / $scenario\n";
    if (!$valid) echo json_encode($response) . "\n";
    exit($valid ? 0 : 1);
});
chdir(__DIR__ . '/../master/' . ($endpoint === 'login' ? 'welcome-page' : 'user-patient'));
require "$endpoint.php";
