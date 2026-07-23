<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Simple key/value application settings : admin-editable configuration that must not require a redeploy
 * (currently the annual price of the Creator/Pro packs). Read through SettingRepository.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];
}
