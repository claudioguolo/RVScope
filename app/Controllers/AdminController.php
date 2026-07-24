<?php

namespace App\Controllers;

use App\Libraries\SettingsSecretProtector;
use App\Libraries\SmtpMailer;
use App\Libraries\UserAuthorization;
use App\Models\AppSettingModel;
use App\Models\AdminUserModel;
use CodeIgniter\Controller;

class AdminController extends Controller
{
    public function access()
    {
        if (UserAuthorization::canAdminister()) {
            return redirect()->to(site_url('admin/users'));
        }

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
        if (UserAuthorization::canAdminister()) {
            return redirect()->to(site_url('admin/users'));
        }

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

        if (UserAuthorization::normalizeRole((string) ($user['role'] ?? 'user')) !== UserAuthorization::ROLE_ADMIN
            || (string) ($user['auth_source'] ?? 'local') !== 'local') {
            session()->setFlashdata('admin_login_error', 'Este usuário não possui acesso administrativo local.');
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
            'user_authenticated' => true,
            'auth_username' => (string) ($user['username'] ?? ''),
            'auth_display_name' => (string) ($user['display_name'] ?? ''),
            'auth_source' => 'local-admin',
            'auth_role' => 'admin',
            'auth_user_id' => (int) ($user['id'] ?? 0),
        ]);
        session()->regenerate(true);

        $userModel->update((int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $reportRedirect = (string) session('authenticated_reports_redirect');
        session()->remove('authenticated_reports_redirect');

        return redirect()->to(
            $reportRedirect !== '' ? $reportRedirect : site_url('admin/users'),
        );
    }

    public function users()
    {
        if (! $this->isAdminLoggedIn()) {
            return redirect()->to(site_url('admin/login'));
        }

        $userModel = new AdminUserModel();
        $users = $userModel->orderBy('username', 'ASC')->findAll();
        $settings = new AppSettingModel();
        $adConfiguration = $settings->adConfiguration();
        $smtpConfiguration = $settings->smtpConfiguration();

        return view('admin/users', [
            'users' => $users,
            'currentUser' => (string) (session('auth_display_name') ?: session('admin_display_name')),
            'createdMessage' => session()->getFlashdata('admin_user_created'),
            'errorMessage' => session()->getFlashdata('admin_user_error'),
            'authenticatedReportsEnabled' => $settings->authenticatedReportsEnabled(),
            'settingsMessage' => session()->getFlashdata('admin_settings_message'),
            'settingsError' => session()->getFlashdata('admin_settings_error'),
            'adConfiguration' => $adConfiguration,
            'adMessage' => session()->getFlashdata('admin_ad_message'),
            'adError' => session()->getFlashdata('admin_ad_error'),
            'smtpConfiguration' => $smtpConfiguration,
            'smtpPasswordConfigured' => (string) $smtpConfiguration['password_encrypted'] !== '',
            'smtpEncryptionKeyConfigured' => (new SettingsSecretProtector())->configured(),
            'smtpMessage' => session()->getFlashdata('admin_smtp_message'),
            'smtpError' => session()->getFlashdata('admin_smtp_error'),
        ]);
    }

    public function updateAuthenticatedReports()
    {
        if (! $this->isAdminLoggedIn()) {
            return redirect()->to(site_url('admin/login'));
        }

        $enabled = (string) ($this->request->getPost('authenticated_reports_enabled') ?? '') === '1';

        try {
            $settings = new AppSettingModel();
            $settings->setAuthenticatedReportsEnabled($enabled);
            session()->setFlashdata(
                'admin_settings_message',
                $enabled
                    ? 'A autenticação para acessar os relatórios foi habilitada.'
                    : 'O acesso público aos relatórios foi habilitado.',
            );
        } catch (\Throwable $exception) {
            log_message('error', 'Falha ao atualizar configuração de acesso: {message}', [
                'message' => $exception->getMessage(),
            ]);
            session()->setFlashdata(
                'admin_settings_error',
                'Não foi possível atualizar a configuração de acesso.',
            );
        }

        return redirect()->to(site_url('admin/users'));
    }

    public function updateActiveDirectory()
    {
        if (! $this->isAdminLoggedIn()) {
            return redirect()->to(site_url('admin/login'));
        }

        $enabled = (string) ($this->request->getPost('ad_enabled') ?? '') === '1';
        $host = strtolower(trim((string) ($this->request->getPost('ad_host') ?? '')));
        $domain = strtolower(trim((string) ($this->request->getPost('ad_domain') ?? '')));
        $port = (int) ($this->request->getPost('ad_port') ?? 636);

        if ($host !== ''
            && filter_var($host, FILTER_VALIDATE_IP) === false
            && ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host)) {
            session()->setFlashdata('admin_ad_error', 'Informe um host LDAPS válido, sem protocolo ou caminho.');
            return redirect()->to(site_url('admin/users'));
        }

        if ($domain !== '' && ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            session()->setFlashdata('admin_ad_error', 'Informe um domínio UPN válido, por exemplo empresa.local.');
            return redirect()->to(site_url('admin/users'));
        }

        if ($port < 1 || $port > 65535) {
            session()->setFlashdata('admin_ad_error', 'Informe uma porta LDAPS válida.');
            return redirect()->to(site_url('admin/users'));
        }

        if ($enabled && ($host === '' || $domain === '')) {
            session()->setFlashdata('admin_ad_error', 'Host e domínio são obrigatórios para habilitar o Active Directory.');
            return redirect()->to(site_url('admin/users'));
        }

        try {
            (new AppSettingModel())->setAdConfiguration($enabled, $host, $port, $domain);
            session()->setFlashdata(
                'admin_ad_message',
                $enabled
                    ? 'Autenticação pelo Active Directory habilitada.'
                    : 'Autenticação pelo Active Directory desabilitada.',
            );
        } catch (\Throwable $exception) {
            log_message('error', 'Falha ao atualizar Active Directory: {message}', [
                'message' => $exception->getMessage(),
            ]);
            session()->setFlashdata('admin_ad_error', 'Não foi possível salvar a configuração do Active Directory.');
        }

        return redirect()->to(site_url('admin/users'));
    }

    public function updateSmtp()
    {
        if (! $this->isAdminLoggedIn()) {
            return redirect()->to(site_url('admin/login'));
        }

        $enabled = (string) ($this->request->getPost('smtp_enabled') ?? '') === '1';
        $host = strtolower(trim((string) ($this->request->getPost('smtp_host') ?? '')));
        $port = (int) ($this->request->getPost('smtp_port') ?? 587);
        $cryptoInput = strtolower(trim((string) ($this->request->getPost('smtp_crypto') ?? 'tls')));
        $username = trim((string) ($this->request->getPost('smtp_username') ?? ''));
        $password = (string) ($this->request->getPost('smtp_password') ?? '');
        $fromEmail = strtolower(trim((string) ($this->request->getPost('smtp_from_email') ?? '')));
        $fromName = trim((string) ($this->request->getPost('smtp_from_name') ?? 'RVScope'));

        $allowedCrypto = ['tls', 'ssl', 'none'];
        if (! in_array($cryptoInput, $allowedCrypto, true)) {
            session()->setFlashdata('admin_smtp_error', 'Selecione uma segurança SMTP válida.');
            return redirect()->to(site_url('admin/users'));
        }
        $crypto = $cryptoInput === 'none' ? '' : $cryptoInput;

        if ($host !== ''
            && filter_var($host, FILTER_VALIDATE_IP) === false
            && ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host)) {
            session()->setFlashdata('admin_smtp_error', 'Informe um host SMTP válido.');
            return redirect()->to(site_url('admin/users'));
        }

        if ($port < 1 || $port > 65535) {
            session()->setFlashdata('admin_smtp_error', 'Informe uma porta SMTP válida.');
            return redirect()->to(site_url('admin/users'));
        }

        if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            session()->setFlashdata('admin_smtp_error', 'Informe um endereço de remetente válido.');
            return redirect()->to(site_url('admin/users'));
        }

        $settings = new AppSettingModel();
        $currentConfiguration = $settings->smtpConfiguration();
        $passwordConfigured = (string) $currentConfiguration['password_encrypted'] !== '';

        if ($enabled && ($host === '' || $fromEmail === '' || $fromName === '')) {
            session()->setFlashdata('admin_smtp_error', 'Host, remetente e nome do remetente são obrigatórios.');
            return redirect()->to(site_url('admin/users'));
        }

        if ($enabled && $username !== '' && $password === '' && ! $passwordConfigured) {
            session()->setFlashdata('admin_smtp_error', 'Informe a senha da conta SMTP.');
            return redirect()->to(site_url('admin/users'));
        }

        try {
            $passwordEncrypted = $password !== ''
                ? (new SettingsSecretProtector())->encrypt($password)
                : '';
            $settings->setSmtpConfiguration([
                'enabled' => $enabled,
                'host' => $host,
                'port' => $port,
                'crypto' => $crypto,
                'username' => $username,
                'password_encrypted' => $passwordEncrypted,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
            ]);
            session()->setFlashdata(
                'admin_smtp_message',
                $enabled
                    ? 'Conta SMTP habilitada e salva com segurança.'
                    : 'Envio SMTP desabilitado.',
            );
        } catch (\Throwable $exception) {
            log_message('error', 'Falha ao atualizar SMTP: {message}', [
                'message' => $exception->getMessage(),
            ]);
            session()->setFlashdata('admin_smtp_error', $exception->getMessage());
        }

        return redirect()->to(site_url('admin/users'));
    }

    public function testSmtp()
    {
        if (! $this->isAdminLoggedIn()) {
            return redirect()->to(site_url('admin/login'));
        }

        $recipient = strtolower(trim((string) ($this->request->getPost('smtp_test_recipient') ?? '')));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            session()->setFlashdata('admin_smtp_error', 'Informe um destinatário válido para o teste.');
            return redirect()->to(site_url('admin/users'));
        }

        try {
            (new SmtpMailer())->send(
                $recipient,
                'Teste de configuração SMTP do RVScope',
                '<p>Esta mensagem confirma que a configuração SMTP do RVScope está funcionando.</p>',
            );
            session()->setFlashdata('admin_smtp_message', 'E-mail de teste enviado com sucesso.');
        } catch (\Throwable $exception) {
            log_message('error', 'Teste SMTP falhou: {message}', [
                'message' => $exception->getMessage(),
            ]);
            session()->setFlashdata('admin_smtp_error', $exception->getMessage());
        }

        return redirect()->to(site_url('admin/users'));
    }

    public function createUser()
    {
        if (! $this->isAdminLoggedIn()) {
            return redirect()->to(site_url('admin/login'));
        }

        $username = strtolower(trim((string) ($this->request->getPost('username') ?? '')));
        $displayName = trim((string) ($this->request->getPost('display_name') ?? ''));
        $password = (string) ($this->request->getPost('password') ?? '');
        $role = UserAuthorization::normalizeRole((string) ($this->request->getPost('role') ?? 'user'));

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
            'auth_source' => 'local',
            'role' => $role,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        session()->setFlashdata('admin_user_created', 'Usuário criado com sucesso.');

        return redirect()->to(site_url('admin/users'));
    }

    public function updateUser(int $userId)
    {
        if (! $this->isAdminLoggedIn()) {
            return redirect()->to(site_url('admin/login'));
        }

        $userModel = new AdminUserModel();
        $user = $userModel->find($userId);
        if (! is_array($user)) {
            session()->setFlashdata('admin_user_error', 'Usuário não encontrado.');
            return redirect()->to(site_url('admin/users'));
        }

        $role = UserAuthorization::normalizeRole((string) ($this->request->getPost('role') ?? 'user'));
        $isActive = (string) ($this->request->getPost('is_active') ?? '') === '1' ? 1 : 0;
        $currentUserId = (int) session('auth_user_id');

        if ($userId === $currentUserId
            && ($role !== UserAuthorization::ROLE_ADMIN || $isActive !== 1)) {
            session()->setFlashdata('admin_user_error', 'Você não pode remover seu próprio acesso administrativo.');
            return redirect()->to(site_url('admin/users'));
        }

        $wasActiveAdmin = (int) ($user['is_active'] ?? 0) === 1
            && UserAuthorization::normalizeRole((string) ($user['role'] ?? 'user')) === UserAuthorization::ROLE_ADMIN;
        if ($wasActiveAdmin && ($role !== UserAuthorization::ROLE_ADMIN || $isActive !== 1)) {
            $activeAdmins = $userModel
                ->where('role', UserAuthorization::ROLE_ADMIN)
                ->where('is_active', 1)
                ->countAllResults();
            if ($activeAdmins <= 1) {
                session()->setFlashdata('admin_user_error', 'Mantenha pelo menos um administrador ativo.');
                return redirect()->to(site_url('admin/users'));
            }
        }

        $userModel->update($userId, [
            'role' => $role,
            'is_active' => $isActive,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        session()->setFlashdata('admin_user_created', 'Permissões do usuário atualizadas.');

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
            'user_authenticated',
            'auth_username',
            'auth_display_name',
            'auth_source',
            'auth_role',
            'auth_user_id',
        ]);

        return redirect()->to(site_url('admin/login'));
    }

    private function hasAdminAccess(): bool
    {
        return (bool) session('admin_gate_authenticated');
    }

    private function isAdminLoggedIn(): bool
    {
        return UserAuthorization::canAdminister();
    }

    private function currentAdminUser(): ?array
    {
        $userId = (int) (session('auth_user_id') ?: session('admin_user_id'));
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
            'auth_source' => 'local',
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
