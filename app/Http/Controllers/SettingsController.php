<?php

namespace App\Http\Controllers;

use App\Models\WritingSetting;
use App\Services\Connections\ConnectionManager;
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
        $connection = app(ConnectionManager::class)->status();

        return ['settings' => $this->settings()->only(['id', 'mode', 'live_analysis', 'dark_mode']), 'version' => config('writing.version'), ...$connection, 'debounce_ms' => config('writing.debounce'), 'connection_notice' => request()->hasSession() ? request()->session()->pull('connection_notice') : null];
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
