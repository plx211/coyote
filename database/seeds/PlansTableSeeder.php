<?php

use Illuminate\Database\Seeder;

class PlansTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \Coyote\Plan::forceCreate([
            'name' => 'Pakiet Mini',
            'price' => 30,
            'vat_rate' => 1.23,
            'discount' => 0,
            'length' => 30,
            'benefits' => ['is_publish']
        ]);

        \Coyote\Plan::forceCreate([
            'name' => 'Pakiet Standard',
            'price' => 126,
            'vat_rate' => 1.23,
            'discount' => 0.45,
            'length' => 30,
            'benefits' => ['is_publish', 'is_ads'],
            'is_default' => 1
        ]);

        \Coyote\Plan::forceCreate([
            'name' => 'Pakiet Max',
            'price' => 270,
            'vat_rate' => 1.23,
            'discount' => 0.5,
            'length' => 30,
            'benefits' => ['is_publish', 'is_ads', 'is_highlight', 'is_on_top', 'is_boost']
        ]);
    }
}
