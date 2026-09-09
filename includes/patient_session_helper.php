<?php

function getPatientSessionSettings(PDO $pdo, int $userId): array {
    $defaults = [
        'enabled' => false,
        'timeout_minutes' => 60
    ];

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_session_settings (
            user_id INT NOT NULL PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            timeout_minutes INT NOT NULL DEFAULT 60,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $pdo->prepare('SELECT enabled, timeout_minutes FROM user_session_settings WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$settings) {
            return $defaults;
        }

        return [
            'enabled' => (int)$settings['enabled'] === 1,
            'timeout_minutes' => max(1, min(1440, (int)$settings['timeout_minutes']))
        ];
    } catch (Exception $e) {
        error_log('Patient session settings error: ' . $e->getMessage());
        return $defaults;
    }
}

function savePatientSessionSettings(PDO $pdo, int $userId, bool $enabled, int $timeoutMinutes): bool {
    $timeoutMinutes = max(1, min(1440, $timeoutMinutes));
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_session_settings (
        user_id INT NOT NULL PRIMARY KEY,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        timeout_minutes INT NOT NULL DEFAULT 60,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare('INSERT INTO user_session_settings (user_id, enabled, timeout_minutes) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), timeout_minutes = VALUES(timeout_minutes)');
    return $stmt->execute([$userId, $enabled ? 1 : 0, $timeoutMinutes]);
}
