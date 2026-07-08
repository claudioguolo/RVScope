<?php

namespace App\Controllers;

use App\Models\AdminUserModel;
use CodeIgniter\Controller;

class AdminController extends Controller
{
    public function access()
    {
        if ($this->hasAdminAccess()) {
            return redirect()->to(site_url('admin/login'));
        }

        $user = $this->gateUsername();
        $password = $this->gatePassword();

        if ($password === '') {
            return $this->response
                ->setStatusCode(500)
                ->setBody('Credencial administrativa nao configurada. Defina security.adminPassword ou security.bootstrapAdminPassword no .env.');
        }

        $providedUser = (string) ($this->request->getServer('PHP_AUTH_USER') ?? '');
        $providedPassword = (string) ($this->request->getServer('PHP_AUTH_PW') ?? '');

        if (!hash_equals($user, $providedUser) || !hash_equals($password, $providedPassword)) {
            return $this->response
                ->setHeader('WWW-Authenticate', 'Basic realm="RVScope Admin"')
                ->setStatusCode(401)
                ->setBody('Autenticacao necessaria.');
        }

        session()->set('admin_gate_authenticated', true);

        return redirect()->to(site_url('admin/login'));
    }

    public function login()
    {
        if (! $this->hasAdminAccess()) {
            return redirect()->to(site_url('admin/access'));
        }

        $this->ensureInitialAdminExists();

        return view('admin/login', [
            'gateUser' => $this->gateUsername(),
            'errorMessage' => session()->getFlashdata('admin_login_error'),
            'initialAdminUser' => $this->bootstrapAdminUsername(),
        ]);
    }

    public function authenticate()
    {
        if (! $this->hasAdminAccess()) {
            return redirect()->to(site_url('admin/access'));
        }

        $this->ensureInitialAdminExists();

        $username = strtolower(trim((string) ($this->request->getPost('username') ?? '')));
        $password = (string) ($this->request->getPost('password') ?? '');

        if ($username === '' || $password === '') {
            session()->setFlashdata('admin_login_error', 'Informe usuário e senha.');
            return redirect()->to(site_url('admin/login'));
        }

        $userModel = new AdminUserModel();
        $user = $userModel->where('username', $username)->first();

        if (! is_array($user) || (int) ($user['is_active'] ?? 0) !== 1) {
            session()->setFlashdata('admin_login_error', 'Usuário inválido ou inativo.');
            return redirect()->to(site_url('admin/login'));
        }

        $passwordHash = (string) ($user['password_hash'] ?? '');
        if ($passwordHash === '' || ! password_verify($password, $passwordHash)) {
            session()->setFlashdata('admin_login_error', 'Usuário inválido ou inativo.');
            return redirect()->to(site_url('admin/login'));
        }

        session()->set([
            'admin_user_id' => (int) ($user['id'] ?? 0),
            'admin_username' => (string) ($user['username'] ?? ''),
            'admin_display_name' => (string) ($user['display_name'] ?? ''),
            'admin_role' => (string) ($user['role'] ?? 'admin'),
            'admin_logged_in' => true,
        ]);

        $userModel->update((int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(site_url('admin/users'));
    }

    public function users()
    {
        if (! $this->isAdminLoggedIn()) {
            return redirect()->to(site_url('admin/login'));
        }

        $userModel = new AdminUserModel();
        $users = $userModel->orderBy('username', 'ASC')->findAll();

        return view('admin/users', [
            'users' => $users,
            'currentUser' => (string) session('admin_display_name'),
            'createdMessage' => session()->getFlashdata('admin_user_created'),
            'errorMessage' => session()->getFlashdata('admin_user_error'),
        ]);
    }

    public function createUser()
    {
        if (! $this->isAdminLoggedIn()) {
            return redirect()->to(site_url('admin/login'));
        }

        $username = strtolower(trim((string) ($this->request->getPost('username') ?? '')));
        $displayName = trim((string) ($this->request->getPost('display_name') ?? ''));
        $password = (string) ($this->request->getPost('password') ?? '');
        $role = strtolower(trim((string) ($this->request->getPost('role') ?? 'admin')));

        if ($username === '' || $displayName === '' || $password === '') {
            session()->setFlashdata('admin_user_error', 'Preencha nome, usuário e senha.');
            return redirect()->to(site_url('admin/users'));
        }

        if (! preg_match('/^[a-z0-9._-]{3,80}$/', $username)) {
            session()->setFlashdata('admin_user_error', 'Use apenas letras minúsculas, números, ponto, hífen ou sublinhado.');
            return redirect()->to(site_url('admin/users'));
        }

        if (strlen($password) < 8) {
            session()->setFlashdata('admin_user_error', 'A senha deve ter pelo menos 8 caracteres.');
            return redirect()->to(site_url('admin/users'));
        }

        $allowedRoles = ['admin'];
        if (! in_array($role, $allowedRoles, true)) {
            $role = 'admin';
        }

        $userModel = new AdminUserModel();
        $exists = $userModel->where('username', $username)->first();
        if (is_array($exists)) {
            session()->setFlashdata('admin_user_error', 'Já existe um usuário com esse login.');
            return redirect()->to(site_url('admin/users'));
        }

        $userModel->insert([
            'username' => $username,
            'display_name' => $displayName,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        session()->setFlashdata('admin_user_created', 'Usuário criado com sucesso.');

        return redirect()->to(site_url('admin/users'));
    }

    public function profile()
    {
        if (! $this->isAdminLoggedIn()) {
            return redirect()->to(site_url('admin/login'));
        }

        $user = $this->currentAdminUser();
        if (! is_array($user)) {
            return redirect()->to(site_url('admin/logout'));
        }

        return view('admin/profile', [
            'displayName' => (string) ($user['display_name'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'role' => (string) ($user['role'] ?? 'admin'),
            'profileMessage' => session()->getFlashdata('admin_profile_message'),
            'profileError' => session()->getFlashdata('admin_profile_error'),
            'passwordMessage' => session()->getFlashdata('admin_password_message'),
            'passwordError' => session()->getFlashdata('admin_password_error'),
        ]);
    }

    public function updateProfile()
    {
        if (! $this->isAdminLoggedIn()) {
            return redirect()->to(site_url('admin/login'));
        }

        $user = $this->currentAdminUser();
        if (! is_array($user)) {
            return redirect()->to(site_url('admin/logout'));
        }

        $displayName = trim((string) ($this->request->getPost('display_name') ?? ''));
        if ($displayName === '') {
            session()->setFlashdata('admin_profile_error', 'Informe o nome que sera exibido no sistema.');
            return redirect()->to(site_url('admin/profile'));
        }

        if (strlen($displayName) > 120) {
            session()->setFlashdata('admin_profile_error', 'O nome deve ter no maximo 120 caracteres.');
            return redirect()->to(site_url('admin/profile'));
        }

        $userModel = new AdminUserModel();
        $userModel->update((int) $user['id'], [
            'display_name' => $displayName,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        session()->set('admin_display_name', $displayName);
        session()->setFlashdata('admin_profile_message', 'Perfil atualizado com sucesso.');

        return redirect()->to(site_url('admin/profile'));
    }

    public function updatePassword()
    {
        if (! $this->isAdminLoggedIn()) {
            return redirect()->to(site_url('admin/login'));
        }

        $user = $this->currentAdminUser();
        if (! is_array($user)) {
            return redirect()->to(site_url('admin/logout'));
        }

        $currentPassword = (string) ($this->request->getPost('current_password') ?? '');
        $newPassword = (string) ($this->request->getPost('new_password') ?? '');
        $confirmPassword = (string) ($this->request->getPost('confirm_password') ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            session()->setFlashdata('admin_password_error', 'Preencha a senha atual, a nova senha e a confirmacao.');
            return redirect()->to(site_url('admin/profile'));
        }

        if (! password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
            session()->setFlashdata('admin_password_error', 'Senha atual invalida.');
            return redirect()->to(site_url('admin/profile'));
        }

        if (strlen($newPassword) < 8) {
            session()->setFlashdata('admin_password_error', 'A nova senha deve ter pelo menos 8 caracteres.');
            return redirect()->to(site_url('admin/profile'));
        }

        if ($newPassword !== $confirmPassword) {
            session()->setFlashdata('admin_password_error', 'A confirmacao nao confere com a nova senha.');
            return redirect()->to(site_url('admin/profile'));
        }

        if (password_verify($newPassword, (string) ($user['password_hash'] ?? ''))) {
            session()->setFlashdata('admin_password_error', 'A nova senha deve ser diferente da senha atual.');
            return redirect()->to(site_url('admin/profile'));
        }

        $userModel = new AdminUserModel();
        $userModel->update((int) $user['id'], [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        session()->setFlashdata('admin_password_message', 'Senha alterada com sucesso.');

        return redirect()->to(site_url('admin/profile'));
    }

    public function logout()
    {
        session()->remove([
            'admin_gate_authenticated',
            'admin_user_id',
            'admin_username',
            'admin_display_name',
            'admin_role',
            'admin_logged_in',
        ]);

        return redirect()->to(site_url('admin/login'));
    }

    private function hasAdminAccess(): bool
    {
        return (bool) session('admin_gate_authenticated');
    }

    private function isAdminLoggedIn(): bool
    {
        return $this->hasAdminAccess() && (bool) session('admin_logged_in');
    }

    private function currentAdminUser(): ?array
    {
        $userId = (int) session('admin_user_id');
        if ($userId <= 0) {
            return null;
        }

        $userModel = new AdminUserModel();
        $user = $userModel->find($userId);

        return is_array($user) ? $user : null;
    }

    private function ensureInitialAdminExists(): void
    {
        $userModel = new AdminUserModel();
        $count = $userModel->countAllResults();
        if ($count > 0) {
            return;
        }

        $password = $this->bootstrapAdminPassword();
        if ($password === '') {
            return;
        }

        $userModel->insert([
            'username' => $this->bootstrapAdminUsername(),
            'display_name' => trim((string) env('security.bootstrapAdminName', 'Administrador inicial')),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function bootstrapAdminUsername(): string
    {
        $username = strtolower(trim((string) env('security.bootstrapAdminUser', $this->gateUsername())));
        return $username !== '' ? $username : 'admin';
    }

    private function bootstrapAdminPassword(): string
    {
        $password = (string) env('security.bootstrapAdminPassword', $this->gatePassword());
        return $password !== '' ? $password : 'troque-esta-senha';
    }

    private function gateUsername(): string
    {
        $username = strtolower(trim((string) env('security.adminUser', 'admin')));
        return $username !== '' ? $username : 'admin';
    }

    private function gatePassword(): string
    {
        $password = (string) env('security.adminPassword', '');
        if ($password !== '') {
            return $password;
        }

        $password = (string) env('security.bootstrapAdminPassword', '');
        return $password !== '' ? $password : 'troque-esta-senha';
    }
}
