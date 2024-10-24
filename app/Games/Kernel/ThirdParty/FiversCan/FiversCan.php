<?php

namespace App\Games\Kernel\ThirdParty\FiversCan;

use App\Models\Settings;
use App\Utils\Exception\UserNotAuthenticated;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FiversCan
{
    private static array $highlightedProviders = [
        'PGSOFT',
        'PRAGMATIC',
        'EVOPLAY',
        'REELKINGDOM',
        'HABANERO',
        // 'BOOONGO',
        'PLAYSON',
        // 'CQ9',
        // 'DREAMTECH',
        'TOPTREND',
        'EVOLUTION',
        // 'GENESIS',
        'EZUGI',
    ];

    public static function keys(): array
    {
        return [
            'agentCode' => Settings::get('[FiversCan] Agent Code', 'german123'),
            'agentToken' => Settings::get('[FiversCan] Agent Token', '066418e315e8a23371d8337293d9064e'),
            'apiUrl' => Settings::get('[FiversCan] API URL', 'https://api.fiverscan.com')
        ];
    }

    public static function debug(): bool
    {
        return Settings::get('[FiversCan] Debug', 'false') === 'true';
    }

    public static function type(): string
    {
        return Settings::get('[FiversCan] Key Type', 'staging');
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

        $client = Http::baseUrl(self::keys()['apiUrl']);

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
            Log::info('FiversCan response: ' . $status . " " . $jsonResponse);
        }
    
        $data = json_decode($jsonResponse, true);

        return [
            'data' => $data,
            'status' => !$data['status'] ? 400 : $status,
            'nonce' => null,
        ];
    }

    public function getProviders(): array
    {
        if (Cache::has('fiverscan:loadingGameList'))
            return [];

        if (Cache::has('fiverscan:providerGameList')) {
            $providers = [];
            $items = Cache::get('fiverscan:providerGameList');

            $providerIds = [];
            foreach ($items as $item)
                if (!in_array($item['provider']['code'], $providerIds)) $providerIds[] = $item['provider']['code'];

            foreach ($providerIds as $providerId) {
                $games = array_filter($items, function ($e) use ($providerId) {
                    return $e['provider']['code'] == $providerId;
                });

                $provider = new FiversCanGame($providerId, $games);
                if (count($provider->createInstances()) > 0) $providers[] = $provider;
            }

            return $providers;
        }

        try {
            Cache::put('fiverscan:loadingGameList', 'true');

            $data = self::request('', ['method' => 'provider_list'], 'post')['data'];

            if (!isset($data['status']) || !$data['status']) return [];

            $providerGames = [];
            $providers = $data['providers'];

            foreach ($providers as $provider) {
                if (!$provider['status'] || !in_array($provider['code'], self::$highlightedProviders)) continue;

                $data = self::request('', ['method' => 'game_list', 'provider_code' => $provider['code']], 'post')['data'];

                if (isset($data['games'])) {
                    $games = collect($data['games'])->filter(function ($game) {
                        return $game['status'];
                    })->map(function ($game) use ($provider) {
                        $game['provider'] = $provider;
                        return $game;
                    })->toArray();

                    $providerGames = array_merge($providerGames, $games);
                }

                sleep(1); // anti rate-limit
            }

            usort($providerGames, function ($a, $b) {
                if (in_array($b['provider']['code'], self::$highlightedProviders)) return 1;
                return -1;
            });

            $providerGames = collect($providerGames)->sortBy(['provider.code', 'game_name'])->values()->toArray();
            Cache::put('fiverscan:providerGameList', $providerGames, Carbon::now()->addDays(1));
            Cache::forget('fiverscan:loadingGameList');
            Cache::forget('game:list');
            return $this->getProviders();
        } catch (\Exception $e) {
            Log::error($e);
            Cache::forget('fiverscan:loadingGameList');
            return [];
        }
    }

    public function createUser($username)
    {
        $data = self::request('', ['method' => 'user_create', 'user_code' => $username], 'post')['data'];

        if (!$data['status']) throw new \Exception($data['msg']);

        return $data;
    }

    public function deposit(string $username, $amount)
    {
        $body = ['method' => 'user_deposit', 'user_code' => $username, 'amount' => $amount];
        $data = self::request('', $body, 'post')['data'];

        if (!$data['status']) {
            Log::error($data);
            throw new \Exception($this->formatMessage($data['msg']));
        }

        return $data;
    }

    public function launchGame($gameCode, array $data = [])
    {
        if (!isset($data['user_code']) || empty($data['user_code'])) return ['error' => ['message' => 'O ID do jogo é inválido.']];
        if (!isset($data['provider_code']) || empty($data['provider_code'])) return ['error' => ['message' => 'O ID do provedor é inválido.']];

        if (auth('sanctum')->user() == null) {
            throw new UserNotAuthenticated();
        } else {
            $response = self::request('', [
                'method' => 'game_launch',
                'game_code' => $gameCode,
                'user_code' => $data['user_code'],
                'provider_code' => $data['provider_code'],
                'lang' => 'en',
            ], 'post')['data'];
        }

        if (!isset($response['launch_url'])) {
            switch ($response['status']) {
                case 403:
                    $message = 'The authenticated user is not allowed to access the specified API endpoint.';
                    break;
                case 401:
                    $message = 'Authentication failed.';
                    break;
                default:
                    Log::error('FiversCan error');
                    Log::error($response);
                    $message = 'Error during game initialization';
                    break;
            }
            return ['error' => ['message' => $message]];
        }

        return [
            'id' => $gameCode,
            'type' => 'third-party',
            'link' => $response['launch_url']
        ];
    }

    private function formatMessage($code)
    {
        switch ($code) {
            case 'INVALID_METHOD':
                return 'O método de solicitação é inválido';
            case 'INVALID_PARAMETER':
                return 'O parâmetro de solicitação é inválido';
            case 'INVALID_AGENT':
                return 'O agente é inválido';
            case 'INVALID_AGENT_ROLE':
                return 'A função do agente é inválida';
            case 'BLOCKED_AGENT':
                return 'Agente está bloqueado';
            case 'INVALID_USER':
                return 'O usuário é inválido';
            case 'INSUFFICIENT_AGENT_FUNDS':
                return 'O saldo do agente não é suficiente';
            case 'INSUFFICIENT_USER_FUNDS':
                return 'O saldo do usuário não é suficiente';
            case 'DUPLICATED_USER':
                return 'O código do usuário está duplicado';
            case 'INVALID_PROVIDER':
                return 'O provedor é inválido';
            case 'INTERNAL_ERROR':
                return 'Erro do Servidor Interno';
            case 'EXTERNAL_ERROR':
                return 'Erro de servidor externo';
            case 'API_CHECKING':
                return 'API agora está verificando';
            case 'AGENT_SEAMLESS':
                return 'Erro contínuo do agente';
            default:
                return $code;
        }
    }
}
