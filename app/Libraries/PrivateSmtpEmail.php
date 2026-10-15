<?php

namespace App\Libraries;

use CodeIgniter\Email\Email;
use Throwable;

/** Evita que o transporte grave o transcript SMTP e os cabeçalhos em logs. */
class PrivateSmtpEmail extends Email
{
    protected function spoolEmail()
    {
        $this->unwrapSpecials();
        try {
            return $this->sendWithSmtp();
        } catch (Throwable $exception) {
            return false;
        }
    }
}
