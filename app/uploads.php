<?php
/**
 * Image upload validation and re-encoding.
 *
 * Every accepted image is decoded with GD and written out as a brand-new file,
 * which strips EXIF/GPS metadata and any embedded payloads. Files live outside
 * the web root (storage/media) and are only ever served through media handlers
 * that check authorisation first.
 */
declare(strict_types=1);

final class UploadError extends RuntimeException
{
}

/** Normalise $_FILES['x'] with multiple entries into a list of single-file arrays. */
function files_list(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !isset($f['name'])) {
        return [];
    }
    if (!is_array($f['name'])) {
        return [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        $out[$i] = [
            'name' => $name, 'type' => $f['type'][$i] ?? '', 'tmp_name' => $f['tmp_name'][$i] ?? '',
            'error' => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $f['size'][$i] ?? 0,
        ];
    }
    return $out;
}

function upload_error_message(int $code, int $maxMb): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "is larger than {$maxMb} MB",
        UPLOAD_ERR_PARTIAL => 'was only partly uploaded — please try again',
        UPLOAD_ERR_NO_FILE => 'is missing',
        default => 'could not be uploaded (server error ' . $code . ')',
    };
}

/**
 * Validate an uploaded image and load it into GD.
 * @return array{0: GdImage, 1: int, 2: int}
 */
function load_uploaded_image(array $file, int $maxBytes, int $maxPixels = 30_000_000): array
{
    $maxMb = (int)round($maxBytes / 1048576);
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        throw new UploadError(upload_error_message($err, $maxMb));
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp) || (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp))) {
        throw new UploadError('could not be read');
    }
    $size = (int)filesize($tmp);
    if ($size <= 0) {
        throw new UploadError('is empty');
    }
    if ($size > $maxBytes) {
        throw new UploadError("is larger than {$maxMb} MB");
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
    $allowed = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new UploadError('is not a supported format (use JPG, PNG or WebP)');
    }
    $info = @getimagesize($tmp);
    if (!$info || $info[0] < 1 || $info[1] < 1) {
        throw new UploadError('is not a valid image');
    }
    [$w, $h] = $info;
    if ($w * $h > $maxPixels || $w > 12000 || $h > 12000) {
        throw new UploadError('has dimensions that are too large');
    }
    if ($w < 160 || $h < 160) {
        throw new UploadError('is too small (minimum 160 × 160 pixels)');
    }
    @ini_set('memory_limit', '384M');
    $img = match ($allowed[$mime]) {
        'jpeg' => @imagecreatefromjpeg($tmp),
        'png'  => @imagecreatefrompng($tmp),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
    };
    if (!$img) {
        throw new UploadError('could not be decoded — the file may be corrupted');
    }
    if ($allowed[$mime] === 'jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $o = (int)($exif['Orientation'] ?? 1);
        $rot = match ($o) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($rot !== 0) {
            $r = imagerotate($img, $rot, 0);
            if ($r) {
                imagedestroy($img);
                $img = $r;
            }
        }
    }
    return [$img, imagesx($img), imagesy($img)];
}

function media_extension(): string
{
    return function_exists('imagewebp') ? 'webp' : 'jpg';
}

/** Write GD image into storage/media and return the relative path. */
function save_media(GdImage $img, string $prefix): string
{
    $ext = media_extension();
    $name = $prefix . '-' . bin2hex(random_bytes(12)) . '.' . $ext;
    $sub = substr(bin2hex(random_bytes(1)), 0, 2);
    $dir = storage_path('media/' . $sub);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create media directory');
    }
    $path = $dir . '/' . $name;
    $ok = $ext === 'webp' ? imagewebp($img, $path, 84) : imagejpeg($img, $path, 86);
    if (!$ok) {
        throw new RuntimeException('Cannot write media file');
    }
    @chmod($path, 0640);
    return $sub . '/' . $name;
}

function flatten_alpha(GdImage $src, int $w, int $h): GdImage
{
    $dst = imagecreatetruecolor($w, $h);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 12, 7, 39)); // brand navy behind transparent PNGs
    return $dst;
}

/** Fit inside a box (no upscaling). */
function resize_fit(GdImage $src, int $sw, int $sh, int $maxW, int $maxH): GdImage
{
    $scale = min(1, $maxW / $sw, $maxH / $sh);
    $w = max(1, (int)round($sw * $scale));
    $h = max(1, (int)round($sh * $scale));
    $dst = flatten_alpha($src, $w, $h);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
    return $dst;
}

/** Cover-crop to an exact box, centred slightly above middle (faces). */
function resize_cover(GdImage $src, int $sw, int $sh, int $w, int $h): GdImage
{
    $scale = max($w / $sw, $h / $sh);
    $cw = (int)round($w / $scale);
    $ch = (int)round($h / $scale);
    $sx = (int)max(0, ($sw - $cw) / 2);
    $sy = (int)max(0, ($sh - $ch) * 0.35);
    $dst = flatten_alpha($src, $w, $h);
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $w, $h, $cw, $ch);
    return $dst;
}

/** @return array{image: string, thumb: string} relative media paths */
function store_participant_photo(array $file): array
{
    $maxBytes = max(1, setting_int('max_upload_mb', 5)) * 1048576;
    [$img, $w, $h] = load_uploaded_image($file, $maxBytes);
    try {
        $full = resize_fit($img, $w, $h, 1200, 1500);
        $thumb = resize_cover($img, $w, $h, 480, 600);
        $paths = ['image' => save_media($full, 'p'), 'thumb' => save_media($thumb, 't')];
        imagedestroy($full);
        imagedestroy($thumb);
        return $paths;
    } finally {
        imagedestroy($img);
    }
}

/**
 * Ad creative. Desktop banners must be wide (3:1 – 12:1), mobile banners 2:1 – 6:1.
 */
function store_ad_creative(array $file, bool $mobile): string
{
    [$img, $w, $h] = load_uploaded_image($file, 2 * 1048576, 12_000_000);
    try {
        $ratio = $w / $h;
        [$minR, $maxR, $minW] = $mobile ? [2.0, 6.0, 320] : [3.0, 12.0, 728];
        if ($ratio < $minR || $ratio > $maxR) {
            throw new UploadError(sprintf('must have an aspect ratio between %.0f:1 and %.0f:1 (yours is %.1f:1)', $minR, $maxR, $ratio));
        }
        if ($w < $minW) {
            throw new UploadError("must be at least {$minW} pixels wide");
        }
        $out = resize_fit($img, $w, $h, $mobile ? 960 : 1800, 600);
        $path = save_media($out, 'ad');
        imagedestroy($out);
        return $path;
    } finally {
        imagedestroy($img);
    }
}

function media_abs_path(?string $rel): ?string
{
    if (!$rel || !preg_match('#^[0-9a-f]{2}/[a-z]{1,3}-[0-9a-f]{24}\.(webp|jpg)$#', $rel)) {
        return null;
    }
    $p = storage_path('media/' . $rel);
    return is_file($p) ? $p : null;
}

function delete_media(?string $rel): void
{
    $p = media_abs_path($rel);
    if ($p) {
        @unlink($p);
    }
}

/** Stream a stored media file. $private = true forbids shared caching. */
function send_media(string $absPath, bool $private): never
{
    $ext = pathinfo($absPath, PATHINFO_EXTENSION);
    header('Content-Type: ' . ($ext === 'webp' ? 'image/webp' : 'image/jpeg'));
    header('Content-Length: ' . filesize($absPath));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
    // Short public cache so removed images stop appearing quickly.
    header($private ? 'Cache-Control: private, no-store' : 'Cache-Control: public, max-age=300');
    if ($private) {
        header('X-Robots-Tag: noindex');
    }
    readfile($absPath);
    exit;
}
