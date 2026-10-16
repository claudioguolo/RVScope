<?php

namespace Tests\Unit;

use App\Libraries\SmtpDiagnostic;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmtpDiagnosticTest extends TestCase
{
    public function testReportsStarttlsRejectionWithoutExposingServerText(): void
    {
        $diagnostic = new SmtpDiagnostic('smtp.example.org', 587, 'tls');
        $diagnostic->begin('starttls');
        $diagnostic->reply("454 4.7.0 TLS unavailable: password=secret-token user@example.org\r\n");
        $message = $diagnostic->message();
        self::assertStringContainsString('solicitação STARTTLS', $message);
        self::assertStringContainsString('smtp.example.org:587', $message);
        self::assertStringContainsString('454 (4.7.0)', $message);
        self::assertStringNotContainsString('secret-token', $message);
        self::assertStringNotContainsString('user@example.org', $message);
    }

    #[DataProvider('knownProblems')]
    public function testClassifiesNetworkAndTlsErrors(string $error, string $expected): void
    {
        $diagnostic = new SmtpDiagnostic('smtp.example.org', 587, 'tls');
        $diagnostic->begin('tls');
        $diagnostic->problem($error . ' password=secret-token');
        self::assertStringContainsString($expected, $diagnostic->message());
        self::assertStringNotContainsString('secret-token', $diagnostic->message());
    }

    public static function knownProblems(): array
    {
        return [
            ['failed loading cafile stream: /private/path/secret-token', 'conferir smtp.caFile'],
            ['SSL operation failed: certificate verify failed', 'não conseguiu validar o certificado'],
            ['Peer certificate CN did not match expected CN', 'nome do certificado não corresponde'],
            ['certificate has expired', 'fora do período de validade'],
            ['php_network_getaddresses: getaddrinfo failed', 'resolver o nome'],
            ['Connection refused', 'conexão TCP foi recusada'],
            ['Connection timed out', 'Tempo de espera excedido (10 segundos)'],
            ['SSL: wrong version number', 'Falha de protocolo TLS'],
            ['Connection reset by peer', 'conexão foi encerrada'],
        ];
    }

    public function testStageClearsOldReplyCodes(): void
    {
        $diagnostic = new SmtpDiagnostic('smtp.example.org', 587, 'tls');
        $diagnostic->begin('starttls');
        $diagnostic->reply('220 Ready to start TLS');
        $diagnostic->begin('tls');
        $diagnostic->problem('certificate verify failed');
        self::assertStringNotContainsString('Código SMTP', $diagnostic->message());
    }

    public function testAuthenticationFailureIncludesOnlyCodesAndSafeGuidance(): void
    {
        $diagnostic = new SmtpDiagnostic('smtp.example.org', 587, 'tls');
        $diagnostic->begin('authentication');
        $diagnostic->reply('535 5.7.8 invalid account: secret-token');
        self::assertStringContainsString('autenticação SMTP', $diagnostic->message());
        self::assertStringContainsString('535 (5.7.8)', $diagnostic->message());
        self::assertStringNotContainsString('secret-token', $diagnostic->message());
    }

    public function testUnknownErrorAndMalformedHostCannotLeakSecrets(): void
    {
        $diagnostic = new SmtpDiagnostic('user:secret-token@smtp.example.org', 587, 'tls');
        $diagnostic->problem('unknown error: secret-token');
        self::assertStringContainsString('(não configurado)', $diagnostic->message());
        self::assertStringNotContainsString('secret-token', $diagnostic->message());
    }
}
