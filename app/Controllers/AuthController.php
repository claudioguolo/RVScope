<?php

namespace App\Controllers;

use App\Libraries\LdapAuthenticator;
use App\Libraries\LocalUserAuthenticator;
use App\Libraries\UserAuthorization;
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
            ->first();

        if ((new LocalUserAuthenticator())->verify($localUser, $password)) {
            $this->startSession(
                (string) ($localUser['username'] ?? $username),
                (string) ($localUser['display_name'] ?? $username),
                'local',
                UserAuthorization::normalizeRole((string) ($localUser['role'] ?? 'user')),
                (int) ($localUser['id'] ?? 0),
            );
            $localUserModel->update((int) ($localUser['id'] ?? 0), [
                'last_login_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return redirect()->to($this->consumeRedirect());
        }

        $ldap = new LdapAuthenticator();
        if ($ldap->authenticate($username, $password)) {
            if (is_array($localUser)
                && (string) ($localUser['auth_source'] ?? 'local') !== 'ad') {
                session()->setFlashdata('auth_login_error', 'Este login está reservado para uma conta local.');
                return redirect()->to(site_url('auth/login'));
            }

            $displayUsername = str_contains($username, '@')
                ? strstr($username, '@', true)
                : $username;

            $adUser = $localUser;
            if (! is_array($adUser)) {
                $userId = $localUserModel->insert([
                    'username' => $username,
                    'display_name' => (string) $displayUsername,
                    'password_hash' => '',
                    'auth_source' => 'ad',
                    'role' => UserAuthorization::ROLE_USER,
                    'is_active' => 1,
                    'last_login_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], true);
                $adUser = $localUserModel->find($userId);
            }

            if (! is_array($adUser) || (int) ($adUser['is_active'] ?? 0) !== 1) {
                session()->setFlashdata('auth_login_error', 'Usuário inválido ou inativo.');
                return redirect()->to(site_url('auth/login'));
            }

            $localUserModel->update((int) ($adUser['id'] ?? 0), [
                'last_login_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->startSession(
                (string) ($adUser['username'] ?? $username),
                (string) ($adUser['display_name'] ?? $displayUsername),
                'ad',
                UserAuthorization::normalizeRole((string) ($adUser['role'] ?? 'user')),
                (int) ($adUser['id'] ?? 0),
            );
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
            'auth_role',
            'auth_user_id',
            'authenticated_reports_redirect',
        ]);

        return redirect()->to(site_url('auth/login'));
    }

    private function startSession(
        string $username,
        string $displayName,
        string $source,
        string $role,
        int $userId,
    ): void
    {
        session()->set([
            'user_authenticated' => true,
            'auth_username' => $username,
            'auth_display_name' => $displayName,
            'auth_source' => $source,
            'auth_role' => $role,
            'auth_user_id' => $userId,
        ]);
        session()->regenerate(true);
    }

    private function isAuthenticated(): bool
    {
        return UserAuthorization::isAuthenticated();
    }

    private function consumeRedirect(): string
    {
        $redirect = (string) session('authenticated_reports_redirect');
        session()->remove('authenticated_reports_redirect');

        return $redirect !== '' ? $redirect : site_url('/');
    }
}
