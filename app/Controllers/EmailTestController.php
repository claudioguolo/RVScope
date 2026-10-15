<?php

namespace App\Controllers;

use App\Libraries\EmailTestRecipients;
use App\Libraries\EmailTestUsage;
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
            $recipients = EmailTestRecipients::parse((string) $this->request->getPost('recipients'));
            $subject = trim((string) $this->request->getPost('subject'));
            $body = trim((string) $this->request->getPost('body'));
            if ($recipients === [] || $subject === '' || strlen($subject) > 200
                || preg_match('/[\r\n\x00]/', $subject) || $body === '' || strlen($body) > 10000) {
                throw new InvalidArgumentException('Preencha destinatários, assunto (até 200 bytes, sem quebras de linha) e mensagem (até 10.000 bytes).');
            }
            (new AppSettingModel())->setEmailTestConfiguration([
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
            $recipients = EmailTestRecipients::parse($configuration['recipients']);
            if ($recipients === []) {
                throw new InvalidArgumentException('Solicite ao administrador o cadastro dos destinatários padrão.');
            }
            $extras = (string) $this->request->getPost('extra_recipients');
            $recipients = EmailTestRecipients::parse(implode(',', $recipients) . ',' . $extras);
            $username = trim((string) $this->request->getPost('username'));
            $password = (string) $this->request->getPost('password');
            $account = null;
            if ($username !== '' || $password !== '') {
                if (! filter_var($username, FILTER_VALIDATE_EMAIL) || strlen($username) > 254
                    || $password === '' || strlen($password) > 4096) {
                    throw new InvalidArgumentException('Para usar outra conta, informe seu e-mail e a senha.');
                }
                $account = ['username' => $username, 'password' => $password];
            }
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
            (new SmtpMailer())->send($recipients, $configuration['subject'], $configuration['body'], $account, true);
            session()->setFlashdata('email_test_success', 'O servidor SMTP aceitou a mensagem de teste. Isso não confirma a entrega na caixa de entrada.');
        } catch (Throwable $exception) {
            session()->setFlashdata('email_test_error', 'Não foi possível enviar o teste. Verifique se o SMTP está habilitado, as credenciais e a disponibilidade do serviço.');
        }
        return redirect()->to(site_url('tools/email-test'));
    }
}
