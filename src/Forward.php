<?php


namespace Karlerss\Mailsplit;


use Ahc\Cli\Application as App;
use Ahc\Cli\Input\Command;
use PhpImap\IncomingMail;
use PhpImap\Mailbox;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\DomCrawler\Crawler;

class Forward extends EmailCommand
{
    public function __construct(
        string $name = 'forward',
        string $desc = '',
        bool $allowUnknown = false,
        App $app = null
    ) {
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
                    if(!$this->isExactMatch($mail, $fwd['phrase'])){
                        continue;
                    }
//                    $this->forward($mail, $fwd['to']);
                    if (isset($fwd['calendar'])) {
                        $this->addToCalendar($mail, $fwd['calendar']);
                    }
                    $mailbox->markMailAsUnread($id);
                    $this->writeToDb($id);
                }
            }
        }
    }

    public function isExactMatch(IncomingMail $mail, string $phrase): bool
    {
        return strpos($mail->textPlain, $phrase) !== false;
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

    public function addToCalendar(IncomingMail $mail, array $calendar)
    {
        $uri = $calendar['uri'];
        $this->authCalendar($uri);
        $res3 = $this->client->get($uri . '?view=form&e_id=0')->getBody()->getContents();
        $c2 = new Crawler($res3);
        $nonce2 = $c2->filter('form.dp-form input')->last();

        preg_match('/ev: "(.*?)"/', $mail->textPlain, $dateMatches);
        preg_match('/eg: "(\d+:\d+) - (\d+:\d+)"/', $mail->textPlain, $timeMatches);

        $isoDate = $dateMatches[1];
        $isoDateParts = explode('-', $isoDate);
        $isoDateParts = array_reverse($isoDateParts);
        $date = implode('.', $isoDateParts);

        preg_match('/<body>(.*?)<\/body>/s', $mail->textHtml, $bodyContentMatches);

        $eventParams = [
            'jform' => [
                'title' => $mail->subject,
                'catid' => $calendar['id'],
                'start_date' => $date,
                'start_date_time' => $timeMatches[1],
                'end_date' => $date,
                'end_date_time' => $timeMatches[2],
                'show_end_time' => 1,
                'scheduling' => 0,
                'description' => $bodyContentMatches[1],
                'color' => $calendar['color'] ?? '#FFC0CB',
            ],
            'task' => 'event.apply',
            $nonce2->attr('name') => 1,
        ];

        $res4 = $this->client->post($uri, [
            'form_params' => $eventParams,
        ])->getBody()->getContents();
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
