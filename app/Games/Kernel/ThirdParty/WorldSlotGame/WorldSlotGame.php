<?php

namespace App\Games\Kernel\ThirdParty\WorldSlotGame;

use App\Models\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WorldSlotGame
{
    private static array $highlightedProviders = [
        'GAMEART',
        'PLAYSTAR',
        'NOVOMATIC',
        'REDTIGER',
        'PGSOFT',
        'PRAGMATIC',
        'EVOPLAY',
        'REELKINGDOM',
        'HABANERO',
        'BOOONGO',
        'PLAYSON',
        'CQ9',
        'DREAMTECH',
        'TOPTREND',
         'EVOLUTION',
        'GENESIS',
         'EZUGI',
    ];

    public static function keys(): array
    {
        return [
            'apiMode' => Settings::get('[WorldSlotGame] API Mode', 'seamless'),
            'agentCode' => Settings::get('[WorldSlotGame] Agent Code', 'Yousif'),
            'agentToken' => Settings::get('[WorldSlotGame] Agent Token', 'aaa18c4081d7b2e5bbd6dad850447fee'),
            'apiUrl' => Settings::get('[WorldSlotGame] API URL', 'https://api.worldslotgame.com/api/v2')
        ];
    }

    public static function debug(): bool
    {
        return Settings::get('[WorldSlotGame] Debug', 'false') === 'true';
    }

    public static function type(): string
    {
        return Settings::get('[WorldSlotGame] Key Type', 'staging');
    }

    public static function request(string $url, array $body = [], string $method = 'post'): array
    {
        if (isset($body['amount'])) {
            $body['amount'] = intval($body['amount']);
        }

        $body = array_merge($body, ['agent_code' => self::keys()['agentCode'], 'agent_token' => self::keys()['agentToken']]);

        if (self::debug()) {
            Log::info(self::keys());
        }

        if (self::debug()) Log::info("Request: " . $url . " " . json_encode($body));

        $client = Http::baseUrl(self::keys()['apiUrl'])->timeout(100);
        //$client = Http::timeout(10);

        $response = null;

        if (mb_strtolower($method) === 'post') {
            $response = $client->post($url, $body);
        } else if (mb_strtolower($method) === 'put') {
            $response = $client->put($url, $body);
        } else if (mb_strtolower($method) === 'delete') {
            $response = $client->delete($url, $body);
        }

        $status = $response->status();
        $jsonResponse = $response->body();
    
        if (self::debug()) {
            Log::info('WorldSlotGame response: ' . $status . " " . $jsonResponse);
        }
    
        $data = json_decode($jsonResponse, true);

        return [
            'data' => $data,
            'status' => array_key_exists('status', $data) && !$data['status'] ? 400 : $status,
            'nonce' => null,
        ];
    }

    public function getProviders(): array
    {
        if (Cache::has('worldslotgame:loadingGameList'))
            return [];

        if (Cache::has('worldslotgame:providerGameList')) {
            $providers = [];
            $items = Cache::get('worldslotgame:providerGameList');

            $providerIds = [];
            foreach ($items as $item)
                if (!in_array($item['provider']['code'], $providerIds)) $providerIds[] = $item['provider']['code'];

            foreach ($providerIds as $providerID) {
                $games = collect($items)->filter(function ($item) use ($providerID) {
                    return $item['provider']['code'] === $providerID;
                })->toArray();

                $provider = new WorldSlotGameGame($games);
                if (count($provider->createInstances()) > 0) $providers[] = $provider;
            }

            return $providers;
        }

        try {
            Cache::put('worldslotgame:loadingGameList', 'true');

            $providerList = self::request('provider_list', ['game_type' => 'slot'], 'post')['data'];

            if (!isset($providerList['status']) || !$providerList['status']) return [];

            $providerGames = [];
            $providers = $providerList['providers'];

            foreach ($providers as $provider) {
                if (!in_array($provider['code'], self::$highlightedProviders)) continue;

                $data = self::request('game_list', ['lang' => 'pt', 'provider_code' => $provider['code']], 'post')['data'];

                if (isset($data['games'])) {
                    $games = collect($data['games'])->filter(function ($game) {
                        return $game['status'];
                    })->map(function ($game) use ($provider) {
                        $game['provider'] = $provider;
                        return $game;
                    });

                    $providerGames = array_merge($providerGames, $games->toArray());
                }

                sleep(1); // anti rate-limit
            }

            $providerGames = collect($providerGames)->filter(function ($providerGame) {
                return in_array($providerGame['provider']['code'], self::$highlightedProviders);
            });

            $providerGames = collect($providerGames)->sortBy(['provider.code', 'game_name'])->values()->toArray();
            Cache::put('worldslotgame:providerGameList', $providerGames, Carbon::now()->addDays(7));
            Cache::forget('worldslotgame:loadingGameList');
            Cache::forget('game:list');
        } catch (\Exception $e) {
            Log::error($e);
            Cache::forget('worldslotgame:loadingGameList');
            return [];
        }

        return $this->getProviders();
    }

    public static function createUser($username)
    {
        $data = self::request('user_create', ['user_code' => $username], 'post')['data'];

        if (!$data['status']) {
            Log::error('[WorldSlotGame] Erro ao realizar o cadastro do usuário', $data);
            throw new \Exception($data['msg']);
        }

        return $data;
    }

    public static function deposit(string $username, $amount)
    {
        $body = ['user_code' => $username, 'amount' => $amount];
        $data = self::request('user_deposit', $body, 'post')['data'];

        if (!$data['status']) {
            Log::error('[WorldSlotGame] Erro ao realizar o depósito', $data);
            throw new \Exception($data['msg']);
        }

        return $data;
    }

    public static function withdraw(string $username, $amount)
    {
        $body = ['method' => 'user_withdraw', 'user_code' => $username, 'amount' => $amount];
        $data = self::request('', $body, 'post')['data'];

        if (!$data['status']) {
            Log::error('[WorldSlotGame] Erro ao realizar a retirada', $data);
            throw new \Exception($data['msg']);
        }

        return $data;
    }

    public function launchGame($gameCode, array $data = [])
    {
        $response = self::request('game_launch', [
            'user_balance' => $data['user_balance'],
            // 'game_type' => 'slot',
            'game_code' => $gameCode,
            'user_code' => $data['user_code'],
            'provider_code' => $data['provider_code'],
            'lang' => 'pt',
        ])['data'];

        if (!isset($response['launch_url'])) {
            switch ($response['status']) {
                case 403:
                    $message = 'The authenticated user is not allowed to access the specified API endpoint.';
                    break;
                case 401:
                    $message = 'Authentication failed.';
                    break;
                default:
                    Log::error('WorldSlotGame error');
                    Log::error($response);
                    $message = 'Error during game initialization';
                    break;
            }
            return ['error' => ['message' => $message]];
        }

        return [
            'id' => '-1',
            'type' => 'third-party',
            'link' => $response['launch_url']
        ];
    }
}
