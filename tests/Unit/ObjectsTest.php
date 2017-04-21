<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\User;
use App\Services\SchemaManager;
use App\Services\ObjectManager;
use App\Exceptions\Object\ObjectNotFoundException;

class ObjectsTest extends TestCase
{
    private $schemaManager;
    private $objectManager;

    public function setUp()
    {
        parent::setUp();

        $user = User::Login([
            'login' => env('TEST_LOGIN'),
            'password' => env('TEST_PASSWORD')
        ], false);

        $this->withSession(['session-token' => $user->token()]);
        $this->objectManager = new ObjectManager;
        $this->schemaManager = new SchemaManager;
    }

    public function testCanFindObject()
    {
        $schemaName = 'meeting1';
        $fields = ['userId' => 0, 't' => '', 't2' => '', 't3' => '', 'test' => null];

        $schema = $this->schemaManager->find($schemaName);
        $object = $this->objectManager->create($schema, $fields);

        $id = $object->id;

        $wantedObject = $this->objectManager->find($schema, $id);

        $this->assertEquals($id, $wantedObject->id);

        $this->objectManager->delete($schema, $id);
    }

    public function testCanCreateObject()
    {
        $schemaName = 'meeting1';
        $fields = ['userId' => 0, 't' => '', 't2' => '', 't3' => '', 'test' => null];

        $schema = $this->schemaManager->find($schemaName);
        $object = $this->objectManager->create($schema, $fields);

        $this->assertTrue(! empty($object->id));

        $this->objectManager->delete($schema, $object->id);
    }

    public function testCanDeleteObject()
    {

        $this->expectException(ObjectNotFoundException::class);

        $schemaName = 'meeting1';
        $fields = ['userId' => 0, 't' => '', 't2' => '', 't3' => '', 'test' => null];

        $schema = $this->schemaManager->find($schemaName);
        $object = $this->objectManager->create($schema, $fields);

        $id = $object->id;

        $this->objectManager->delete($schema, $id);
        $this->objectManager->find($schema, $id);
    }

    public function testCanSaveObject()
    {
        $schemaName = 'meeting1';
        $fields = ['userId' => 0, 't' => '', 't2' => '', 't3' => '', 'test' => null];
        $updatedFields = ['userId' => 1, 't' => '', 't2' => '', 't3' => '', 'test' => null];

        $schema = $this->schemaManager->find($schemaName);
        $object = $this->objectManager->create($schema, $fields);
        $object = $this->objectManager->save($schema, $object->id, $updatedFields);

        $this->assertEquals(1, $object->fields['userId']);

        $this->objectManager->delete($schema, $object->id);
    }
}
