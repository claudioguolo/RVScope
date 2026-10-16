<?php

namespace Tests\Integration;

use App\Libraries\PrivateSmtpEmail;
use App\Libraries\SmtpDeliveryException;
use ErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmtpTlsTest extends TestCase
{
    private static string $directory;

    public static function setUpBeforeClass(): void
    {
        self::$directory = sys_get_temp_dir() . '/rvscope-smtp-tls-' . bin2hex(random_bytes(8));
        mkdir(self::$directory, 0700);
        $configuration = self::$directory . '/openssl.cnf';
        file_put_contents($configuration, "[req]\ndistinguished_name=dn\n[dn]\n[ca]\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,cRLSign\n[server]\nbasicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature,keyEncipherment\nextendedKeyUsage=serverAuth\nsubjectAltName=DNS:localhost\n");
        $options = ['config' => $configuration, 'digest_alg' => 'sha256'];
        $caKey = openssl_pkey_new(['private_key_bits' => 2048]);
        $caRequest = openssl_csr_new(['commonName' => 'RVScope Test CA'], $caKey, $options);
        $ca = openssl_csr_sign($caRequest, null, $caKey, 1, $options + ['x509_extensions' => 'ca']);
        openssl_x509_export_to_file($ca, self::$directory . '/ca.crt');
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $request = openssl_csr_new(['commonName' => 'localhost'], $key, $options);
        $certificate = openssl_csr_sign($request, $ca, $caKey, 1, $options + ['x509_extensions' => 'server']);
        openssl_x509_export_to_file($certificate, self::$directory . '/server.crt');
        openssl_pkey_export_to_file($key, self::$directory . '/server.key');
        chmod(self::$directory . '/server.key', 0600);
        file_put_contents(self::$directory . '/invalid.crt', 'Not a PEM certificate');
    }

    public static function tearDownAfterClass(): void
    {
        foreach (glob(self::$directory . '/*') as $file) {
            unlink($file);
        }
        rmdir(self::$directory);
    }

    #[DataProvider('tlsConnections')]
    public function testValidatesRealTlsConnections(string $mode, string $host, string $ca, bool $expectedSuccess): void
    {
        $defaultContext = stream_context_get_options(stream_context_get_default());
        $process = proc_open([
            PHP_BINARY, __DIR__ . '/../_support/smtp-tls-server.php',
            self::$directory . '/server.crt', self::$directory . '/server.key', $mode,
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        try {
            stream_set_timeout($pipes[1], 5);
            $port = (int) fgets($pipes[1]);
            self::assertGreaterThan(0, $port, 'O servidor SMTP local deve iniciar.');
            $email = new SmtpTlsProbe([
                'SMTPHost' => $host, 'SMTPPort' => $port, 'SMTPCrypto' => $mode,
                'SMTPTimeout' => 2,
            ]);
            $email->setTrustedCaFile($ca === '' ? '' : self::$directory . '/' . $ca);
            try {
                self::assertTrue($email->probe());
                self::assertTrue($expectedSuccess, 'O certificado inválido não pode ser aceito.');
            } catch (ErrorException $exception) {
                self::assertFalse($expectedSuccess, $exception->getMessage());
                self::assertMatchesRegularExpression('/certificate|cafile|Peer certificate/i', $exception->getMessage());
            }
            self::assertSame($defaultContext, stream_context_get_options(stream_context_get_default()));
        } finally {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_terminate($process);
            proc_close($process);
        }
    }

    public static function tlsConnections(): array
    {
        return [
            'STARTTLS com CA confiável' => ['tls', 'localhost', 'ca.crt', true],
            'TLS implícito com CA confiável' => ['ssl', 'localhost', 'ca.crt', true],
            'STARTTLS com CA ausente' => ['tls', 'localhost', '', false],
            'TLS implícito com CA ausente' => ['ssl', 'localhost', '', false],
            'STARTTLS com hostname divergente' => ['tls', '127.0.0.1', 'ca.crt', false],
            'TLS implícito com hostname divergente' => ['ssl', '127.0.0.1', 'ca.crt', false],
            'CA em formato inválido' => ['tls', 'localhost', 'invalid.crt', false],
            'SMTP sem TLS preservado' => ['', 'localhost', '', true],
        ];
    }

    public function testRejectsMissingAndRemoteCaFilesWithoutExposingPaths(): void
    {
        foreach ([self::$directory . '/missing-private-path.crt', 'https://example.org/ca.crt', 'relative.crt'] as $path) {
            try {
                (new SmtpTlsProbe())->setTrustedCaFile($path);
                self::fail('A CA inválida deve ser recusada.');
            } catch (SmtpDeliveryException $exception) {
                self::assertStringContainsString('smtp.caFile', $exception->getMessage());
                self::assertStringNotContainsString($path, $exception->getMessage());
            }
        }
    }
}

/** Exercita o transporte real sem inicializar banco, sessão ou aplicação web. */
final class SmtpTlsProbe extends PrivateSmtpEmail
{
    protected function getHostname()
    {
        return 'localhost';
    }

    public function probe(): bool
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        }, E_WARNING);
        try {
            return $this->SMTPConnect();
        } finally {
            restore_error_handler();
            if (is_resource($this->SMTPConnect)) {
                fclose($this->SMTPConnect);
            }
            $this->SMTPConnect = null;
        }
    }
}
