<?php
/**
 * Controlador de autenticación
 */

class AuthController extends Controller
{
    /** GET /login */
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/login', [], 'auth');
    }

    /** POST /login */
    public function processLogin(): void
    {
        $this->verifyCsrf();

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $this->flash('danger', 'Ingresa tu usuario y contraseña.');
            $this->redirect('/login');
            return;
        }

        if (Auth::login($username, $password)) {
            $this->redirect('/dashboard');
        } else {
            $this->flash('danger', 'Usuario o contraseña incorrectos. Intenta de nuevo.');
            $this->redirect('/login');
        }
    }

    /** GET /logout */
    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}
