<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        if (! Schema::hasColumn('payments', 'wallet_credited')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->boolean('wallet_credited')
                    ->default(false)
                    ->after('mail_sent');
            });
        }

        DB::table('payments')
            ->where('type', 'wallet_deposit')
            ->where('status', 'completed')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('wallet_transactions')
                    ->whereColumn(
                        'wallet_transactions.payment_id',
                        'payments.id'
                    );
            })
            ->update([
                'wallet_credited' => true,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Wallets
        |--------------------------------------------------------------------------
        */

        Schema::table('wallets', function (Blueprint $table) {
            // Signed BIGINT حتى لا تفشل بسبب أرصدة سالبة قديمة.
            $table->bigInteger('balance')
                ->default(0)
                ->change();

            $table->bigInteger('payback_balance')
                ->default(0)
                ->change();
        });

        /*
        |--------------------------------------------------------------------------
        | Wallet Transactions
        |--------------------------------------------------------------------------
        */

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->bigInteger('points')
                ->default(0)
                ->change();

            $table->bigInteger('balance_before')
                ->default(0)
                ->change();

            $table->bigInteger('balance_after')
                ->default(0)
                ->change();
        });

        /*
        |--------------------------------------------------------------------------
        | Unique Indexes
        |--------------------------------------------------------------------------
        */

        if (! $this->indexExists(
            'wallet_transactions',
            'wallet_transactions_payment_id_unique'
        )) {
            if ($this->hasDuplicates('wallet_transactions', 'payment_id')) {
                throw new \RuntimeException(
                    'Duplicate payment_id values exist in wallet_transactions.'
                );
            }

            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->unique(
                    'payment_id',
                    'wallet_transactions_payment_id_unique'
                );
            });
        }

        if (! $this->indexExists(
            'wallet_transactions',
            'wallet_transactions_slug_unique'
        )) {
            if ($this->hasDuplicates('wallet_transactions', 'slug')) {
                throw new \RuntimeException(
                    'Duplicate slug values exist in wallet_transactions.'
                );
            }

            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->unique(
                    'slug',
                    'wallet_transactions_slug_unique'
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | PayPal Webhook Events
        |--------------------------------------------------------------------------
        */

        if (! Schema::hasTable('paypal_webhook_events')) {
            Schema::create('paypal_webhook_events', function (Blueprint $table) {
                $table->id();

                $table->string('event_id')->unique();
                $table->string('event_type');

                $table->string('paypal_order_id')
                    ->nullable()
                    ->index();

                $table->string('capture_id')
                    ->nullable()
                    ->index();

                $table->json('payload');

                $table->string('status')
                    ->default('received')
                    ->index();

                $table->text('error_message')->nullable();
                $table->timestamp('processed_at')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paypal_webhook_events');

        if (
            Schema::hasTable('wallet_transactions')
            && $this->indexExists(
                'wallet_transactions',
                'wallet_transactions_payment_id_unique'
            )
        ) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->dropUnique(
                    'wallet_transactions_payment_id_unique'
                );
            });
        }

        if (
            Schema::hasTable('wallet_transactions')
            && $this->indexExists(
                'wallet_transactions',
                'wallet_transactions_slug_unique'
            )
        ) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->dropUnique(
                    'wallet_transactions_slug_unique'
                );
            });
        }

        if (
            Schema::hasTable('payments')
            && Schema::hasColumn('payments', 'wallet_credited')
        ) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('wallet_credited');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $definition) => ($definition['name'] ?? null) === $index);
    }

    private function hasDuplicates(string $table, string $column): bool
    {
        $result = DB::selectOne(
            "SELECT `{$column}`, COUNT(*) AS duplicate_count
             FROM `{$table}`
             WHERE `{$column}` IS NOT NULL
             GROUP BY `{$column}`
             HAVING COUNT(*) > 1
             LIMIT 1"
        );

        return $result !== null;
    }
};
