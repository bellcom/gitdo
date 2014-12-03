<?php

namespace GitDo\Command;

use GitDo\DB;
use GitDo\Config;
use GitDo\Services\GithubService;
use GitDo\Services\ScrumDoService;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubToScrumDoCommand extends Command
{
    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('gitdo:github-to-scrumdo')
            ->setDescription('Syncronize Github issues to ScrumDo')
        ;
    }


    /**
     * Process the job of syncronizing issues from Github to ScrumDo
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $file = __DIR__.'/since';
        if (!is_file($file)) {
            touch($file);
        }
        $timestamp = $since = trim(file_get_contents($file));

        // started, but cannot remember why ....
        // $db = DB::getInstance();


// $github  = new GithubService($this->output);
// $scrumdo = new ScrumDoService($this->output);
// $story = $scrumdo->doSearch('tag:git230');
// print_r($scrumdo->fetchComments($story[0]));
// print_r($github->fetchComments($timestamp, ['number' => 230]));

        $this->handleNewGithubIssues($timestamp);
        $this->handleClosedGithubIssues($timestamp);

        file_put_contents(__DIR__.'/since', date('c'));
    }


    /**
     * Handles new or updated github issues
     *
     * @param  Date $timestamp ISO 8601 formattet date
     */
    protected function handleNewGithubIssues($timestamp)
    {
        $github  = new GithubService($this->output);
        $scrumdo = new ScrumDoService($this->output);
        $issues  = $github->fetchIssues($timestamp, 'open');

        $count = count($issues['ids']);
        if (0 == $count) {
            return;
        }

        $this->output->writeln('<info>Proccessing '.$count.' "add/update" issues from Github</info>');

        // Lookup issues on ScrumDo, we need this to figure out wether to update or create the story
        $response = $scrumdo->doSearch(implode(', ', $issues['search_tags']));

        // map scrum stories to github issues
        foreach ($response as $story) {
            $issues[$story['github_id']]['scrumdo_id'] = $story['id'];
        }

        foreach ($issues as $key => $issue) {
            if (!preg_match('/^[0-9]+$/', $key)) {
                continue;
            }

            $scrumdo->saveStory($issue);
        }
    }


    /**
     * Handle closed github issues.
     *
     * @param  Date $timestamp ISO 8601 formattet date
     */
    protected function handleClosedGithubIssues($timestamp)
    {
        $github  = new GithubService($this->output);
        $scrumdo = new ScrumDoService($this->output);
        $issues  = $github->fetchIssues($timestamp, 'closed');

        $count = count($issues['ids']);
        if (0 == $count) {
            return;
        }

        $this->output->writeln('<info>Proccessing '.$count.' "closed" issues from Github</info>');

        // Lookup issues on ScrumDo, we need this to figure out wether to update or create the story
        $response = $scrumdo->doSearch(implode(', ', $issues['search_tags']));

        foreach ($response as $story) {
            $scrumdo->closeStory($story);
        }
    }
}
