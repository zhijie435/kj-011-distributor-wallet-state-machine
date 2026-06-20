<?php

namespace App\Http\Controllers\Api;

use App\Enums\WalletStatus;
use App\Enums\WalletTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\WalletTransitionRequest;
use App\Models\DealerWallet;
use App\Models\Distributor;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $service
    ) {}

    public function index(Request $request)
    {
        $params = $request->only(['status', 'distributor_id', 'search']);
        $params['per_page'] = $this->perPage($request);

        $wallets = $this->service->listWallets($params);

        return $this->success($wallets);
    }

    public function show(DealerWallet $wallet)
    {
        $wallet->load(['distributor', 'stateLogs.operator']);

        return $this->success($wallet);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'distributor_id' => 'required|exists:distributors,id',
        ]);

        $distributor = Distributor::findOrFail($validated['distributor_id']);
        $wallet = $this->service->createWallet($distributor, $request->user()->id);

        return $this->success($wallet, '钱包创建成功', 201);
    }

    public function balance(DealerWallet $wallet)
    {
        $balance = $this->service->getWalletBalance($wallet);

        return $this->success($balance);
    }

    public function myBalance(Request $request)
    {
        if (!$request->user()->isDistributor() || !$request->user()->distributor) {
            return $this->error('用户不是经销商', 'USER_NOT_DISTRIBUTOR', [], 400);
        }

        $wallet = $request->user()->distributor->wallet;

        if (!$wallet) {
            return $this->error('钱包不存在', 'WALLET_NOT_FOUND', [], 404);
        }

        $balance = $this->service->getWalletBalance($wallet);

        return $this->success($balance);
    }

    public function activate(WalletTransitionRequest $request, DealerWallet $wallet)
    {
        $wallet = $this->service->activateWallet(
            $wallet,
            $request->input('reason', ''),
            $request->user()->id
        );

        return $this->success($wallet, '钱包激活成功');
    }

    public function freeze(WalletTransitionRequest $request, DealerWallet $wallet)
    {
        $wallet = $this->service->freezeWallet(
            $wallet,
            $request->input('reason', ''),
            $request->user()->id
        );

        return $this->success($wallet, '钱包冻结成功');
    }

    public function unfreeze(WalletTransitionRequest $request, DealerWallet $wallet)
    {
        $wallet = $this->service->unfreezeWallet(
            $wallet,
            $request->input('reason', ''),
            $request->user()->id
        );

        return $this->success($wallet, '钱包解冻成功');
    }

    public function restrict(WalletTransitionRequest $request, DealerWallet $wallet)
    {
        $wallet = $this->service->restrictWallet(
            $wallet,
            $request->input('reason', ''),
            $request->user()->id
        );

        return $this->success($wallet, '钱包限制成功');
    }

    public function unrestrict(WalletTransitionRequest $request, DealerWallet $wallet)
    {
        $wallet = $this->service->unrestrictWallet(
            $wallet,
            $request->input('reason', ''),
            $request->user()->id
        );

        return $this->success($wallet, '钱包解除限制成功');
    }

    public function close(WalletTransitionRequest $request, DealerWallet $wallet)
    {
        $wallet = $this->service->closeWallet(
            $wallet,
            $request->input('reason', ''),
            $request->user()->id
        );

        return $this->success($wallet, '钱包注销成功');
    }

    public function recharge(Request $request, DealerWallet $wallet)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'remark' => 'nullable|string|max:500',
        ]);

        $transaction = $this->service->recharge(
            $wallet,
            (float) $validated['amount'],
            $validated['remark'] ?? '',
            $request->user()->id
        );

        return $this->success($transaction, '充值成功');
    }

    public function transactions(Request $request, DealerWallet $wallet)
    {
        $params = $request->only(['type', 'start_date', 'end_date']);
        $params['per_page'] = $this->perPage($request);

        $transactions = $this->service->getWalletTransactions($wallet, $params);

        return $this->success($transactions);
    }

    public function stateLogs(Request $request, DealerWallet $wallet)
    {
        $params['per_page'] = $this->perPage($request);

        $logs = $this->service->getWalletStateLogs($wallet, $params);

        return $this->success($logs);
    }

    public function statistics(Request $request, DealerWallet $wallet)
    {
        $params = $request->only(['start_date', 'end_date']);

        $stats = $this->service->getStatistics($wallet, $params);

        return $this->success($stats);
    }

    public function myTransactions(Request $request)
    {
        if (!$request->user()->isDistributor() || !$request->user()->distributor) {
            return $this->error('用户不是经销商', 'USER_NOT_DISTRIBUTOR', [], 400);
        }

        $wallet = $request->user()->distributor->wallet;

        if (!$wallet) {
            return $this->error('钱包不存在', 'WALLET_NOT_FOUND', [], 404);
        }

        $params = $request->only(['type', 'start_date', 'end_date']);
        $params['per_page'] = $this->perPage($request);

        return $this->success($this->service->getWalletTransactions($wallet, $params));
    }

    public function myStatistics(Request $request)
    {
        if (!$request->user()->isDistributor() || !$request->user()->distributor) {
            return $this->error('用户不是经销商', 'USER_NOT_DISTRIBUTOR', [], 400);
        }

        $wallet = $request->user()->distributor->wallet;

        if (!$wallet) {
            return $this->error('钱包不存在', 'WALLET_NOT_FOUND', [], 404);
        }

        $params = $request->only(['start_date', 'end_date']);

        return $this->success($this->service->getStatistics($wallet, $params));
    }
}
