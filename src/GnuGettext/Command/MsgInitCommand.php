<?php
declare(strict_types=1);

namespace GnuGettext\Command;

use Gettext\Generator\PoGenerator;
use Gettext\Languages\Language;
use Gettext\Loader\PoLoader;
use Gettext\Translations;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \GnuGettext\Console\Application getApplication()
 */
#[AsCommand(
    name: 'msginit',
    description: 'Creates a new PO file, initializing the meta information with values from the user’s environment.',
    hidden: false
)]
class MsgInitCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('msginit')
            ->setDescription('Creates a new PO file, initializing the meta information with values from the user’s environment.')
            ->setDefinition([
                new InputOption('input', 'i', InputOption::VALUE_REQUIRED, 'Input POT file. If no inputfile is given, the current directory is searched for the POT file. If it is ‘-’, standard input is read.'),
                new InputOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Write output to specified PO file. If no output file is given, it depends on the ‘--locale’ option or the user’s locale setting. If it is ‘-’, the results are written to standard output.'),
                new InputOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Set target locale. ll should be a language code, and CC should be a country code. The optional part .encoding specifies the encoding of the locale; most often this part is .UTF-8. The command ‘locale -a’ can be used to output a list of all installed locales. The default is the user’s locale setting.', ''),
                new InputOption('no-translator', null, InputOption::VALUE_NONE, 'Declares that the PO file will not have a human translator and is instead automatically generated.')
            ])
            ->setHelp(
                <<<EOT
The <info>msginit</info> command creates a new PO file, initializing the meta information with values from the user’s environment.
Here are more details. The following header fields of a PO file are automatically filled, when possible.

<info>php gettext.phar msginit [option]</info>

<fg=cyan;options=bold>‘Project-Id-Version’</>
The value is guessed from the configure script or any other files in the current directory.

<fg=cyan;options=bold>‘PO-Creation-Date, PO-Revision-Date’</>
The value is taken from the PO-Creation-Data in the input POT file, or the current date is used.

<fg=cyan;options=bold>‘Last-Translator’</>
The value is taken from user’s password file entry and the mailer configuration files.

<fg=cyan;options=bold>‘Language-Team, Language’</>
These values are set according to the current locale and the predefined list of translation teams.

<fg=cyan;options=bold>‘MIME-Version, Content-Type, Content-Transfer-Encoding’</>
These values are set according to the content of the POT file and the current locale. If the POT file 
contains charset=UTF-8, it means that the POT file contains non-ASCII characters, and we keep the UTF-8 
encoding. Otherwise, when the POT file is plain ASCII, we use the locale’s encoding.

<fg=cyan;options=bold>‘Plural-Forms’</>
The value is first looked up from the embedded table.
EOT
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fs = $this->getApplication()->getFilesystem();
        if ($locale = $input->getOption('locale')) {
            $info = Language::getById($locale);
            $encoding = (preg_match('#\.([a-z\-_]+)$#i', $locale, $matches))
                ? $matches[1]
                : 'UTF-8';
        } else {
            $locale = 'en_US';
        }
        $translations = Translations::create(null, $locale);
        $creationDate = null;

        if (($inputFile = $input->getOption('input')) && $fs->exists($inputFile)) {
            $loader = new PoLoader();
            $translations = $loader->loadFile($inputFile);
            $creationDate = @filemtime($inputFile);
        }
        if (!isset($encoding)) {
            $encoding = 'UTF-8';
        }

        $headers = $translations->getHeaders();
        $headers->set('Project-Id-Version', $headers->get('Project-Id-Version') ?? '');
        if ($creationDate) {
            $headers->set('POT-Creation-Date', date('c', $creationDate));
        }
        $headers->set('PO-Revision-Date', date('c'));
        if (!$input->getOption('no-translator')) {
            $headers->set('Last-Translator', $headers->get('Last-Translator') ?? '');
            $headers->set('Language-Team', $headers->get('Language-Team') ?? '');
        }
        $headers->set('Language', $locale);
        $headers->set('MIME-Version', '1.0');
        $headers->set('Content-Type', "text/plain; charset=$encoding");
        $headers->set('Content-Transfer-Encoding', '8bit');
        if (isset($info) && $info) {
            $headers->set('Plural-Forms', $info->buildFormula(true));
        }

        if (!($outputFile = $input->getOption('output-file'))) {
            $outputFile = $translations->getLanguage() . '.po';
        }
        $generator = new PoGenerator();
        $generator->generateFile($translations, $outputFile);

        return self::SUCCESS;
    }
}
