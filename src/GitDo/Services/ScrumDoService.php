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

        $target = 'organizations/'.$this->parameters['organization'].'/projects/'.$this->parameters['project'].'/iterations/'.$this->parameters['iteration'].'/stories';
        $data = [
            'detail'  => $issue['body']."\n\n\nRef: ".$issue['url'],
            'rank'    => 1,
            'summary' => $issue['title'],
            'tags'    => implode(', ', $tags),
            'status'  => $status,
        ];

        if ($issue['assignee']) {
            $data['assignees'] = $issue['assignee'];
        }

        $client = $this->getClient();

        if (empty($issue['scrumdo_id'])) {
            $this->output->writeln('<comment>- creating issue #'.$issue['number'].' ('.$issue['title'].')</comment>');
            $request = $client->post($target, null, $data);
        } else {
            $this->output->writeln('<comment>- updating issue #'.$issue['number'].' ('.$issue['title'].')</comment>');
            $target .= '/'.$issue['scrumdo_id'];
            $request = $client->put($target, null, $data);
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
            ->get('organizations/'.$this->parameters['organization'].'/projects/'.$this->parameters['project'].'/search', null, [
                'query' => ['q' => $search],
            ])
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
     *
     * @param  array $story a ScrumDo story
     */
    public function closeStory($story)
    {
        try {
            $this->output->writeln('<info>- closing story #'.$story['id'].' (git # '.$story['github_id'].')</info>');
            return $this->getClient()
                ->put('organizations/'.$this->parameters['organization'].'/projects/'.$this->parameters['project'].'/stories/'.$story['id'], null, [
                    'status' => 10
                ])
                ->send()
            ;
        } catch (\Exception $e) {
            $this->output->writeln('<error>- could not close issue, reason: '.$e->getMessage().'</error>');
        }
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
