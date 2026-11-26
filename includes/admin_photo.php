<?php
/**
 * Profile photo for the chairman / administrator (admins.photo). Self-contained on purpose so the admin layout can
 * load it on every page. Same rules as the instructor photo: JPG/PNG/WEBP, max 2 MB, re-encoded as a 400 px square
 * JPEG when PHP GD is on, stored under a random name in uploads/admin/ (uploads/.htaccess blocks scripts there).
 */

if (!function_exists('ensure_admin_photo_column')) {
    /** Adds admins.photo the first time it is needed. */
    function ensure_admin_photo_column(mysqli $c): void {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            if ($c->query("SHOW COLUMNS FROM admins LIKE 'photo'")->num_rows === 0) {
                $c->query("ALTER TABLE admins ADD COLUMN photo varchar(120) DEFAULT NULL");
            }
        } catch (Throwable $e) { /* the page simply shows the initial */ }
    }
}

if (!function_exists('admin_photo_dir')) {
    function admin_photo_dir(): string { return dirname(__DIR__) . '/uploads/admin'; }
}

if (!function_exists('admin_photo_url')) {
    /** URL relative to a page one folder deep (admin/), or '' when there is no photo. */
    function admin_photo_url(?string $file): string {
        $file = basename((string)$file);
        if ($file === '' || !is_file(admin_photo_dir() . '/' . $file)) return '';
        return '../uploads/admin/' . rawurlencode($file) . '?v=' . filemtime(admin_photo_dir() . '/' . $file);
    }
}

if (!function_exists('save_admin_photo')) {
    /** Validates and stores an uploaded photo. Returns the new file name; throws RuntimeException with a user message. */
    function save_admin_photo(array $f, int $adminId): string {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a photo first.');
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || ($f['size'] ?? 0) > 2 * 1024 * 1024) throw new RuntimeException('The photo is too large. Maximum is 2 MB.');
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new RuntimeException('The upload failed. Please try again.');
        $info = @getimagesize($f['tmp_name']);
        $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
        $exts    = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($exts[$info[2]])) throw new RuntimeException('Please upload a JPG, PNG or WEBP image.');
        $dir = admin_photo_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) throw new RuntimeException('Could not create the uploads folder.');
        $base = 'a' . $adminId . '_' . bin2hex(random_bytes(6));

        $canResize = function_exists('imagecreatetruecolor') && function_exists('imagejpeg')
                  && function_exists($loaders[$info[2]]);
        if ($canResize) {
            $src = @($loaders[$info[2]])($f['tmp_name']);
            if ($src) {
                [$w, $h] = [imagesx($src), imagesy($src)];
                $side = min($w, $h);
                $dst = imagecreatetruecolor(400, 400);
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), 400, 400, $side, $side);
                $name = $base . '.jpg';
                if (imagejpeg($dst, $dir . '/' . $name, 88)) return $name;
            }
        }
        // GD not available: the file was verified as a real image by getimagesize(), keep it under a random name
        $name = $base . '.' . $exts[$info[2]];
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Could not save the photo.');
        return $name;
    }
}

if (!function_exists('delete_admin_photo_file')) {
    function delete_admin_photo_file(?string $file): void {
        $file = basename((string)$file);
        if ($file !== '' && is_file(admin_photo_dir() . '/' . $file)) @unlink(admin_photo_dir() . '/' . $file);
    }
}
