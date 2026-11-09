<?php
header('Content-Type: application/json; charset=utf-8');

$eventsDir = __DIR__ . '/events';
if (!is_dir($eventsDir)) {
    echo json_encode(['success' => true, 'data' => ['list' => []]]);
    exit;
}

$eventDirectories = scandir($eventsDir);
if ($eventDirectories === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not read the events folder.']);
    exit;
}

$extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$events = [];

foreach ($eventDirectories as $eventDirectory) {
    if ($eventDirectory === '.' || $eventDirectory === '..') {
        continue;
    }

    $eventPath = $eventsDir . DIRECTORY_SEPARATOR . $eventDirectory;
    if (is_link($eventPath) || !is_dir($eventPath)) {
        continue;
    }

    $files = scandir($eventPath);
    if ($files === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not read an event folder.']);
        exit;
    }

    $photos = [];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $filePath = $eventPath . DIRECTORY_SEPARATOR . $file;
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (is_link($filePath) || !is_file($filePath) || !in_array($extension, $extensions, true)) {
            continue;
        }

        $caption = pathinfo($file, PATHINFO_FILENAME);
        $caption = preg_replace('/[-_]+/', ' ', $caption);
        $caption = preg_replace('/\s*\d{4}[-_]\d{2}[-_]\d{2}\s*/', ' ', $caption);
        $caption = trim(ucwords(strtolower($caption)));
        $eventUrl = './events/' . rawurlencode($eventDirectory) . '/' . rawurlencode($file);

        $photos[] = [
            'caption' => $caption,
            'thumb' => $eventUrl,
            'full' => $eventUrl,
        ];
    }

    usort($photos, fn($a, $b) => strnatcasecmp($a['full'], $b['full']));
    if (!$photos) {
        continue;
    }

    $name = preg_replace('/[-_]+/', ' ', $eventDirectory);
    $events[] = [
        'name' => trim(ucwords(strtolower($name))),
        'photos' => $photos,
    ];
}

usort($events, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
echo json_encode(['success' => true, 'data' => ['list' => $events]], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
