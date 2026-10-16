<?php

namespace App\Libraries;

final class SmtpDiagnostic
{
    private string $stage = 'connection';
    private ?string $reason = null;
    private ?string $replyCode = null;
    private ?string $enhancedCode = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $crypto,
        private readonly int $timeout = 10,
    ) {
    }

    public function begin(string $stage): void
    {
        $this->stage = $stage;
        $this->reason = $this->replyCode = $this->enhancedCode = null;
    }

    public function reply(string $reply): void
    {
        // O texto da resposta pode conter credenciais ou endereços: retenha apenas códigos.
        if (preg_match('/\A([2-5][0-9]{2})[ -](?:([245]\.[0-9]{1,3}\.[0-9]{1,3})(?:\s|$))?/', $reply, $matches)) {
            $this->replyCode = $matches[1];
            $this->enhancedCode = $matches[2] ?? null;
        }
    }

    public function problem(string $message): void
    {
        $message = strtolower($message);
        $reasons = [
            'ca_file' => ['failed loading cafile', 'failed to load cafile', 'error setting certificate file', 'failed loading capath'],
            'certificate_name' => ['did not match expected', 'hostname mismatch', 'peer name'],
            'certificate_expired' => ['certificate has expired', 'certificate expired', 'certificate is not yet valid'],
            'certificate_trust' => ['certificate verify failed', 'self-signed certificate', 'self signed certificate', 'unable to get local issuer', 'unable to verify the first certificate'],
            'dns' => ['getaddrinfo', 'php_network_getaddresses', 'name or service not known', 'nodename nor servname'],
            'refused' => ['connection refused'],
            'timeout' => ['timed out', 'timeout'],
            'tls_protocol' => ['wrong version number', 'unsupported protocol', 'no shared cipher', 'handshake failure'],
            'closed' => ['connection reset', 'broken pipe', 'unexpected eof', 'connection closed'],
        ];
        foreach ($reasons as $reason => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($message, $pattern)) {
                    $this->reason = $reason;
                    if (str_starts_with($reason, 'certificate_') || in_array($reason, ['tls_protocol', 'ca_file'], true)) {
                        $this->stage = 'tls';
                    }
                    return;
                }
            }
        }
    }

    public function message(): string
    {
        $stages = [
            'connection' => 'conexão TCP', 'greeting' => 'saudação SMTP (EHLO/HELO)',
            'starttls' => 'solicitação STARTTLS', 'tls' => 'negociação TLS',
            'authentication' => 'autenticação SMTP', 'sender' => 'aceitação do remetente',
            'recipient' => 'aceitação do destinatário', 'data' => 'início do envio (DATA)',
            'message' => 'confirmação da mensagem',
        ];
        $hints = [
            'connection' => 'Verifique conectividade, porta, firewall e disponibilidade do SMTP.',
            'greeting' => 'O servidor não concluiu a saudação SMTP esperada.',
            'starttls' => 'O servidor recusou o comando STARTTLS. Verifique suporte e configuração TLS no serviço dessa porta.',
            'tls' => 'A negociação TLS não foi concluída. Verifique certificado, cadeia de confiança e protocolos do servidor.',
            'authentication' => 'A autenticação não foi concluída. Verifique conta, senha, método AUTH LOGIN e políticas de acesso do servidor.',
            'sender' => 'O servidor recusou o remetente. Verifique se a conta pode enviar com esse FROM.',
            'recipient' => 'O servidor recusou um destinatário. Verifique endereço e política de relay.',
            'data' => 'O servidor recusou o início do envio da mensagem.',
            'message' => 'O servidor não confirmou a aceitação da mensagem. Verifique limites e políticas de envio.',
        ];
        $reasons = [
            'ca_file' => 'Não foi possível carregar a CA SMTP. O administrador deve conferir smtp.caFile, o formato PEM e as permissões do arquivo no container.',
            'certificate_name' => 'O nome do certificado não corresponde ao host SMTP. Confira o SAN do certificado apresentado pelo MTA.',
            'certificate_expired' => 'O certificado TLS está fora do período de validade. Confira validade e relógio dos servidores.',
            'certificate_trust' => 'O PHP/OpenSSL não conseguiu validar o certificado TLS (certificate verify failed). Confira validade, cadeia intermediária e CA confiável no container. Para CA interna, o administrador pode configurar smtp.caFile com a cadeia pública confiável.',
            'dns' => 'Não foi possível resolver o nome do servidor SMTP. Verifique o host e o DNS do container.',
            'refused' => 'A conexão TCP foi recusada. Verifique se existe um serviço escutando nessa porta.',
            'timeout' => 'Tempo de espera excedido (' . $this->timeout . ' segundos). Verifique resposta do servidor e conectividade.',
            'tls_protocol' => 'Falha de protocolo TLS. Confira STARTTLS na porta 587, TLS implícito na 465 e os protocolos aceitos pelo servidor.',
            'closed' => 'A conexão foi encerrada pelo servidor antes da conclusão. Confira os logs do MTA.',
        ];
        $host = filter_var($this->host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || filter_var($this->host, FILTER_VALIDATE_IP) ? $this->host : '(não configurado)';
        $security = match (true) {
            $this->crypto === 'ssl' || $this->port === 465 => 'SSL/TLS',
            $this->crypto === 'tls' => 'STARTTLS',
            default => 'sem criptografia',
        };
        $message = 'Etapa: ' . ($stages[$this->stage] ?? 'envio SMTP') . '. Servidor: ' . $host . ':' . $this->port
            . '. Segurança: ' . $security . '. ' . ($reasons[$this->reason] ?? $hints[$this->stage] ?? $hints['connection']);
        if ($this->replyCode !== null) {
            $message .= ' Código SMTP: ' . $this->replyCode . ($this->enhancedCode !== null ? ' (' . $this->enhancedCode . ')' : '') . '.';
        }
        return $message;
    }
}
