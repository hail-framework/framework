<?php

namespace Hail\I18n\Console\Gettext;

use Hail\Console\Command;

class Languages extends Command
{
    public function brief()
    {
        return 'Generate gettext language list from CLDR data.';
    }

    public function init()
    {
        $this->addOption('p|path:', 'CLDR common data path');
        $this->addOption('l|lang+', 'Languages which want to generate');
    }

    public function execute()
    {
        $logger = $this->logger;

        $path = $this->getOption('path');
        $langs = $this->getOption('lang') ?? ['en_US'];

        foreach ($langs as $lang) {
            if (!$this->buildMain($path, $lang)) {
                return;
            }
        }

        $file = "$path/supplemental/plurals.xml";
        if (!\is_file($file)) {
            $logger->error( 'supplemental/plurals.xml not found in CLDR common dictionary!');
            return;
        }

        $pluralsFile = \hail_path('I18n', 'Gettext', 'Languages', 'cldr-data', 'supplemental', 'plurals.php');

        $plurals = [
            'version' => [],
        ];
        $xml = \simplexml_load_file($file, 'SimpleXMLElement', LIBXML_DTDATTR);

        foreach ($xml->children() as $n) {
            switch ($n->getName()) {
                case 'version':
                    $array = &$plurals['version'];
                    foreach ($n->attributes() as $key => $v) {
                        $array[$key] = (string) $v;
                    }
                    break;

                case 'plurals':
                    $key = 'plurals-type-' . $n->attributes()['type'];
                    if (!isset($plurals[$key])) {
                        $plurals[$key] = [];
                    }
                    $array = &$plurals[$key];

                    foreach ($n->children() as $c) {
                        $langs = \explode(' ', (string) $c->attributes()['locales']);

                        $data = [];
                        foreach ($c->children() as $v) {
                            $k = $v->getName() . '-count-' . $v->attributes()['count'];
                            $data[$k] = (string) $v;
                        }

                        foreach ($langs as $l) {
                            $array[$l] = $data;
                        }
                    }

                    \ksort($array);
                    break;
            }
        }
        unset($array);

        \file_put_contents($pluralsFile, "<?php\nreturn " . self::dump($plurals) . ';');
        $logger->writeln(\str_replace(__DIR__, '.', $pluralsFile) . ' generated');

        $logger->writeln('Finished');
    }


    private function buildMain($path, $lang)
    {
        $logger = $this->logger;

        $local = null;
        $main = [
            'identity' => [
                'version' => [],
            ],
            'localeDisplayNames' => [
                'languages' => [],
                'scripts' => [],
                'territories' => [],
            ],
        ];

        foreach (\explode('_', $lang) as $l) {
            if ($local === null) {
                $local = $l;
            } else {
                $local .= '_' . $l;
            }

            $file = "$path/main/{$local}.xml";

            if (!\is_file($file)) {
                $logger->error($local . ' invalid!');
                return false;
            }

            $xml = \simplexml_load_file($file, 'SimpleXMLElement', LIBXML_DTDATTR);

            foreach ($xml->children() as $n) {
                switch ($n->getName()) {
                    case 'identity':
                        $array = &$main['identity'];

                        foreach ($n->children() as $c) {
                            $k = $c->getName();

                            if ($k === 'version') {
                                foreach ($c->attributes() as $key => $v) {
                                    $array[$k][$key] = (string) $v;
                                }
                            } else {
                                $array[$k] = (string) $c->attributes()['type'];
                            }
                        }
                        break;

                    case 'localeDisplayNames':
                        $array = &$main['localeDisplayNames'];
                        $keys = \array_keys($array);

                        foreach ($n->children() as $c) {
                            $k = $c->getName();
                            if (\in_array($k, $keys, true)) {
                                $sub = &$array[$k];
                                foreach ($c->children() as $v) {
                                    $attrs = $v->attributes();
                                    $key = (string) $attrs['type'];

                                    if (isset($attrs['alt'])) {
                                        $key .= '-alt-' . (string) $attrs['alt'];
                                    }

                                    $sub[$key] = (string) $v;
                                }
                            }
                        }
                        break;
                }
            }
            unset($array);
        }

        $mainFile = \hail_path('I18n', 'Gettext', 'Languages', 'cldr-data', $lang . '.php');
        \file_put_contents($mainFile, "<?php\nreturn " . self::dump($main) . ';');
        unset($main);

        $logger->writeln(\str_replace(__DIR__, '.', $mainFile) . ' generated');

        return true;
    }

    private static function dump($var, $indent = '')
    {
        if (\is_array($var)) {
            $newIndent = $indent . "\t";
            $to = '';
            foreach ($var as $key => $value) {
                $to .= $newIndent . \var_export((string) $key, true) . ' => ' . self::dump($value, $newIndent) . ",\n";
            }

            return "[\n{$to}{$indent}]";
        }

        return \var_export($var, true);
    }
}
