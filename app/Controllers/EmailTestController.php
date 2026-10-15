<?php

namespace App\Controllers;

use App\Libraries\EmailTestRecipients;
use App\Libraries\EmailTestUsage;
use App\Libraries\EmailTestRequest;
use App\Libraries\SettingsSecretProtector;
use App\Libraries\SmtpMailer;
use App\Models\AppSettingModel;
use CodeIgniter\Controller;
use InvalidArgumentException;
use Throwable;

class EmailTestController extends Controller
{
    public function index()
    {
        helper(['url', 'form']);
        $this->response->setHeader('Cache-Control', 'no-store');
        return view('tools/email_test', [
            'configuration' => (new AppSettingModel())->emailTestConfiguration(),
            'administrative' => false,
        ]);
    }

    public function settings()
    {
        helper(['url', 'form']);
        $this->response->setHeader('Cache-Control', 'no-store');
        return view('tools/email_test', [
            'configuration' => (new AppSettingModel())->emailTestConfiguration(),
            'administrative' => true,
        ]);
    }

    public function saveSettings()
    {
        try {
            $settings = new AppSettingModel();
            $current = $settings->emailTestConfiguration();
            $username = EmailTestRequest::account((string) $this->request->getPost('test_username'));
            $password = (string) $this->request->getPost('test_password');
            if (strlen($password) > 4096 || ($password === '' && ($current['password_encrypted'] === '' || $current['username'] !== $username))) {
                throw new InvalidArgumentException('Informe a senha ao cadastrar ou trocar a conta de testes.');
            }
            $encrypted = $password !== '' ? (new SettingsSecretProtector())->encrypt($password) : '';
            $recipients = EmailTestRecipients::parse((string) $this->request->getPost('recipients'));
            $subject = trim((string) $this->request->getPost('subject'));
            $body = trim((string) $this->request->getPost('body'));
            if ($recipients === [] || $subject === '' || strlen($subject) > 200
                || preg_match('/[\r\n\x00]/', $subject) || $body === '' || strlen($body) > 10000) {
                throw new InvalidArgumentException('Preencha destinatários, assunto (até 200 bytes, sem quebras de linha) e mensagem (até 10.000 bytes).');
            }
            (new AppSettingModel())->setEmailTestConfiguration([
                'username' => $username, 'password_encrypted' => $encrypted,
                'recipients' => implode(', ', $recipients), 'subject' => $subject, 'body' => $body,
            ]);
            session()->setFlashdata('email_test_success', 'Configuração do teste salva.');
        } catch (InvalidArgumentException $exception) {
            session()->setFlashdata('email_test_error', $exception->getMessage());
        } catch (Throwable $exception) {
            session()->setFlashdata('email_test_error', 'Não foi possível salvar a configuração do teste.');
        }
        return redirect()->to(site_url('admin/email-test'));
    }

    public function send()
    {
        try {
            $configuration = (new AppSettingModel())->emailTestConfiguration();
            $resolved = EmailTestRequest::resolve([
                'mode' => $this->request->getPost('mode'),
                'username' => $this->request->getPost('username'),
                'password' => $this->request->getPost('password'),
                'custom_recipients' => $this->request->getPost('custom_recipients'),
            ], $configuration['recipients']);
        } catch (InvalidArgumentException $exception) {
            session()->setFlashdata('email_test_error', $exception->getMessage());
            return redirect()->to(site_url('tools/email-test'));
        } catch (Throwable $exception) {
            session()->setFlashdata('email_test_error', 'A configuração do teste está indisponível.');
            return redirect()->to(site_url('tools/email-test'));
        }
        try {
            (new EmailTestUsage())->record((int) (session('auth_user_id') ?: session('admin_user_id')));
        } catch (Throwable $exception) {
            session()->setFlashdata('email_test_error', $exception->getMessage());
            return redirect()->to(site_url('tools/email-test'));
        }
        try {
            (new SmtpMailer())->sendTest($resolved['recipients'], $configuration, $resolved['account']);
            session()->setFlashdata('email_test_success', 'O servidor SMTP aceitou a mensagem de teste. Isso não confirma a entrega na caixa de entrada.');
        } catch (Throwable $exception) {
            session()->setFlashdata('email_test_error', 'Não foi possível enviar o teste. Verifique se o SMTP está habilitado, as credenciais e a disponibilidade do serviço.');
        }
        return redirect()->to(site_url('tools/email-test'));
    }
}
