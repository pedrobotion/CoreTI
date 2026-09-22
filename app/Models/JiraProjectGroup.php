<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JiraProjectGroup extends Model
{
    protected $fillable = [
        'name',
        'jira_project_id',
        'jira_group_id',
        'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
