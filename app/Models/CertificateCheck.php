<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateCheck extends Model
{
    protected $table = 'certificate_checks';

    protected $fillable = [
        'host', 'expira_em', 'fingerprint', 'status', 'detalhe',
        'verificado_em', 'jira_issue_key', 'jira_aberto_em',
    ];

    protected function casts(): array
    {
        return [
            'expira_em' => 'datetime',
            'verificado_em' => 'datetime',
            'jira_aberto_em' => 'datetime',
        ];
    }
}
