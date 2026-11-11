<?php

namespace App\Console\Commands;

use App\Support\Mailing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailTest extends Command
{
    protected $signature = 'app:mail-test {to : Address to send the test email to}';

    protected $description = 'Send one test email, to confirm the SMTP settings in .env actually work.';

    public function handle(): int
    {
        if (! Mailing::isLive()) {
            $this->warn('MAIL_MAILER is "'.config('mail.default').'" — emails are not really sent yet.');
            $this->line('Set MAIL_MAILER=smtp and the MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD values in .env, run "php artisan config:clear", then try again.');

            return self::FAILURE;
        }

        try {
            Mail::raw('This is a test email from '.config('app.name').'. If you can read this, SMTP works.', function ($message): void {
                $message->to($this->argument('to'))->subject('SMTP test — '.config('app.name'));
            });
        } catch (Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent. Check the inbox (and spam folder) of '.$this->argument('to').'.');

        return self::SUCCESS;
    }
}
