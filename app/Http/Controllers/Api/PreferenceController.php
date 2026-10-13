<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserPreferenceResource;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller {
    public function show(Request $request): UserPreferenceResource {
        return new UserPreferenceResource($request->user()->preference);
    }

    /** Any subset of fields; the rules are the old app's, which were already tight. */
    public function update(Request $request): UserPreferenceResource {
        $validated = $request->validate([
            'palette'     => ['sometimes', Rule::in(UserPreference::ACCENTS)],
            'appearance'  => ['sometimes', Rule::in(['light', 'dark', 'os'])],
            // Browsers still list older names (Asia/Calcutta, Europe/Kiev) that
            // plain 'timezone' refuses; PHP understands them all the same.
            'timezone'    => ['sometimes', 'timezone:all_with_bc'],
            'week_start'  => ['sometimes', Rule::in(['monday', 'sunday'])],
            'time_format' => ['sometimes', Rule::in(['12', '24'])],
        ]);

        $preference = $request->user()->preference;
        $preference->update($validated);

        return new UserPreferenceResource($preference);
    }
}
