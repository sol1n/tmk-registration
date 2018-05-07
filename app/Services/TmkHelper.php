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
    const PHOTO_DIRECTORY = '892630b0-b07c-4cfa-9290-378cd0bfd16e';
    const PRESENTATION_DIRECTORY = '43308c2c-f012-4877-bd12-ffa697b5a42b';

    private $backend;

    public function __construct(Backend $backend)
    {
        $this->backend = $backend;
    }

    public function getRandomPassword(int $length = 6): string 
    {
        return (string) rand(100000, 999999);
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

    public function uploadFile($file, $parent) {
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

        $result = app(FileManager::class)->createFile($fileProperties, $parent);
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

    public function uploadPhoto($file) {
        return $this->uploadFile($file, self::PHOTO_DIRECTORY);
    }

    public function uploadPresentation($file) {
        return $this->uploadFile($file, self::PRESENTATION_DIRECTORY);
    }

}