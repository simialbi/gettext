<?php

namespace GnuGettext;

use Seld\PharUtils\Timestamps;
use Symfony\Component\Finder\Finder;

/**
 * The Compiler class compiles composer into a phar
 */
class Compiler
{
    /**
     * Compiles composer into a single phar file
     *
     * @param string $pharFile The full path to the file to create
     *
     * @throws \RuntimeException
     */
    public function compile(string $pharFile = 'gettext.phar'): void
    {
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }

        $phar = new \Phar($pharFile, 0, 'gettext.phar');
        $phar->setSignatureAlgorithm(\Phar::SHA512);

        $phar->startBuffering();

        $finderSort = static function ($a, $b): int {
            return strcmp(strtr($a->getRealPath(), '\\', '/'), strtr($b->getRealPath(), '\\', '/'));
        };

        // add files
        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->notName('Compiler.php')
            ->in(__DIR__ . '/..')
            ->sort($finderSort);
        foreach ($finder as $file) {
            /** @var \SplFileInfo $file */
            $this->addFile($phar, $file);
        }

        // add vendor
        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->notPath('/\/(composer\.(json|lock)|[A-Z]+\.md(?:own)?|\.gitignore|appveyor.yml|phpunit\.xml\.dist|phpstan\.neon\.dist|phpstan-config\.neon|phpstan-baseline\.neon)$/')
            ->notPath('/bin\/(jsonlint|validate-json|simple-phpunit|phpstan|phpstan\.phar)(\.bat)?$/')
            ->in(__DIR__ . '/../../vendor/')
            ->sort($finderSort);

        foreach ($finder as $file) {
            /** @var \SplFileInfo $file */
            $this->addFile($phar, $file, preg_match('#\.php[\d.]*$#', $file->getFilename()));
        }

        // add bin
        $content = preg_replace(
            '#^\#!/usr/bin/env php\s*#',
            '',
            file_get_contents(__DIR__ . '/../../bin/gettext')
        );
        $phar->addFromString('bin/gettext', $content);

        // add stub
        $stub = <<<'EOT'
#!/usr/bin/env php
<?php
/*
 * This file is part of gettext.
 *
 * For the full copyright and license information, please view
 * the license that is located at the bottom of this file.
 */
if (extension_loaded('apc') && filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN) && filter_var(ini_get('apc.cache_by_default'), FILTER_VALIDATE_BOOLEAN)) {
    if (version_compare(phpversion('apc'), '3.0.12', '>=')) {
        ini_set('apc.cache_by_default', 0);
    }
}
if (!class_exists('Phar')) {
    echo 'PHP\'s phar extension is missing. gettext requires it to run. Enable the extension or recompile php without --disable-phar then try again.' . PHP_EOL;
    exit(1);
}
Phar::mapPhar('gettext.phar');

require 'phar://gettext.phar/bin/gettext';

__HALT_COMPILER();
EOT;

        $phar->setStub($stub);

        $phar->stopBuffering();

        $util = new Timestamps($pharFile);
        $util->updateTimestamps(time());
        $util->save($pharFile, \Phar::SHA512);
    }

    /**
     * Add file to phar.
     *
     * @param \Phar $phar The phar where to add the file
     * @param \SplFileInfo $file The file
     * @param bool $strip Strip whitespaces?
     * @return void
     */
    private function addFile(\Phar $phar, \SplFileInfo $file, bool $strip = true): void
    {
        $path = $file->getRealPath();
        $pathPrefix = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        $pos = strpos($path, $pathPrefix);
        $relativePath = ($pos !== false) ? substr_replace($path, '', $pos, strlen($pathPrefix)) : $path;
        $relativePath = strtr($relativePath, '\\', '/');
        $content = file_get_contents((string) $file);
        if ($strip) {
            $content = $this->stripWhitespace($content);
        }

        $phar->addFromString($relativePath, $content);
    }

    /**
     * Removes whitespace from a PHP source string while preserving line numbers.
     *
     * @param string $source A PHP string
     * @return string The PHP string with the whitespace removed
     */
    private function stripWhitespace(string $source): string
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], [T_COMMENT, T_DOC_COMMENT])) {
                $output .= str_repeat("\n", substr_count($token[1], "\n"));
            } elseif (T_WHITESPACE === $token[0]) {
                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $token[1]);
                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
                // trim leading spaces
                $whitespace = preg_replace('{\n +}', "\n", $whitespace);
                $output .= $whitespace;
            } else {
                $output .= $token[1];
            }
        }

        return $output;
    }
}
