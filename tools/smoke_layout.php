<?php
/**
 * Smoke test offline del nuevo layout PHP puro.
 * No toca BD ni hace requests reales: solo verifica que los archivos existen,
 * que paths.php define todas las constantes esperadas, y que los helpers
 * estan registrados.
 */

$root = realpath(__DIR__ . '/..');
require_once $root . '/app/config/paths.php';

$expected = ['BASE_PATH', 'APP_PATH', 'PUBLIC_PATH', 'VIEWS_PATH', 'CONTROLLERS_PATH',
             'MODELS_PATH', 'CORE_PATH', 'HELPERS_PATH', 'CONFIG_PATH',
             'UPLOADS_PATH', 'LOG_PATH', 'CACHE_PATH', 'SQL_PATH'];
foreach ($expected as $c) {
    if (!defined($c)) { fwrite(STDERR, "FALTA constante $c\n"); exit(1); }
}
echo "[OK] paths.php define las 13 constantes.\n";

require_once CONFIG_PATH . '/config.php';
foreach (['APP_ENV','APP_DEBUG','APP_URL','APP_BASE_PATH','DB_HOST','DB_NAME',
          'UPLOAD_DIR','SESSION_NAME','CSRF_TOKEN_NAME','FOLIO_PREFIJO',
          'ROL_GOD','ROL_ADMIN','ROL_USER'] as $c) {
    if (!defined($c)) { fwrite(STDERR, "FALTA constante $c\n"); exit(1); }
}
echo "[OK] config.php define las constantes runtime.\n";
echo "    APP_BASE_PATH=" . APP_BASE_PATH . "\n";
echo "    APP_DEBUG="     . (APP_DEBUG ? 'true' : 'false') . "\n";

require_once HELPERS_PATH . '/url_helper.php';
require_once HELPERS_PATH . '/auth_helper.php';
require_once HELPERS_PATH . '/csrf_helper.php';
require_once HELPERS_PATH . '/upload_helper.php';
foreach (['base_path','url_path','app_url','asset_url','redirect_to',
          'is_logged_in','current_user','has_role','require_login',
          'csrf_token','csrf_field','csrf_validate','csrf_check',
          'upload_detect_mime','upload_extension_for_mime',
          'upload_sanitize_filename','upload_generate_physical_name',
          'upload_validate'] as $f) {
    if (!function_exists($f)) { fwrite(STDERR, "FALTA funcion $f\n"); exit(1); }
}
echo "[OK] helpers cargados (" . count(get_defined_functions()['user']) . " funciones user-defined).\n";

// url_path / app_url smoke
$tests = [
    ['url_path', '/', '/oficios/'],
    ['url_path', '/login', '/oficios/login'],
    ['url_path', '/dashboard', '/oficios/dashboard'],
    ['app_url',  '/login', 'http://187.174.221.162:8080/oficios/login'],
];
foreach ($tests as [$fn, $in, $exp]) {
    $got = $fn($in);
    if ($got !== $exp) {
        fwrite(STDERR, "FAIL $fn('$in') = '$got' (esperado '$exp')\n");
        exit(1);
    }
}
echo "[OK] url_path/app_url respetan APP_BASE_PATH.\n";

require_once CORE_PATH . '/ErrorHandler.php';
require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/Session.php';
require_once CORE_PATH . '/Response.php';
require_once CORE_PATH . '/Router.php';
require_once CORE_PATH . '/Controller.php';
require_once CORE_PATH . '/Auth.php';
require_once CORE_PATH . '/Validator.php';
require_once CORE_PATH . '/FolioService.php';
require_once CORE_PATH . '/VacacionesService.php';
foreach (['ErrorHandler','Database','Session','Response','Router','Controller',
          'Auth','Validator','FolioService','VacacionesService'] as $cls) {
    if (!class_exists($cls)) { fwrite(STDERR, "FALTA clase $cls\n"); exit(1); }
}
echo "[OK] core cargado (10 clases).\n";

require_once MODELS_PATH . '/PersonalModel.php';
require_once MODELS_PATH . '/VacacionesModel.php';
require_once MODELS_PATH . '/IncidenciaModel.php';
foreach (['PersonalModel','VacacionesModel','IncidenciaModel'] as $cls) {
    if (!class_exists($cls)) { fwrite(STDERR, "FALTA clase $cls\n"); exit(1); }
}
echo "[OK] models cargados.\n";

foreach (glob(CONTROLLERS_PATH . '/*.php') as $f) require_once $f;
foreach (['AuthController','DashboardController','OficioController',
          'MovimientoController','EvidenciaController','UsuarioController',
          'CatalogoController','PersonalController','VacacionesController',
          'IncidenciaController','ImportController'] as $cls) {
    if (!class_exists($cls)) { fwrite(STDERR, "FALTA clase $cls\n"); exit(1); }
}
echo "[OK] controllers cargados (11 clases).\n";

// Simulacion ligera de Router::dispatch
$router = new Router();
$called = false;
$router->get('/dashboard', function() use (&$called) { $called = true; });
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/oficios/dashboard';
$router->dispatch();
if (!$called) { fwrite(STDERR, "Router NO disparo /dashboard tras recorte de /oficios\n"); exit(1); }
echo "[OK] Router recorta APP_BASE_PATH=/oficios y dispara la ruta.\n";

echo "\nALL GREEN.\n";
