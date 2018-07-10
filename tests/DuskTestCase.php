<?php

namespace Tests;

use App\Library;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;

    /* @var Library */
    protected $currentLibrary;

    /**
     * Prepare for Dusk test execution.
     *
     * @beforeClass
     * @return      void
     */
    public static function prepare()
    {
        static::startChromeDriver();
    }

    public function setUp()
    {
        parent::setUp();
        $this->currentLibrary = factory(Library::class)->create([
            'email' => 'post@eksempelbiblioteket.no',
        ]);
    }

    /**
     * Create the RemoteWebDriver instance.
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    protected function driver()
    {
        Browser::$waitSeconds = 15;

        switch (config('dusk.browser')) {
            default:
                $caps = [
                    'platform' => config('testing.platform'),
                    'browserName' => config('testing.browser'),
                    'browserVersion' => config('testing.browser_version'),

                    'chromeOptions' => (new ChromeOptions)->addArguments([
                        '--disable-gpu',
                //     // '--headless'
                    ]),
                ];

                if (env('SAUCE_TUNNEL')) {
                    $caps['tunnel-identifier'] = env('SAUCE_TUNNEL');
                } elseif (env('TRAVIS_JOB_NUMBER')) {
                    $caps['tunnel-identifier'] = env('TRAVIS_JOB_NUMBER');
                }

                // $caps = DesiredCapabilities::chrome()
                //     ->setPlatform('MacOS Sierra 10.12')
                //     ->setCapability(ChromeOptions::CAPABILITY, $options);
                // if (env('TRAVIS_JOB_NUMBER')) {
                //     $caps->setCapability('tunnel-identifier', env('TRAVIS_JOB_NUMBER'));
                // }

                return RemoteWebDriver::create(config('testing.host'), $caps);
        }
    }

    /**
     * Return the default user to authenticate.
     *
     * @return \App\Library|int|null
     * @throws \Exception
     */
    protected function user()
    {
        return $this->currentLibrary;
    }
}
