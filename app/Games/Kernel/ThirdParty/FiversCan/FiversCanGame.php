<?php

namespace App\Games\Kernel\ThirdParty\FiversCan;

use App\Events\BalanceModification;
use App\Games\Kernel\Data;
use App\Games\Kernel\Metadata;
use App\Games\Kernel\ThirdParty\ThirdPartyGame;
use App\Utils\Exception\UnsupportedOperationException;
use App\Utils\Exception\UserNotAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FiversCanGame extends ThirdPartyGame
{
    private string $providerId;

    public function __construct(string $providerId, ?array $data = null)
    {
        parent::__construct($data);

        $this->providerId = $providerId;
    }

    public function provider(): string
    {
        return $this->providerId;
    }

    function metadata(): ?Metadata
    {
        if (!$this->data) return null;

        return new class($this->data) extends Metadata
        {
            private ?array $data;

            public function __construct(?array $data)
            {
                $this->data = $data;
            }

            function id(): string
            {
                return 'external:fc:' . $this->data['game_code'];
            }

            function name(): string
            {
                return $this->data['provider']['type'];
            }

            function icon(): string
            {
                return "slots";
            }

            public function image(): string
            {
                return $this->data['banner'];
            }

            public function category(): array
            {
                $categories = [];

                if ($this->data['provider']['type'] === 'slot')
                    $categories[] = 'slots';
                else
                    $categories[] = 'live';

                return $categories;
            }

            public function isMobile(): ?bool
            {
                return str_contains(strtolower($this->name()), "mobile");
            }
        };
    }

    public function processUserBalance($user, $userBalance)
    {
        $body = [
            'status' => !$user ? false : true,
            'user_balance' => !$user ? 0 : $userBalance->get(),
        ];

        if (!$user) {
            $body['msg'] = 'INVALID_USER';
        }

        return $body;
    }

    public function processTransaction($user, $currency, array $data)
    {
        try {
            $code = $data['game_code'];
            $gameName = $data['type'];
            $game = \App\Models\Game::where('fc_gameId', $code)->first();

            if (!$game) {
                $game = \App\Models\Game::create([
                    'id' => DB::table('games')->count() + 1,
                    'user' => $user->_id,
                    'game' => 'external:fc:' . $code,
                    'multiplier' => 0,
                    'status' => 'in-progress',
                    'profit' => 0,
                    'data' => [],
                    'type' => 'third-party',
                    'demo' => false,
                    'wager' => $data['bet_money'],
                    'currency' => $currency->id(),
                    'fc_gameId' => $code,
                    'bet_usd_converted' => floatval($data['bet_money'])
                ]);
            }

            $profit = $game->profit;

            if ($data['win_money'] > 0) {
                if (!$game->sentUpdate) {
                    try {
                        self::analytics($game, 'Slots');
                        event(new \App\Events\LiveFeedGame($game, 0));
                    } catch (\Exception $ignored) {
                        //
                    }

                    // if ($user->vipLevel() > 0 && ($user->weekly_bonus ?? 0) < 100)
                    //   $user->update(['weekly_bonus' => ($user->weekly_bonus ?? 0) + 0.1]);
                }

                $game->update(['sentUpdate' => true]);

                $profit += floatval($data['win_money']);
            }

            $payout = $profit > 0 && $game->wager > 0 ? $profit / $game->wager : 0;

            $game->update([
                'multiplier' => $payout,
                'status' => $payout > 0 ? 'win' : 'lose',
                'profit' => $profit
            ]);

            $userBalance = $user->balance($currency);

            $userBalance->subtract(
                floatval($data['bet_money']),
                \App\Models\Transaction::builder()->game($gameName)->message('[FiversCan] Ação: aposta / ID transação: ' . $data['txn_id'])->get(),
                $data['txn_id'],
                'bet'
            );

            event(new BalanceModification($user, $currency, 'subtract', $game->demo, $game->wager, 0));

            if ($data['win_money'] > 0) {
                $userBalance->add(
                    floatval($data['win_money']),
                    \App\Models\Transaction::builder()->game($gameName)->message('[FiversCan] Ação: vitória / ID transação: ' . $data['txn_id'])->get(),
                    0,
                    $data['txn_id'],
                    'win'
                );

                event(new BalanceModification($user, $currency, 'add', $game->demo, $game->profit, 0));
            }
            $status = true;
        } catch (\Exception $exception) {
            $status = false;
        }

        return [
            'status' => $status,
        ];
    }

    function processCallback(array $data): array
    {
        return [];
    }

    function process(Data $data): array
    {
        $metadata = $this->metadata();

        // if (!in_array('live', $metadata->category())) {
        if(true) {
            if ($data->demo() || $data->user() == null) {
                throw new UserNotAuthenticated();
            } else {
                $response = FiversCan::request('', [
                    'method' => 'game_launch',
                    'user_code' => $data->user()->name,
                    'provider_code' => $this->provider(),
                    'game_code' => str_replace("external:fc:", "", $metadata->id()),
                    'lang' => 'en',
                ]);
            }

            if (!isset($response['data']['launch_url'])) {
                switch ($response['data']['status']) {
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
                'response' => [
                    'id' => '-1',
                    'wager' => $data->bet(),
                    'type' => 'third-party',
                    'link' => $response['data']['launch_url']
                ]
            ];
        } else {
            throw new UnsupportedOperationException();
        }

        return [];
    }

    // function process(Data $data): array
    // {
    //     $metadata = $this->metadata();

    //     $response = FiversCan::request('', [
    //         'method' => 'game_launch',
    //         'user_code' => $data->user()->name,
    //         'provider_code' => $this->provider(),
    //         'game_code' => str_replace("external:fc:", "", $metadata->id()),
    //         'lang' => 'en',
    //     ]);

    //     if (!isset($response['launch_url'])) {
    //         switch ($response['status']) {
    //             case 403:
    //                 $message = 'The authenticated user is not allowed to access the specified API endpoint.';
    //                 break;
    //             case 401:
    //                 $message = 'Authentication failed.';
    //                 break;
    //             default:
    //                 Log::error('FiversCan error', $response);
    //                 $message = 'Error during game initialization';
    //                 break;
    //         }
    //         return ['error' => ['message' => $message]];
    //     }

    //     return [
    //         'response' => [
    //             'id' => '-1',
    //             'wager' => $data->bet(),
    //             'type' => 'third-party',
    //             'link' => $response['launch_url']
    //         ]
    //     ];
    // }

    public function createInstances(): array
    {
        $games = [];

        $blacklist = [
            '',
        ];

        $gameNames = [];

        foreach ($this->data as $game) {
            $name = str_replace(" Mobile", "", $game['game_name']);

            if (!in_array($name, $gameNames) && !in_array($game['id'], $blacklist)) {
                $games[] = new FiversCanGame($this->providerId, $game);
                $gameNames[] = $name;
            }
        }

        return $games;
    }
}
