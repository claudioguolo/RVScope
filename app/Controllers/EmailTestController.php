<?php

namespace App\Controllers;

use App\Libraries\EmailTestRecipients;
use App\Libraries\EmailTestUsage;
use App\Libraries\EmailTestRequest;
use App\Libraries\SettingsSecretProtector;
use App\Libraries\SmtpMailer;
use App\Libraries\SmtpDeliveryException;
use App\Libraries\SmtpTransportConfiguration;
use App\Models\AppSettingModel;
use CodeIgniter\Controller;
use InvalidArgumentException;
use Throwable;

class EmailTestController extends Controller
{
    public function index()
    {
        return $this->render(false);
    }

    public function settings()
    {
        return $this->render(true);
    }

    private function render(bool $administrative)
    {
        helper(['url', 'form']);
        $this->response->setHeader('Cache-Control', 'no-store');
        $domain = '';
        $domainError = null;
        try {
            $domain = EmailTestRequest::domain();
        } catch (InvalidArgumentException $exception) {
            $domainError = $administrative
                ? $exception->getMessage()
                : 'O domínio das contas de teste está indisponível. Solicite a correção ao administrador.';
        }
        return view('tools/email_test', [
            'configuration' => (new AppSettingModel())->emailTestConfiguration(),
            'administrative' => $administrative,
            'domain' => $domain,
            'domainError' => $domainError,
        ]);
    }

    public function saveSettings()
    {
        try {
            $settings = new AppSettingModel();
            $current = $settings->emailTestConfiguration();
            $transport = SmtpTransportConfiguration::fromArray([
                'port' => $this->request->getPost('test_smtp_port'),
                'crypto' => $this->request->getPost('test_smtp_crypto'),
            ], $current['port'], $current['crypto']);
            $interval = EmailTestUsage::validateInterval(
                $this->request->getPost('test_interval_seconds') ?? $current['interval_seconds'],
            );
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
                'port' => $transport['port'], 'crypto' => $transport['crypto'],
                'interval_seconds' => $interval,
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
            EmailTestRequest::domain();
            $configuration = (new AppSettingModel())->emailTestConfiguration();
            $resolved = EmailTestRequest::resolve([
                'mode' => $this->request->getPost('mode'),
                'username' => $this->request->getPost('username'),
                'password' => $this->request->getPost('password'),
                'custom_recipients' => $this->request->getPost('custom_recipients'),
            ], $configuration['recipients']);
            if ($resolved['account'] === null && ! EmailTestRequest::matchesDomain($configuration['username'])) {
                throw new InvalidArgumentException('Solicite ao administrador o cadastro da conta de testes no domínio configurado para este ambiente.');
            }
        } catch (InvalidArgumentException $exception) {
            session()->setFlashdata('email_test_error', $exception->getMessage());
            return redirect()->to(site_url('tools/email-test'));
        } catch (Throwable $exception) {
            session()->setFlashdata('email_test_error', 'A configuração do teste está indisponível.');
            return redirect()->to(site_url('tools/email-test'));
        }
        try {
            (new EmailTestUsage())->record(
                (int) (session('auth_user_id') ?: session('admin_user_id')),
                $configuration['interval_seconds'],
            );
        } catch (Throwable $exception) {
            session()->setFlashdata('email_test_error', $exception->getMessage());
            return redirect()->to(site_url('tools/email-test'));
        }
        try {
            (new SmtpMailer())->sendTest($resolved['recipients'], $configuration, $resolved['account']);
            session()->setFlashdata('email_test_success', 'O servidor SMTP aceitou a mensagem de teste. Isso não confirma a entrega na caixa de entrada.');
        } catch (SmtpDeliveryException $exception) {
            session()->setFlashdata('email_test_error', 'Não foi possível enviar o teste. ' . $exception->getMessage());
        } catch (Throwable $exception) {
            session()->setFlashdata('email_test_error', 'Não foi possível enviar o teste. Verifique se o SMTP está habilitado, as credenciais e a disponibilidade do serviço.');
        }
        return redirect()->to(site_url('tools/email-test'));
    }
}
