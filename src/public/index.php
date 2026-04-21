<?php
/**
 * Front Controller - Punto de entrada único
 */

// Bootstrap
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/ErrorHandler.php';
ErrorHandler::register();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/core/Router.php';
require_once dirname(__DIR__) . '/core/Controller.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/Validator.php';
require_once dirname(__DIR__) . '/core/FolioService.php';
require_once dirname(__DIR__) . '/core/VacacionesService.php';

// Modelos (repositorios)
require_once dirname(__DIR__) . '/models/PersonalModel.php';
require_once dirname(__DIR__) . '/models/VacacionesModel.php';
require_once dirname(__DIR__) . '/models/IncidenciaModel.php';

// Controladores
require_once dirname(__DIR__) . '/controllers/AuthController.php';
require_once dirname(__DIR__) . '/controllers/DashboardController.php';
require_once dirname(__DIR__) . '/controllers/OficioController.php';
require_once dirname(__DIR__) . '/controllers/MovimientoController.php';
require_once dirname(__DIR__) . '/controllers/EvidenciaController.php';
require_once dirname(__DIR__) . '/controllers/UsuarioController.php';
require_once dirname(__DIR__) . '/controllers/CatalogoController.php';
require_once dirname(__DIR__) . '/controllers/PersonalController.php';
require_once dirname(__DIR__) . '/controllers/VacacionesController.php';
require_once dirname(__DIR__) . '/controllers/IncidenciaController.php';
require_once dirname(__DIR__) . '/controllers/ImportController.php';

// Iniciar sesión segura
configurar_sesion();

// =========================================================
// DEFINICIÓN DE RUTAS
// =========================================================
$router = new Router();

// Auth
$router->get('/login',  function() { (new AuthController())->showLogin(); });
$router->post('/login', function() { (new AuthController())->processLogin(); });
$router->any('/logout', function() { (new AuthController())->logout(); });

// Raíz
$router->get('/',         function() { header('Location: /dashboard'); exit; });
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

// Catálogos
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
