<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class CompanyModel extends Model
{
    protected $table = 'companies';

    protected $fillable = [
        'name',
    ];
}
