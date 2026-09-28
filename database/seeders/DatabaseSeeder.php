<?php

declare(strict_types=1);

namespace Database\Seeders;

// use App\Models\User;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Infrastructure\Persistence\Eloquent\Models\CardModel;
use App\Infrastructure\Persistence\Eloquent\Models\CompanyModel;
use App\Infrastructure\Persistence\Eloquent\Models\TransactionModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * A gestora do painel já vem pronta. O restante do cadastro é seu.
     */
    public function run(): void
    {
        // User::query()->firstOrCreate(
        //     ['email' => 'marina@acme.test'],
        //     ['name' => 'Marina', 'password' => 'password'],
        // );
        $company = CompanyModel::query()->create([
            'name' => 'Acme',
        ]);

        $marina = UserModel::query()->create([
            'company_id' => $company->id,
            'name' => 'Marina',
            'email' => 'marina@acme.test',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $ana = UserModel::query()->create([
            'company_id' => $company->id,
            'name' => 'Ana',
            'email' => 'ana@acme.test',
            'role' => 'card_holder',
            'password' => Hash::make('password'),
        ]);

        $bruno = UserModel::query()->create([
            'company_id' => $company->id,
            'name' => 'Bruno',
            'email' => 'bruno@acme.test',
            'role' => 'card_holder',
            'password' => Hash::make('password'),
        ]);

        $carla = UserModel::query()->create([
            'company_id' => $company->id,
            'name' => 'Carla',
            'email' => 'carla@acme.test',
            'role' => 'card_holder',
            'password' => Hash::make('password'),
        ]);

        $diego = UserModel::query()->create([
            'company_id' => $company->id,
            'name' => 'Diego',
            'email' => 'diego@acme.test',
            'role' => 'card_holder',
            'password' => Hash::make('password'),
        ]);

        $anaCard = CardModel::query()->create([
            'user_id' => $ana->id,
            'card_token' => 'tok_ana',
            'monthly_limit_cents' => 200_000,
            'purchase_limit_cents' => 80_000,
            'status' => 'active',
        ]);

        $brunoCard = CardModel::query()->create([
            'user_id' => $bruno->id,
            'card_token' => 'tok_bruno',
            'monthly_limit_cents' => 50_000,
            'purchase_limit_cents' => null,
            'status' => 'active',
        ]);

        CardModel::query()->create([
            'user_id' => $carla->id,
            'card_token' => 'tok_carla',
            'monthly_limit_cents' => 50_000,
            'purchase_limit_cents' => null,
            'status' => 'blocked',
        ]);

        CardModel::query()->create([
            'user_id' => $diego->id,
            'card_token' => 'tok_diego',
            'monthly_limit_cents' => 5_000_000,
            'purchase_limit_cents' => null,
            'status' => 'active',
        ]);

        $anaCard->mccRules()->create([
            'mcc' => '7995',
            'rule' => 'blocked',
            'tolerance_percent' => 0,
        ]);

        foreach (['7011', '5812', '5541'] as $mcc) {
            $anaCard->mccRules()->create([
                'mcc' => $mcc,
                'rule' => 'capture_tolerance',
                'tolerance_percent' => 20,
            ]);
        }

        TransactionModel::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'card_id' => null,
            'authorization_id' => null,
            'event_id' => null,
            'type' => TransactionTypeEnum::DEPOSIT->value,
            'amount_cents' => 1_000_000,
            'occurred_at' => now(),
            'limit_month' => null,
            'reference' => 'initial_balance',
        ]);
    }
}
