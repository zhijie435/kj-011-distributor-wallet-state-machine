# 经销商钱包状态机 - 部署文档

## 一、系统要求

- PHP >= 8.3
- Laravel >= 13.x
- MySQL >= 8.0 或 PostgreSQL >= 13.0
- Redis >= 6.0（用于队列和缓存）
- Composer >= 2.0
- Node.js >= 18.x（用于前端构建）

## 二、环境变量配置

复制 `.env.example` 为 `.env` 并根据实际环境修改以下配置：

### 2.1 基础配置

```env
APP_NAME="内容审核标注平台"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_TIMEZONE=Asia/Shanghai
APP_LOCALE=zh_CN
```

### 2.2 数据库配置

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wallet_state_machine
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

### 2.3 Redis 配置

```env
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
```

### 2.4 队列配置

```env
QUEUE_CONNECTION=redis
DB_QUEUE_CONNECTION=mysql
DB_QUEUE_TABLE=jobs
DB_QUEUE=default
DB_QUEUE_RETRY_AFTER=90
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=90
DB_BATCHING_CONNECTION=mysql
DB_BATCHING_TABLE=job_batches
QUEUE_FAILED_DRIVER=database-uuids
DB_FAILED_CONNECTION=mysql
DB_FAILED_TABLE=failed_jobs
```

### 2.5 钱包模块专用配置

```env
# 钱包余额限制
WALLET_MIN_BALANCE=0
WALLET_MAX_SINGLE_RECHARGE=500000
WALLET_MAX_SINGLE_WITHDRAW=200000
WALLET_AUTO_FREEZE_ON_NEGATIVE=false

# 状态日志保留天数
WALLET_STATE_LOG_RETENTION_DAYS=365

# 默认货币和钱包号前缀
WALLET_DEFAULT_CURRENCY=CNY
WALLET_NO_PREFIX=W

# 队列配置
WALLET_QUEUE_CONNECTION=redis
WALLET_STATE_TRANSITION_QUEUE=default
WALLET_NOTIFICATION_QUEUE=default
WALLET_STATE_TRANSITION_TRIES=3
WALLET_STATE_TRANSITION_BACKOFF=30

# 通知配置
WALLET_NOTIFICATION_ENABLED=true
WALLET_NOTIFICATION_CHANNEL=log

# 健康检查配置
WALLET_HEALTH_CHECK_SCHEDULE=daily
WALLET_WARN_NEGATIVE_BALANCE=true
WALLET_WARN_INCONSISTENT_FREEZE=true
WALLET_WARN_INACTIVE_WITH_BALANCE=true
```

## 三、部署步骤

### 3.1 安装依赖

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

### 3.2 生成应用密钥

```bash
php artisan key:generate
```

### 3.3 数据库迁移

执行所有数据库迁移创建钱包相关表：

```bash
php artisan migrate --force
```

迁移文件列表：

| 迁移文件 | 说明 |
|-----------|------|
| `0001_01_01_000000_create_users_table` | 用户表 |
| `0001_01_01_000001_create_cache_table` | 缓存表 |
| `0001_01_01_000002_create_jobs_table` | 队列表 |
| `2026_06_20_163951_create_personal_access_tokens_table` | API Token 表 |
| `2026_06_20_163952_create_permission_tables` | 权限表 |
| `2026_06_20_163953_create_distributors_table` | 经销商表 |
| `2026_06_20_164000_create_dealer_wallets_table` | 经销商钱包表 |
| `2026_06_20_164001_create_wallet_transactions_table` | 钱包交易记录表 |
| `2026_06_20_164002_create_wallet_state_logs_table` | 钱包状态变更日志表 |

### 3.4 数据库种子数据

执行种子数据初始化权限和演示数据：

```bash
php artisan db:seed --force
```

种子类列表：

| 种子类 | 说明 |
|---------|------|
| `PermissionSeeder` | 初始化钱包相关权限和角色 |
| `WalletSeeder` | 初始化演示经销商、钱包和用户数据 |

单独执行某个种子：

```bash
php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=WalletSeeder --force
```

种子数据创建的默认账号：

| 角色 | 邮箱 | 密码 | 说明 |
|-----|------|-----|------|
| 平台管理员 | admin@platform.com | password123 | 拥有所有钱包管理权限 |
| 经销商用户 | distributor_{id}@example.com | password123 | 各经销商对应账号 |

### 3.5 前端构建

```bash
npm install --ignore-scripts
npm run build
```

### 3.6 缓存配置

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 四、队列任务配置

### 4.1 队列 Worker 启动

使用 Supervisor 管理队列进程，创建配置文件 `/etc/supervisor/conf.d/wallet-queue.conf`：

```ini
[program:wallet-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/backend/artisan queue:work redis --sleep=3 --tries=3 --timeout=0
directory=/path/to/backend
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/backend/storage/logs/queue-worker.log
```

启动队列 Worker：

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start wallet-queue:*
```

### 4.2 钱包专用队列（可选）

如需分离钱包状态变更和通知队列：

```ini
[program:wallet-state-transition]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/backend/artisan queue:work redis --queue=wallet_state_transition --sleep=3 --tries=3
directory=/path/to/backend
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/backend/storage/logs/wallet-state-transition.log

[program:wallet-notification]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/backend/artisan queue:work redis --queue=wallet_notification --sleep=3 --tries=3
directory=/path/to/backend
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/backend/storage/logs/wallet-notification.log
```

修改 `.env` 中对应队列名：

```env
WALLET_STATE_TRANSITION_QUEUE=wallet_state_transition
WALLET_NOTIFICATION_QUEUE=wallet_notification
```

### 4.3 失败任务重试

查看失败任务：

```bash
php artisan queue:failed
```

重试所有失败任务：

```bash
php artisan queue:retry all
```

重试指定 ID 的失败任务：

```bash
php artisan queue:retry 1
```

清除所有失败任务：

```bash
php artisan queue:flush
```

### 4.4 定时任务配置

配置 Cron 任务（`/etc/cron.d/laravel-scheduler）：

```cron
* * * * * www-data cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

已注册的定时任务：

| 命令 | 频率 | 说明 |
|-----|-----|------|
| `wallet:health-check` | 每天 02:00 | 钱包状态机健康检查 |

## 五、验收命令

### 5.1 运行测试套件

运行所有测试：

```bash
php artisan test
```

运行单元测试：

```bash
php artisan test --testsuite=Unit
```

运行功能测试：

```bash
php artisan test --testsuite=Feature
```

测试文件说明：

| 测试文件 | 说明 |
|---------|------|
| `tests/Unit/WalletStatusEnumTest.php` | 钱包状态枚举测试 |
| `tests/Unit/WalletStateMachineTest.php` | 状态机核心逻辑测试 |
| `tests/Unit/DealerWalletModelTest.php` | 钱包模型测试 |
| `tests/Unit/StateTransitionExceptionTest.php` | 状态迁移异常测试 |
| `tests/Unit/WalletExceptionTest.php` | 钱包异常测试 |
| `tests/Unit/WalletTransactionTypeEnumTest.php` | 交易类型枚举测试 |
| `tests/Unit/WalletTransitionActionEnumTest.php` | 状态迁移动作枚举测试 |
| `tests/Unit/TransitionResultTest.php` | 迁移结果测试 |
| `tests/Feature/WalletApiTest.php` | 钱包 API 接口测试 |
| `tests/Feature/WalletServiceTest.php` | 钱包服务测试 |
| `tests/Feature/WalletServiceEdgeCaseTest.php` | 钱包服务边界测试 |
| `tests/Feature/WalletStateSyncTest.php` | 钱包状态同步测试 |

### 5.2 健康检查

执行钱包健康检查命令：

```bash
php artisan wallet:health-check
```

输出示例：

```
=== 钱包状态机健康检查 ===
钱包总数: 4
  未激活: 1
  正常: 1
  已冻结: 1
  受限: 1
  已注销: 0
=== 健康检查完成 ===
```

### 5.3 数据库连接验证

```bash
php artisan tinker
```

```php
// 检查钱包表是否存在
DB::table('dealer_wallets')->count();

// 检查权限是否正确
Spatie\Permission\Models\Role::all()->pluck('name');

// 检查钱包状态枚举
App\Enums\WalletStatus::cases();
```

### 5.4 API 接口验收

使用登录并测试 API（使用 cURL 或 Postman）：

```bash
# 登录获取 Token
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@platform.com", "password": "password123"}'
```

```bash
# 获取钱包列表（替换 {token} 为实际 Token
curl -H "Authorization: Bearer {token}" \
  http://localhost/api/wallets
```

```bash
# 获取指定钱包详情
curl -H "Authorization: Bearer {token}" \
  http://localhost/api/wallets/1
```

```bash
# 激活钱包
curl -X POST -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"reason": "开户激活"}' \
  http://localhost/api/wallets/4/activate
```

```bash
# 冻结钱包
curl -X POST -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"reason": "违规操作"}' \
  http://localhost/api/wallets/1/freeze
```

```bash
# 钱包充值
curl -X POST -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"amount": 1000, "remark": "测试充值"}' \
  http://localhost/api/wallets/1/recharge
```

### 5.5 队列任务验证

```bash
# 检查队列是否运行
php artisan queue:monitor redis:default

# 监听队列（调试用）
php artisan queue:listen --tries=1
```

分发测试队列任务：

```bash
php artisan tinker
```

```php
// 手动分发状态迁移任务
App\Jobs\WalletStateTransitionJob::dispatch(1, 'freeze', ['reason' => '测试']);

// 手动分发通知任务
App\Jobs\WalletNotificationJob::dispatch(1, 'active', 'frozen', '测试冻结');
```

### 5.6 权限验证

```bash
php artisan tinker
```

```php
// 检查管理员权限
$user = App\Models\User::where('email', 'admin@platform.com')->first();
$user->getAllPermissions()->pluck('name');

// 检查经销商角色权限
$role = Spatie\Permission\Models\Role::findByName('distributor');
$role->permissions->pluck('name');
```

## 六、常见问题排查

### 6.1 队列任务不执行

1. 检查队列 Worker 是否运行：`ps aux | grep queue:work
2. 检查队列连接配置是否正确
3. 查看失败任务表：`php artisan queue:failed
4. 查看日志文件：`storage/logs/laravel.log`

### 6.2 状态迁移失败

1. 检查钱包当前状态是否允许迁移
2. 检查钱包余额和冻结金额是否为0（注销时）
3. 查看 `wallet_state_logs` 表查看历史变更记录

### 6.3 数据库迁移失败

1. 检查数据库连接是否正常
2. 检查数据库用户权限
3. 使用 `--pretend` 预览 SQL：`php artisan migrate --pretend`

## 七、回滚操作

回滚最后一批迁移：

```bash
php artisan migrate:rollback --force
```

回滚所有迁移并重新执行：

```bash
php artisan migrate:refresh --force --seed
```
