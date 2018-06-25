<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

class Form extends Page
{
    private $companyUrl;

    public function __construct($companyUrl = null)
    {
        $this->companyUrl = $companyUrl;
    }

    /**
     * Get the URL for the page.
     *
     * @return string
     */
    public function url()
    {
        if (isset($this->companyUrl)) {
            return $this->companyUrl;
        } else {
            return '/form/';
        }
    }

    /**
     * Assert that the browser is on the page.
     *
     * @param  Browser  $browser
     * @return void
     */
    public function assert(Browser $browser)
    {
        $browser->assertPathIs($this->url());
    }

    /**
     * Get the element shortcuts for the page.
     *
     * @return array
     */
    public function elements()
    {
        return [
            '@logout' => 'header > div > p.main-header-logout > a',
            '@create' => '#new-member-form .button-orange-hollow',
            '@addButton' => '.icon-memeber-add',
            '@creationStatusesList' => '#form-status',
            '@creationSectionsList' => '#form-edit-1-section',
            '@creationKVNTeamsList' => '#form-KVNTeam',
            '@creationFootballTeamsList' => '#form-footballTeam',
        ];
    }

    public function logOff(Browser $browser)
    {
        $browser->click('@logout');
    }

    public function createParticipant(Browser $browser, $model)
    {
        $browser->waitFor('@addButton')->click('@addButton');

        foreach ($model['listFields'] as $field => $value) {
            if (is_array($value)) {
                foreach ($value as $one) {
                    $browser->select($field . '[]', $one);
                }
            } else {
                if (in_array($field, ['KVNTeam', 'footballTeam'])) {
                    $browser->select($field, $value);
                } else {
                    $browser->select($field . '[]', $value);
                }
            }
        }

        foreach ($model['textFields'] as $field => $value) {
            $browser->type($field, $value);
        }

        foreach ($model['lectures'] as $lecture) {
            $browser->type('subject[]', $lecture['subject']);
            $browser->type('theses[]', $lecture['theses']);
            $browser->select('section[]', $lecture['section']);
        }

        $browser->press('@create');
    }

    public function deleteParticipant(Browser $browser, string $id)
    {
        $browser->click('[data-delete-member=' . $id . ']');
    }
}
