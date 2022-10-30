<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Tool;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Yiisoft\VarDumper\VarDumper;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

/** @method YiiDevToolApplication getApplication() **/
class Init extends Command
{
    private ?OutputManager $io = null;
    private InputInterface $input;
    private OutputInterface $output;

    protected function configure()
    {
        $this
            ->setName('tool/init')
            ->setAliases(['init'])
            ->setDescription('Initiate the creation of a config file for the DevTool tool')
            ->setDefinition([
                new InputOption('owner-packages', null, InputOption::VALUE_REQUIRED, 'Package owner nickname'),
                new InputOption('git-repository', null, InputOption::VALUE_REQUIRED, 'The domain of the git repository'),
                new InputOption('api-token', null, InputOption::VALUE_REQUIRED, 'Token for access to api git repository'),
                new InputOption('config-dir', null, InputOption::VALUE_REQUIRED, 'Path to the configuration folder'),
                new InputOption('packages-dir', null, InputOption::VALUE_REQUIRED, 'Path to the packages root folder'),
                new InputOption('packages', null, InputOption::VALUE_REQUIRED, 'List packages'),
            ]);

        parent::configure();
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new OutputManager(new YiiDevToolStyle($input, $output));
    }

    protected function getIO(): OutputManager
    {
        if ($this->io === null) {
            throw new RuntimeException('IO is not initialized.');
        }

        return $this->io;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ownerPackages = $input->getOption('owner-packages');
        $gitRepository = $input->getOption('git-repository');
        $apiToken = $input->getOption('api-token');
        $configDir = $input->getOption('config-dir');
        $packagesDir = $input->getOption('packages-dir');
        $packages = $input->getOption('packages');

        if ($input->isInteractive()) {
            $helper = $this->getHelper('question');
            $confirmQuestion = new ConfirmationQuestion('Do you confirm generation [<comment>yes</comment>]? ');
            if (!$helper->ask($input, $output, $confirmQuestion)) {
                throw new InvalidArgumentException('<error> Command aborted! </error>');
            }
        }

        $packagesArray = [];
        $packages = $packages ? explode(',', $packages) : [];
        foreach ($packages as $value) {
            $packagesArray[$value] = true;
        }
        ksort($packagesArray);
        $devToolConfig = [
            'owner-packages' => $ownerPackages,
            'git-repository' => $gitRepository,
            'api-token' => $apiToken,
            'config-dir' => $configDir,
            'packages-dir' => $packagesDir,
            'packages' => $packagesArray,
        ];

        if (!is_writable($this->getApplication()->getRootDir())) {
            throw new InvalidArgumentException('No write access to the working folder.');
        }
        $exportArray = VarDumper::create($devToolConfig)->export();
        file_put_contents($this->getApplication()->getConfigFile(), "<?php\n\nreturn $exportArray;\n");

        if ((!file_exists($configDir) && !is_dir($configDir)) && (!file_exists($packagesDir) && !is_dir($packagesDir))) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion("<question>Create directories for configs and root for packages?</question> [<comment>config-dir</comment>] [<comment>packages-dir</comment>]");
            $question->setMaxAttempts(3);

            if ($helper->ask($this->input, $this->output, $question)) {
                $errorCreateDirectory = '';
                if (!mkdir($configDir) && !is_dir($configDir)) {
                    $errorCreateDirectory .= sprintf('Directory "%s" was not created\n', $configDir);
                }

                if (!mkdir($packagesDir) && !is_dir($packagesDir)) {
                    $errorCreateDirectory .= sprintf('Directory "%s" was not created\n', $packagesDir);
                }
                if ($errorCreateDirectory !== '') {
                    throw new InvalidArgumentException($errorCreateDirectory);
                }
            }
        }

        return Command::SUCCESS;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $ownerPackages = $input->getOption('owner-packages');
        $this->askQuestion(
            'Package owner nickname?',
            'owner-packages',
            static function ($value) use ($ownerPackages) {
                if ($value === null) {
                    if ($ownerPackages === null) {
                        throw new InvalidArgumentException('Nick name of package owner not specified.');
                    }
                    return $ownerPackages;
                }

                return $value;
            }
        );
        $gitRepository = $input->getOption('git-repository');
        $this->askQuestion(
            'Specify the domain of the git repository?',
            'git-repository',
            static function ($value) use ($gitRepository) {
                if ($value === null) {
                    if ($gitRepository === null) {
                        throw new InvalidArgumentException('Git repository domain not specified.');
                    }
                    return $gitRepository;
                }

                return $value;
            }
        );
        $githubToken = $input->getOption('api-token');
        $this->askQuestion(
            'Specify a token to access the api git repository?',
            'api-token',
            static function ($value) use ($githubToken) {
                if ($value === null) {
                    if ($githubToken === null) {
                        throw new InvalidArgumentException('No specified api git repository token. You can create a github.com token here: https://github.com/settings/tokens for other repositories, read the docs. Choose \'repo\' rights.');
                    }
                    return $githubToken;
                }

                return $value;
            }
        );
        $configDir = $input->getOption('config-dir');
        $this->askQuestion(
            'Specify the path to the configuration folder?',
            'config-dir',
            static function ($value) use ($configDir) {
                if ($value === null) {
                    if ($configDir === null) {
                        throw new InvalidArgumentException('The configuration directory with working files no specified.');
                    }
                    return $configDir;
                }

                return $value;
            }
        );
        $packagesDir = $input->getOption('packages-dir');
        $this->askQuestion(
            'Specify the path to the root folder for packages?',
            'packages-dir',
            static function ($value) use ($packagesDir) {
                if ($value === null) {
                    if ($packagesDir === null) {
                        throw new InvalidArgumentException('No packages root directory specified.');
                    }
                    return $packagesDir;
                }

                return $value;
            }
        );

        $packages = $input->getOption('packages');
        $this->askQuestion(
            'Specify a list of Packages',
            'packages',
            static fn($value) => $value ?? $packages
        );

        return Command::SUCCESS;
    }

    private function askQuestion($question, $optionName, $validator = null, $attempts = 3): void
    {
        $optionInput = $this->input->getOption($optionName);
        $helper = $this->getHelper('question');
        $question = new Question("<question> $question </question> [<comment>$optionInput</comment>]");
        $question->setValidator($validator);
        $question->setMaxAttempts($attempts);
        $answer = $helper->ask($this->input, $this->output, $question);
        $this->input->setOption($optionName, $answer);
    }
}
