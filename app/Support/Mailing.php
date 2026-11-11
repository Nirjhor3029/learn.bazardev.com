<?php

namespace App\Support;

class Mailing
{
    /**
     * True once a real mailer (SMTP etc.) is configured in .env.
     *
     * While MAIL_MAILER is still "log", emails are only written to the log file,
     * so the UI tells people an admin will verify them instead of promising an email.
     */
    public static function isLive(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }
}
