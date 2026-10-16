<?php

namespace App\Http\Controllers;

use App\Models\WritingSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    private function settings()
    {
        return WritingSetting::firstOrCreate(['id' => 1], ['live_analysis' => config('writing.live')]);
    }

    public function show()
    {
        return ['settings' => $this->settings(), 'version' => config('writing.version'), 'provider' => config('writing.provider'), 'configured' => config('writing.provider') === 'mock' || (bool) config('writing.key'), 'debounce_ms' => config('writing.debounce')];
    }

    public function update(Request $r)
    {
        $d = $r->validate(['mode' => ['sometimes', Rule::in(array_keys(config('writing.modes')))], 'live_analysis' => 'sometimes|boolean', 'dark_mode' => 'sometimes|boolean']);
        $s = $this->settings();
        $s->update($d);

        return $this->show();
    }

    public function modes()
    {
        return collect(config('writing.modes'))->map(fn ($m, $id) => ['id' => $id, 'label' => $m['label']])->values();
    }
}
