#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

(new Application('init', '1.0.0'))
    ->register('init')
    ->setCode(function(InputInterface $input, OutputInterface $output) {
        $questionHelper = new QuestionHelper();
        $finder = new Finder();

        $vendor = $questionHelper->ask($input, $output, new Question('What is your plugin namespace (usually this is your organization name)? '));

        $q = new Question('What is your plugin name? ');
        $q->setValidator(static function (mixed $answer): string {
            if (!is_string($answer)) {
                throw new \RuntimeException(
                    'Please enter the plugin name.'
                );
            }

            return $answer;
        });
        $pluginName = $questionHelper->ask($input, $output, $q);

        if (str_ends_with($pluginName, 'Plugin')) {
            $pluginName = substr($pluginName, 0, -6);
        }

        $pluginNameWithSuffix = $pluginName . 'Plugin';

        $yamlNamespace = Container::underscore($vendor) . '_' . Container::underscore($pluginName);

        $composerName = str_replace('_', '-', Container::underscore($vendor)) . '/' . str_replace('_', '-', Container::underscore($pluginNameWithSuffix));

        $finder
            ->in('.')
            ->exclude(['vendor', 'node_modules', '.github', '.idea'])
            ->ignoreVCSIgnored(true)
            ->ignoreVCS(true)
            ->contains('/acme/i')
            ->ignoreDotFiles(false)
            ->notName('init')
        ;

        foreach ($finder as $file) {
            replaceInFile($file->getPathname(), [
                'acme_sylius_example' => $yamlNamespace,
                'Acme\\SyliusExamplePlugin' => $vendor . '\\' . $pluginNameWithSuffix,
                'AcmeSyliusExamplePlugin' => $vendor.$pluginNameWithSuffix,
                'AcmeSyliusExample' => $vendor.$pluginName,
                'Acme' => $vendor,
                'SyliusExamplePlugin' => $pluginNameWithSuffix,
            ]);
        }


        replaceInFile('composer.json', [
            'sylius/plugin-skeleton' => $composerName,
            'SyliusExamplePlugin' => $pluginNameWithSuffix,
        ]);

        replaceInFile('README.md', [
            'sylius/plugin-skeleton' => $composerName,
        ]);

        rename('src/DependencyInjection/AcmeSyliusExampleExtension.php', sprintf('src/DependencyInjection/%s%sExtension.php', $vendor, $pluginName));
        rename('src/AcmeSyliusExamplePlugin.php', sprintf('src/%s%s.php', $vendor, $pluginNameWithSuffix));

        $process = new Process(['composer', 'dump-autoload']);
        $process->run();
    })
    ->getApplication()
    ->setDefaultCommand('init', true) // Single command application
    ->run()
;

function replaceInFile(string $file, array $replacements): void
{
    $search = array_keys($replacements);
    $replace = array_values($replacements);

    $data = file_get_contents($file);
    file_put_contents($file, str_replace($search, $replace, $data));
}
