<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webpatser\Uuid\Uuid;

class MyCompany extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $fillable = [
      'name',
      'hash',
      'email',
      'mobile',
      'address',
      'country',
      'logo',
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->hash = (string) Uuid::generate(4);
        });
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'client_id', 'id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'my_company_id', 'id');
    }

}
