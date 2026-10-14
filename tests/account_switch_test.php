<?php
function logAudit(...$args): bool { $GLOBALS['switchTestAudit'] = $args; return true; }
require_once __DIR__ . '/../includes/account_switch_helper.php';
function assertSwitch(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

assertSwitch(switchableClinicRole('Admin') === 'admin', 'Admin role missing.');
assertSwitch(switchableClinicRole('Dentist') === 'dentist', 'Dentist role missing.');
assertSwitch(switchableClinicRole('Staff') === null, 'Staff must be excluded.');
assertSwitch(switchableClinicRole('Patient') === null, 'Patient must be excluded.');
assertSwitch(clinicAccountDashboard('dentist') === '../dentist/dentist_dashboard.php', 'Dentist route incorrect.');

$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, first_name TEXT, last_name TEXT, role TEXT, profile_picture TEXT, status TEXT, is_archived INTEGER)');
$pdo->exec("INSERT INTO users VALUES (8,'admin','Anne','Admin','Admin',NULL,'Active',0),(12,'staff','Sam','Staff','Staff',NULL,'Active',0),(14,'dentist','Jane','Smith','Dentist',NULL,'Active',0),(16,'patient','Pat','One','Patient',NULL,'Active',0),(18,'dentist2','Dan','Dentist','Dentist',NULL,'Active',0)");
$pdo->prepare('INSERT INTO settings VALUES (?, ?)')->execute([CLINIC_SWITCH_SETTING, json_encode(['8' => [12, 14, 16, 18]])]);
assertSwitch(!clinicSwitchAuthorized($pdo, 12, 8), 'Linked staff reached admin.');
assertSwitch(!clinicSwitchAuthorized($pdo, 12, 14), 'Linked staff reached dentist.');
assertSwitch(clinicSwitchAuthorized($pdo, 14, 8), 'Linked dentist cannot reach admin.');
assertSwitch(clinicSwitchAuthorized($pdo, 14, 18), 'Linked dentists are not connected.');
assertSwitch(!clinicSwitchAuthorized($pdo, 14, 12), 'Staff was allowed as a target.');
assertSwitch(!clinicSwitchAuthorized($pdo, 14, 16), 'Patient was allowed in switch list.');
$pdo->exec("UPDATE users SET status = 'Inactive' WHERE id = 18");
assertSwitch(!clinicSwitchAuthorized($pdo, 14, 18), 'Inactive target was allowed.');
$pdo->exec("UPDATE users SET status = 'Active' WHERE id = 18");
$pdo->exec("UPDATE settings SET setting_value = '{\"8\":[18]}'");
assertSwitch(!clinicSwitchAuthorized($pdo, 14, 8), 'Revoked link was still allowed.');
$pdo->exec("UPDATE settings SET setting_value = '{\"8\":[12,14,18]}'");
$pdo->exec("UPDATE users SET status = 'Inactive' WHERE id = 8");
assertSwitch(!clinicSwitchAuthorized($pdo, 14, 18), 'Disabled admin link still granted access.');
$pdo->exec("UPDATE users SET status = 'Active' WHERE id = 8");

$sessionDirectory = sys_get_temp_dir() . '/clinic-switch-test-' . bin2hex(random_bytes(6));
mkdir($sessionDirectory);
session_save_path($sessionDirectory);
session_start();
$_SESSION = ['user_id' => 14, 'username' => 'current', 'role' => 'dentist', 'patient_id' => 9,
    'clinic_switch_pending' => ['id' => 14], 'other_sensitive_state' => 'old'];
$oldSessionId = session_id();
$target = ['id' => 8, 'username' => 'admin', 'role' => 'Admin', 'status' => 'Active',
    'is_archived' => 0, 'first_name' => 'Anne', 'last_name' => 'Admin', 'auth_version' => 5];
$route = completeClinicAccountSwitch($pdo, $target);
assertSwitch($route === '../admin/admin_dashboard.php', 'Wrong destination.');
assertSwitch(session_id() !== $oldSessionId, 'Session ID was not rotated.');
assertSwitch($_SESSION['user_id'] === 8 && $_SESSION['role'] === 'admin' && $_SESSION['auth_version'] === 5, 'Target identity was not installed.');
assertSwitch(!isset($_SESSION['patient_id'], $_SESSION['clinic_switch_pending'], $_SESSION['other_sensitive_state']), 'Previous role state remained.');
assertSwitch(!isset($_SESSION['clinic_switch_accounts']), 'Old session account list remained.');
assertSwitch(($GLOBALS['switchTestAudit'][5] ?? '') === 'users', 'Switch was not audited.');
foreach (['Patient', 'Staff'] as $excludedRole) {
    try { completeClinicAccountSwitch($pdo, ['id' => 15, 'role' => $excludedRole]); throw new RuntimeException("{$excludedRole} switch accepted."); }
    catch (RuntimeException $e) { assertSwitch($_SESSION['user_id'] === 8, 'Rejected target altered the session.'); }
}
$_SESSION['role'] = 'staff';
$beforeStaffAttempt = session_id();
try { completeClinicAccountSwitch($pdo, $target); throw new RuntimeException('Staff source switch accepted.'); }
catch (RuntimeException $e) { assertSwitch($_SESSION['role'] === 'staff' && session_id() === $beforeStaffAttempt, 'Staff source switch altered the session.'); }
session_destroy();
foreach (glob($sessionDirectory . '/sess_*') as $file) unlink($file);
rmdir($sessionDirectory);
echo "Account switch tests passed: admin/dentist links, staff and patient exclusion, revocation, rotated session, cleared role state, and audit event.\n";
