<?php
/**
 * WebSecretaries.com request form handler.
 *
 * Hosted on HostGator at https://forms.websecretaries.com/send.php
 * Receives the Contact page form (with optional file uploads) and emails it,
 * with attachments, to info@WebSecretaries.com. Nothing is stored on the server.
 *
 * (This folder starts with "_" so GitHub Pages does not publish it.)
 */

// ---------- Settings ----------
const TO_EMAIL       = 'info@WebSecretaries.com';
const FROM_EMAIL     = 'forms@websecretaries.com';   // must be @websecretaries.com so the mail passes SPF
const FROM_NAME      = 'WebSecretaries Website';
const MAX_FILES      = 10;
const MAX_TOTAL_MB   = 20;
const RATE_LIMIT     = 5;      // max submissions per IP...
const RATE_WINDOW    = 3600;   // ...per this many seconds
const ALLOWED_ORIGINS = [
    'https://websecretaries.com',
    'https://www.websecretaries.com',
    'https://databasicsintl.github.io',
    'http://localhost:8080',
];
const ALLOWED_EXT = [
    'pdf', 'doc', 'docx', 'rtf', 'txt', 'odt', 'pages',
    'xls', 'xlsx', 'csv', 'ods', 'numbers',
    'ppt', 'pptx', 'odp', 'key',
    'jpg', 'jpeg', 'png', 'gif', 'heic', 'webp', 'tif', 'tiff',
    'mp3', 'm4a', 'wav', 'aac', 'ogg', 'wma',
    'zip', 'vcf',
];

// ---------- Helpers ----------
// The website submits the form as a normal page POST (HostGator's firewall blocks
// cross-site fetch requests), so by default we redirect back to the website:
// to /thanks.html on success, or to /contact.html?error=... on failure.
// Clients that ask for JSON (Accept: application/json) get JSON instead.
function return_base(): string {
    // e.g. "https://websecretaries.com/" or "https://databasicsintl.github.io/websecretaries/"
    $r = rtrim((string)($_POST['_return'] ?? ''), '/');
    foreach (ALLOWED_ORIGINS as $o) {
        if (($r === $o || strpos($r, $o . '/') === 0) && preg_match('#^https?://[A-Za-z0-9.:-]+(/[A-Za-z0-9._-]+)*$#', $r)) {
            return $r;
        }
    }
    return 'https://websecretaries.com';
}

function respond(int $status, bool $ok, string $message): void {
    if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message]);
        exit;
    }
    $url = return_base() . ($ok ? '/thanks.html' : '/contact.html?error=' . rawurlencode($message) . '#request');
    header('Location: ' . $url, true, 303);
    exit;
}

function clean_line(string $s, int $max = 200): string {
    // Single-line value: strip control chars (blocks email header injection) and trim.
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);
    return mb_substr(trim($s), 0, $max);
}

function clean_text(string $s, int $max = 10000): string {
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]+/u', '', $s);
    return mb_substr(trim($s), 0, $max);
}

function encode_header(string $s): string {
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

// ---------- CORS ----------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ALLOWED_ORIGINS, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Accept');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: https://websecretaries.com/contact.html', true, 302);
    exit;
}
if ($origin !== '' && !in_array($origin, ALLOWED_ORIGINS, true)) {
    respond(403, false, 'Forbidden.');
}

// Upload bigger than PHP's post_max_size: PHP drops the whole body.
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    respond(413, false, 'Your files are too large. Please email them to ' . TO_EMAIL . '.');
}

// ---------- Spam checks ----------
if (!empty($_POST['_gotcha'])) {
    respond(200, true, 'Thank you.');   // bot filled the hidden field; pretend success
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = sys_get_temp_dir() . '/ws_form_' . md5($ip);
$now = time();
$hits = [];
if (is_file($rateFile)) {
    $hits = array_filter(
        array_map('intval', explode(',', (string)file_get_contents($rateFile))),
        fn($t) => $t > $now - RATE_WINDOW
    );
}
if (count($hits) >= RATE_LIMIT) {
    respond(429, false, 'Too many requests. Please try again later, or email ' . TO_EMAIL . '.');
}

// ---------- Validate fields ----------
$name    = clean_line($_POST['name'] ?? '', 100);
$email   = clean_line($_POST['email'] ?? '', 200);
$phone   = clean_line($_POST['phone'] ?? '', 40);
$service = clean_line($_POST['service'] ?? '', 100);
$message = clean_text($_POST['message'] ?? '');
$consent = ($_POST['consent'] ?? '') === 'yes';

if ($name === '' || $message === '' || !$consent) {
    respond(422, false, 'Please fill in your name, project details, and the consent box.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, false, 'Please enter a valid email address.');
}

// ---------- Validate files ----------
$attachments = [];
$total = 0;
if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
    $f = $_FILES['attachments'];
    $count = count($f['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($f['error'][$i] === UPLOAD_ERR_INI_SIZE || $f['error'][$i] === UPLOAD_ERR_FORM_SIZE) {
            respond(413, false, 'A file is too large. Please email large files to ' . TO_EMAIL . '.');
        }
        if ($f['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'][$i])) {
            respond(400, false, 'A file could not be uploaded. Please try again.');
        }
        $orig = basename((string)$f['name'][$i]);
        $safe = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $orig);
        $safe = mb_substr(trim($safe, ' .') ?: 'file', 0, 120);
        $ext  = strtolower(pathinfo($safe, PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXT, true)) {
            respond(415, false, "Sorry, files of type .$ext can't be sent here. Please email it to " . TO_EMAIL . '.');
        }
        $size = (int)$f['size'][$i];
        $total += $size;
        $attachments[] = ['name' => $safe, 'path' => $f['tmp_name'][$i], 'size' => $size];
    }
}
if (count($attachments) > MAX_FILES) {
    respond(413, false, 'Please attach no more than ' . MAX_FILES . ' files.');
}
if ($total > MAX_TOTAL_MB * 1048576) {
    respond(413, false, 'Files total more than ' . MAX_TOTAL_MB . ' MB. Please email large files to ' . TO_EMAIL . '.');
}

// ---------- Build email ----------
$fileList = $attachments
    ? implode("\n", array_map(fn($a) => sprintf('  - %s (%.1f MB)', $a['name'], $a['size'] / 1048576), $attachments))
    : '  (none)';

$body = "New request from the WebSecretaries.com website\n"
      . "------------------------------------------------\n\n"
      . "Name:     $name\n"
      . "Email:    $email\n"
      . "Phone:    " . ($phone !== '' ? $phone : '(not given)') . "\n"
      . "Service:  $service\n\n"
      . "Project details:\n$message\n\n"
      . "Files:\n$fileList\n\n"
      . "------------------------------------------------\n"
      . "Sent " . date('Y-m-d H:i T') . " from IP $ip. Reply to this email to respond to $name.\n";

$boundary = 'ws_' . bin2hex(random_bytes(12));
$subject  = encode_header("Website request: $service - $name");

$headers  = 'From: ' . encode_header(FROM_NAME) . ' <' . FROM_EMAIL . ">\r\n";
$headers .= 'Reply-To: ' . encode_header($name) . " <$email>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"";

$mime  = "--$boundary\r\n";
$mime .= "Content-Type: text/plain; charset=UTF-8\r\n";
$mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
$mime .= chunk_split(base64_encode($body)) . "\r\n";

foreach ($attachments as $a) {
    $data = file_get_contents($a['path']);
    if ($data === false) continue;
    $mime .= "--$boundary\r\n";
    $mime .= "Content-Type: application/octet-stream; name=\"{$a['name']}\"\r\n";
    $mime .= "Content-Transfer-Encoding: base64\r\n";
    $mime .= "Content-Disposition: attachment; filename=\"{$a['name']}\"\r\n\r\n";
    $mime .= chunk_split(base64_encode($data)) . "\r\n";
}
$mime .= "--$boundary--\r\n";

// ---------- Send ----------
$sent = mail(TO_EMAIL, $subject, $mime, $headers, '-f' . FROM_EMAIL);

$hits[] = $now;
@file_put_contents($rateFile, implode(',', $hits), LOCK_EX);

if (!$sent) {
    respond(500, false, 'Sorry, your request could not be sent. Please email ' . TO_EMAIL . ' or call +1 (404) 721-2620.');
}
respond(200, true, "Thank you! Your request has been sent. We'll be in touch shortly.");
