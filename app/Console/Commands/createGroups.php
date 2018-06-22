<?php

namespace App\Console\Commands;

use App\Backend;
use App\Helpers\AdminTokens;
use App\Services\SchemaManager;
use App\Services\ObjectManager;

use Illuminate\Console\Command;

class createGroups extends Command
{
    const PROJECT_NAME = 'tmk';
    const GROUPS_COLLECTION = 'Groups';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:groups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create tmk groups';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function collections()
    {
        return [
            'KVNTeams',
            'footballTeam',
            'Companies',
            'Sections',
            'Statuses'
        ];
    }

    private function init(string $project)
    {
        $backend = (new AdminTokens)->getSession($project);
        app()->instance(Backend::Class, $backend);
    }

    private function getParentGroup(string $collection)
    {
        $schema = app(SchemaManager::Class)->find(self::GROUPS_COLLECTION);
        $object = app(ObjectManager::Class)->search($schema, [
            'take' => 1,
            'where' => [
                'title' => $collection
            ]
        ])->first();
        return is_null($object) ? null : $object->id;
    }

    private function createParentGroup(string $collection): string
    {
        $schema = app(SchemaManager::Class)->find(self::GROUPS_COLLECTION);
        return app(ObjectManager::Class)->create($schema, [
            'title' => $collection
        ])->id;
    }

    private function createGroupForElement(string $title, $parentId = null)
    {
        $schema = app(SchemaManager::Class)->find(self::GROUPS_COLLECTION);
        return app(ObjectManager::Class)->create($schema, [
            'title' => $title,
            'parentId' => $parentId
        ])->id; 
    }

    private function createGroupsForCollection(string $collection)
    {
        try {
            $schema = app(SchemaManager::Class)->find($collection);
        } catch (\Exception $e) {
            $this->error("Can`t find $collection collection");
            return false;
        }       

        $schemaFields = collect($schema->fields)->mapWithKeys(function($item) {
            return [$item['name'] => true];
        })->toArray();

        if (isset($schemaFields['groupId'])) {
            $this->info("$collection is in progress");

            $parentId = $this->getParentGroup($collection);
            if (is_null($parentId)) {
                $parentId = $this->createParentGroup($collection);
            }

            $objects = app(ObjectManager::Class)->search($schema, [
                'take' => -1,
                'where' => [
                    'groupId' => [
                        '$exists' => false
                    ]
                ]
            ]);

            foreach ($objects as $object) {
                $title = $object->fields['title'] ?? $object->fields['Title'] ?? '';
                if ($title) {
                    $id = $this->createGroupForElement($title, $parentId);
                    $this->info("Created $id element with $title title");
                }
            }
        } else {
            $this->error("Collection $collection has not groupId field");
        }

        return true;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Started');

        $this->init(self::PROJECT_NAME);

        foreach ($this->collections() as $collection) {
            $this->createGroupsForCollection($collection);
        }

        $this->info('Done');
    }
}
