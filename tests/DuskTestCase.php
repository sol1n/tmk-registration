<?php

namespace Tests;

use Laravel\Dusk\TestCase as BaseTestCase;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;

use App\Backend;
use App\Helpers\AdminTokens;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;

    protected $backend;

    public function setUp()
    {
        parent::setUp();
        if (! isset(app(Backend::Class)->token)) {
            $adminTokens = new AdminTokens();
            $this->backend = $adminTokens->getSession(env('PROJECT_NAME'));
            app()->instance(Backend::class, $this->backend);
        }
    }

    /**
     * Prepare for Dusk test execution.
     *
     * @beforeClass
     * @return void
     */
    public static function prepare()
    {
        static::startChromeDriver();
    }

    /**
     * Create the RemoteWebDriver instance.
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    protected function driver()
    {
        $options = (new ChromeOptions)->addArguments([
            '--disable-gpu',
            '--headless'
        ]);

        return RemoteWebDriver::create(
            'http://localhost:9515', DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
