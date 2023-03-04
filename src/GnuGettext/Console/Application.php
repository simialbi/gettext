<?php
declare(strict_types=1);

namespace GnuGettext\Console;

use GnuGettext\Command\MsgFmtCommand;
use GnuGettext\Command\MsgInitCommand;
use GnuGettext\Command\XGetTextCommand;
use Symfony\Component\Filesystem\Filesystem;

class Application extends \Symfony\Component\Console\Application
{
    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * Initialize a new gettext instance
     */
    public function __construct(string $name = 'gettext', string $version = '0.21')
    {
        parent::__construct($name, $version);

        $this->filesystem = new Filesystem();

        $this->addCommands([
            new MsgFmtCommand(),
            new MsgInitCommand(),
            new XGetTextCommand()
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getHelp(): string
    {
        $logo = <<<EOT
                __    __                   __   
   ____   _____/  |__/  |_  ____ ___  ____/  |_ 
  / ___\_/ __ \   __\   __\/ __ \\  \/  /\   __\
 / /_/  >  ___/|  |  |  | \  ___/ >    <  |  |  
 \___  / \___  >__|  |__|  \___  >__/\_ \ |__|  
/_____/      \/                \/      \/      
EOT;
        return $logo . PHP_EOL . PHP_EOL . parent::getHelp();
    }

    /**
     * {@inheritDoc}
     */
    public function getLongVersion(): string
    {
        return sprintf('<info>%s</info> version <comment>%s</comment>', $this->getName(), $this->getVersion());
    }

    /**
     * Get the applications file system
     *
     * @return Filesystem
     */
    public function getFileSystem(): Filesystem
    {
        return $this->filesystem;
    }
}
