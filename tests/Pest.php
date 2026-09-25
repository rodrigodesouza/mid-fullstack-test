<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature e Unit compartilham o TestCase da aplicação e o RefreshDatabase.
| Os testes rodam no Postgres de .env.testing, o mesmo banco que você usa
| em desenvolvimento, para que locks e constraints sejam testados de verdade.
|
*/

pest()->group('feature')->in('Feature');
pest()->group('unit')->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

pest()->printer()->compact();

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function something(): void
{
    // ..
}
