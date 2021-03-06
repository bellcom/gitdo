<?php

require_once __DIR__.'/../vendor/autoload.php';
$loader = new Composer\Autoload\ClassLoader();
$loader->add('GitDo', __DIR__.'/../src');
$loader->register();

use GitDo\Command\GithubToScrumDoCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new GithubToScrumDoCommand());
$application->run();


class AppKernel
{

}

$app = new AppKerle();
return $app;
