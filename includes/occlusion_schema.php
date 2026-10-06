<?php
// Keep the original molar checkbox intact so old records are not reclassified.
function ensureOcclusionSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $columns = $pdo->query('SHOW COLUMNS FROM consultations')->fetchAll(PDO::FETCH_COLUMN);
    $additions = [];
    if (!in_array('occlusion_molar_class', $columns, true)) {
        $additions[] = 'ADD COLUMN occlusion_molar_class VARCHAR(20) DEFAULT NULL';
    }
    if (!in_array('occlusion_notes', $columns, true)) {
        $additions[] = 'ADD COLUMN occlusion_notes TEXT DEFAULT NULL';
    }
    if ($additions) {
        $pdo->exec('ALTER TABLE consultations ' . implode(', ', $additions));
    }
    $ready = true;
}
