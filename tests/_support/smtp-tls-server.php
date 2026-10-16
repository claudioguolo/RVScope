<?php

// Servidor restrito ao loopback, sem autenticação ou entrega de mensagens reais.
[$script, $certificate, $key, $mode] = $argv;
$context = stream_context_create(['ssl' => ['local_cert' => $certificate, 'local_pk' => $key]]);
$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    exit(1);
}
echo substr(strrchr(stream_socket_get_name($server, false), ':'), 1) . "\n";
flush();
$client = stream_socket_accept($server, 5);
if ($client === false) {
    fclose($server);
    exit(2);
}
try {
    stream_set_timeout($client, 5);
    if ($mode === 'ssl' && @stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_SERVER) !== true) {
        exit;
    }
    fwrite($client, "220 localhost ESMTP\r\n");
    while (($line = fgets($client)) !== false) {
        $command = strtoupper(trim($line));
        if (str_starts_with($command, 'EHLO ') || str_starts_with($command, 'HELO ')) {
            fwrite($client, "250-localhost\r\n250 STARTTLS\r\n");
        } elseif ($command === 'STARTTLS') {
            fwrite($client, "220 Ready to start TLS\r\n");
            if (@stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_SERVER) !== true) {
                break;
            }
        } elseif ($command === 'QUIT') {
            fwrite($client, "221 Bye\r\n");
            break;
        } else {
            fwrite($client, "500 Unexpected command\r\n");
        }
    }
} finally {
    fclose($client);
    fclose($server);
}
