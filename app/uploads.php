<?php
/**
 * Image upload validation, processing and storage.
 *
 * With GD (normal PHP hosting, XAMPP) every accepted image is decoded and written
 * out as a brand-new file, which strips EXIF/GPS metadata and any embedded payloads.
 *
 * Without GD (Vercel's PHP runtime has no image extension) the browser resizes and
 * re-encodes photos to JPEG before upload (assets/js/app.js). The server then
 * validates the file and removes every metadata segment in plain PHP before storing it.
 *
 * Files are stored in media/uploads (never web-accessible) or, on serverless hosts, in
 * the media_files table. They are only ever served through media handlers that check
 * authorisation first.
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

/** True when GD can decode and re-encode images on this server. */
function media_can_reencode(): bool
{
    return extension_loaded('gd') && function_exists('imagecreatetruecolor');
}

/**
 * Checks shared by both processing paths.
 * @return array{tmp: string, kind: string, w: int, h: int}
 */
function validate_uploaded_image(array $file, int $maxBytes, int $maxPixels): array
{
    $maxMb = max(1, (int)round($maxBytes / 1048576));
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
    return ['tmp' => $tmp, 'kind' => $allowed[$mime], 'w' => (int)$w, 'h' => (int)$h];
}

/**
 * Validate an uploaded image and load it into GD.
 * @return array{0: GdImage, 1: int, 2: int}
 */
function load_uploaded_image(array $file, int $maxBytes, int $maxPixels = 30_000_000): array
{
    ['tmp' => $tmp, 'kind' => $kind] = validate_uploaded_image($file, $maxBytes, $maxPixels);
    @ini_set('memory_limit', '384M');
    $img = match ($kind) {
        'jpeg' => @imagecreatefromjpeg($tmp),
        'png'  => @imagecreatefrompng($tmp),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
    };
    if (!$img) {
        throw new UploadError('could not be decoded — the file may be corrupted');
    }
    $rot = match (jpeg_orientation($tmp, $kind)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
    if ($rot !== 0) {
        $r = imagerotate($img, $rot, 0);
        if ($r) {
            imagedestroy($img);
            $img = $r;
        }
    }
    return [$img, imagesx($img), imagesy($img)];
}

function jpeg_orientation(string $tmp, string $kind): int
{
    if ($kind !== 'jpeg' || !function_exists('exif_read_data')) {
        return 1;
    }
    $o = (int)(@exif_read_data($tmp)['Orientation'] ?? 1);
    return $o >= 1 && $o <= 8 ? $o : 1;
}

/**
 * No-GD path: validate, strip metadata and return the cleaned bytes.
 * @return array{bytes: string, ext: string, w: int, h: int}
 */
function load_uploaded_image_raw(array $file, int $maxBytes, int $maxPixels): array
{
    $v = validate_uploaded_image($file, $maxBytes, $maxPixels);
    $data = (string)file_get_contents($v['tmp']);
    $bytes = match ($v['kind']) {
        'jpeg' => jpeg_strip_metadata($data, jpeg_orientation($v['tmp'], 'jpeg')),
        'png'  => png_strip_metadata($data),
        'webp' => throw new UploadError('could not be processed — please use a JPG or PNG'),
    };
    return ['bytes' => $bytes, 'ext' => $v['kind'] === 'jpeg' ? 'jpg' : 'png', 'w' => $v['w'], 'h' => $v['h']];
}

/**
 * Rebuild a JPEG keeping only the segments needed to display it. Drops EXIF (GPS,
 * camera serials), XMP, IPTC, comments and every other APPn block. If the photo
 * carried an orientation flag, a minimal EXIF block with only that flag is re-added.
 */
function jpeg_strip_metadata(string $d, int $orientation = 1): string
{
    $n = strlen($d);
    if ($n < 4 || substr($d, 0, 2) !== "\xFF\xD8") {
        throw new UploadError('is not a valid JPEG');
    }
    $orientSeg = '';
    if ($orientation > 1) {
        $tiff = 'MM' . "\x00\x2A" . pack('N', 8) . pack('n', 1)
              . pack('nnN', 0x0112, 3, 1) . pack('n', $orientation) . "\x00\x00" . pack('N', 0);
        $payload = "Exif\x00\x00" . $tiff;
        $orientSeg = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;
    }
    $out = "\xFF\xD8";
    $i = 2;
    while ($i + 1 < $n) {
        if ($d[$i] !== "\xFF") {
            throw new UploadError('is not a valid JPEG');
        }
        $m = ord($d[$i + 1]);
        if ($m === 0xFF) { // fill byte
            $i++;
            continue;
        }
        if ($m === 0x01 || ($m >= 0xD0 && $m <= 0xD7)) { // markers without a length
            $out .= substr($d, $i, 2);
            $i += 2;
            continue;
        }
        if ($i + 4 > $n) {
            break;
        }
        $len = unpack('n', substr($d, $i + 2, 2))[1];
        if ($len < 2 || $i + 2 + $len > $n) {
            throw new UploadError('is not a valid JPEG');
        }
        $isApp0 = $m === 0xE0;
        if (!$isApp0 && $orientSeg !== '') {
            $out .= $orientSeg; // after JFIF APP0 (if any), before everything else
            $orientSeg = '';
        }
        if ($m === 0xDA) { // start of scan: image data runs to the final end-of-image marker
            $end = strrpos($d, "\xFF\xD9");
            if ($end === false || $end < $i) {
                throw new UploadError('is not a complete JPEG');
            }
            return $out . substr($d, $i, $end + 2 - $i);
        }
        $isIcc = $m === 0xE2 && substr($d, $i + 4, 12) === "ICC_PROFILE\x00";
        $isAdobe = $m === 0xEE; // colour-transform flag needed to decode CMYK JPEGs correctly
        $isMeta = ($m >= 0xE0 && $m <= 0xEF) || $m === 0xFE;
        if (!$isMeta || $isApp0 || $isIcc || $isAdobe) {
            $out .= substr($d, $i, 2 + $len);
        }
        $i += 2 + $len;
    }
    throw new UploadError('is not a complete JPEG');
}

/** Rebuild a PNG keeping only image and colour chunks (drops text, EXIF, timestamps, animation). */
function png_strip_metadata(string $d): string
{
    $sig = "\x89PNG\r\n\x1a\n";
    if (strlen($d) < 8 || substr($d, 0, 8) !== $sig) {
        throw new UploadError('is not a valid PNG');
    }
    $keep = ['IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'gAMA', 'cHRM', 'sRGB', 'iCCP', 'sBIT', 'pHYs', 'bKGD'];
    $out = $sig;
    $i = 8;
    $n = strlen($d);
    while ($i + 12 <= $n) {
        $len = unpack('N', substr($d, $i, 4))[1];
        $type = substr($d, $i + 4, 4);
        if ($len > $n - $i - 12) {
            break;
        }
        if (in_array($type, $keep, true)) {
            $out .= substr($d, $i, 12 + $len);
        }
        $i += 12 + $len;
        if ($type === 'IEND') {
            return $out;
        }
    }
    throw new UploadError('is not a complete PNG');
}

function media_extension(): string
{
    return function_exists('imagewebp') ? 'webp' : 'jpg';
}

/* ------------------------------------------------------------------ storage */

function media_driver(): string
{
    return config('media_driver', 'files') === 'db' ? 'db' : 'files';
}

/** Store bytes and return the media key (also used as the relative path on disk). */
function media_put(string $prefix, string $ext, string $bytes): string
{
    $sub = bin2hex(random_bytes(1));
    $key = $sub . '/' . $prefix . '-' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (media_driver() === 'db') {
        q('INSERT INTO media_files (mkey, data) VALUES (?, ?)', [$key, $bytes]);
        return $key;
    }
    $dir = uploads_path($sub);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create media directory');
    }
    if (file_put_contents(uploads_path($key), $bytes, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write media file');
    }
    @chmod(uploads_path($key), 0640);
    return $key;
}

/** Encode a GD image and store it. */
function save_media(GdImage $img, string $prefix): string
{
    $ext = media_extension();
    ob_start();
    $ok = $ext === 'webp' ? imagewebp($img, null, 84) : imagejpeg($img, null, 86);
    $bytes = (string)ob_get_clean();
    if (!$ok || $bytes === '') {
        throw new RuntimeException('Cannot encode media file');
    }
    return media_put($prefix, $ext, $bytes);
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

/** @return array{image: string, thumb: string} media keys */
function store_participant_photo(array $file): array
{
    $maxBytes = max(1, setting_int('max_upload_mb', 5)) * 1048576;
    if (!media_can_reencode()) {
        // The browser already resized the photo; thumbnails reuse it (CSS crops with object-fit).
        $raw = load_uploaded_image_raw($file, $maxBytes, 9_000_000);
        if ($raw['w'] > 3000 || $raw['h'] > 3000) {
            throw new UploadError('is too large in pixels (maximum 3000 × 3000)');
        }
        $key = media_put('p', $raw['ext'], $raw['bytes']);
        return ['image' => $key, 'thumb' => $key];
    }
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
    [$minR, $maxR, $minW] = $mobile ? [2.0, 6.0, 320] : [3.0, 12.0, 728];
    $check = static function (int $w, int $h) use ($minR, $maxR, $minW): void {
        $ratio = $w / $h;
        if ($ratio < $minR || $ratio > $maxR) {
            throw new UploadError(sprintf('must have an aspect ratio between %.0f:1 and %.0f:1 (yours is %.1f:1)', $minR, $maxR, $ratio));
        }
        if ($w < $minW) {
            throw new UploadError("must be at least {$minW} pixels wide");
        }
    };
    if (!media_can_reencode()) {
        $raw = load_uploaded_image_raw($file, 2 * 1048576, 12_000_000);
        $check($raw['w'], $raw['h']);
        return media_put('ad', $raw['ext'], $raw['bytes']);
    }
    [$img, $w, $h] = load_uploaded_image($file, 2 * 1048576, 12_000_000);
    try {
        $check($w, $h);
        $out = resize_fit($img, $w, $h, $mobile ? 960 : 1800, 600);
        $path = save_media($out, 'ad');
        imagedestroy($out);
        return $path;
    } finally {
        imagedestroy($img);
    }
}

function media_valid_key(?string $rel): bool
{
    return (bool)($rel && preg_match('#^[0-9a-f]{2}/[a-z]{1,3}-[0-9a-f]{24}\.(webp|jpg|png)$#', $rel));
}

/** Returns a handle for send_media() if the stored file exists, otherwise null. */
function media_locate(?string $rel): ?string
{
    if (!media_valid_key($rel)) {
        return null;
    }
    if (media_driver() === 'db') {
        return q_val('SELECT 1 FROM media_files WHERE mkey = ?', [$rel]) ? 'db:' . $rel : null;
    }
    $p = uploads_path($rel);
    return is_file($p) ? $p : null;
}

function delete_media(?string $rel): void
{
    if (!media_valid_key($rel)) {
        return;
    }
    if (media_driver() === 'db') {
        q('DELETE FROM media_files WHERE mkey = ?', [$rel]);
        return;
    }
    $p = uploads_path($rel);
    if (is_file($p)) {
        @unlink($p);
    }
}

/** Stream a stored media file. $private = true forbids shared caching. */
function send_media(string $handle, bool $private): never
{
    $isDb = str_starts_with($handle, 'db:');
    $bytes = $isDb ? (string)q_val('SELECT data FROM media_files WHERE mkey = ?', [substr($handle, 3)]) : null;
    $type = match (pathinfo($handle, PATHINFO_EXTENSION)) {
        'webp' => 'image/webp',
        'png' => 'image/png',
        default => 'image/jpeg',
    };
    header('Content-Type: ' . $type);
    header('Content-Length: ' . ($isDb ? strlen($bytes) : filesize($handle)));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
    // Short public cache so removed images stop appearing quickly.
    header($private ? 'Cache-Control: private, no-store' : 'Cache-Control: public, max-age=300');
    if ($private) {
        header('X-Robots-Tag: noindex');
    }
    if ($isDb) {
        echo $bytes;
    } else {
        readfile($handle);
    }
    exit;
}
