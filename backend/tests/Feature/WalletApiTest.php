<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Enums\WalletStatus;
use App\Enums\WalletTransactionType;
use App\Models\DealerWallet;
use App\Models\Distributor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $platformUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->platformUser = User::factory()->create([
            'user_type' => UserType::PLATFORM->value,
        ]);
        Sanctum::actingAs($this->platformUser);
    }

    protected function createWallet(WalletStatus $status = WalletStatus::ACTIVE, array $attributes = []): DealerWallet
    {
        $distributor = Distributor::factory()->create();

        return DealerWallet::factory()->for($distributor)->create(array_merge([
            'status' => $status->value,
        ], $attributes));
    }

    public function test_authentication_required(): void
    {
        $this->app->forgetInstance('auth');
        $response = $this->getJson('/api/wallets');

        $response->assertUnauthorized();
    }

    public function test_index_returns_wallets_list(): void
    {
        $wallet1 = $this->createWallet(WalletStatus::ACTIVE);
        $wallet2 = $this->createWallet(WalletStatus::FROZEN);
        $wallet3 = $this->createWallet(WalletStatus::INACTIVE);

        $response = $this->getJson('/api/wallets');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data',
                    'meta' => [
                        'current_page',
                        'total',
                    ],
                ],
            ]);

        $this->assertEquals(3, $response->json('data.meta.total'));
    }

    public function test_index_with_status_filter(): void
    {
        $active = $this->createWallet(WalletStatus::ACTIVE);
        $frozen = $this->createWallet(WalletStatus::FROZEN);

        $response = $this->getJson('/api/wallets?status=' . WalletStatus::ACTIVE->value);

        $this->assertEquals(1, $response->json('data.meta.total'));
        $this->assertEquals(WalletStatus::ACTIVE->value, $response->json('data.data.0.status'));
    }

    public function test_index_with_search_filter(): void
    {
        $distributor1 = Distributor::factory()->create(['name' => '测试经销商AAA']);
        $distributor2 = Distributor::factory()->create(['name' => '经销商BBB']);
        DealerWallet::factory()->for($distributor1)->create();
        DealerWallet::factory()->for($distributor2)->create();

        $response = $this->getJson('/api/wallets?search=AAA');

        $this->assertEquals(1, $response->json('data.meta.total'));
    }

    public function test_store_creates_wallet(): void
    {
        $distributor = Distributor::factory()->create();

        $response = $this->postJson('/api/wallets', [
            'distributor_id' => $distributor->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '钱包创建成功',
            ])
            ->assertJsonPath('data.status', WalletStatus::INACTIVE->value)
            ->assertJsonPath('data.distributor_id', $distributor->id);

        $balance = $response->json('data.balance');
        $this->assertEquals(0, $balance);

        $this->assertDatabaseHas('dealer_wallets', [
            'distributor_id' => $distributor->id,
            'status' => WalletStatus::INACTIVE->value,
        ]);
    }

    public function test_store_requires_distributor_id(): void
    {
        $response = $this->postJson('/api/wallets', []);

        $response->assertStatus(422);
    }

    public function test_store_throws_exception_for_duplicate_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        DealerWallet::factory()->for($distributor)->create();

        $response = $this->postJson('/api/wallets', [
            'distributor_id' => $distributor->id,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => '经销商钱包已存在',
            ]);
    }

    public function test_show_returns_wallet_details(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 1000.00,
            'frozen_amount' => 100.00,
        ]);

        $response = $this->getJson("/api/wallets/{$wallet->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.id', $wallet->id)
            ->assertJsonPath('data.wallet_no', $wallet->wallet_no)
            ->assertJsonPath('data.status', WalletStatus::ACTIVE->value);
    }

    public function test_balance_returns_wallet_balance_info(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 1000.00,
            'frozen_amount' => 100.00,
        ]);

        $response = $this->getJson("/api/wallets/{$wallet->id}/balance");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'wallet_id',
                    'status',
                    'status_label',
                    'total_balance',
                    'frozen_amount',
                    'available_balance',
                    'can_activate',
                    'can_freeze',
                    'can_unfreeze',
                    'can_restrict',
                    'can_unrestrict',
                    'can_close',
                ],
            ]);

        $this->assertEqualsWithDelta(1000.0, $response->json('data.total_balance'), 0.001);
        $this->assertEqualsWithDelta(100.0, $response->json('data.frozen_amount'), 0.001);
        $this->assertEqualsWithDelta(900.0, $response->json('data.available_balance'), 0.001);
    }

    public function test_activate_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);

        $response = $this->postJson("/api/wallets/{$wallet->id}/activate", [
            'reason' => '测试激活',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => '钱包激活成功',
            ])
            ->assertJsonPath('data.status', WalletStatus::ACTIVE->value);

        $this->assertDatabaseHas('dealer_wallets', [
            'id' => $wallet->id,
            'status' => WalletStatus::ACTIVE->value,
        ]);
    }

    public function test_activate_wallet_throws_exception_for_active_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);

        $response = $this->postJson("/api/wallets/{$wallet->id}/activate", [
            'reason' => '再次激活',
        ]);

        $response->assertStatus(422);
    }

    public function test_freeze_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);

        $response = $this->postJson("/api/wallets/{$wallet->id}/freeze", [
            'reason' => '违规操作',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => '钱包冻结成功',
            ])
            ->assertJsonPath('data.status', WalletStatus::FROZEN->value);

        $this->assertDatabaseHas('dealer_wallets', [
            'id' => $wallet->id,
            'status' => WalletStatus::FROZEN->value,
            'freeze_reason' => '违规操作',
        ]);
    }

    public function test_freeze_wallet_from_restricted(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED);

        $response = $this->postJson("/api/wallets/{$wallet->id}/freeze", [
            'reason' => '确认违规',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', WalletStatus::FROZEN->value);
    }

    public function test_unfreeze_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN, [
            'freeze_reason' => '违规',
        ]);

        $response = $this->postJson("/api/wallets/{$wallet->id}/unfreeze", [
            'reason' => '解冻',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => '钱包解冻成功',
            ])
            ->assertJsonPath('data.status', WalletStatus::ACTIVE->value);
    }

    public function test_restrict_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);

        $response = $this->postJson("/api/wallets/{$wallet->id}/restrict", [
            'reason' => '风险预警',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => '钱包限制成功',
            ])
            ->assertJsonPath('data.status', WalletStatus::RESTRICTED->value);
    }

    public function test_unrestrict_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED, [
            'restrict_reason' => '风险预警',
        ]);

        $response = $this->postJson("/api/wallets/{$wallet->id}/unrestrict", [
            'reason' => '解除风险',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => '钱包解除限制成功',
            ])
            ->assertJsonPath('data.status', WalletStatus::ACTIVE->value);
    }

    public function test_close_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 0,
            'frozen_amount' => 0,
        ]);

        $response = $this->postJson("/api/wallets/{$wallet->id}/close", [
            'reason' => '经销商注销',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => '钱包注销成功',
            ])
            ->assertJsonPath('data.status', WalletStatus::CLOSED->value);
    }

    public function test_close_wallet_fails_with_balance(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 100.00,
            'frozen_amount' => 0,
        ]);

        $response = $this->postJson("/api/wallets/{$wallet->id}/close", [
            'reason' => '注销',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => '钱包余额不为0，无法注销',
            ]);
    }

    public function test_close_wallet_fails_with_frozen_amount(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 0,
            'frozen_amount' => 50.00,
        ]);

        $response = $this->postJson("/api/wallets/{$wallet->id}/close", [
            'reason' => '注销',
        ]);

        $response->assertStatus(422);
    }

    public function test_recharge_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 100.00,
        ]);

        $response = $this->postJson("/api/wallets/{$wallet->id}/recharge", [
            'amount' => 500.00,
            'remark' => '充值测试',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => '充值成功',
            ])
            ->assertJsonPath('data.type', WalletTransactionType::RECHARGE->value);

        $this->assertEqualsWithDelta(500.0, $response->json('data.amount'), 0.001);
        $this->assertEqualsWithDelta(100.0, $response->json('data.balance_before'), 0.001);
        $this->assertEqualsWithDelta(600.0, $response->json('data.balance_after'), 0.001);

        $wallet->refresh();
        $this->assertEquals(600.00, $wallet->balance);
    }

    public function test_recharge_requires_valid_amount(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);

        $response = $this->postJson("/api/wallets/{$wallet->id}/recharge", [
            'amount' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_recharge_fails_for_frozen_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN);

        $response = $this->postJson("/api/wallets/{$wallet->id}/recharge", [
            'amount' => 100.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_transactions_returns_paginated_list(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);

        $response = $this->getJson("/api/wallets/{$wallet->id}/transactions");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'meta' => [
                        'current_page',
                        'total',
                    ],
                ],
            ]);
    }

    public function test_state_logs_returns_paginated_list(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);

        $response = $this->getJson("/api/wallets/{$wallet->id}/state-logs");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'meta' => [
                        'current_page',
                        'total',
                    ],
                ],
            ]);
    }

    public function test_statistics_returns_stats(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);

        $response = $this->getJson("/api/wallets/{$wallet->id}/statistics");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period',
                    'income',
                    'expense',
                    'net_flow',
                    'transaction_count',
                ],
            ]);
    }

    public function test_my_balance_returns_error_for_platform_user(): void
    {
        $response = $this->getJson('/api/wallets/my-balance');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error_code' => 'USER_NOT_DISTRIBUTOR',
            ]);
    }

    public function test_my_balance_returns_wallet_for_distributor(): void
    {
        $distributor = Distributor::factory()->create();
        $distributorUser = User::factory()->create([
            'user_type' => UserType::DISTRIBUTOR->value,
            'distributor_id' => $distributor->id,
        ]);
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'balance' => 500.00,
        ]);

        Sanctum::actingAs($distributorUser);

        $response = $this->getJson('/api/wallets/my-balance');

        $response->assertOk()
            ->assertJsonPath('data.wallet_id', $wallet->id);

        $this->assertEqualsWithDelta(500.0, $response->json('data.total_balance'), 0.001);
    }

    public function test_my_balance_returns_not_found_for_distributor_without_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $distributorUser = User::factory()->create([
            'user_type' => UserType::DISTRIBUTOR->value,
            'distributor_id' => $distributor->id,
        ]);

        Sanctum::actingAs($distributorUser);

        $response = $this->getJson('/api/wallets/my-balance');

        $response->assertStatus(404)
            ->assertJson([
                'error_code' => 'WALLET_NOT_FOUND',
            ]);
    }

    public function test_reason_cannot_exceed_500_characters(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $longReason = str_repeat('A', 501);

        $response = $this->postJson("/api/wallets/{$wallet->id}/freeze", [
            'reason' => $longReason,
        ]);

        $response->assertStatus(422);
    }

    public function test_invalid_state_transition_returns_422(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);

        $response = $this->postJson("/api/wallets/{$wallet->id}/freeze", [
            'reason' => '冻结未激活钱包',
        ]);

        $response->assertStatus(422);
    }

    public function test_closed_wallet_all_transitions_fail(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED);

        $this->postJson("/api/wallets/{$wallet->id}/activate")->assertStatus(422);
        $this->postJson("/api/wallets/{$wallet->id}/freeze", ['reason' => 'x'])->assertStatus(422);
        $this->postJson("/api/wallets/{$wallet->id}/unfreeze")->assertStatus(422);
        $this->postJson("/api/wallets/{$wallet->id}/restrict", ['reason' => 'x'])->assertStatus(422);
        $this->postJson("/api/wallets/{$wallet->id}/unrestrict")->assertStatus(422);
        $this->postJson("/api/wallets/{$wallet->id}/close", ['reason' => 'x'])->assertStatus(422);
        $this->postJson("/api/wallets/{$wallet->id}/recharge", ['amount' => 100])->assertStatus(422);
    }
}
