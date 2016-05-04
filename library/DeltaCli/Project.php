<?php

namespace DeltaCli;

use DeltaCli\Console\Output\Banner;
use DeltaCli\Exception\EnvironmentNotFound;
use DeltaCli\Exception\ProjectNotConfigured;
use DeltaCli\Exception\ScriptNotFound;
use DeltaCli\Extension\Vagrant as VagrantExtension;
use DeltaCli\Script\Step\Rsync as RsyncStep;
use DeltaCli\Script\Step\Scp as ScpStep;
use DeltaCli\Script\Step\Ssh as SshStep;
use DeltaCli\Script\SshInstallKey as SshInstallKeyScript;
use DeltaCli\Template\TemplateInterface;
use DeltaCli\Template\WordPress as WordPressTemplate;
use Symfony\Component\Console\Output\OutputInterface;

class Project
{
    /**
     * @var string
     */
    private $name = 'Delta Systems CLI Tools';

    /**
     * @var array
     */
    private $environments = [];

    /**
     * @var array
     */
    private $scripts = [];

    /**
     * @var bool
     */
    private $configFileLoaded = false;

    public function __construct()
    {
        $this->createScript('deploy', 'Deploy this project.')
            ->setPlaceholderCallback($this->getDeployScriptPlaceholderCallback());
        $this->addScript(new SshInstallKeyScript($this));

        $vagrantExtension = new VagrantExtension();
        $vagrantExtension->extend($this);
    }

    public function configFileExists()
    {
        return file_exists(getcwd() . '/delta-cli.php');
    }

    public function loadConfigFile()
    {
        if (!$this->configFileLoaded) {
            $cwd = getcwd();

            if (!file_exists($cwd . '/delta-cli.php')) {
                throw new ProjectNotConfigured();
            }

            $project = $this;
            require_once $cwd . '/delta-cli.php';

            $this->configFileLoaded = true;
        }
    }

    public function writeConfig($contents)
    {
        file_put_contents(getcwd() . '/delta-cli.php', $contents, LOCK_EX);
    }

    public function applyTemplate(TemplateInterface $template)
    {
        $template->apply($this);

        return $this;
    }

    public function wordPressTemplate()
    {
        return new WordpressTemplate();
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        // Load config file if available so we can get custom project name
        if ($this->configFileExists()) {
            $this->loadConfigFile();
        }

        return $this->name;
    }

    public function getScripts()
    {
        // Load config file if available so we can get custom scripts
        if ($this->configFileExists()) {
            $this->loadConfigFile();
        }

        return $this->scripts;
    }

    /**
     * @param $name
     * @return Script
     * @throws ScriptNotFound
     */
    public function getScript($name)
    {
        if (!array_key_exists($name, $this->scripts)) {
            throw new ScriptNotFound("No script could be found with the name '{$name}'.");
        }

        return $this->scripts[$name];
    }

    public function addScript(Script $script)
    {
        $this->scripts[$script->getName()] = $script;
        return $this;
    }

    /**
     * @param string $name
     * @param string $description
     * @return Script
     */
    public function createScript($name, $description)
    {
        $script = new Script($this, $name, $description);
        $this->addScript($script);
        return $script;
    }

    /**
     * @param string $name
     * @param string $description
     * @return Script
     */
    public function createEnvironmentScript($name, $description)
    {
        return $this->createScript($name, $description)
            ->requireEnvironment();
    }

    /**
     * @return Script
     * @throws ScriptNotFound
     */
    public function getDeployScript()
    {
        return $this->getScript('deploy')
            ->requireEnvironment();
    }

    public function createEnvironment($name)
    {
        $environment = new Environment($name);
        $this->environments[$name] = $environment;
        return $environment;
    }

    public function addEnvironment(Environment $environment)
    {
        $this->environments[$environment->getName()] = $environment;
        return $this;
    }

    public function hasEnvironment($name)
    {
        return array_key_exists($name, $this->environments);
    }

    public function getEnvironment($name)
    {
        if (!$this->hasEnvironment($name)) {
            $exception = new EnvironmentNotFound();
            $exception->setName($name);
            throw $exception;
        }

        return $this->environments[$name];
    }

    public function rsync($localPath, $remotePath)
    {
        return new RsyncStep($localPath, $remotePath);
    }

    public function ssh($command, $includeApplicationEnv = SshStep::INCLUDE_APPLICATION_ENV)
    {
        return new SshStep($command, $includeApplicationEnv);
    }

    public function scp($localFile, $remoteFile)
    {
        return new ScpStep($localFile, $remoteFile);
    }

    private function getDeployScriptPlaceholderCallback()
    {
        return function (OutputInterface $output) {
            $banner = new Banner($output);
            $banner->setBackground('cyan');
            $banner->render('A deploy script has not yet been created for this project.');

            $output->writeln(
                [
                    'Learn more about how to write a good deploy script for your project on Github at:',
                    '<fg=blue;options=underscore>https://github.com/DeltaSystems/delta-cli-tools</>'
                ]
            );
        };
    }
}
