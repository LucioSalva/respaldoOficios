<?php
/**
 * Front Controller - Punto de entrada unico.
 *
 * Layout fisico:
 *   <BASE_PATH>/public/index.php   <-- este archivo
 *   <BASE_PATH>/app/...            <-- codigo de la app
 */

// 1) Paths base (define BASE_PATH, APP_PATH, PUBLIC_PATH, VIEWS_PATH, LOG_PATH, etc.)
require_once dirname(__DIR__) . '/app/config/paths.php';

// 2) Configuracion (lee .env, define APP_*, DB_*, etc.)
require_once CONFIG_PATH . '/config.php';

// 3) Helpers globales (deben cargarse antes que cualquier vista o controlador)
require_once HELPERS_PATH . '/url_helper.php';
require_once HELPERS_PATH . '/auth_helper.php';
require_once HELPERS_PATH . '/csrf_helper.php';
require_once HELPERS_PATH . '/upload_helper.php';

// 4) Error handler
require_once CORE_PATH . '/ErrorHandler.php';
ErrorHandler::register();

// 5) Infraestructura
require_once CORE_PATH . '/Database.php';
require_once CONFIG_PATH . '/session.php';
require_once CORE_PATH . '/Session.php';
require_once CORE_PATH . '/Response.php';
require_once CORE_PATH . '/Router.php';
require_once CORE_PATH . '/Controller.php';
require_once CORE_PATH . '/Auth.php';
require_once CORE_PATH . '/Validator.php';
require_once CORE_PATH . '/FolioService.php';
require_once CORE_PATH . '/VacacionesService.php';

// 6) Modelos
require_once MODELS_PATH . '/PersonalModel.php';
require_once MODELS_PATH . '/VacacionesModel.php';
require_once MODELS_PATH . '/IncidenciaModel.php';

// 7) Controladores
require_once CONTROLLERS_PATH . '/AuthController.php';
require_once CONTROLLERS_PATH . '/DashboardController.php';
require_once CONTROLLERS_PATH . '/OficioController.php';
require_once CONTROLLERS_PATH . '/MovimientoController.php';
require_once CONTROLLERS_PATH . '/EvidenciaController.php';
require_once CONTROLLERS_PATH . '/UsuarioController.php';
require_once CONTROLLERS_PATH . '/CatalogoController.php';
require_once CONTROLLERS_PATH . '/PersonalController.php';
require_once CONTROLLERS_PATH . '/VacacionesController.php';
require_once CONTROLLERS_PATH . '/IncidenciaController.php';
require_once CONTROLLERS_PATH . '/ImportController.php';

// 8) Sesion segura
configurar_sesion();

// =========================================================
// DEFINICION DE RUTAS
// =========================================================
$router = new Router();

// Auth
$router->get('/login',  function() { (new AuthController())->showLogin(); });
$router->post('/login', function() { (new AuthController())->processLogin(); });
$router->any('/logout', function() { (new AuthController())->logout(); });

// Raiz
$router->get('/',         function() { redirect_to('/dashboard'); });
$router->get('/dashboard',function() { (new DashboardController())->index(); });

// Oficios
$router->get('/oficios',                    function()       { (new OficioController())->index(); });
$router->get('/oficios/crear',              function()       { (new OficioController())->create(); });
$router->post('/oficios/crear',             function()       { (new OficioController())->store(); });
$router->get('/oficios/:id',                function($p)     { (new OficioController())->show($p); });
$router->get('/oficios/:id/editar',         function($p)     { (new OficioController())->edit($p); });
$router->post('/oficios/:id/editar',        function($p)     { (new OficioController())->update($p); });
$router->post('/oficios/:id/eliminar',      function($p)     { (new OficioController())->destroy($p); });

// Movimientos
$router->post('/oficios/:id/movimiento',    function($p)     { (new MovimientoController())->store($p); });

// Evidencias
$router->post('/oficios/:id/evidencia',     function($p)     { (new EvidenciaController())->store($p); });
$router->get('/evidencia/:id/descargar',    function($p)     { (new EvidenciaController())->download($p); });
$router->get('/evidencia/:id/ver',          function($p)     { (new EvidenciaController())->view_pdf($p); });

// Usuarios
$router->get('/usuarios',                   function()       { (new UsuarioController())->index(); });
$router->get('/usuarios/crear',             function()       { (new UsuarioController())->create(); });
$router->post('/usuarios/crear',            function()       { (new UsuarioController())->store(); });
$router->get('/usuarios/:id/editar',        function($p)     { (new UsuarioController())->edit($p); });
$router->post('/usuarios/:id/editar',       function($p)     { (new UsuarioController())->update($p); });
$router->post('/usuarios/:id/toggle',       function($p)     { (new UsuarioController())->toggle($p); });
$router->post('/usuarios/:id/eliminar',     function($p)     { (new UsuarioController())->destroy($p); });

// Catalogos
$router->get('/catalogos',                              function()   { (new CatalogoController())->index(); });
$router->post('/catalogos/dependencias',                function()   { (new CatalogoController())->storeDependencia(); });
$router->post('/catalogos/dependencias/:id/toggle',     function($p) { (new CatalogoController())->toggleDependencia($p); });
$router->post('/catalogos/dependencias/:id/editar',     function($p) { (new CatalogoController())->updateDependencia($p); });
$router->post('/catalogos/areas-internas',              function()   { (new CatalogoController())->storeAreaInterna(); });
$router->post('/catalogos/areas-internas/:id/toggle',   function($p) { (new CatalogoController())->toggleAreaInterna($p); });
$router->post('/catalogos/areas-internas/:id/editar',   function($p) { (new CatalogoController())->updateAreaInterna($p); });
$router->post('/catalogos/estados',                     function()   { (new CatalogoController())->storeEstado(); });
$router->post('/catalogos/estados/:id/toggle',          function($p) { (new CatalogoController())->toggleEstado($p); });
$router->post('/catalogos/tipos-evidencia',             function()   { (new CatalogoController())->storeTipoEvidencia(); });
$router->post('/catalogos/tipos-evidencia/:id/toggle',  function($p) { (new CatalogoController())->toggleTipoEvidencia($p); });

// Personal
$router->get('/personal',                   function()   { (new PersonalController())->index(); });
$router->get('/personal/crear',             function()   { (new PersonalController())->create(); });
$router->post('/personal/crear',            function()   { (new PersonalController())->store(); });
$router->get('/personal/:id',               function($p) { (new PersonalController())->show($p); });
$router->get('/personal/:id/editar',        function($p) { (new PersonalController())->edit($p); });
$router->post('/personal/:id/editar',       function($p) { (new PersonalController())->update($p); });

// Vacaciones
$router->get('/vacaciones',                          function()   { (new VacacionesController())->index(); });
$router->get('/vacaciones/crear',                    function()   { (new VacacionesController())->create(); });
$router->post('/vacaciones/crear',                   function()   { (new VacacionesController())->store(); });
$router->get('/vacaciones/inconsistencias',          function()   { (new VacacionesController())->inconsistencias(); });
$router->get('/vacaciones/buscar-personal',          function()   { (new VacacionesController())->buscarPersonal(); });
$router->get('/vacaciones/empleado/:pid',            function($p) { (new VacacionesController())->show($p); });
$router->get('/vacaciones/mov/:id/editar',           function($p) { (new VacacionesController())->edit($p); });
$router->post('/vacaciones/mov/:id/editar',          function($p) { (new VacacionesController())->update($p); });
$router->post('/vacaciones/mov/:id/cancelar',        function($p) { (new VacacionesController())->cancel($p); });

// Incidencias
$router->get('/incidencias',                 function()   { (new IncidenciaController())->index(); });
$router->get('/incidencias/crear',           function()   { (new IncidenciaController())->create(); });
$router->post('/incidencias/crear',          function()   { (new IncidenciaController())->store(); });
$router->get('/incidencias/:id',             function($p) { (new IncidenciaController())->show($p); });
$router->get('/incidencias/:id/editar',      function($p) { (new IncidenciaController())->edit($p); });
$router->post('/incidencias/:id/editar',     function($p) { (new IncidenciaController())->update($p); });
$router->post('/incidencias/:id/justificar', function($p) { (new IncidenciaController())->justificar($p); });
$router->post('/incidencias/:id/cancelar',   function($p) { (new IncidenciaController())->cancelar($p); });

// Importar (admin)
$router->get('/importar',                   function()   { (new ImportController())->index(); });
$router->post('/importar/subir',            function()   { (new ImportController())->upload(); });

// Despachar
$router->dispatch();
