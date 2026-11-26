<?php
/**
 * Public Resources site -> admin.
 *
 * Copy this file next to checkAuth.php, so the site can call /resources/siteApi.php.
 * It works on PHP 7.2. The secret is NOT in this file.
 *
 * Create siteApi.config.php in the same folder (do not commit it):
 *
 *     <?php
 *     return array(
 *         'token' => 'SAME LONG SECRET AS RESOURCES_API_TOKEN IN THE ADMIN',
 *         'admin' => 'https://resources.tzivoshashem.com',
 *         // optional: where the Hachayol PDFs (pdfs/666.pdf) are, if not in site/dist, site/public or site
 *         // 'pdf_dirs' => array(__DIR__ . '/site/dist'),
 *     );
 *
 * Then fill in the sign-in block below with the same check checkAuth.php already does.
 * Until that block sets $email, this file answers "not signed in" and the public site
 * keeps showing the pages it was built with.
 */

$configFile = __DIR__ . '/siteApi.config.php';
if (!is_file($configFile)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo '{"message":"not set up"}';
    exit;
}
$config = require $configFile;
if (empty($config['token']) || empty($config['admin'])) {
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo '{"message":"not set up"}';
    exit;
}

// --- Sign-in check (same person checkAuth.php uses) ---
// Copy the lines from checkAuth.php that find the signed-in person,
// and set $email, $name and $role. Leave $email empty if nobody is signed in.
// $role is the Mashpia role words, such as "Teacher" or "Base Commander".
$email = '';
$name = '';
$role = '';

if ($email === '') {
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo '{"message":"Sign in first."}';
    exit;
}

$admin = rtrim($config['admin'], '/');
$headers = array(
    'Authorization: Bearer ' . $config['token'],
    'Accept: application/json',
    'X-Resources-Email: ' . $email,
    'X-Resources-Role: ' . $role,
);

if (isset($_GET['page'])) {
    proxy_json($admin . '/api/content/pages/' . rawurlencode($_GET['page']), $headers);
}
if (isset($_GET['q'])) {
    proxy_json($admin . '/api/content/search?q=' . rawurlencode($_GET['q']), $headers);
}
// View PDF: the file itself, only for the site's own reader (it sends X-Resources-View: 1; a plain link or the
// address bar can't), opened inline and never kept. The admin checks this person may see it.
if (isset($_GET['pdf'])) {
    need_reader();
    $path = (string) $_GET['pdf'];
    list($code, $body) = admin_call($admin . '/api/content/bar?href=' . rawurlencode($path), $headers);
    if ($code !== 200) {
        http_response_code($code > 0 ? $code : 502);
        header('Content-Type: application/json; charset=UTF-8');
        echo $body !== false && $body !== '' ? $body : '{"message":"The file could not be opened."}';
        exit;
    }
    $file = site_pdf($path, $config);
    if ($file === null) {
        http_response_code(404);
        header('Content-Type: application/json; charset=UTF-8');
        echo '{"message":"The file could not be found."}';
        exit;
    }
    view_headers('application/pdf');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}
if (isset($_GET['file']) && ctype_digit((string) $_GET['file']) && isset($_GET['view']) && $_GET['view'] === '1') {
    need_reader();
    list($code, $body, $type) = admin_call($admin . '/api/content/files/' . $_GET['file'] . '?view=1', $headers, true);
    if ($code === 200 && $body !== false && strpos((string) $type, 'application/json') !== 0) {
        view_headers($type !== '' ? $type : 'application/pdf');
        header('Content-Length: ' . strlen($body));
        echo $body;
        exit;
    }
    http_response_code($code > 0 ? $code : 502);
    header('Content-Type: application/json; charset=UTF-8');
    echo $body !== false && $body !== '' ? $body : '{"message":"The file could not be opened."}';
    exit;
}
if (isset($_GET['file']) && ctype_digit((string) $_GET['file'])) {
    $download = isset($_GET['download']) && $_GET['download'] === '1' ? '?download=1' : '';   // the admin checks who may download
    list($code, $body) = admin_call($admin . '/api/content/files/' . $_GET['file'] . $download, $headers);
    $data = json_decode($body, true);
    if ($code === 200 && is_array($data) && !empty($data['url'])) {
        header('Location: ' . $data['url']);
        exit;
    }
    http_response_code($code > 0 ? $code : 502);
    header('Content-Type: application/json; charset=UTF-8');
    echo $body !== false && $body !== '' ? $body : '{"message":"The file could not be opened."}';
    exit;
}

http_response_code(404);
header('Content-Type: application/json; charset=UTF-8');
echo '{"message":"Ask for a page, a search, or a file."}';
exit;

function proxy_json($url, $headers)
{
    list($code, $body) = admin_call($url, $headers);
    http_response_code($code > 0 ? $code : 502);
    header('Content-Type: application/json; charset=UTF-8');
    echo $body !== false && $body !== '' ? $body : '{"message":"The admin did not answer."}';
    exit;
}

function admin_call($url, $headers, $file = false)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, $file ? 60 : 20);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return array($code, $body, $type);
}

/** Only the site's PDF reader asks with this header. */
function need_reader()
{
    if (!isset($_SERVER['HTTP_X_RESOURCES_VIEW']) || $_SERVER['HTTP_X_RESOURCES_VIEW'] !== '1') {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo '{"message":"Open this from the site."}';
        exit;
    }
}

/** Open in the page, keep no copy. */
function view_headers($type)
{
    header('Content-Type: ' . $type);
    header('Content-Disposition: inline');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
}

/**
 * A Hachayol PDF on this server, like pdfs/666.pdf, looked for in the site's folders
 * (or the folders listed as 'pdf_dirs' in siteApi.config.php). Never outside them, and only .pdf.
 */
function site_pdf($path, $config)
{
    if ($path === '' || strpos($path, '..') !== false || !preg_match('/^[A-Za-z0-9_\-\/. ]+\.pdf$/i', $path) || $path[0] === '/') {
        return null;
    }
    $dirs = !empty($config['pdf_dirs']) && is_array($config['pdf_dirs'])
        ? $config['pdf_dirs']
        : array(__DIR__ . '/site/dist', __DIR__ . '/site/public', __DIR__ . '/site', __DIR__);
    foreach ($dirs as $dir) {
        $base = realpath($dir);
        $real = $base ? realpath($base . '/' . $path) : false;
        if ($real && strpos($real, $base . DIRECTORY_SEPARATOR) === 0 && is_file($real)) {
            return $real;
        }
    }
    return null;
}
