<?php

namespace App\Controllers;

use App\Libraries\LdapAuthenticator;
use App\Models\AdminUserModel;
use CodeIgniter\Controller;

class AuthController extends Controller
{
    public function login()
    {
        if ($this->isAuthenticated()) {
            return redirect()->to($this->consumeRedirect());
        }

        return view('auth/login', [
            'errorMessage' => session()->getFlashdata('auth_login_error'),
            'adEnabled' => (new LdapAuthenticator())->enabled(),
        ]);
    }

    public function authenticate()
    {
        $username = strtolower(trim((string) ($this->request->getPost('username') ?? '')));
        $password = (string) ($this->request->getPost('password') ?? '');

        if ($username === '' || $password === '') {
            session()->setFlashdata('auth_login_error', 'Informe usuário e senha.');
            return redirect()->to(site_url('auth/login'));
        }

        $localUserModel = new AdminUserModel();
        $localUser = $localUserModel
            ->where('username', $username)
            ->where('is_active', 1)
            ->first();

        if (is_array($localUser)
            && password_verify($password, (string) ($localUser['password_hash'] ?? ''))) {
            $this->startSession(
                (string) ($localUser['username'] ?? $username),
                (string) ($localUser['display_name'] ?? $username),
                'local',
            );
            $localUserModel->update((int) ($localUser['id'] ?? 0), [
                'last_login_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return redirect()->to($this->consumeRedirect());
        }

        $ldap = new LdapAuthenticator();
        if ($ldap->authenticate($username, $password)) {
            $displayUsername = str_contains($username, '@')
                ? strstr($username, '@', true)
                : $username;
            $this->startSession($username, (string) $displayUsername, 'ad');
            return redirect()->to($this->consumeRedirect());
        }

        session()->setFlashdata('auth_login_error', 'Usuário ou senha inválidos.');
        return redirect()->to(site_url('auth/login'));
    }

    public function logout()
    {
        session()->remove([
            'user_authenticated',
            'auth_username',
            'auth_display_name',
            'auth_source',
            'authenticated_reports_redirect',
        ]);

        return redirect()->to(site_url('auth/login'));
    }

    private function startSession(string $username, string $displayName, string $source): void
    {
        session()->set([
            'user_authenticated' => true,
            'auth_username' => $username,
            'auth_display_name' => $displayName,
            'auth_source' => $source,
        ]);
        session()->regenerate(true);
    }

    private function isAuthenticated(): bool
    {
        return (bool) session('user_authenticated')
            || (bool) session('admin_logged_in');
    }

    private function consumeRedirect(): string
    {
        $redirect = (string) session('authenticated_reports_redirect');
        session()->remove('authenticated_reports_redirect');

        return $redirect !== '' ? $redirect : site_url('/');
    }
}
