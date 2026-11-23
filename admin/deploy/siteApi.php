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
if (isset($_GET['file']) && ctype_digit((string) $_GET['file'])) {
    list($code, $body) = admin_call($admin . '/api/content/files/' . $_GET['file'], $headers);
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

function admin_call($url, $headers)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, $body);
}
