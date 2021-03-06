<?php

namespace GitDo\Services;

use Guzzle\Http\Client;

class GithubService extends Services
{
    protected $config_name = 'github';


    /**
     * Fetch issues from Github updated, added or closed since last fetch.
     *
     * @param  Date   $ts_since ISO 8601 formattet date
     * @param  string $state    wich state the issues should be in, can either be "open" or "closed"
     * @return array
     */
    public function fetchIssues($ts_since = null, $state = 'open')
    {
        $filter = ['state' => $state];

        if ($ts_since) {
            $filter['since'] = $ts_since;
        }

        $response = $this->getClient()
            ->get(['/repos/{owner}/{repo}/issues{?query*}', [
                'owner' => $this->parameters['organization'],
                'repo'  => $this->parameters['project'],
                'query' => $filter,
            ]])
            ->send();
        ;

        $json = $response->json();

        $issues = [
            'ids'  => [],
            'search_tags' => [],
        ];

        if (empty($json)) {
            return $issues;
        }

        foreach ($json as $issue) {
            if (('closed' == $state) && ($issue['state'] != 'closed')) {
                continue;
            }
            $issues[$issue['number']] = $issue;
            $issues['ids'][$issue['number']] = $issue['number'];
            $issues['search_tags'][] = 'tag:git'.$issue['number'];
        }

        // Loop through all pages, github lists issues in pages of 30 issues pr. page.
        $links = [];
        if ($response->hasHeader('Link')) {
            foreach ($response->getHeader('Link')->getLinks() as $link) {
                if ('next' == $link['rel']) {
                    parse_str(parse_url($link['url'], PHP_URL_QUERY), $filter);

                    $response = $this->getClient()
                        ->get(['/repos/{owner}/{repo}/issues{?query}', [
                            'owner' => $this->parameters['organization'],
                            'repo'  => $this->parameters['project'],
                            'query' => $filter,
                        ]])
                        ->send();
                    ;

                    foreach ($response->json() as $issue) {
                        if (('closed' == $state) && ($issue['state'] != 'closed')) {
                            continue;
                        }
                        $issues[$issue['number']] = $issue;
                        $issues['ids'][$issue['number']] = $issue['number'];
                        $issues['search_tags'][] = 'tag:git'.$issue['number'];
                    }
                }
            }
        }

        return $issues;
    }


    /**
     * Fetch issue comments.
     *
     * @param  Date  $ts_since ISO 8601 formattet date
     * @param  array $issue    Github issue
     * @return array
     */
    public function fetchComments($ts_since, $issue)
    {
        $filter = [];
        if ($ts_since) {
            $filter['since'] = $ts_since;
        }

        $response = $this->getClient()
            ->get(['repos/{owner}/{repo}/issues/{number}/comments{?query*}', [
                'owner'  => $this->parameters['organization'],
                'repo'   => $this->parameters['project'],
                'number' => $issue['number'],
                'query'  => $filter,
            ]])
            ->send();
        ;

        return $response->json();
        // /repos/:owner/:repo/issues/:number/comments
    }


    /**
     * Get guzzle client for Github interaction.
     *
     * @return Client Guzzle Client
     */
    protected function getClient()
    {
        static $client;

        if (empty($client)) {
            $client = new Client('https://api.github.com');

            $client->setDefaultOption('auth', [
                $this->parameters['user'],
                $this->parameters['token'],
                'Basic'
            ]);
        }

        return $client;
    }
}
