<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $this->call(PagesTableSeeder::class);
        $this->call(UsersTableSeeder::class);
        $this->call(GroupsTableSeeder::class);
        $this->call(PermissionsTableSeeder::class);
        $this->call(WordsTableSeeder::class);
        $this->call(TagsTableSeeder::class);
        $this->call(ReputationTypesTableSeeder::class);
        $this->call(MicroblogsTableSeeder::class);
        $this->call(NotificationTypesTableSeeder::class);
        $this->call(ForumsTableSeeder::class);
        $this->call(FlagTypesTableSeeder::class);
        $this->call(ForumReasonsTableSeeder::class);
        $this->call(CurrenciesTableSeeder::class);
        $this->call(CountriesTableSeeder::class);
        $this->call(WikiTableSeeder::class);
        $this->call(BlocksTableSeeder::class);
        $this->call(FeaturesTableSeeder::class);
        $this->call(PlansTableSeeder::class);
        $this->call(TagCategoriesTableSeeder::class);
    }
}
