<?php

namespace App\Services;

use App\Models\CmdbAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CmdbAssetDuplicateService
{
    public function __construct(
        private readonly CmdbQuickCreateService $quickCreateService,
    ) {
    }

    public function prepare(CmdbAsset $asset): array
    {
        $payload = $this->quickCreateService->payloadFromAsset($asset);
        unset(
            $payload['main_ip'],
            $payload['subnet_mask'],
            $payload['gateway'],
            $payload['dns'],
            $payload['vlan'],
            $payload['mac']
        );

        return $payload;
    }

    public function duplicate(CmdbAsset $asset, array $overrides = [], ?User $user = null, bool $copyRelationships = false): CmdbAsset
    {
        $payload = array_merge($this->prepare($asset), $overrides);

        return DB::transaction(function () use ($asset, $payload, $user, $copyRelationships): CmdbAsset {
            $newAsset = $this->quickCreateService->create($payload, $user, 'Ativo duplicado a partir de ativo existente');

            if ($copyRelationships && Schema::hasTable('cmdb_relationships')) {
                foreach ($asset->outgoingRelationships()->get() as $relationship) {
                    $relationship->replicate([
                        'id',
                        'created_at',
                        'updated_at',
                        'deleted_at',
                    ])->fill([
                        'source_asset_id' => $newAsset->id,
                    ])->save();
                }
            }

            return $newAsset;
        });
    }
}
