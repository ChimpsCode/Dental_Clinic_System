<?php

function typingStatusDir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'typing';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function typingStatusPath(string $scope, int $id, string $senderType): string
{
    $scope = $scope === 'inquiry' ? 'inquiry' : 'chat';
    $senderType = $senderType === 'staff' ? 'staff' : 'patient';
    return typingStatusDir() . DIRECTORY_SEPARATOR . $scope . '_' . $id . '_' . $senderType . '.json';
}

function setTypingStatus(string $scope, int $id, string $senderType, bool $isTyping, ?int $userId = null): void
{
    if ($id <= 0) {
        return;
    }

    $payload = json_encode([
        'is_typing' => $isTyping ? 1 : 0,
        'updated_at' => time(),
        'user_id' => ($userId !== null && $userId > 0) ? $userId : null,
    ]);
    @file_put_contents(typingStatusPath($scope, $id, $senderType), $payload, LOCK_EX);
}

function getTypingStatus(string $scope, int $id, string $senderType, int $ttlSeconds): array
{
    $empty = ['is_typing' => false, 'user_id' => 0];
    if ($id <= 0) {
        return $empty;
    }

    $path = typingStatusPath($scope, $id, $senderType);
    if (!is_file($path)) {
        return $empty;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $empty;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return $empty;
    }

    $updatedAt = (int)($data['updated_at'] ?? 0);
    $isTyping = ((int)($data['is_typing'] ?? 0) === 1)
        && $updatedAt > 0
        && (time() - $updatedAt) <= $ttlSeconds;

    return [
        'is_typing' => $isTyping,
        'user_id' => (int)($data['user_id'] ?? 0),
    ];
}
