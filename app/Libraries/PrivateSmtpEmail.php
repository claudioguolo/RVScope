<?php

namespace App\Libraries;

use CodeIgniter\Email\Email;
use ErrorException;
use RuntimeException;
use Throwable;

/** Evita que o transporte grave o transcript SMTP e os cabeçalhos em logs. */
class PrivateSmtpEmail extends Email
{
    private ?SmtpDiagnostic $diagnostic = null;
    private bool $cleanupCommand = false;
    private string $trustedCaFile = '';

    public function setTrustedCaFile(string $path): void
    {
        $path = trim($path);
        if ($path !== '' && (! str_starts_with($path, '/') || ! is_file($path) || ! is_readable($path))) {
            throw new SmtpDeliveryException('Configuração TLS: o arquivo da CA SMTP não existe ou não pode ser lido pela aplicação. O administrador deve conferir smtp.caFile no ambiente e a montagem do certificado público no container.');
        }
        $this->trustedCaFile = $path;
    }

    public function failureMessage(): string
    {
        return $this->diagnostic?->message() ?? 'Não foi possível preparar a mensagem. Verifique a configuração do remetente SMTP.';
    }

    protected function spoolEmail()
    {
        $this->diagnostic = new SmtpDiagnostic($this->SMTPHost, $this->SMTPPort, $this->SMTPCrypto, $this->SMTPTimeout);
        $this->unwrapSpecials();
        $success = false;
        // Intercepte avisos de rede/OpenSSL antes que o handler global os registre.
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        }, E_WARNING | E_USER_WARNING);
        try {
            $success = $this->sendWithSmtp();
            return $success;
        } catch (Throwable $exception) {
            $this->diagnostic->problem($exception->getMessage());
            return false;
        } finally {
            restore_error_handler();
            // Evite que o destrutor tente QUIT e registre uma segunda falha com dados brutos.
            if (! $success && is_resource($this->SMTPConnect)) {
                fclose($this->SMTPConnect);
                $this->SMTPConnect = null;
            }
        }
    }

    protected function SMTPConnect()
    {
        $this->diagnostic?->begin('connection');
        if ($this->isSMTPConnected()) {
            return true;
        }

        // Contexto exclusivo desta conexão: não afeta HTTPS, LDAPS ou outros streams.
        $ssl = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => $this->SMTPHost,
        ];
        if ($this->trustedCaFile !== '') {
            $ssl['cafile'] = $this->trustedCaFile;
        }
        $host = str_contains($this->SMTPHost, ':') ? '[' . $this->SMTPHost . ']' : $this->SMTPHost;
        $this->SMTPConnect = stream_socket_client(
            'tcp://' . $host . ':' . $this->SMTPPort,
            $errorCode,
            $errorMessage,
            $this->SMTPTimeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => $ssl]),
        );
        if (! is_resource($this->SMTPConnect)) {
            throw new RuntimeException('Não foi possível abrir a conexão SMTP.');
        }
        stream_set_timeout($this->SMTPConnect, $this->SMTPTimeout);

        $implicitTls = $this->SMTPCrypto === 'ssl' || $this->SMTPPort === 465;
        if ($implicitTls) {
            $this->enableVerifiedTls();
        }
        if (! str_starts_with($this->getSMTPData(), '220')) {
            throw new RuntimeException('Saudação inicial SMTP recusada.');
        }
        if (! $implicitTls && $this->SMTPCrypto === 'tls') {
            $this->sendCommand('hello');
            $this->sendCommand('starttls');
            $this->enableVerifiedTls();
        }
        return $this->sendCommand('hello');
    }

    private function enableVerifiedTls(): void
    {
        $this->diagnostic?->begin('tls');
        if (stream_socket_enable_crypto($this->SMTPConnect, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
            throw new RuntimeException('A negociação TLS não foi concluída.');
        }
    }

    protected function SMTPAuthenticate()
    {
        $this->diagnostic?->begin('authentication');
        return parent::SMTPAuthenticate();
    }

    protected function sendCommand($cmd, $data = '')
    {
        if (in_array($cmd, ['quit', 'reset'], true)) {
            $this->cleanupCommand = true;
            try {
                return parent::sendCommand($cmd, $data);
            } catch (Throwable $exception) {
                // Uma falha no QUIT não altera a confirmação de entrega já recebida.
                return false;
            } finally {
                $this->cleanupCommand = false;
                if ($cmd === 'quit') {
                    if (is_resource($this->SMTPConnect)) {
                        fclose($this->SMTPConnect);
                    }
                    $this->SMTPConnect = null;
                }
            }
        }
        $stage = match ($cmd) {
            'hello' => 'greeting', 'starttls' => 'starttls', 'from' => 'sender',
            'to' => 'recipient', 'data' => 'data', default => 'message',
        };
        $this->diagnostic?->begin($stage);
        $result = parent::sendCommand($cmd, $data);
        if (! $result) {
            // Em especial, não tente o handshake se STARTTLS foi recusado.
            throw new RuntimeException('Comando SMTP recusado.');
        }
        if ($cmd === 'starttls' || $cmd === 'data') {
            $this->diagnostic?->begin($cmd === 'starttls' ? 'tls' : 'message');
        }
        return $result;
    }

    protected function getSMTPData()
    {
        $reply = parent::getSMTPData();
        if (! $this->cleanupCommand) {
            $this->diagnostic?->reply($reply);
            if ($reply === '' && is_resource($this->SMTPConnect)) {
                $metadata = stream_get_meta_data($this->SMTPConnect);
                $this->diagnostic?->problem(! empty($metadata['timed_out']) ? 'timed out' : 'connection closed');
                throw new RuntimeException('O servidor SMTP não respondeu.');
            }
        }
        return $reply;
    }
}
