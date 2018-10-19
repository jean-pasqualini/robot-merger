<?php

namespace App;

use Gitlab\Client;
use Gitlab\Model\MergeRequest;
use Gitlab\Model\Project;

require_once __DIR__.'/vendor/autoload.php';

class Configuration {
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getGitlabUrl(): string
    {
        return $this->configuration['gitlab_url'];
    }

    public function getGitlabToken(): string
    {
        return $this->configuration['gitlab_token'];
    }

    public function getProjectId(): string
    {
        return $this->configuration['project_id'];
    }
}

class AutoMerger {
    private $jetonStorage;
    private $client;
    private $project;

    public function __construct(JetonStorage $storage, Client $client, Project $project)
    {
        $this->jetonStorage = $storage;
        $this->client = $client;
        $this->project = $project;
    }

    public function execute()
    {
        $mrs = $this->client->api('mr')->all(365, ['labels' => 'Ready for merge', 'state' => 'opened']);
        foreach ($mrs as $mr) {
            $mrModel = MergeRequest::fromArray($this->client, $this->project, $mr);

            echo 'Mr '.$mrModel->title. ' de '.$mrModel->author->name.'('.$mrModel->author->id.')'.PHP_EOL;

            $jetonsDisponible = $this->jetonStorage->getCountJeton($mrModel->author->id);

            echo $jetonsDisponible.' jetons disponible'.PHP_EOL;

            if ($jetonsDisponible < 1) {
                echo 'Envoie d\'un message sur slack, il faut que tu review pour pouvoir merger ta pr (lien)'.PHP_EOL;
                continue;
            }

            try {
                echo 'Je merge automatiquement avec le message "'.$mrModel->title.' (merged by robot)"'.PHP_EOL;
                $this->jetonStorage->useJeton($mrModel->author->id);
            } catch (\Exception $e) {
                echo 'Pour une raison x, le merge automatique à été impossible'.PHP_EOL;
            }
        }

    }
}


class JetonStorage {
    private $jetons = [
        88 => 5
    ];

    public function getCountJeton($authorId)
    {
        return $this->jetons[$authorId];
    }

    public function hasJeton($authorId)
    {
        return $this->getCountJeton($authorId) > 0;
    }

    public function useJeton($authorId)
    {
        $this->jetons[$authorId]--;
    }

    public function addJeton($authorId)
    {
        if (!isset($this->jetons[$authorId])) {
            $this->jetons[$authorId] = 0;
        }

        $this->jetons[$authorId]++;
    }
}

class ReviewerMemory {
    private $mrReviewerByUser = [
        // id author => [ids mrs]
    ];

    public function declareReview($authorId, $mrId)
    {
        if (!isset($this->mrReviewerByUser[$authorId])) {
            $this->mrReviewerByUser[$authorId] = [];
        }

        if (!in_array($mrId, $this->mrReviewerByUser[$authorId])) {
            $this->mrReviewerByUser[$authorId][] = $mrId;

            return true;
        }

        return false;
    }
}

class JetonCollector {

    private $jetonStorage;
    private $reviewerMemory;
    private $client;
    private $project;

    public function __construct(JetonStorage $storage, ReviewerMemory $reviewerMemory, Client $client, Project $project)
    {
        $this->reviewerMemory = $reviewerMemory;
        $this->jetonStorage = $storage;
        $this->client = $client;
        $this->project = $project;
    }

    public function execute()
    {
        $mrs = $this->client->api('mr')->all(365, ['labels' => 'Ready For Review', 'state' => 'opened']);
        foreach ($mrs as $mr) {
            $mrModel = MergeRequest::fromArray($this->client, $this->project, $mr);

            echo 'Mr ' . $mrModel->title . ' de ' . $mrModel->author->name . '(' . $mrModel->author->id . ')' . PHP_EOL;

            $comments = $mrModel->showComments();

            foreach ($comments as $comment) {
                if ($this->reviewerMemory->declareReview($comment->author->id, $mrModel->id)) {
                    $this->jetonStorage->addJeton($comment->author->id);
                    echo "\t".'+1 jeton pour '.$comment->author->name.PHP_EOL;
                }
            }
        }
    }
}


$configuration = new Configuration(parse_ini_file(__DIR__.'/setting.ini'));

$client = \Gitlab\Client::create($configuration->getGitlabUrl())
    ->authenticate($configuration->getGitlabToken(), \Gitlab\Client::AUTH_URL_TOKEN)
;

$project = Project::fromArray($client, $client->api('projects')->show($configuration->getProjectId()));


$jetonStorage = new JetonStorage();
$reviewerMemory = new ReviewerMemory();

$autoMerged = new AutoMerger($jetonStorage, $client, $project);
$jetonCollector = new JetonCollector($jetonStorage, $reviewerMemory, $client, $project);

$jetonCollector->execute();


// On parcours la liste des mr pour savoir les discutions en cours
