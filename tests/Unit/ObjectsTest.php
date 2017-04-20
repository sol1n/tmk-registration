<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\User;
use App\Services\SchemaManager;
use App\Services\ObjectManager;
use App\Exceptions\Object\ObjectNotFoundException;

class ObjectsTest extends TestCase
{
    public function testCanFindObject()
    {
        $this->withSession(['session-token' => env('TEST_TOKEN')]);

        $schemaManager = new SchemaManager;
        $objectManager = new ObjectManager;

        $schemaName = 'meeting1';
        $fields = ['userId' => 0, 't' => '', 't2' => '', 't3' => '', 'test' => null];

        $schema = $schemaManager->find($schemaName);
        $object = $objectManager->create($schema, $fields);

        $id = $object->id;

        $wantedObject = $objectManager->Find($schema, $id);

        $this->assertEquals($id, $wantedObject->id);
    }

    public function testCanCreateObject()
    {
        $this->withSession(['session-token' => env('TEST_TOKEN')]);

        $schemaManager = new SchemaManager;
        $objectManager = new ObjectManager;

        $schemaName = 'meeting1';
        $fields = ['userId' => 0, 't' => '', 't2' => '', 't3' => '', 'test' => null];

        $schema = $schemaManager->find($schemaName);
        $object = $objectManager->create($schema, $fields);

        $this->assertTrue(! empty($object->id));

        $objectManager->delete($schema, $object->id);
    }

    public function testCanDeleteObject()
    {
        $this->withSession(['session-token' => env('TEST_TOKEN')]);

        $schemaManager = new SchemaManager;
        $objectManager = new ObjectManager;

        $this->expectException(ObjectNotFoundException::class);

        $schemaName = 'meeting1';
        $fields = ['userId' => 0, 't' => '', 't2' => '', 't3' => '', 'test' => null];

        $schema = $schemaManager->find($schemaName);
        $object = $objectManager->create($schema, $fields);

        $id = $object->id;

        $objectManager->delete($schema, $id);

        $objectManager->find($schema, $id);
    }

    public function testCanSaveObject()
    {
        $this->withSession(['session-token' => env('TEST_TOKEN')]);

        $schemaManager = new SchemaManager;
        $objectManager = new ObjectManager;

        $schemaName = 'meeting1';
        $fields = ['userId' => 0, 't' => '', 't2' => '', 't3' => '', 'test' => null];
        $updatedFields = ['userId' => 1, 't' => '', 't2' => '', 't3' => '', 'test' => null];

        $schema = $schemaManager->find($schemaName);
        $object = $objectManager->create($schema, $fields);
        $object = $objectManager->save($schema, $object->id, $updatedFields);

        $this->assertEquals(1, $object->fields['userId']);

        $objectManager->delete($schema, $object->id);
    }
}
