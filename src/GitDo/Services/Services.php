<?php

namespace GitDo\Services;

use GitDo\Config;
use Symfony\Component\Console\Output\OutputInterface;

class Services
{
    protected $output;
    protected $name;
    protected $parameters = [];

    /**
     * Setup parameters for the service.
     *
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output     = $output;
        $this->name       = trim(strrchr(get_called_class(), '\\'), '\\');
        $this->parameters = $conf = Config::get($this->config_name);
    }
}
