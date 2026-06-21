<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Enums\WalletStatus;
use App\Models\DealerWallet;
use App\Models\Distributor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        $platformAdmin = User::firstOrCreate(
            ['email' => 'admin@platform.com'],
            [
                'name' => '平台管理员',
                'email' => 'admin@platform.com',
                'password' => Hash::make('password123'),
                'user_type' => UserType::PLATFORM->value,
                'email_verified_at' => now(),
            ]
        );
        $platformAdmin->assignRole('admin');

        $distributorsData = [
            [
                'name' => '华东区总代',
                'company_name' => '上海华东贸易有限公司',
                'type' => 'first_level',
                'region' => '上海',
                'contact_person' => '张三',
                'phone' => '13800138001',
                'email' => 'zhangsan@huadong.com',
                'address' => '上海市浦东新区世纪大道100号',
                'bank_name' => '中国工商银行上海分行',
                'bank_account' => '6222021234567890001',
                'credit_limit' => 500000.00,
                'wallet_status' => WalletStatus::ACTIVE,
                'wallet_balance' => 125000.00,
                'wallet_frozen' => 5000.00,
            ],
            [
                'name' => '北京区总代',
                'company_name' => '北京北方商贸有限公司',
                'type' => 'first_level',
                'region' => '北京',
                'contact_person' => '李四',
                'phone' => '13800138002',
                'email' => 'lisi@beijing.com',
                'address' => '北京市朝阳区建国路88号',
                'bank_name' => '中国建设银行北京分行',
                'bank_account' => '6227001234567890002',
                'credit_limit' => 300000.00,
                'wallet_status' => WalletStatus::FROZEN,
                'wallet_balance' => 80000.00,
                'wallet_frozen' => 80000.00,
                'freeze_reason' => '违规操作，待审核',
            ],
            [
                'name' => '深圳区代理',
                'company_name' => '深圳南方科技有限公司',
                'type' => 'second_level',
                'region' => '深圳',
                'contact_person' => '王五',
                'phone' => '13800138003',
                'email' => 'wangwu@shenzhen.com',
                'address' => '深圳市南山区科技园路1号',
                'bank_name' => '招商银行深圳分行',
                'bank_account' => '6226091234567890003',
                'credit_limit' => 100000.00,
                'wallet_status' => WalletStatus::RESTRICTED,
                'wallet_balance' => 25000.00,
                'wallet_frozen' => 0,
                'restrict_reason' => '风险预警，限制出金',
            ],
            [
                'name' => '成都新签约',
                'company_name' => '成都西部发展有限公司',
                'type' => 'second_level',
                'region' => '成都',
                'contact_person' => '赵六',
                'phone' => '13800138004',
                'email' => 'zhaoliu@chengdu.com',
                'address' => '成都市高新区天府大道1000号',
                'bank_name' => '中国银行成都分行',
                'bank_account' => '6217851234567890004',
                'credit_limit' => 50000.00,
                'wallet_status' => WalletStatus::INACTIVE,
                'wallet_balance' => 0,
                'wallet_frozen' => 0,
            ],
        ];

        foreach ($distributorsData as $data) {
            $walletStatus = $data['wallet_status'];
            $walletBalance = $data['wallet_balance'];
            $walletFrozen = $data['wallet_frozen'];
            $freezeReason = $data['freeze_reason'] ?? null;
            $restrictReason = $data['restrict_reason'] ?? null;

            unset($data['wallet_status'], $data['wallet_balance'], $data['wallet_frozen'], $data['freeze_reason'], $data['restrict_reason']);

            $distributor = Distributor::firstOrCreate(
                ['email' => $data['email']],
                $data
            );

            $wallet = DealerWallet::firstOrCreate(
                ['distributor_id' => $distributor->id],
                [
                    'distributor_id' => $distributor->id,
                    'wallet_no' => 'W' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                    'status' => $walletStatus->value,
                    'balance' => $walletBalance,
                    'frozen_amount' => $walletFrozen,
                    'credit_limit' => $distributor->credit_limit,
                    'currency' => 'CNY',
                    'last_activated_at' => $walletStatus === WalletStatus::ACTIVE ? now() : null,
                    'last_frozen_at' => $walletStatus === WalletStatus::FROZEN ? now() : null,
                    'last_restricted_at' => $walletStatus === WalletStatus::RESTRICTED ? now() : null,
                    'freeze_reason' => $freezeReason,
                    'restrict_reason' => $restrictReason,
                ]
            );

            $distributorUser = User::firstOrCreate(
                ['email' => 'distributor_' . $distributor->id . '@example.com'],
                [
                    'name' => $distributor->contact_person,
                    'email' => 'distributor_' . $distributor->id . '@example.com',
                    'password' => Hash::make('password123'),
                    'user_type' => UserType::DISTRIBUTOR->value,
                    'distributor_id' => $distributor->id,
                    'email_verified_at' => now(),
                ]
            );
            $distributorUser->assignRole('distributor');
        }
    }
}
