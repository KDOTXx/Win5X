<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware {

  /**
   * The URIs that should be excluded from CSRF verification.
   *
   * @var array
   */
  protected $except = [
    '/installer/firstTimeUpdate',
    '/gold_api/user_balance',
    '/gold_api/game_callback',
    '/gold_api/money_callback',
    '/gold_api',
    '/txNotify/*'
  ];

}
