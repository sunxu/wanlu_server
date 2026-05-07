<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorCode;
use App\Constants\UserConst;
use App\Exception\BusinessException;
use App\Model\Account;
use App\Model\App;
use App\Model\UserApp;
use App\Model\UserRole;
use App\Utils\RedisManager;
use Hyperf\DbConnection\Db;

class UserService
{
    /**
     * 注册
     * @param array $params
     * @return void
     * @throws \Throwable
     */
    public function register(array $params): void
    {
        $account = new Account();
        $account->user_name = data_get($params, 'user_name');
        $account->saveOrFail();
    }

    /**
     * 登录
     * @param array $params
     * @return string
     * @throws \Exception
     */
    public function login(array $params): string
    {
        $account = Account::where('user_name', data_get($params, 'username'))
            ->where('status', UserConst::ACCOUNT_STATUS['normal'])->first();
        if (md5(data_get($params, 'password') . $account->salt) !== $account->password) {
            throw new BusinessException(ErrorCode::PARAMS_ERROR, '账号或密码错误');
        }
        Account::where('user_id', $account->user_id)->update(['login_time' => time()]);

        return $this->buildToken((int)$account->user_id);
    }

    /**
     * 生成token
     * @param int $userId
     * @param int $expire
     * @return string
     */
    public function buildToken(int $userId, int $expire = 60 * 60 * 24): string
    {
        $account = $this->getUserInfo($userId);
        $token = md5(json_encode([
                'user_id' => $userId,
                'expire' => $expire,
                'time' => time(),
            ]) . $account->salt . uniqid());
        $redis = RedisManager::get();
        if ($redis->exists("token:{$userId}")) {
            $oldToken = $redis->get("token:{$userId}");
            $redis->del("token:{$oldToken}");
        }
        $redis->set("token:{$token}", json_encode($account));
        $redis->set("token:{$userId}", $token);
        $redis->expire("token:{$token}", $expire);
        $redis->expire("token:{$userId}", $expire);
        return $token;
    }

    public function getUserInfo(int $userId)
    {
        $account = Account::with('roles')->where('user_id', $userId)->first();
        $apis = collect(data_get($account, 'roles', []))->pluck('apis')->flatten(1)->toArray();
        $apis = $this->radixTree($apis);
        $pages = collect(data_get($account, 'roles', []))->pluck('pages')->flatten(1)->toArray();
        $account->setAttribute('apis', $apis);
        $account->setAttribute('pages', $pages);
        if ($account->is_super == UserConst::USER_TYPE['super']) {
            $apps = App::with('auth')
                ->select([
                    'app_id',
                    'app_code',
                    'app_name'
                ])
                ->where('abbr', '<>', 'web')
                ->where('status', 1)
                ->get()
                ->all();
        } else {
            $appIds = UserApp::where('user_id', $account->user_id)
                ->pluck('app_id')->toArray();
            $apps = App::with('auth')
                ->select([
                    'app_id',
                    'app_code',
                    'app_name'
                ])
                ->where('abbr', '<>', 'web')
                ->where('status', 1)
                ->whereIn('app_id', $appIds)
                ->get()->all();
        }
        $account->setAttribute('apps', $apps);

        return $account;
    }

    public function radixTree(array $apis): array
    {
        $tree = [];
        foreach ($apis as $api) {
            $path = explode('/', trim($api['path'], '/'));
            $method = strtolower($api['method']);
            $this->saveTree($tree, $path, $method);
        }

        return $tree;
    }

    public function saveTree(array &$tree, array $path, string $method): array
    {
        if (count($path) <= 0) {
            return $tree;
        }
        $node = array_shift($path);
        if (preg_match('/{\w+}/', $node)) {
            $node = '*';
        }
        if (!isset($tree[$node])) {
            $tree[$node] = [
                'child' => [],
                'method' => [],
            ];
        }
        if (count($path) > 0) {
            $this->saveTree($tree[$node]['child'], $path, $method);
        } else {
            $tree[$node]['method'][$method] = $method;
        }

        return $tree;
    }

    /**
     * 通过token获取用户信息
     * @param string $token
     * @return mixed
     * @throws \Exception
     */
    public function getUserByToken(string $token): array
    {
        $redis = RedisManager::get();
        if (!$redis->exists("token:{$token}")) {
            throw new BusinessException(ErrorCode::NOT_LOGIN, ErrorCode::getMessage(ErrorCode::NOT_LOGIN));
        }
        $userInfo = $redis->get("token:{$token}");

        return json_decode($userInfo, true);
    }

    /**
     * 删除token
     * @param string $token
     * @return void
     */
    public function moveToken(string $token): void
    {
        $redis = RedisManager::get();
        $redis->del("token:{$token}");
    }

    public function userList(array $params)
    {
        return Account::with(['roles', 'apps'])->when($userName = data_get($params, 'user_name'), function ($query) use ($userName) {
            $query->where('user_name', 'like', "%{$userName}%");
        })
            ->orderBy('login_time', 'desc')
            ->orderBy('updated_time', 'desc')
            ->orderBy('user_id', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)));
    }

    public function addUser(array $params)
    {
        $userName = data_get($params, 'user_name');
        $account = Account::where('user_name', $userName)->first();
        if ($account) {
            throw new BusinessException(ErrorCode::PARAMS_ERROR, '【{$userName}】已存在');
        }
        Db::beginTransaction();
        try {
            $account = new Account();
            $account->salt = uniqid();
            $account->user_name = $userName;
            $account->password = md5(data_get($params, 'password', UserConst::DEFAULT_PASSWORD) . $account->salt);
            $account->status = UserConst::ACCOUNT_STATUS['normal'];
            $account->saveOrFail();
            $roles = collect(data_get($params, 'role_ids', []))->map(function ($item) use ($account) {
                return [
                    'role_id' => $item,
                    'user_id' => $account->user_id,
                ];
            })->toArray();
            if ($roles) {
                UserRole::insert($roles);
            }
            $apps = collect(data_get($params, 'app_ids', []))->map(function ($item) use ($account) {
                return [
                    'app_id' => $item,
                    'user_id' => $account->user_id,
                ];
            })->toArray();
            if ($apps) {
                UserApp::insert($apps);
            }
            Db::commit();
        } catch (\Throwable $ex) {
            Db::rollBack();
            throw new BusinessException(ErrorCode::PARAMS_ERROR, '添加失败');
        }
    }

    public function updateUser(int $userId, array $params)
    {
        Db::beginTransaction();
        try {
            $account = Account::findOrFail($userId);
            $account->user_name = data_get($params, 'user_name');
            $account->saveOrFail();
            $roles = collect(data_get($params, 'role_ids', []))->map(function ($item) use ($account) {
                return [
                    'role_id' => $item,
                    'user_id' => $account->user_id,
                ];
            })->toArray();
            UserRole::where('user_id', $account->user_id)->delete();
            if ($roles) {
                UserRole::insert($roles);
            }
            $apps = collect(data_get($params, 'app_ids', []))->map(function ($item) use ($account) {
                return [
                    'app_id' => $item,
                    'user_id' => $account->user_id,
                ];
            })->toArray();
            UserApp::where('user_id', $account->user_id)->delete();
            if ($apps) {
                UserApp::insert($apps);
            }
            Db::commit();
        } catch (\Throwable $ex) {
            Db::rollBack();
            throw new BusinessException(ErrorCode::PARAMS_ERROR, '修改失败');
        }
    }

    public function updatePassword(int $userId, string $password)
    {
        $account = Account::findOrFail($userId);
        $account->password = md5($password . $account->salt);
        $account->saveOrFail();
    }

    public function editStatus(int $userId)
    {
        $account = Account::findOrFail($userId);
        $account->status ^= UserConst::ACCOUNT_STATUS['normal'] ^ UserConst::ACCOUNT_STATUS['close'];
        $account->saveOrFail();
    }

    public function emptyToken($userIds): void
    {
        if (!is_array($userIds)) {
            $userIds = [$userIds];
        }
        $redis = RedisManager::get();
        foreach ($userIds as $userId) {
            if (!$redis->exists("token:{$userId}")) {
                continue;
            }
            $token = $redis->get("token:{$userId}");
            $redis->del("token:{$token}");
            $redis->del("token:{$userId}");
        }
    }

    public function getUserIdsNotSuper(array $userIds): array
    {
        return Account::whereIn('user_id', $userIds)->where('is_super', 0)->get('user_id')->pluck('user_id')->toArray();
    }
}