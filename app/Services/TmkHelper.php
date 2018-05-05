<?php

namespace App\Services;

use App\User;
use App\Backend;
use App\Services\SchemaManager;
use App\Services\ObjectManager;
use App\Services\FileManager;

use App\Exceptions\Site\EmptyCompanyList;

class TmkHelper
{
    const FILE_DIRECORY = 'dea7f51b-f59b-4bdd-950f-fe07b531ea78';

    private $backend;

    public function __construct(Backend $backend)
    {
        $this->backend = $backend;
    }

    public function getRandomPassword(int $length = 6): string 
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[rand(0, $charactersLength - 1)];
        }
        return $result;
    }

    public function getCurrentUser()
    {
        $user = new User();

        return app(ObjectManager::Class)->search(app(SchemaManager::Class)->find('UserProfiles'), [
            'take' => 1,
            'where' => ['userId' => $user->id]
        ])->first();
    }

    public function checkCompanyAvailability($profile, $companyCode)
    {
        if (isset($profile->fields) and is_array($profile->fields) and isset($profile->fields['companies']) and is_array($profile->fields['companies'])) {
            $schema = app(SchemaManager::Class)->find('Companies');
            $companies = app(ObjectManager::Class)->search($schema, [
                'take' => -1,
                'where' => ['id' => ['$in' => $profile->fields['companies']]]
            ])->mapWithKeys(function($item) {
                return [$item->id => $item->fields];
            });
            
            if (! is_null($companyCode) and !isset($companies[$companyCode])) {
                throw new EmptyCompanyList('Can`t find company with code ' . $companyCode);
            }

            return $companies;
        } else {
            throw new EmptyCompanyList('Can`t process companies list');
        }
    }

    public function uploadFile($file) {
        $fileProperties = [
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'fileProperties' => [
                "rights" => [
                    "read" => true,
                    "write" => true,
                    "delete" => true
                ],
            ],
            "shareStatus" => "shared"
        ];

        $result = app(FileManager::class)->createFile($fileProperties, self::FILE_DIRECORY);
        $uploadResult = app(FileManager::class)->uploadFile(
            $result['file']->id,
            $file
        );
        if ($uploadResult) {
            return $result['file']->id;
            
        } else {
            throw new EmptyCompanyList('Can`t upload file');
        }
    }

}