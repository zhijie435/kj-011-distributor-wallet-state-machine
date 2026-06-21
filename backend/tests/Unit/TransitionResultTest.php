<?php

namespace Tests\Unit;

use App\Contracts\StateMachine\TransitionResult;
use Tests\TestCase;

class TransitionResultTest extends TestCase
{
    public function test_success_returns_valid_result(): void
    {
        $result = TransitionResult::success('操作成功');

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isInvalid());
        $this->assertEquals('操作成功', $result->message);
        $this->assertEmpty($result->errors);
    }

    public function test_success_without_message(): void
    {
        $result = TransitionResult::success();

        $this->assertTrue($result->isValid());
        $this->assertEquals('', $result->message);
    }

    public function test_failure_returns_invalid_result(): void
    {
        $errors = ['field' => '错误信息'];
        $result = TransitionResult::failure('操作失败', $errors);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->isInvalid());
        $this->assertEquals('操作失败', $result->message);
        $this->assertEquals($errors, $result->errors);
    }

    public function test_failure_without_errors(): void
    {
        $result = TransitionResult::failure('操作失败');

        $this->assertFalse($result->isValid());
        $this->assertEmpty($result->errors);
    }

    public function test_valid_property_is_readonly(): void
    {
        $result = TransitionResult::success();

        $this->assertTrue($result->valid);
    }

    public function test_message_property_is_readonly(): void
    {
        $result = TransitionResult::success('测试消息');

        $this->assertEquals('测试消息', $result->message);
    }

    public function test_errors_property_is_readonly(): void
    {
        $errors = ['key' => 'value'];
        $result = TransitionResult::failure('失败', $errors);

        $this->assertEquals($errors, $result->errors);
    }
}
