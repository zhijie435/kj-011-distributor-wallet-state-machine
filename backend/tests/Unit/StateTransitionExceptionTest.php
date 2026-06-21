<?php

namespace Tests\Unit;

use App\Enums\WalletStatus;
use App\Enums\WalletTransitionAction;
use App\Exceptions\StateTransitionException;
use App\Models\DealerWallet;
use App\Models\Distributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateTransitionExceptionTest extends TestCase
{
    use RefreshDatabase;

    protected function createWallet(WalletStatus $status = WalletStatus::ACTIVE): DealerWallet
    {
        $distributor = Distributor::factory()->create();

        return DealerWallet::factory()->for($distributor)->create([
            'status' => $status->value,
        ]);
    }

    public function test_invalid_transition_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $exception = StateTransitionException::invalidTransition(
            '未激活',
            '已冻结',
            ['正常', '已注销'],
            $wallet
        );

        $this->assertInstanceOf(StateTransitionException::class, $exception);
        $this->assertStringContainsString('不允许从', $exception->getMessage());
        $this->assertStringContainsString('变更为', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('INVALID_STATE_TRANSITION', $exception->getErrorCode());
        $this->assertEquals('未激活', $exception->getDetails()['from_state']);
        $this->assertEquals('已冻结', $exception->getDetails()['to_state']);
        $this->assertContains('正常', $exception->getDetails()['allowed_states']);
        $this->assertEquals($wallet->id, $exception->getDetails()['wallet_id']);
    }

    public function test_invalid_transition_exception_without_allowed_states(): void
    {
        $exception = StateTransitionException::invalidTransition('已注销', '正常');

        $this->assertStringNotContainsString('允许的目标状态', $exception->getMessage());
    }

    public function test_terminal_state_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED);
        $exception = StateTransitionException::terminalState(WalletStatus::CLOSED, $wallet);

        $this->assertStringContainsString('终态', $exception->getMessage());
        $this->assertStringContainsString('已注销', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('TERMINAL_STATE_REACHED', $exception->getErrorCode());
        $this->assertTrue($exception->getDetails()['is_terminal']);
        $this->assertEquals('closed', $exception->getDetails()['current_state']);
    }

    public function test_validation_failed_exception(): void
    {
        $wallet = $this->createWallet();
        $exception = StateTransitionException::validationFailed(
            '验证失败',
            ['balance' => 100],
            $wallet,
            WalletTransitionAction::CLOSE
        );

        $this->assertEquals('验证失败', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('STATE_TRANSITION_VALIDATION_FAILED', $exception->getErrorCode());
        $this->assertEquals(100, $exception->getDetails()['balance']);
        $this->assertEquals(WalletTransitionAction::CLOSE->value, $exception->getDetails()['action']);
    }

    public function test_rule_violation_exception(): void
    {
        $wallet = $this->createWallet();
        $exception = StateTransitionException::ruleViolation(
            'balance_check',
            '余额检查失败',
            ['required' => 0, 'current' => 100],
            $wallet,
            WalletTransitionAction::CLOSE
        );

        $this->assertEquals('余额检查失败', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('STATE_TRANSITION_RULE_VIOLATION', $exception->getErrorCode());
        $this->assertEquals('balance_check', $exception->getDetails()['rule']);
        $this->assertEquals(0, $exception->getDetails()['context']['required']);
    }

    public function test_exception_includes_wallet_details(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $exception = StateTransitionException::validationFailed('测试', [], $wallet);

        $details = $exception->getDetails();
        $this->assertEquals($wallet->id, $details['wallet_id']);
        $this->assertEquals($wallet->wallet_no, $details['wallet_no']);
        $this->assertEquals($wallet->distributor_id, $details['distributor_id']);
        $this->assertEquals('active', $details['current_status']);
    }

    public function test_exception_includes_action_details(): void
    {
        $exception = StateTransitionException::validationFailed(
            '测试',
            [],
            null,
            WalletTransitionAction::FREEZE
        );

        $details = $exception->getDetails();
        $this->assertEquals('freeze', $details['action']);
        $this->assertEquals('冻结', $details['action_label']);
    }

    public function test_exception_render_returns_json_response(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED);
        $exception = StateTransitionException::terminalState(WalletStatus::CLOSED, $wallet);
        $response = $exception->render();

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals('TERMINAL_STATE_REACHED', $data['error_code']);
    }
}
