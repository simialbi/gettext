<?php
declare(strict_types=1);

namespace GnuGettext\Command;

use Gettext\Generator\PoGenerator;
use Gettext\Headers;
use Gettext\Loader\PoLoader;
use Gettext\Scanner\JsScanner;
use Gettext\Scanner\PhpScanner;
use Gettext\Translations;
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
    name: 'xgettext',
    description: 'Extracts translatable strings from given input files.',
    hidden: false
)]
class XGetTextCommand extends Command
{
    protected function configure()
    {
        $this->setName('xgettext')
            ->setDescription('Extracts translatable strings from given input files.')
            ->setDefinition([
                new InputArgument('inputfile', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Input files.', []),
                new InputOption('files-from', 'f', InputOption::VALUE_REQUIRED, 'Read the names of the input files from file instead of getting them from the command line.'),
                new InputOption('directory', 'D', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Add directory to the list of directories. Source files are searched relative to this list of directories. The resulting .po file will be written relative to the current directory, though.'),
                new InputOption('default-domain', 'd', InputOption::VALUE_REQUIRED, 'Use name.po for output (instead of messages.po).'),
                new InputOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write output to specified file (instead of name.po or messages.po).'),
                new InputOption('output-dir', 'p', InputOption::VALUE_REQUIRED, 'Output files will be placed in directory dir.'),
                new InputOption('language-name', 'L', InputOption::VALUE_REQUIRED, 'Specifies the language of the input files. The supported languages are PHP, JavasScript.', 'PHP', ['PHP', 'JavaScript']),
                new InputOption('join-existing', 'j', InputOption::VALUE_NONE, 'Join messages with existing file.'),
                new InputOption('exclude-file', 'x', InputOption::VALUE_REQUIRED, 'Entries from file are not extracted. file should be a PO or POT file.'),
                new InputOption('msgid-bugs-address', null, InputOption::VALUE_REQUIRED, 'Set the reporting address for msgid bugs. This is the email address or URL to which the translators shall report bugs in the untranslated strings.'),
                new InputOption('msgstr-prefix', 'm', InputOption::VALUE_OPTIONAL, 'Use string (or "" if not specified) as prefix for msgstr values.', ''),
                new InputOption('msgstr-suffix', 'M', InputOption::VALUE_OPTIONAL, 'Use string (or "" if not specified) as suffix for msgstr values.', '')
            ])
        ->setHelp(
            <<<EOT
The <info>xgettext</info> command extracts translatable strings from given input files.

<info>php gettext.phar xgettext [option] [inputfile] …</info>
EOT
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fs = $this->getApplication()->getFileSystem();
        if (($lang = $input->getOption('language-name')) && $lang === 'JavaScript') {
            $scanner = new JsScanner();
        } else {
            $scanner = new PhpScanner();
        }
        $scanner->setDefaultDomain('messages');

        $directories = [];
        $files = $input->getArgument('inputfile');
        if ($domain = $input->getOption('default-domain')) {
            $scanner->setDefaultDomain($domain);
        }
        if (($inputFile = $input->getOption('files-from')) && $fs->exists($inputFile)) {
            $files = array_merge($files, file($inputFile));
        }
        if ($directory = $input->getOption('directory')) {
            foreach ((array) $directory as $dir) {
                if ($fs->exists($dir)) {
                    $directories[] = $dir;
                }
            }
        } else {
            $directories[] = '.' . DIRECTORY_SEPARATOR;
        }

        foreach ($directories as $directory) {
            if (!str_ends_with($directory, DIRECTORY_SEPARATOR)) {
                $directory .= DIRECTORY_SEPARATOR;
            }
            foreach ($files as $file) {
                if ($fs->exists($directory . $file)) {
                    $scanner->scanFile($file);
                }
            }
        }

        $loader = new PoLoader();
        $generator = new PoGenerator();
        $outputDir = $input->getOption('output-dir') ?? '.' . DIRECTORY_SEPARATOR;
        $joinExisting = $input->getOption('join-existing');
        $prefix = $input->getOption('msgstr-prefix');
        $suffix = $input->getOption('msgstr-suffix');
        $translationsToExclude = Translations::create();
        if (($excludeFile = $input->getOption('exclude-file')) && $fs->exists($excludeFile)) {
            $translationsToExclude = $loader->loadFile($excludeFile);
        }
        $headers = new Headers([
            'Project-Id-Version' => '',
            'Report-Msgid-Bugs-To' => $input->getOption('msgid-bugs-address'),
            'POT-Creation-Date' => date('c'),
            'PO-Revision-Date' => date('c'),
            'Last-Translator' => '',
            'Language-Team' => '',
            'Language' => '',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit'
        ]);
        foreach ($scanner->getTranslations() as $domain => $translations) {
            if ($joinExisting && $fs->exists($outputDir . $domain . '.po')) {
                $existingTranslations = $loader->loadFile($outputDir . $domain . '.po');
                $existingTranslations->mergeWith($translations);
                $translations = $existingTranslations;
            }
            foreach ($translationsToExclude->getTranslations() as $translation) {
                $translations->remove($translation);
            }
            if (!empty($prefix) || !empty($suffix)) {
                /** @var \Gettext\Translation $translation */
                foreach ($translations->getTranslations() as $translation) {
                    $translation->translate($prefix . $translation->getTranslation() . $suffix);
                }
            }

            $translations->getHeaders()->mergeWith($headers);

            $generator->generateFile($translations, $outputDir . $domain . '.po');
        }

        return self::SUCCESS;
    }
}
