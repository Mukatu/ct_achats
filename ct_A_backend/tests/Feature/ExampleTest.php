<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class ExampleTest extends TestCase
{
    /**
     * Test que les utilisateurs existent en base de données.
     */
    public function test_users_exist_in_database(): void
    {
        $this->assertDatabaseHas('users', [
            'email' => 'admin@congotelecom.cg'
        ]);
    }

    /**
     * Test que les zones existent en base de données.
     */
    public function test_zones_exist_in_database(): void
    {
        $this->assertDatabaseHas('zones', [
            'code' => 'DRC'
        ]);
    }

    /**
     * Test que les rôles existent en base de données.
     */
    public function test_roles_exist_in_database(): void
    {
        $this->assertDatabaseHas('roles', [
            'code' => 'ADMIN'
        ]);
    }
}
