<?php


namespace Karlerss\Mailsplit;


use Ahc\Cli\Application as App;
use Ahc\Cli\Input\Command;
use PhpImap\IncomingMail;
use PhpImap\Mailbox;
use Swift_Mailer;
use Swift_Message;

class Forward extends EmailCommand
{
    public function __construct(string $name = 'forward', string $desc = '', bool $allowUnknown = false, App $app = null)
    {
        parent::__construct($name, $desc, $allowUnknown, $app);
    }


    public function execute()
    {
        $forwards = $this->getForwards();
        $mailbox = $this->getMailbox();
        $today = date('Y-m-d');

        foreach ($forwards as $fwd) {
            $mailIds = $mailbox->searchMailbox('TEXT "' . $fwd['phrase'] . '" SINCE "' . $today . '"');
            foreach ($mailIds as $id) {
                if (!$this->isSent($id)) {
                    $mail = $mailbox->getMail($id);
                    $this->forward($mail, $fwd['to']);
                    $this->writeToDb($id);
                }
            }
        }
    }

    protected function getForwards(): array
    {
        $cts = file_get_contents($this->getBasePath() . 'config.json');
        $config = json_decode($cts, true);
        return $config['forwards'] ?? [];
    }

    public function forward(IncomingMail $mail, string $to)
    {
        $mailer = new Swift_Mailer($this->getTransport());

        $message = (new Swift_Message("FWD: $mail->subject"))
            ->setFrom([getenv('SMTP_FROM_ADDRESS') => getenv('SMTP_FROM_NAME')])
            ->setTo([$to])
            ->setBody($mail->textPlain)
            ->addPart($mail->textHtml, 'text/html');

        $mailer->send($message);
    }

    public function isSent($id)
    {
        return in_array($id, $this->getDb());
    }

    public function writeToDb($id)
    {
        $h = fopen($this->getDbPath(), 'a');
        fputcsv($h, [$id], ';');
        fclose($h);
    }

    public function getDb(): array
    {
        $dbPath = $this->getDbPath();
        if (!is_file($dbPath)) {
            file_put_contents($dbPath, "");
        }
        $h = fopen($dbPath, 'r');
        $result = [];
        while ($row = fgetcsv($h, 1000, ";")) {
            $result[] = $row[0];
        }
        fclose($h);
        return $result;
    }

    /**
     * @return string
     */
    protected function getDbPath(): string
    {
        $dbPath = $this->getBasePath() . "db.csv";
        return $dbPath;
    }

    protected function getBasePath(): string
    {
        list($scriptPath) = get_included_files();
        return preg_replace('/mailsplit$/', '', $scriptPath);
    }
}