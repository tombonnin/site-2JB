<?php
declare(strict_types=1);

// Local PHP mail transport; no API key or external form service.
const SITE_ORIGIN = 'https://societe2jbf.fr';
const RECIPIENT = 'societe2jbf@gmail.com';
const SENDER = 'formulaire@societe2jbf.fr';
const PHONE = '+33631740560';

ini_set('display_errors', '0');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

function stop_request(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    $styles = glob(__DIR__ . '/assets/css/styles.*.css');
    $css = '/assets/css/' . basename($styles[0] ?? 'styles.css');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Votre demande | 2JBF</title><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="' . htmlspecialchars($css, ENT_QUOTES, 'UTF-8') . '"></head><body><main class="container text-hero compact-hero"><h1>Votre demande n’a pas été envoyée.</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><div class="hero-actions"><a class="button button-primary" href="/contact/">Revenir au formulaire</a><a class="text-link" href="tel:' . htmlspecialchars(PHONE, ENT_QUOTES, 'UTF-8') . '">Appelez-moi</a></div></main></body></html>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    stop_request(405, 'Utilisez le formulaire de contact pour transmettre votre message.');
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) {
    stop_request(413, 'Votre message est trop long. Réduisez sa longueur et réessayez.');
}
$type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($type !== 'application/x-www-form-urlencoded' || !empty($_FILES)) {
    stop_request(415, 'Le format de la demande n’est pas accepté.');
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if ($origin !== '' && !hash_equals(SITE_ORIGIN, rtrim($origin, '/'))) {
    stop_request(403, 'Ouvrez le formulaire depuis ce site pour envoyer votre demande.');
}
if ($origin === '' && $referer !== '') {
    $ref = parse_url($referer);
    $refOrigin = is_array($ref) ? ($ref['scheme'] ?? '') . '://' . ($ref['host'] ?? '') . (isset($ref['port']) ? ':' . $ref['port'] : '') : '';
    if (!hash_equals(SITE_ORIGIN, $refOrigin)) stop_request(403, 'Ouvrez le formulaire depuis ce site pour envoyer votre demande.');
}
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
    stop_request(403, 'Ouvrez le formulaire depuis ce site pour envoyer votre demande.');
}

function field(string $key, int $limit, bool $required = false, bool $multiline = false): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value) || preg_match('//u', $value) !== 1) stop_request(400, 'Vérifiez les champs du formulaire.');
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    $controls = $multiline ? '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/' : '/[\x00-\x1f\x7f]/';
    $length = preg_match_all('/./us', $value);
    if ($length === false || $length > $limit || ($required && $value === '') || preg_match($controls, $value)) {
        stop_request(400, 'Vérifiez les champs obligatoires et la longueur de votre message.');
    }
    return $value;
}

if (field('website', 200) !== '') stop_request(400, 'La demande n’a pas pu être validée.');
$name = field('nom', 120, true);
$email = field('email', 254, true);
$town = field('commune', 120, true);
$phone = field('telephone', 40);
$service = field('prestation', 40);
$message = field('message', 6000, true, true);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) stop_request(400, 'Vérifiez votre adresse e-mail.');
if (!in_array($service, ['', 'Charpente', 'Ossature bois', 'Couverture', 'Ramonage', 'Démoussage'], true)) {
    stop_request(400, 'Choisissez une prestation proposée dans le formulaire.');
}

// The private state contains only timestamps, a random key and short-lived IP hashes.
// It is kept above public_html; never fall back to the public directory.
$private = dirname(__DIR__) . '/.2jbf-form';
if (!is_dir($private) && !@mkdir($private, 0700, true) && !is_dir($private)) {
    stop_request(503, 'L’envoi est momentanément indisponible. Contactez-moi par téléphone ou par e-mail.');
}
$handle = @fopen($private . '/limites.json', 'c+');
if (!$handle || !flock($handle, LOCK_EX)) stop_request(503, 'L’envoi est momentanément indisponible. Contactez-moi par téléphone ou par e-mail.');
@chmod($private . '/limites.json', 0600);
$raw = stream_get_contents($handle);
$state = $raw === '' ? ['key' => bin2hex(random_bytes(32)), 'events' => []] : json_decode($raw, true);
if (!is_array($state) || !isset($state['key'], $state['events']) || !is_array($state['events'])) {
    flock($handle, LOCK_UN); fclose($handle);
    stop_request(503, 'L’envoi est momentanément indisponible. Contactez-moi par téléphone ou par e-mail.');
}
$now = time();
$hash = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $state['key']);
$state['events'] = array_values(array_filter($state['events'], static function ($event) use ($now) {
    return is_array($event) && isset($event['time'], $event['ip']) && $event['time'] > $now - 900;
}));
$byIp = count(array_filter($state['events'], static function ($event) use ($hash) { return $event['ip'] === $hash; }));
if ($byIp >= 5 || count($state['events']) >= 30) {
    flock($handle, LOCK_UN); fclose($handle);
    header('Retry-After: 900');
    stop_request(429, 'Trop de demandes ont été envoyées récemment. Réessayez dans quinze minutes ou appelez-moi.');
}
$state['events'][] = ['ip' => $hash, 'time' => $now];
$encoded = json_encode($state);
rewind($handle);
$saved = $encoded !== false && ftruncate($handle, 0) && fwrite($handle, $encoded) === strlen($encoded) && fflush($handle);
flock($handle, LOCK_UN); fclose($handle);
if (!$saved) stop_request(503, 'L’envoi est momentanément indisponible. Contactez-moi par téléphone ou par e-mail.');

$body = "Nouvelle demande depuis le site 2JBF\n\nNom : $name\nE-mail : $email\nTéléphone : $phone\nCommune : $town\nPrestation : $service\n\n$message\n";
$headers = [
    'From' => '2JBF - Site <' . SENDER . '>',
    'Reply-To' => $email,
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
    'Content-Transfer-Encoding' => 'base64'
];
if (!function_exists('mail') || !@mail(RECIPIENT, '2JBF - Nouvelle demande depuis le site', chunk_split(base64_encode($body)), $headers, '-f' . SENDER)) {
    stop_request(503, 'L’envoi est momentanément indisponible. Contactez-moi par téléphone ou par e-mail.');
}
header('Location: /merci/', true, 303);
exit;
