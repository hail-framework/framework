<?php

namespace Hail\Console\Command;

use Hail\Framework;
use Hail\Facade\Config;
use Hail\Console\Command;

class Optimize extends Command
{
    public function brief()
    {
        return 'Improve performance and development efficiency.';
    }

    public function execute()
    {
        $logger = $this->logger;

        //$logger->writeln('Please use "composer dump-autoload --optimize" to optimize autoload performance.');

        Framework::compileContainer(
            Config::getInstance()
        );
        $logger->writeln('Container Generated');

        $helperDir = \storage_path('helper');
        if (!\is_dir($helperDir)) {
            \mkdir($helperDir, 0755, true);
        }

        foreach (\scandir($helperDir, SCANDIR_SORT_NONE) as $file) {
            if (\in_array($file, ['.', '..', 'dependence'], true)) {
                continue;
            }

            \unlink($helperDir . DIRECTORY_SEPARATOR . $file);
        }

        $helperDir .= DIRECTORY_SEPARATOR;

        $alias = $this->config->get('alias');
        $template = <<<EOD
<?php
class %s extends %s
{
}
EOD;

        foreach ($alias as $k => $v) {
            \file_put_contents($helperDir . $k . '.php', \sprintf($template, $k, $v));
        }
        $logger->writeln('Alias Class Helper Generated');

        $check = function ($class) {
            $ref = new \ReflectionClass($class);
            return $ref->isInstantiable();
        };

        foreach (
            [
                ['App\Service', $check],
                ['App\Library', $check],
                ['App\Model', $check],
            ] as $v
        ) {
            [$namespace, $check] = $v;

            $comment = '/**' . "\n";
            $dir = \base_path('app', \explode('\\', $namespace)[1]);
            foreach (\scandir($dir, SCANDIR_SORT_ASCENDING) as $file) {
                if (\in_array($file, ['.', '..'], true) || \strrchr($file, '.') !== '.php') {
                    continue;
                }

                $name = \substr($file, 0, -4);
                $classFull = '\\' . $namespace . '\\' . $name;

                try {
                    if ($check($classFull)) {
                        $comment .= ' * @property-read ' . $classFull . ' $' . \lcfirst($name) . "\n";
                    }
                } catch (\Exception $e) {
                }
            }
            $comment .= ' */';
            $template = <<<EOD
<?php
%s
class %s {}
EOD;

            $class = \substr(\strrchr($namespace, '\\'), 1) . 'Factory';
            \file_put_contents($helperDir . $class . '.php', \sprintf($template, $comment, $class));
        }
        $logger->writeln('Object Factory Helper Generated');
    }
}
