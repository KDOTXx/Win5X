<?php namespace App\Currency\Native;

use App\Currency\Currency;
use App\Currency\CurrencyTransactionResult;
use App\Currency\Utils\EthereumAbi;
use App\Currency\Utils\InputDataDecoder;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Web3;
use Symfony\Component\Process\Process;

class TokenUSDC extends EthereumToken {

  function id(): string {
    return "token_usdc";
  }

  function walletId(): string {
    return 'usdc';
  }

  function name(): string {
    return 'USDC';
  }

  function alias(): string {
    return 'usd-coin';
  }

  function displayName(): string {
    return 'USDC';
  }

  function icon(): string {
    return 'usdc';
  }

  function style(): string {
    return '#2775ca';
  }

  public function decimals(): int {
    return 6;
  }

  public function tokenAddress(): string {
    return '0x07865c6e87b9f70255377e024ace6630c1eaa37f';
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

  protected function getClient() {
    return new Web3(new HttpProvider(new HttpRequestManager('https://goerli.infura.io/v3/'.$this->option('infura_api_key'), 30)));
  }

}
