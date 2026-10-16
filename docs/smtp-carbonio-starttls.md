# Diagnóstico de STARTTLS no Carbonio — porta 587

O Carbonio documenta a porta TCP 587 no componente MTA para submissão SMTP
autenticada com STARTTLS. A porta 465 usa TLS implícito. No RVScope, selecione
porta 587 e STARTTLS e salve a configuração. O botão de preenchimento não salva
automaticamente. O servidor SMTP vem de **Conta remetente SMTP**; o domínio
`emailTest.domain` controla as contas USER/FROM, não o host do servidor.

A partir do RVScope 1.3.6, a falha mostra etapa, host, porta, segurança e códigos
SMTP disponíveis. Use esses dados para escolher o teste abaixo: uma recusa
454 na solicitação STARTTLS difere de falha de certificado na negociação TLS,
e uma resposta 535 na autenticação ocorre em outra etapa. O RVScope não mostra
o transcript, as credenciais ou o texto bruto retornado pelo servidor.

Fonte: [requisitos de rede do Carbonio](https://docs.zextras.com/carbonio/html/install/requirements.html).

## 1. Testar a partir do container do RVScope

Substitua `smtp.exemplo.org` pelo mesmo nome usado no campo Servidor SMTP.
O teste negocia TLS, verifica confiança e hostname, sem autenticar ou enviar
e-mail. Executá-lo dentro do container testa a rede e as CAs desse ambiente.

```bash
docker exec -i rvscope_app timeout 20 openssl s_client \
  -connect smtp.exemplo.org:587 \
  -starttls smtp \
  -servername smtp.exemplo.org \
  -verify_hostname smtp.exemplo.org \
  -verify_return_error \
  -brief </dev/null
```

Procure `Verification: OK` e uma versão TLS negociada. `-servername` envia
SNI; `-verify_hostname` valida o nome; `-verify_return_error` impede que falhas
de certificado sejam ignoradas. Uma negociação bem-sucedida não comprova
autenticação ou entrega de mensagens.

Fonte: [OpenSSL s_client](https://docs.openssl.org/3.0/man1/openssl-s_client/).

| Resultado | O que verificar |
|---|---|
| Timeout ou conexão recusada | Listener 587, firewall, NAT, DNS e caminho até o nó MTA |
| STARTTLS não anunciado/aceito | Serviço submission, parâmetros TLS e eventual proxy na conexão |
| Certificado não confiável | Cadeia intermediária enviada pelo MTA e CA confiável no container |
| Nome do certificado diferente | SAN do certificado deve cobrir o host SMTP configurado |
| Certificado expirado | Certificado efetivamente apresentado na porta 587 |
| TLS funciona, mas envio falha | Autenticação SASL, conta/senha, remetente, destinatário e política de relay |

Se usar CA interna, disponibilize a cadeia pública de confiança no container
e no PHP/OpenSSL. A CA instalada apenas no host Docker não é automaticamente
confiável dentro da aplicação. Não desabilite verificação TLS como solução.

## 2. Consultar o MTA no servidor Carbonio

Execute no nó responsável por receber as conexões SMTP. Consulte o listener:

```bash
sudo ss -ltnp 'sport = :587'
```

Nas instalações com os binários Carbonio em `/opt/zextras`, consulte como
usuário `zextras` (ajuste caminho/serviço conforme a versão):

```bash
sudo -iu zextras /opt/zextras/common/sbin/postconf -M submission/inet
sudo -iu zextras /opt/zextras/common/sbin/postconf -P submission/inet
sudo -iu zextras /opt/zextras/common/sbin/postconf \
  smtpd_tls_security_level smtpd_tls_wrappermode \
  smtpd_tls_cert_file smtpd_tls_key_file \
  smtpd_sasl_auth_enable smtpd_tls_auth_only
sudo -iu zextras /opt/zextras/bin/zmcertmgr viewdeployedcrt
```

Esses comandos são de leitura, sem `postconf -e`, atribuições ou reinícios.
O caminho da chave privada pode aparecer na configuração, mas seu conteúdo
não deve ser exibido. Se o listener se chamar `587/inet`, consulte esse nome
em vez de `submission/inet`. Parâmetros específicos do serviço apresentados
por `postconf -P` têm precedência sobre os valores globais de `postconf`.

Fontes: [parâmetros por serviço no Postfix](https://www.postfix.org/wip.html)
e [certificados de infraestrutura do Carbonio](https://docs.zextras.com/carbonio/html/admincli/security/sslcert.html).

Para o serviço da porta 587, confira os valores efetivos:

- `smtpd_tls_security_level = may`: oferece STARTTLS opcional;
- `smtpd_tls_security_level = encrypt`: exige STARTTLS;
- `smtpd_tls_wrappermode = no`: usa negociação STARTTLS, em vez de TLS implícito;
- `smtpd_sasl_auth_enable = yes`: habilita autenticação SMTP;
- certificado e chave disponíveis ao MTA, com cadeia e hostname corretos.

AUTH pode ser anunciado somente depois do TLS quando a política exige uma
conexão criptografada. Isso é esperado e não significa, sozinho, falha de SASL.
Esses valores são critérios de diagnóstico, não instruções para modificar
globalmente a instalação. Em especial, não imponha TLS globalmente à porta 25
para tentar corrigir o serviço de submissão da porta 587.

Fonte: [TLS e AUTH no Postfix](https://www.postfix.org/TLS_README.html).

Um certificado válido no webmail/443 não comprova que o MTA apresenta o mesmo
certificado em 587. O Carbonio distingue certificados de domínio/virtual host
e de infraestrutura. Compare o certificado de MTA mostrado por `zmcertmgr`
com o apresentado ao cliente e confira validade, SAN e cadeia.

Fonte: [certificados de domínio e infraestrutura](https://docs.zextras.com/carbonio/html/adminpanel/domains/details.html#virtual-hosts-domain-certificates).

## 3. Correlacionar com os logs

Consulte os logs do componente MTA para o horário e IP da tentativa. Os caminhos
e unidades de serviço variam conforme a versão e configuração de logs do
Carbonio. Procure falhas de handshake/certificado, respostas de autenticação e
recusas de relay. Remova senhas, tokens e dados de autenticação antes de
compartilhar qualquer trecho.

Para prescrever a alteração persistente no Carbonio, registre edição/versão,
sistema operacional e os resultados dessas consultas. A configuração gerenciada
deve ser alterada pelo mecanismo suportado nessa versão; este roteiro não
executa alterações no Carbonio. O RVScope mantém a validação de certificados.
