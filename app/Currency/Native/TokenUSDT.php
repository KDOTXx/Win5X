<?php namespace App\Currency\Native;

use App\Currency\Currency;
use App\Currency\CurrencyTransactionResult;
use App\Currency\Utils\EthereumAbi;
use App\Currency\Utils\InputDataDecoder;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Web3\Contract;

class TokenUSDT extends EthereumToken {

  public function id(): string {
    return "token_usdt";
  }

  public function walletId(): string {
    return "tether";
  }

  public function name(): string {
    return "USDT";
  }

  public function alias(): string {
    return "tether";
  }

  public function displayName(): string {
    return "USDT";
  }

  public function icon(): string {
    return "tether";
  }

  public function style(): string {
    return "lightgreen";
  }

  public function tokenAddress(): string {
    return '0xdac17f958d2ee523a2206206994597c13d831ec7';
  }

  public function abi(): string {
    return file_get_contents(base_path('storage/erc20.json'));
  }

  public function isToken(): bool {
    return true;
  }

  public function coldWalletBalance(): float {
    return -1;
    //return json_decode(file_get_contents('https://api.etherscan.io/api?module=account&action=tokenbalance&contractaddress=' . $this->tokenAddress() .'&address=' .  . '&tag=latest'))
  }

}
