<?php
/**
 * Lumière — Photo Scanner
 * -----------------------
 * Scans a local folder of images and returns them as JSON.
 * Deploy in the same directory as index.html on Hostinger.
 *
 * FOLDER STRUCTURE:
 *   photos/          → all photos, no filtering
 *   photos/travel/   → photos tagged "travel"
 *   photos/portrait/ → photos tagged "portrait"
 *   (subfolders become filter buttons in the gallery)
 */

// ============================================================
// ▶ CONFIGURATION
// ============================================================
$PHOTOS_DIR = __DIR__ . '/photos_other/mister';
$PHOTOS_URL = './photos_other/mister';
$THUMB_DIR  = __DIR__ . '/thumbs';
$THUMB_URL  = './thumbs';
$THUMB_SIZE = 600; // max thumbnail width/height in pixels
// ============================================================

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

if (!is_dir($PHOTOS_DIR)) {
    echo json_encode(['success' => false, 'error' => 'Photos folder not found. Create a /photos folder and upload images to it.']);
    exit;
}

if (!is_dir($THUMB_DIR)) {
    mkdir($THUMB_DIR, 0755, true);
}

$extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$photos = [];

// Scan root photos/ folder (no tag) and each subfolder (subfolder name = tag)
$scanDirs = ['' => $PHOTOS_DIR];
foreach (scandir($PHOTOS_DIR) as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    if (is_dir($PHOTOS_DIR . '/' . $entry)) {
        $scanDirs[$entry] = $PHOTOS_DIR . '/' . $entry;
    }
}

foreach ($scanDirs as $tag => $dir) {
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions)) continue;

        $fullPath  = $dir . '/' . $file;
        $thumbKey  = $tag ? $tag . '_' . $file : $file;
        $thumbPath = $THUMB_DIR . '/' . $thumbKey;
        $thumbUrl  = $THUMB_URL . '/' . $thumbKey;
        $fullUrl   = $PHOTOS_URL . ($tag ? '/' . $tag : '') . '/' . $file;

        // Generate thumbnail with EXIF orientation correction
        if (!file_exists($thumbPath) && function_exists('imagecreatefromjpeg')) {
            generateThumb($fullPath, $thumbPath, $ext, $THUMB_SIZE);
        }

        $thumb = file_exists($thumbPath) ? $thumbUrl : $fullUrl;

        $caption = pathinfo($file, PATHINFO_FILENAME);
        $caption = preg_replace('/[-_]+/', ' ', $caption);
        $caption = preg_replace('/\s*\d{4}[-_]\d{2}[-_]\d{2}\s*/', ' ', $caption);
        $caption = trim(ucwords(strtolower($caption)));

        $photos[] = [
            'id'      => md5($tag . $file),
            'name'    => $file,
            'caption' => $caption,
            'thumb'   => $thumb,
            'full'    => $fullUrl,
            'tags'    => $tag ? [$tag] : [],
        ];
    }
}

usort($photos, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

echo json_encode(['success' => true, 'data' => ['list' => $photos]], JSON_UNESCAPED_SLASHES);

// ── Thumbnail generator with EXIF orientation ─────────────────
function generateThumb(string $src, string $dest, string $ext, int $maxSize): void {
    try {
        $info = getimagesize($src);
        if (!$info) return;

        [$w, $h] = $info;

        // Read EXIF orientation (JPEG only)
        $orientation = 1;
        if (in_array($ext, ['jpg', 'jpeg']) && function_exists('exif_read_data')) {
            $exif = @exif_read_data($src);
            $orientation = $exif['Orientation'] ?? 1;
        }

        // For orientations that swap width/height, use rotated dimensions for scaling
        $swapped = in_array($orientation, [5, 6, 7, 8]);
        $scaleW  = $swapped ? $h : $w;
        $scaleH  = $swapped ? $w : $h;

        $ratio = min($maxSize / $scaleW, $maxSize / $scaleH, 1.0);
        $newW  = (int)round($scaleW * $ratio);
        $newH  = (int)round($scaleH * $ratio);

        $source = match($ext) {
            'jpg', 'jpeg' => imagecreatefromjpeg($src),
            'png'         => imagecreatefrompng($src),
            'gif'         => imagecreatefromgif($src),
            'webp'        => imagecreatefromwebp($src),
            default       => null,
        };
        if (!$source) return;

        // Apply EXIF rotation/flip before resizing
        $source = applyOrientation($source, $orientation);
        if (!$source) return;

        // Get dimensions of the (possibly rotated) source
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $ratio = min($maxSize / $srcW, $maxSize / $srcH, 1.0);
        $newW  = (int)round($srcW * $ratio);
        $newH  = (int)round($srcH * $ratio);

        $thumb = imagecreatetruecolor($newW, $newH);

        if (in_array($ext, ['png', 'gif'])) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefilledrectangle($thumb, 0, 0, $newW, $newH, $transparent);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

        match($ext) {
            'jpg', 'jpeg' => imagejpeg($thumb, $dest, 85),
            'png'         => imagepng($thumb, $dest, 8),
            'gif'         => imagegif($thumb, $dest),
            'webp'        => imagewebp($thumb, $dest, 85),
            default       => null,
        };

        imagedestroy($source);
        imagedestroy($thumb);
    } catch (Throwable) {
        // Silently fail — full image used as fallback
    }
}

function applyOrientation(GdImage $img, int $orientation): ?GdImage {
    return match($orientation) {
        1 => $img,
        2 => imageflip($img, IMG_FLIP_HORIZONTAL) ? $img : $img,
        3 => imagerotate($img, 180, 0),
        4 => imageflip($img, IMG_FLIP_VERTICAL)   ? $img : $img,
        5 => (function() use ($img) {
                $r = imagerotate($img, -90, 0);
                imageflip($r, IMG_FLIP_HORIZONTAL);
                return $r;
             })(),
        6 => imagerotate($img, -90, 0),
        7 => (function() use ($img) {
                $r = imagerotate($img, 90, 0);
                imageflip($r, IMG_FLIP_HORIZONTAL);
                return $r;
             })(),
        8 => imagerotate($img, 90, 0),
        default => $img,
    };
}