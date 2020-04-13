<?php


namespace Karlerss\Mailsplit;


use Ahc\Cli\Input\Command;
use PhpImap\Mailbox;

abstract class EmailCommand extends Command
{
    public function getMailbox(): Mailbox
    {
        return new \PhpImap\Mailbox(
            getenv('IMAP_PATH'),
            getenv('IMAP_LOGIN'),
            getenv('IMAP_PASS'),
            __DIR__,
            'UTF-8'
        );
    }

    public function getTransport(): \Swift_SmtpTransport
    {
        $transport = new \Swift_SmtpTransport(
            getenv('SMTP_HOST'),
            getenv('SMTP_PORT'),
            getenv('SMTP_ENCRYPTION')
        );
        $transport->setUsername(getenv('SMTP_USERNAME'));
        $transport->setPassword(getenv('SMTP_PASSWORD'));
        return $transport;
    }
}