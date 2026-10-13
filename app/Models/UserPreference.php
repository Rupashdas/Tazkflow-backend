<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model {
    /** The frontend's ACCENTS (src/lib/theme.js). The two lists must match. */
    public const ACCENTS = ['blue', 'violet', 'emerald', 'amber', 'rose'];

    protected $fillable = ['palette', 'appearance', 'timezone', 'week_start', 'time_format'];
}
