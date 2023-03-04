<?php

namespace GnuGettext\Command;

use Gettext\Generator\JsonGenerator;
use Gettext\Generator\MoGenerator;
use Gettext\Loader\JsonLoader;
use Gettext\Loader\PoLoader;
use Gettext\Loader\StrictPoLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \GnuGettext\Console\Application getApplication()
 */
#[AsCommand(
    name: 'msgfmt',
    description: 'Generates a binary message catalog from a textual translation description.',
    hidden: false
)]
class MsgFmtCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this->setName('msgfmt')
            ->setDescription('Generates a binary message catalog from textual translation description.')
            ->setDefinition([
                new InputArgument('filename', InputArgument::REQUIRED, 'The file to compile. If an input file is ‘-’, standard input is read.'),
                new InputOption('directory', 'D', InputOption::VALUE_REQUIRED, 'Add ‘directory’ to the list of directories. Source files are searched relative to this list of directories. The resulting binary file will be written relative to the current directory, though.'),
                new InputOption('json', null, InputOption::VALUE_NONE, 'JSON mode: generate a .json file.'),
                new InputOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Write output to specified file. If the output file is ‘-’, output is written to standard output.'),
                new InputOption('strict', null, InputOption::VALUE_NONE, 'Direct the program to work strictly following the Uniforum/Sun implementation. Currently this only affects the naming of the output file. If this option is not given the name of the output file is the same as the domain name. If the strict Uniforum mode is enabled the suffix .mo is added to the file name if it is not already present. We find this behaviour of Sun’s implementation rather silly and so by default this mode is not selected.'),
                new InputOption('json-flags', null, InputOption::VALUE_REQUIRED, 'PHPs json_encode flags. See https://www.php.net/manual/en/json.constants.php for all available flags.')
            ])
            ->setHelp(
                <<<EOT
The <info>msgfmt</info> command generates a binary message catalog from a 
textual translation description.

<info>php gettext.phar msgfmt [option] filename.po …</info>
EOT

            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputFile = $input->getArgument('filename');

        if ($input->getOption('json')) {
            $generator = new JsonGenerator();

            if ($flags = $input->getOption('json-flags')) {
                $generator->jsonOptions($flags);
            }
        } else {
            $generator = new MoGenerator();
        }
        if (str_ends_with(strtolower($inputFile), 'json')) {
            $loader = new JsonLoader();
        } else {
            if ($input->getOption('strict')) {
                $loader = new StrictPoLoader();
            } else {
                $loader = new PoLoader();
            }
        }

        $translations = $loader->loadFile($inputFile);

        if ($outputFile = $input->getOption('output-file')) {
            $generator->generateFile($translations, $outputFile);
        } else {
            $string = $generator->generateString($translations);
            $output->write($string, true, OutputInterface::OUTPUT_RAW);
        }

        return self::SUCCESS;
    }
}
