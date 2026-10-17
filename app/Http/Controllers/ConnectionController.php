<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatGptAccountRequest;
use App\Http\Requests\ChatGptModelRequest;
use App\Http\Requests\ChatGptStartRequest;
use App\Http\Requests\ConnectionSelectionRequest;
use App\Models\ChatGptAccount;
use App\Services\AI\AiException;
use App\Services\AI\ChatGptProvider;
use App\Services\Connections\ChatGptAuth;
use App\Services\Connections\ConnectionManager;
use Illuminate\Http\Request;

class ConnectionController extends Controller
{
    public function show(ConnectionManager $c)
    {
        return $c->status();
    }

    public function select(ConnectionSelectionRequest $r, ConnectionManager $c, ChatGptAuth $auth)
    {
        $d = $r->validated();
        $auth->locked(fn () => $c->choose($d['provider'], $d['account_id'] ?? null));

        return $c->status();
    }

    public function start(ChatGptStartRequest $r, ChatGptAuth $auth)
    {
        $d = $r->validated();

        return ['url' => $auth->start($r, $d['account_id'] ?? null)];
    }

    public function callback(Request $r, ChatGptAuth $auth)
    {
        try {
            $a = $auth->finish($r);
            $notice = 'ChatGPT account connected. Load its models and select one to start writing.';
        } catch (AiException $e) {
            $notice = $e->getMessage();
        }

        return redirect('/?connection=return')->with('connection_notice', $notice);
    }

    public function models(ChatGptAccountRequest $r, ChatGptProvider $provider)
    {
        $d = $r->validated();

        return ['models' => $provider->models($d['account_id'])];
    }

    public function model(ChatGptModelRequest $r, ChatGptProvider $provider, ChatGptAuth $auth)
    {
        $d = $r->validated();
        $before = ChatGptAccount::findOrFail($d['account_id']);
        $epoch = $before->auth_epoch;
        $models = $provider->models($d['account_id']);
        if (! in_array($d['model'], array_column($models, 'id'), true)) {
            throw new AiException('Select a model available to this ChatGPT account.', 422);
        }
        $auth->locked(function () use ($d, $epoch) {
            $a = ChatGptAccount::findOrFail($d['account_id']);
            if ($a->auth_epoch !== $epoch || ! $a->credentials) {
                throw new AiException('This connection changed. Reload models.', 409);
            }$a->update(['model' => $d['model']]);
        });

        return ['model' => $d['model']];
    }

    public function disconnect(ChatGptAccountRequest $r, ChatGptAuth $auth)
    {
        $d = $r->validated();
        $confirmed = $auth->disconnect($d['account_id']);

        return ['revocation_confirmed' => $confirmed, 'message' => $confirmed ? 'Signed out. Renewable session revoked.' : 'Signed out locally. Remote revocation was not confirmed; disconnect the app in ChatGPT Settings.'];
    }
}
