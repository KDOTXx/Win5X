<?php namespace App\Currency\Native;

use App\Currency\Currency;
use App\Currency\CurrencyTransactionResult;
use App\Currency\Option\WalletOption;
use Illuminate\Support\Facades\Log;
// use Nbobtc\Command\Command;
// use Nbobtc\Http\Client;
use App\Currency\Utils\Monero\walletRPC;
use App\Currency\Utils\Monero\daemonRPC;


class Monero extends Currency {

  function id(): string {
    return "native_xmr";
  }

  public function walletId(): string {
    return "xmr";
  }

  function name(): string {
    return "XMR";
  }

  public function alias(): string {
    return "monero";
  }

  public function displayName(): string {
    return "Monero";
  }

  function icon(): string {
    return "Image/img/misc/monero-logo.svg";
  }

  function style(): string {
    return "";
  }

  public function coldWalletBalance(): float {
    // try {
    //   return json_decode(file_get_contents('https://sochain.com/api/v2/get_address_balance/BTC/' . $this->option('transfer_address') . '/1'))->data->confirmed_balance;
    // } catch (\Exception $e) {
    //   return 0;
    // }
    try {
      //return 0.00;
    // $curl = curl_init();

    // curl_setopt_array($curl, array(
    //   CURLOPT_URL => 'https://min-api.cryptocompare.com/data/price?fsym=XMR&tsyms=USD',
    //   CURLOPT_RETURNTRANSFER => true,
    //   CURLOPT_ENCODING => '',
    //   CURLOPT_MAXREDIRS => 10,
    //   CURLOPT_TIMEOUT => 0,
    //   CURLOPT_FOLLOWLOCATION => true,
    //   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //   // CURLOPT_CUSTOMREQUEST => 'POST',
    //   //  CURLOPT_POST => false,
    //    // CURLOPT_POSTFIELDS => json_encode(array('pricing_type' => 'no_price')),
    //   //  CURLOPT_HTTPHEADER => array(
    //   //   'Content-Type: application/json',
    //   //   'Accept: application/json',
    //   //   'X-CC-Api-Key: ' . Settings::get('[Coinbase Commerce] API Key', '')
    //   // ),
    // ));

    // $response = json_decode(curl_exec($curl), true);
    // Log::info($response);

    // if(curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 201) {
    //   return ($this->getWallet()->get_balance()['balance'] / 1000000000000.0);
    // }
      return 0.00;
      return ($this->getWallet()->get_balance()['balance'] / 1000000000000.0);

      } catch (\Exception $e) {
        Log::error($e);
        return "Error";
      }
  }

  public function url(): ?string {
     //return "https://mempool.space/address/%s"; // TODO instead of this implement mechanism that check txid with transaction key for transaction validity
    return "";
  }

  public function send(string $from, string $to, float $sum) {
    // $this->getClient()->sendCommand(new Command('sendtoaddress', [$to, $sum, "", "", true]));
    $client = $this->getWallet();
  //  $client->open_wallet('monero', '');
    $client->transfer($sum , $to); // TODO it might make sense to check for fees and do it when network is less busy
  }

  public function isRunning(): bool {
    // try {
    //   $this->getClient()->sendCommand(new Command('getbalance'));
    //   return true;
    // } catch (\Exception $e) {
    //   return false;
    // }
      try {
        return true;
        $this->getWallet()->get_balance();
        return true;
      } catch (\Exception $e) {
        Log::error($e);
        return false;
      }
  }

  public function process(string $wallet = null): string {
    // try {
    //   $client = $this->getClient();
    //   $command = new Command('gettransaction', $wallet);
    //   $response = $client->sendCommand($command);
    //   $contents = json_decode($response->getBody()->getContents(), true)['result'];

    //   if (isset($contents['details'][0]['address'])) {
    //     if (!$this->accept($contents['confirmations'], $contents['details'][0]['address'], $contents['hex'], abs($contents['details'][0]['amount'])))
    //       return CurrencyTransactionResult::$invalidRecipientAddress;

    //     return CurrencyTransactionResult::$success;
    //   } else if (isset($contents['details']['address'])) {
    //     if (!$this->accept($contents['confirmations'], $contents['details']['address'], $contents['hex'], abs($contents['details']['amount'])))
    //       return CurrencyTransactionResult::$invalidRecipientAddress;

    //     return CurrencyTransactionResult::$success;
    //   }

    //   return CurrencyTransactionResult::$invalidTransaction;
    // } catch (\Exception $e) {
    //   Log($e);
    //   return CurrencyTransactionResult::$invalidTransaction;
    // }
      try {
        $client = $this->getWallet();
       // $client->open_wallet('monero', '');
        Log::info('processing transaction' . $wallet);
        //$contents = json_decode($client->get_tx_key(), true)['result'];
       // $contents = json_decode($client->get_transfer_by_txid(), true)['result'];
        $contents = $client->get_transfer_by_txid($wallet);
        Log::info($contents);

        if(isset($contents['transfer']['address'])) {
          //$destinations = json_decode($contents['transfer']['destinations'], true);
          if(!$this->accept($contents['transfer']['confirmations'], $contents['transfer']['address'], 
            $contents['transfer']['txid'], $contents['transfer']['amount'] / 1000000000000)) {


            return CurrencyTransactionResult::$invalidRecipientAddress;
          }

          return CurrencyTransactionResult::$success;
        }

        return CurrencyTransactionResult::$invalidTransaction;
      } catch(\Exception $e) {
        Log::info($e);
        return CurrencyTransactionResult::$invalidTransaction;
      }
  }

  public function processBlock($blockId) {
    // $client = $this->getClient();
    // $response = json_decode($client->sendCommand(new Command('listtransactions'))->getBody()->getContents(), true)['result'];
    // foreach ($response as $tx) $this->process($tx['txid']);
  }

  function newWalletAddress($accountName = null, ?string $chainId = null): string {
    // try {
    //   $client = $this->getClient();
    //   $command = new Command('getnewaddress', $accountName == null ? auth('sanctum')->user()->_id : $accountName);
    //   $response = $client->sendCommand($command);
    //   $contents = json_decode($response->getBody()->getContents());
    //   if ($contents->error != null) throw new \Exception('Exception during getnewaddress');
    //   return $contents->result;
    // } catch (\Exception $e) {
    //   Log::critical($e);
    //   return 'Error';
    // }
    try {
      $client = $this->getWallet();
    //  $client->open_wallet('monero', '');
      $user = auth('sanctum')->user();
      $contents = $client->create_address();
      if (in_array('error', $contents)) throw new \Exception('Exception during getnewaddress');
      Log::info($contents);
      return $contents['address'];
    } catch(\Exception $e) {
        Log::critical($e);
        return 'Error';
    }
  }

  public function setupWallet(): ?string {
    // try {
    //   $depositAccount = $this->newWalletAddress('deposit');

    //   if ($depositAccount === 'Error') return null;

    //   $this->option('transfer_address', $depositAccount);

    //   $this->getClient()->sendCommand(new Command('backupwallet', storage_path('app/' . $this->id() . '_wallet.dat')));
    // } catch (\Exception $e) {
    //   return null;
    // }

    // return $this->id() . '_wallet.dat';

    try {
      $walletRPC = $this->getWallet();
     // $response = json_decode($walletRPC->create_wallet('monero','')[0], true);
     // $response = $walletRPC->create_wallet('monero','');
      //Log::info($response);
      // if(!empty($response)) {
      //   $walletRPC->open_wallet('monero');
      //   $contents = json_decode($walletRPC->getAddress(), true);
      //   $this->option('transfer_address', $contents['address']);
      // }
      //$walletRPC->open_wallet('monero', '');
      //$contents = json_decode($walletRPC->getAddress()[0], true);
      $contents = $walletRPC->get_address();
      Log::info($contents);
      $this->option('transfer_address', $contents['address']);

    } catch (\Exception $e) {
      Log::critical($e);
      return null;
    }
  }

  public function getClient() {
   // return new Client($this->option('rpc'));  
    return new daemonRPC(['host' => '31.7.58.106', 'port' => 18081, 'protocol' => 'http', 'ssl' => false,
       'user' => 'monero', 'password' => 'monero123']); // TODO pull real IP from currency option
  }

  public function getWallet() {
    return new walletRPC(['host' => '31.7.58.106', 'port' => 18083, 'protocol' => 'http', 'ssl' => false,
       'user' => 'monero', 'password' => 'monero123']); // TODO pull real IP from currency option
  }

  protected function options(): array {
    return [
      // new class extends WalletOption {
      //   function id() {
      //     return "rpc";
      //   }

      //   function name(): string {
      //     return "RPC URL";
      //   }

      //   public function description(): string {
      //     return "";
      //   }
      // },
      new class extends WalletOption {
        public function id() {
          return "transfer_address";
        }

        public function name(): string {
          return "Transfer deposits to this address";
        }

        public function description(): string {
          return "";
        }

        public function readOnly(): bool {
          return true;
        }
      }
    ];
  }

}
