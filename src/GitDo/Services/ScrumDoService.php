<?php

namespace GitDo\Services;

use Guzzle\Http\Client;

class ScrumDoService extends Services
{
    protected $config_name = 'scrumdo';

    protected $status_map = [
        'igang'    => 4,
        'til test' => 7,
        'done'     => 10,
    ];

    protected $user_map = [
				'by-nissen'     => 'mvnissen88',
        'andersbryrup'  => 'andersbryrup',
        'henrik-farre'  => 'henrik_farre',
        'mrbase'        => 'mrbase',
        'mmh'           => 'mmh',
        'HeinrichDalby' => 'POMPdeLUX',
        'pdlcec'        => 'POMPdeLUX',
    ];


    /**
     * Save a story in ScrumDo
     *
     * @param  array  $issue  Github formatted issue
     * @param  string $action Can either be 'add' or 'update'
     * @return json           Response
     */
    public function saveStory($issue)
    {
        $tags = ['git'.$issue['number']];

        $status = 1;
        foreach ($issue['labels'] as $label) {
            $tags[] = $label['name'];

            if (isset($this->status_map[$label['name']])) {
                $status = $this->status_map[$label['name']];
            }
        }

        $target = 'organizations/{organization}/projects/{project}/iterations/{iteration}/stories';
        $params = [
            'iteration'    => $this->parameters['iteration'],
            'organization' => $this->parameters['organization'],
            'project'      => $this->parameters['project'],
        ];

        $data = [
            'detail'  => $issue['body']."\n\n\nRef: ".$issue['html_url'],
            'status'  => $status,
            'summary' => $issue['title'].' (#'.$issue['number'].')',
            'tags'    => implode(', ', $tags),
        ];

        if (isset($issue['assignee'])) {
            $data['assignees'] = $this->user_map[$issue['assignee']['login']];
        }

        // for some reason a description in scrum cannot start with an "@"
        $data['detail'] = ltrim($data['detail'], '@');

        $client = $this->getClient();

        if (empty($issue['scrumdo_id'])) {
            $data['rank'] = 100;
            $this->output->writeln('<comment>- creating issue #'.$issue['number'].' ('.$issue['title'].')</comment>');
            try {
                $request = $client->post([$target, $params], null, $data);
            } catch (\Exception $e) {
                $this->output->writeln('<comment>- failed to save issue #'.$issue['number'].' ('.$issue['title'].'), reason: '.$e->getMessage().'</comment>');
                return;
            }
        } else {
            $this->output->writeln('<comment>- updating issue #'.$issue['number'].' ('.$issue['title'].')</comment>');

            // "closed by github" issues is auto moved to iteration 113013
            if (10 == $status) {
                $target = 'organizations/{organization}/projects/{project}/iterations/113013/stories';
            }
            $target .= '/'.$issue['scrumdo_id'];

            $request = $client->put([$target, $params], null, $data);
        }

        try {
            return $request->send()->json();
        } catch (\Exception $e) {
            $this->output->writeln('<error>- could not save issue, reason: '.$e->getMessage().'</error>');
        }
    }


    /**
     * Perform ScrumDo search
     *
     * @param  string $search ScrumDo search string
     * @see    http://support.scrumdo.com/kb/faq/searching-for-stories
     * @return array
     */
    public function doSearch($search)
    {
        $response = $this->getClient()
            ->get(['organizations/{organization}/projects/{project}/search{?query*}', [
                'organization' => $this->parameters['organization'],
                'project'      => $this->parameters['project'],
                'query'        => ['q' => $search],
            ]])
            ->send()
        ;

        $stories = $response->json();

        if (0 == $stories['count']) {
            return [];
        }

        $ids = explode(', ', str_replace('tag:', '', $search));

        foreach ($stories['items'] as $i => $item) {
            foreach ($ids as $id) {
                if (in_array($id, $item['tags_list'])) {
                    $stories['items'][$i]['github_id'] = trim($id, 'git');
                    goto end;
                }
            }
            end:
        }

        return $stories['items'];
    }


    /**
     * Close a story
     * Note: "closed by github" issues is auto moved to iteration 113013
     *
     * @param  array $story a ScrumDo story
     */
    public function closeStory($story)
    {
        try {
            $this->output->writeln('<info>- closing story #'.$story['id'].' (git #'.$story['github_id'].')</info>');
            return $this->getClient()
                ->put(['organizations/{organization}/projects/{project}/iterations/113013/stories/{id}', [
                    'organization' => $this->parameters['organization'],
                    'project'      => $this->parameters['project'],
                    'id'           => $story['id'],
                ]], null, ['status' => 10])
                ->send()
            ;
        } catch (\Exception $e) {
            $this->output->writeln('<error>- could not close issue, reason: '.$e->getMessage().'</error>');
        }
    }


    /**
     * Fetch Story comments
     *
     * @param  array $story A ScrumDo story
     * @return array        Array of comments
     */
    public function fetchComments($story)
    {
        $response = $this->getClient()
            ->get(['comments/story/{id}', [
                'id' => $story['id']
            ]])->send()
        ;

        return $response->json();
    }


    /**
     * Get guzzle client for ScrumDo interaction.
     *
     * @return Client Guzzle Client
     */
    protected function getClient()
    {
        static $client;

        if (empty($client)) {
            $client = new Client('https://www.scrumdo.com/api/v2');

            $client->setDefaultOption('auth', [
                $this->parameters['user'],
                $this->parameters['token'],
                'Basic'
            ]);
        }

        return $client;
    }
}
