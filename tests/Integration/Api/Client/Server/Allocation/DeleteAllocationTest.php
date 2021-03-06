<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server\Allocation;

use Illuminate\Http\Response;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class DeleteAllocationTest extends ClientApiIntegrationTestCase
{
    /**
     * Test that an allocation is deleted from the server and the notes are properly reset
     * to an empty value on assignment.
     *
     * @param array $permission
     * @dataProvider permissionDataProvider
     */
    public function testAllocationCanBeDeletedFromServer(array $permission)
    {
        /** @var \Pterodactyl\Models\Server $server */
        [$user, $server] = $this->generateTestAccount($permission);

        /** @var \Pterodactyl\Models\Allocation $allocation */
        $allocation = factory(Allocation::class)->create([
            'server_id' => $server->id,
            'node_id' => $server->node_id,
            'notes' => 'hodor',
        ]);

        $this->actingAs($user)->deleteJson($this->link($allocation))->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseHas('allocations', ['id' => $allocation->id, 'server_id' => null, 'notes' => null]);
    }

    /**
     * Test that an error is returned if the user does not have permissiont to delete an allocation.
     */
    public function testErrorIsReturnedIfUserDoesNotHavePermission()
    {
        /** @var \Pterodactyl\Models\Server $server */
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_ALLOCATION_CREATE]);

        /** @var \Pterodactyl\Models\Allocation $allocation */
        $allocation = factory(Allocation::class)->create([
            'server_id' => $server->id,
            'node_id' => $server->node_id,
            'notes' => 'hodor',
        ]);

        $this->actingAs($user)->deleteJson($this->link($allocation))->assertForbidden();

        $this->assertDatabaseHas('allocations', ['id' => $allocation->id, 'server_id' => $server->id]);
    }

    /**
     * Test that an allocation is not deleted if it is currently marked as the primary allocation
     * for the server.
     */
    public function testErrorIsReturnedIfAllocationIsPrimary()
    {
        /** @var \Pterodactyl\Models\Server $server */
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->deleteJson($this->link($server->allocation))
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonPath('errors.0.code', 'DisplayException')
            ->assertJsonPath('errors.0.detail', 'You cannot delete the primary allocation for this server.');
    }

    /**
     * Test that an allocation cannot be deleted if it does not belong to the server instance.
     */
    public function testErrorIsReturnedIfAllocationDoesNotBelongToServer()
    {
        /** @var \Pterodactyl\Models\Server $server */
        [$user, $server] = $this->generateTestAccount();
        [, $server2] = $this->generateTestAccount();

        $this->actingAs($user)->deleteJson($this->link($server2->allocation))->assertNotFound();
        $this->actingAs($user)->deleteJson($this->link($server, "/network/allocations/{$server2->allocation_id}"))->assertNotFound();
    }

    /**
     * @return array
     */
    public function permissionDataProvider()
    {
        return [[[Permission::ACTION_ALLOCATION_DELETE]], [[]]];
    }
}
