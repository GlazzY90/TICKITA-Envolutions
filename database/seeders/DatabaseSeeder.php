<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $organizationA = Organization::factory()->create([
            'name' => 'Acme Corporation',
        ]);

        $organizationB = Organization::factory()->create([
            'name' => 'Globex Corporation',
        ]);

        User::factory()
            ->forOrganization($organizationA)
            ->create([
                'name' => 'Acme Client',
                'email' => 'client@acme.test',
            ]);

        User::factory()
            ->forOrganization($organizationB)
            ->create([
                'name' => 'Globex Client',
                'email' => 'client@globex.test',
            ]);

        User::factory()
            ->supportAgent()
            ->create([
                'name' => 'Support Agent',
                'email' => 'agent@support.test',
            ]);
    }
}