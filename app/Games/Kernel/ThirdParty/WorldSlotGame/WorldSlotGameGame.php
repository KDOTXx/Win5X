<?php

namespace App\Games\Kernel\ThirdParty\WorldSlotGame;

use App\Events\BalanceModification;
use App\Games\Kernel\Data;
use App\Games\Kernel\GameCategory;
use App\Games\Kernel\Metadata;
use App\Games\Kernel\ThirdParty\ThirdPartyGame;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorldSlotGameGame extends ThirdPartyGame
{
    public function __construct($data = null)
    {
        parent::__construct($data);
    }

    public function provider(): string
    {
        return $this->metadata()->provider()['code'];
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
                return 'external:wsg:' . $this->data['game_code'];
            }

            function name(): string
            {
                return $this->data['game_name'];
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

                if (isset($this->data['game_type'])) {
                    if ($this->data['game_type'] === 'live') $categories[] = GameCategory::$live;
                    if ($this->data['game_type'] === 'slot') $categories[] = GameCategory::$slots;
                }

                if (isset($this->data['provider'])) {
                    if ($this->data['provider']['type'] === 'live') $categories[] = GameCategory::$live;
                    if ($this->data['provider']['type'] === 'slot') $categories[] = GameCategory::$slots;
                }

                return $categories;
            }

            public function round()
            {
                return $this->data['round_id'];
            }

            public function provider(): array
            {
                return $this->data['provider'];
            }

            public function transaction(): array
            {
                return $this->data['transaction']; 
            }
        };
    }

    public function processCallback(\App\Currency\Currency $currency): array
    {
        try {
            $metadata = $this->metadata();
            $transaction = $metadata->transaction();
            $user = \App\Models\User::where('name', $transaction['user_code'])->first();
            $transactionID = $transaction['txn_id'];
            $transactionType = $transaction['txn_type'];
            $betMoney = $currency->convertUSDToToken(floatval($transaction['bet']));
            $winMoney = $currency->convertUSDToToken(floatval($transaction['win']));
            $userBalance = $user->balance($currency);
            $balance = $userBalance->get();
            Log::info($transaction['bet']);
            Log::info($transaction);
            Log::info($balance);
            Log::info($betMoney);

            if ($betMoney > $balance) return [
                'status' => 0,
                'msg' => 'INSUFFICIENT_USER_FUNDS'
            ];

            if ($transactionType === 'debit') {
                if (\App\Models\Transaction::where('service_id', $transactionID)->where('service_type', 'bet')->exists()) return [
                    'status' => 0,
                    'user_balance' => $balance,
                    'msg' => 'DUPLICATED_REQUEST',
                ];
            }

            if ($transactionType === 'credit') {
                if (\App\Models\Transaction::where('service_id', $transactionID)->where('service_type', 'win')->exists()) return [
                    'status' => 0,
                    'user_balance' => $balance,
                    'msg' => 'DUPLICATED_REQUEST',
                ];
            }

            $game = \App\Models\Game::where('wsg_gameId', $metadata->round())->first();

            if (!$game) {
                $game = \App\Models\Game::create([
                    'id' => DB::table('games')->count() + 1,
                    'user' => $user->_id,
                    'game' => $metadata->id(),
                    'multiplier' => 0,
                    'status' => 'in-progress',
                    'profit' => 0,
                    'server_seed' => $this->server_seed(),
                    'client_seed' => $this->client_seed(),
                    'data' => [],
                    'type' => 'third-party',
                    'demo' => false,
                    'wager' => $betMoney,
                    'currency' => $currency->id(),
                    'wsg_gameId' => $metadata->round(),
                    'bet_usd_converted' => $betMoney
                ]);
            }

            $game->update(['status' => 'in-progress']);

            $userBalance->subtract(
                $betMoney,
                Transaction::builder()->game($metadata->name())->provider($metadata->provider()['name'])->message("Ação: aposta / ID transação: {$game->wsg_gameId}")->wager($transaction['bet'])->profit(0)->get(),
                $transactionID,
                'bet'
            );

            event(new BalanceModification($user, $currency, 'subtract', $game->demo, $game->wager, 0));

            // $rolloverEnabled = $currency->option('rollover_enabled') ?? false;

            // if ($rolloverEnabled) {
            //     if ($user->depositRollovers->count() > 0) {
            //         $depositRollover = $user->depositRollovers->first();
            //         $user->update(['current_bets_rollover' => $user->current_bets_rollover + $betMoney]);

            //         if ($user->current_bets_rollover >= ($depositRollover->value * $currency->option('rollover_multiplicator'))) {
            //             $depositRollover->delete();
            //             $user->update(['current_bets_rollover' => 0]);
            //         }
            //     }
            // }

            $profit = $game->profit;

            if ($winMoney > 0) {
                $userBalance->add(
                    $winMoney,
                    Transaction::builder()->game($metadata->name())->provider($metadata->provider()['name'])->message("Ação: vitória / ID transação: {$game->wsg_gameId}")->wager($betMoney)->profit($winMoney)->get(),
                    // Transaction::builder()->game($metadata->name())->message("Ação: vitória / ID transação: {$game->wsg_gameId}")->get(),
                    0,
                    $transactionID,
                    'win'
                );

                $profit += $winMoney;
                $game->update(['profit' => $profit]);

                event(new BalanceModification($user, $currency, 'add', $game->demo, 
                    $game->profit, 0));

                try {
                    self::analytics($game, 'Providers');
                    event(new \App\Events\LiveFeedGame($game, 0));
                } catch (\Exception $ignored) {
                    //
                }
            }

            if (($transactionType === 'credit' || $transactionType === 'debit_credit') && $winMoney == 0) {
                try {
                    self::analytics($game, 'Providers');
                    event(new \App\Events\LiveFeedGame($game, 0));
                } catch (\Exception $ignored) {
                    //
                }
            }

            $payout = $profit > 0 && $game->wager > 0 ? $profit / $game->wager : 0;

            $game->update([
                'multiplier' => $payout,
                'status' => $payout > 0 ? 'win' : 'lose',
                'profit' => $profit
            ]);

            return [
                'status' => 1,
                'user_balance' => $currency->fiatNumberFormat($currency->convertTokenToUSD($userBalance->get())),
                'transaction_id' => $transactionID
            ];
        } catch (\Exception $exception) {
            return [
                'status' => 0,
                'msg' => 'INTERNAL_ERROR',
                'error_message' => "Unknown error: {$exception->getMessage()}"
            ];
        }
    }

    function process(Data $data): array
    {
        $metadata = $this->metadata();
        //$currencySetting = \App\Models\Settings::get('currencies', 'local_suitpay');
       // $currencyData = json_decode($currencySetting, true)[0];
        $currency = \App\Currency\Currency::find($data->currency());

        $response = WorldSlotGame::request('game_launch', [
            'user_balance' => $currency->fiatNumberFormat($currency->convertTokenToUSD($data->user()->balance($currency)->get())),
            'user_code' => $data->user()->name,
            'game_type' => 'slot',
            'provider_code' => $this->provider(),
            'game_code' => str_replace("external:wsg:", "", $metadata->id()),
            'lang' => 'en',
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
                    Log::error('FiversCan error', $response);
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
                'link' => $response['launch_url']
            ]
        ];
    }

    public function createInstances(): array
    {
        $games = [];

        $blacklist = [
            '' // id game
        ];

        $gameNames = [];

        foreach ($this->data as $game) {
            $name = $game['game_name'];

            if (!in_array($name, $gameNames) && !in_array($game['game_code'], $blacklist)) {
                $games[] = new WorldSlotGameGame($game);
                $gameNames[] = $name;
            }
        }

        return $games;
    }
}
