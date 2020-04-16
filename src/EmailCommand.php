<?php


namespace Karlerss\Mailsplit;


use Ahc\Cli\Input\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use PhpImap\Mailbox;
use Symfony\Component\DomCrawler\Crawler;

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

    protected Client $client;

    public function authCalendar(string $uri)
    {
        $jar = new CookieJar;

        $this->client = new Client([
            'cookies' => $jar,
            'verify' => false,
        ]);

        $res = $this->client->get($uri)->getBody()->getContents();
        $c = new Crawler($res);
        $nonce = $c->filter('.sclogin-joomla-login form input')->last();
        $return = $c->filter('.sclogin-joomla-login form input[name="return"]')->first();
        $modId = $c->filter('.sclogin-joomla-login form input[name="mod_id"]')->first();

        $formParams = [
            'username' => getenv('CAL_USER'),
            'password' => getenv('CAL_PASS'),
            'Submit' => '',
            'remember' => '',
            'option' => 'com_users',
            'task' => 'user.login',
            'return' => $return->attr('value'),
            'mod_id' => $modId->attr('value'),
            $nonce->attr('name') => '1',
        ];
        $res2 = $this->client->post($uri, [
            'form_params' => $formParams,
        ])->getBody()->getContents();

        assert(preg_match('/logout-button/', $res2));
    }
}