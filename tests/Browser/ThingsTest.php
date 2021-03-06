<?php

namespace Tests\Browser;

use App\Thing;
use Tests\Browser\Pages\LoansPage;
use Tests\Browser\Pages\ThingsPage;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ThingsTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function setUp()
    {
        parent::setUp();
        $this->faker = $this->app->make('Faker\Generator');
        $this->faker->seed(1234);
    }

    public function testCanCreateThing()
    {
        $thingName = $this->faker->sentence(2);
        $this->browse(
            function (Browser $browser) use ($thingName) {
                $browser->loginAs('post@eksempelbiblioteket.no');
                // $browser->assertAuthenticated();

                $browser->visit(new ThingsPage)
                    ->clickLink('Ny ting')
                    ->pause(300)
                    ->type('name', $thingName)
                    ->pause(300)
                    ->type('name_indefinite.nob', $this->faker->sentence(3))
                    ->type('name_definite.nob', $this->faker->sentence(3))
                    ->type('name_indefinite.nno', $this->faker->sentence(3))
                    ->type('name_definite.nno', $this->faker->sentence(3))
                    ->type('name_indefinite.eng', $this->faker->sentence(3))
                    ->type('name_definite.eng', $this->faker->sentence(3))
                    ->type('loan_time', $this->faker->randomDigitNotNull)
                    ->pause(300)
                    ->press('Lagre')
                    ->waitForText('Tingen ble lagret')
                    ->waitForText($thingName);
            }
        );
    }
}
