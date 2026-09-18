<?php
require_once __DIR__ . '/db.php';

function get_site_by_slug(string $slug): ?array {
    return db_one('SELECT * FROM sites WHERE slug = ? AND active = 1', [$slug]);
}

function get_site(int $id): ?array {
    return db_one('SELECT * FROM sites WHERE id = ?', [$id]);
}

function get_room(int $roomId, int $siteId): ?array {
    return db_one('SELECT * FROM rooms WHERE id = ? AND site_id = ?', [$roomId, $siteId]);
}

function get_room_facilities(int $roomId): array {
    return db_all(
        'SELECT f.* FROM facilities f
         JOIN room_facilities rf ON rf.facility_id = f.id
         WHERE rf.room_id = ? ORDER BY f.id',
        [$roomId]
    );
}

function get_room_media(int $roomId): array {
    return db_all('SELECT * FROM room_media WHERE room_id = ? ORDER BY is_cover DESC, sort_order ASC, id ASC', [$roomId]);
}

// Clones a room (details, facilities, weekly schedule, and gallery files) so an admin can start
// from an existing room instead of rebuilding one from scratch.
function duplicate_room(int $roomId, int $siteId): ?int {
    $source = get_room($roomId, $siteId);
    if (!$source) return null;

    $baseSlug = $source['slug'] . '-copy';
    $newSlug = $baseSlug;
    $i = 2;
    while (db_one('SELECT id FROM rooms WHERE site_id = ? AND slug = ?', [$siteId, $newSlug])) {
        $newSlug = $baseSlug . '-' . $i++;
    }

    db_run(
        'INSERT INTO rooms (site_id, name, slug, description, price_per_hour, show_price_per_hour, price_per_day,
         price_3h, price_extra_hour, min_hours, capacity, size_sqm, bed_type, house_rules_text, active, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $siteId, $source['name'] . ' (עותק)', $newSlug, $source['description'],
            $source['price_per_hour'], $source['show_price_per_hour'], $source['price_per_day'],
            $source['price_3h'], $source['price_extra_hour'], $source['min_hours'], $source['capacity'],
            $source['size_sqm'], $source['bed_type'], $source['house_rules_text'], 0, $source['sort_order'],
        ]
    );
    $newRoomId = db_insert_id();

    foreach (get_room_facilities($roomId) as $f) {
        db_run('INSERT INTO room_facilities (room_id, facility_id) VALUES (?, ?)', [$newRoomId, $f['id']]);
    }

    foreach (get_room_schedule($roomId) as $day) {
        db_run(
            'INSERT INTO availability_schedule (room_id, weekday, is_closed, open_time, close_time) VALUES (?, ?, ?, ?, ?)',
            [$newRoomId, $day['weekday'], $day['is_closed'], $day['open_time'], $day['close_time']]
        );
    }

    $newDir = __DIR__ . "/../uploads/sites/{$siteId}/rooms/{$newRoomId}";
    ensure_dir($newDir);
    foreach (get_room_media($roomId) as $m) {
        $srcPath = __DIR__ . '/../' . $m['path'];
        if (!is_file($srcPath)) continue;
        $newFilename = uniqid('m_', true) . '.' . pathinfo($m['path'], PATHINFO_EXTENSION);
        if (!copy($srcPath, $newDir . '/' . $newFilename)) continue;
        db_run(
            'INSERT INTO room_media (room_id, type, path, is_cover, sort_order) VALUES (?, ?, ?, ?, ?)',
            [$newRoomId, $m['type'], "uploads/sites/{$siteId}/rooms/{$newRoomId}/{$newFilename}", $m['is_cover'], $m['sort_order']]
        );
    }

    return $newRoomId;
}

function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    return $text ?: ('x' . substr(md5((string) mt_rand()), 0, 6));
}

function ensure_dir(string $path): void {
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

// Downscales an on-disk image in place if it exceeds $maxDim on its longest side.
// Uploaded phone/camera photos can be 6000px+ / several MB, which makes gallery thumbnails
// (rendered at ~80px) take seconds to decode; capping dimensions fixes that without a visible quality loss.
function resize_image_file(string $path, int $maxDim = 1920, int $quality = 82): void {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) return;
    $info = @getimagesize($path);
    if (!$info) return;
    [$width, $height] = $info;
    if ($width <= $maxDim && $height <= $maxDim) return;

    $src = match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($path),
        'png' => @imagecreatefrompng($path),
        'webp' => @imagecreatefromwebp($path),
        default => null,
    };
    if (!$src) return;

    $ratio = min($maxDim / $width, $maxDim / $height);
    $newW = max(1, (int) round($width * $ratio));
    $newH = max(1, (int) round($height * $ratio));
    $dst = imagecreatetruecolor($newW, $newH);
    if ($ext === 'png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

    match ($ext) {
        'jpg', 'jpeg' => imagejpeg($dst, $path, $quality),
        'png' => imagepng($dst, $path, (int) round(9 - ($quality / 100) * 9)),
        'webp' => imagewebp($dst, $path, $quality),
        default => null,
    };
    imagedestroy($src);
    imagedestroy($dst);
}

function upload_room_media(int $siteId, int $roomId, array $file): ?array {
    $allowedImg = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedVid = ['mp4', 'webm', 'mov'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $isImg = in_array($ext, $allowedImg, true);
    $isVid = in_array($ext, $allowedVid, true);
    if (!$isImg && !$isVid) return null;
    if ($isVid && $file['size'] > 60 * 1024 * 1024) return null; // 60MB cap

    $dir = __DIR__ . "/../uploads/sites/{$siteId}/rooms/{$roomId}";
    ensure_dir($dir);
    $filename = uniqid('m_', true) . '.' . $ext;
    $dest = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    if ($isImg) resize_image_file($dest);

    return [
        'type' => $isVid ? 'video' : 'image',
        'path' => "uploads/sites/{$siteId}/rooms/{$roomId}/{$filename}",
    ];
}

function upload_site_image(int $siteId, array $file, string $prefix): ?string {
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return null;
    $dir = __DIR__ . "/../uploads/sites/{$siteId}";
    ensure_dir($dir);
    $filename = $prefix . '_' . time() . '.' . $ext;
    $dest = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    if ($ext !== 'svg') resize_image_file($dest, 2400);
    return "uploads/sites/{$siteId}/{$filename}";
}

// Palettes and fonts a super-admin can assign to a site. Keep in sync with assets/css/style.css.
function palette_options(): array {
    return [
        ''            => ['label' => 'ספיר (ברירת מחדל)', 'ground' => '#ffffff', 'accent' => '#2f6fa8'],
        'amethyst'    => ['label' => 'אמטיסט',            'ground' => '#faf8fc', 'accent' => '#6d3fc0'],
        'terracotta'  => ['label' => 'טרקוטה',             'ground' => '#f6f1e6', 'accent' => '#b5622f'],
        'emerald'     => ['label' => 'אמרלד',              'ground' => '#f4f7f4', 'accent' => '#1f6b52'],
        'rosewood'    => ['label' => 'בורדו',               'ground' => '#fbf5f6', 'accent' => '#9c3a54'],
        'graphite'    => ['label' => 'אפור בהיר',           'ground' => '#f1f1ef', 'accent' => '#4a4d52'],
        'burgundy'    => ['label' => 'בורגונדי',            'ground' => '#faf7f4', 'accent' => '#8b2942'],
        'midnight'    => ['label' => 'חצות (כהה)',          'ground' => '#0f0f12', 'accent' => '#c9a96e'],
        'ocean'       => ['label' => 'אוקיינוס',             'ground' => '#f2f7f9', 'accent' => '#0f6b7a'],
        'forest'      => ['label' => 'יער',                 'ground' => '#f4f6f1', 'accent' => '#3f6b3a'],
        'lavender'    => ['label' => 'לבנדר',               'ground' => '#f8f6fb', 'accent' => '#6c4cb3'],
        'sunset'      => ['label' => 'שקיעה',               'ground' => '#fdf6ef', 'accent' => '#d2691e'],
    ];
}

function font_options(): array {
    return [
        ''           => 'קלאסי — Frank Ruhl Libre',
        'warm'       => 'חם — David Libre',
        'editorial'  => 'עיתונאי — Suez One',
        'modern'     => 'מודרני — Heebo',
    ];
}

// Trust-strip items for a site's home page, falling back to sensible defaults if the admin hasn't filled them in.
function get_site_trust_items(array $site): array {
    $items = [];
    for ($i = 1; $i <= 3; $i++) {
        $title = trim($site["trust{$i}_title"] ?? '');
        $text = trim($site["trust{$i}_text"] ?? '');
        if ($title !== '' || $text !== '') {
            $items[] = ['title' => $title, 'text' => $text];
        }
    }
    if ($items) return $items;
    return [
        ['title' => 'הפרטים שלכם נשארים בינינו', 'text' => 'שם וטלפון בלבד, בלי שיתוף עם צד שלישי.'],
        ['title' => 'ביטול חינם מראש', 'text' => 'שינוי או ביטול מתבצע בהודעה בוואטסאפ, בלי חיוב.'],
        ['title' => 'כניסה עצמאית, בלי דלפק קבלה', 'text' => 'פרטי הכניסה נשלחים בוואטסאפ קצת לפני השעה שנקבעה.'],
    ];
}

// Weekly schedule for a room: [weekday => ['is_closed'=>, 'open_time'=>, 'close_time'=>]]
function get_room_schedule(int $roomId): array {
    $rows = db_all('SELECT * FROM availability_schedule WHERE room_id = ? ORDER BY weekday', [$roomId]);
    $out = [];
    foreach ($rows as $r) $out[(int) $r['weekday']] = $r;
    return $out;
}

// Minutes of cleaning/reset time required immediately before and after every hourly booking.
// New requests can't be made inside this window even though it's not part of the booking itself.
const CLEANING_BUFFER_MIN = 30;

// Returns booked/blocked time ranges (as [start,end] in minutes-from-midnight) for a room on a date.
// Hourly bookings are padded by CLEANING_BUFFER_MIN on each side; a booking near midnight can spill
// its buffer into the adjacent date, so neighboring days are checked for that too.
function get_occupied_ranges(int $roomId, string $date): array {
    $ranges = [];

    // Pending requests hold the slot for 15 minutes only; after that they're treated as expired
    // (still visible to the admin, but no longer block the calendar).
    $bookings = db_all(
        "SELECT time_start, time_end, date_start, date_end, booking_type FROM bookings
         WHERE room_id = ?
           AND (status = 'approved' OR (status = 'pending' AND created_at > NOW() - INTERVAL 15 MINUTE))
           AND ? BETWEEN date_start AND date_end",
        [$roomId, $date]
    );
    foreach ($bookings as $b) {
        if ($b['booking_type'] === 'daily') {
            $ranges[] = [0, 24 * 60]; // whole day blocked
        } elseif ($b['time_start'] && $b['time_end']) {
            $ranges[] = [
                max(0, time_to_minutes($b['time_start']) - CLEANING_BUFFER_MIN),
                min(24 * 60, time_to_minutes($b['time_end']) + CLEANING_BUFFER_MIN),
            ];
        }
    }

    $blocks = db_all('SELECT start_time, end_time FROM availability_blocks WHERE room_id = ? AND block_date = ?', [$roomId, $date]);
    foreach ($blocks as $b) {
        $ranges[] = [time_to_minutes($b['start_time']), time_to_minutes($b['end_time'])];
    }

    $prevDate = (new DateTime($date))->modify('-1 day')->format('Y-m-d');
    $prevLate = db_all(
        "SELECT time_end FROM bookings
         WHERE room_id = ? AND booking_type = 'hourly' AND date_start = ?
           AND (status = 'approved' OR (status = 'pending' AND created_at > NOW() - INTERVAL 15 MINUTE))
           AND time_end > ?",
        [$roomId, $prevDate, minutes_to_time(24 * 60 - CLEANING_BUFFER_MIN)]
    );
    foreach ($prevLate as $b) {
        $spill = (time_to_minutes($b['time_end']) + CLEANING_BUFFER_MIN) - 24 * 60;
        if ($spill > 0) $ranges[] = [0, $spill];
    }

    $nextDate = (new DateTime($date))->modify('+1 day')->format('Y-m-d');
    $nextEarly = db_all(
        "SELECT time_start FROM bookings
         WHERE room_id = ? AND booking_type = 'hourly' AND date_start = ?
           AND (status = 'approved' OR (status = 'pending' AND created_at > NOW() - INTERVAL 15 MINUTE))
           AND time_start < ?",
        [$roomId, $nextDate, minutes_to_time(CLEANING_BUFFER_MIN)]
    );
    foreach ($nextEarly as $b) {
        $spill = CLEANING_BUFFER_MIN - time_to_minutes($b['time_start']);
        if ($spill > 0) $ranges[] = [24 * 60 - $spill, 24 * 60];
    }

    return $ranges;
}

function time_to_minutes(string $t): int {
    [$h, $m] = array_map('intval', explode(':', $t));
    return $h * 60 + $m;
}

function minutes_to_time(int $mins): string {
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    return sprintf('%02d:%02d', $h, $m);
}

// Generate list of possible hourly start-times for a room+date, marking each available/unavailable.
function get_hourly_slots(int $roomId, string $date, int $slotStepMinutes = 30): array {
    $weekday = (int) date('w', strtotime($date));
    $schedule = get_room_schedule($roomId);
    $day = $schedule[$weekday] ?? null;
    if (!$day || $day['is_closed']) return [];

    $open = time_to_minutes($day['open_time']);
    $close = time_to_minutes($day['close_time']);
    $occupied = get_occupied_ranges($roomId, $date);

    $slots = [];
    for ($t = $open; $t < $close; $t += $slotStepMinutes) {
        $isFree = true;
        foreach ($occupied as [$s, $e]) {
            if ($t < $e && ($t + $slotStepMinutes) > $s) { $isFree = false; break; }
        }
        $slots[] = ['time' => minutes_to_time($t), 'available' => $isFree];
    }
    return $slots;
}

function is_range_free(int $roomId, string $date, string $startTime, string $endTime): bool {
    $occupied = get_occupied_ranges($roomId, $date);
    $s = time_to_minutes($startTime);
    $e = time_to_minutes($endTime);
    foreach ($occupied as [$os, $oe]) {
        if ($s < $oe && $e > $os) return false;
    }
    return true;
}

// A daily booking occupies the room for the whole day, so it conflicts with ANY existing
// booking or block on each date in [checkIn, checkOut). checkOut itself is not occupied.
function is_daily_range_free(int $roomId, string $checkIn, string $checkOut): bool {
    $start = new DateTime($checkIn);
    $end = new DateTime($checkOut);
    if ($end <= $start) return false;
    for ($d = clone $start; $d < $end; $d->modify('+1 day')) {
        if (get_occupied_ranges($roomId, $d->format('Y-m-d'))) return false;
    }
    return true;
}

// Human-readable date/time range for a booking row, used in admin lists.
function format_booking_range(array $booking): string {
    if ($booking['booking_type'] === 'daily') {
        $in = (new DateTime($booking['date_start']))->format('d.m.Y');
        $out = (new DateTime($booking['date_end']))->modify('+1 day')->format('d.m.Y');
        return "{$in} – {$out}";
    }
    $date = (new DateTime($booking['date_start']))->format('d.m.Y');
    return "{$date}, " . substr($booking['time_start'], 0, 5) . '–' . substr($booking['time_end'], 0, 5);
}

function push_admin_notification(int $siteId, ?int $bookingId, string $message): void {
    db_run('INSERT INTO admin_notifications (site_id, booking_id, message) VALUES (?, ?, ?)', [$siteId, $bookingId, $message]);
}

function count_unread_notifications(int $siteId): int {
    $row = db_one('SELECT COUNT(*) AS c FROM admin_notifications WHERE site_id = ? AND is_read = 0', [$siteId]);
    return (int) ($row['c'] ?? 0);
}

function notify_admin_email(array $site, string $subject, string $body): void {
    $to = $site['email'] ?? '';
    if (!$to) return;
    $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>' . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
    @mail($to, $subject, $body, $headers);
}

function whatsapp_link(string $phone, string $message): string {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Eye/eye-off toggle button for a password field. Wrap the <input type="password"> and this
// together in a .pwd-wrap div; assets/js/pwd-toggle.js wires up the click handler.
function pwd_toggle_button(): string {
    return '<button type="button" class="pwd-toggle" aria-label="הצגת סיסמה">'
        . '<svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>'
        . '<svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.24 4.24M9.9 5.1A11 11 0 0 1 12 5c7 0 11 7 11 7a13.5 13.5 0 0 1-3.1 3.9M6.6 6.6A13.6 13.6 0 0 0 1 12s4 7 11 7c1.4 0 2.7-.2 3.9-.6"/></svg>'
        . '</button>';
}

// Inline SVG icons for the built-in facility keys, with a generic dot fallback for custom ones.
function facility_icon_svg(string $fkey): string {
    $icons = [
        'bath' => '<path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-3Z"/><path d="M4 12V7a2 2 0 0 1 2-2"/><path d="M9 5v2"/>',
        'jacuzzi' => '<circle cx="12" cy="12" r="8"/><path d="M9 9c0 1.5 1 1.5 1 3s-1 1.5-1 3M12 9c0 1.5 1 1.5 1 3s-1 1.5-1 3M15 9c0 1.5 1 1.5 1 3s-1 1.5-1 3"/>',
        'parking' => '<path d="M3 16l1.5-5A2 2 0 0 1 6.4 9.5h11.2A2 2 0 0 1 19.5 11L21 16"/><rect x="3" y="16" width="18" height="4" rx="1"/><circle cx="7" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>',
        'terrace' => '<circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M3 12h2M19 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/>',
        'tv' => '<rect x="3" y="5" width="18" height="12" rx="1.5"/><path d="M8 21h8M12 17v4"/>',
        'wifi' => '<path d="M2 8.5c5.5-5 14.5-5 20 0M5.5 12c3.6-3.2 9.4-3.2 13 0M9 15.5c1.7-1.5 4.3-1.5 6 0"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/>',
        'ac' => '<path d="M3 9h18M6 9v11M18 9v11M9 12l3-2 3 2M9 16l3-2 3 2"/>',
        'minibar' => '<path d="M8 3h8l-1 9a3 3 0 0 1-6 0L8 3Z"/><path d="M12 15v6M9 21h6"/>',
        'sound' => '<circle cx="7" cy="17" r="2.5"/><circle cx="17" cy="15" r="2.5"/><path d="M9.5 17V6l10-2v11"/>',
        'breakfast' => '<path d="M4 4h11v7a5.5 5.5 0 0 1-11 0V4Z"/><path d="M15 8h2a3 3 0 0 1 0 6h-2"/><path d="M4 20h11"/>',
    ];
    $path = $icons[$fkey] ?? '<circle cx="12" cy="12" r="3"/>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke-width="1.6">' . $path . '</svg>';
}
