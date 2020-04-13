<?php


namespace Karlerss\Mailsplit;


use Ahc\Cli\Application as App;

class TestMail extends EmailCommand
{
    public function __construct(string $name = 'test', string $desc = '', bool $allowUnknown = false, App $app = null)
    {
        parent::__construct($name, $desc, $allowUnknown, $app);
        $this->option('-a --all');
    }

    public function execute()
    {
        $this->testTransport();
        $this->testImap();
    }

    public function testTransport()
    {
        try {
            $this->getTransport()->start();
            echo "Transport: OK\n";
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            echo "Transport: FAIL ($msg) \n";
        }

    }

    public function testImap()
    {
        try {
            $mb = $this->getMailbox();
            $mb->checkMailbox();
            echo "IMAP: OK\n";
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            echo "IMAP: FAIL ($msg)\n";
        }
    }


}