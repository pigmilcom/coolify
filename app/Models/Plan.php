<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'resources_limit' => 'integer',
            'projects_limit' => 'integer',
            'bandwidth_limit' => 'integer',
            'storage_limit' => 'integer',
            'team_members_limit' => 'integer',
        ];
    }

    public function teamPlans(): HasMany
    {
        return $this->hasMany(TeamPlan::class);
    }

    public function formattedPrice(): string
    {
        if ($this->billing_cycle === 'free') {
            return 'Free';
        }

        return '$'.number_format($this->price, 2).' / '.$this->billing_cycle;
    }
}
