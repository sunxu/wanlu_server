# 腾讯广告私有应用：Token 导入与广告主绑定操作手册

> 文档版本：2026-11-14。适用仓库：`sunxu/wanlu_server`，`admin_server` Hyperf。  
> 目标：用**真实腾讯广告账户 ID**，将**腾讯私有应用 Token** 安全关联到业务 App，并完成可审计的本地验收。  
> 范围止于「账户/广告主绑定 + 本地预检」。**点击归因、DataNexus Callback 鉴权和真实转化回传是独立的后续上线关卡**，不能因为本手册通过就视为已开通。

## 0. 一页流程

| 顺序 | 操作 | 成功判据 | 是否写入数据库 |
| --- | --- | --- | --- |
| 1 | 核对业务 App、腾讯开发者应用、真实广告账户身份 | 三类 ID 不混用 | 否 |
| 2 | `mkt:txPrivateImport` 导入私有 Refresh Token | 内部 `txapp_...` 凭证登记，腾讯刷新成功 | **是**（`mkt_app`、`mkt_user`） |
| 3 | `mkt:txPrivateAccountProbe` 只读查询指定广告账户 | `remote_advertiser_accessible=true`、ID 完全匹配 | 否 |
| 4 | `mkt:txPrivateBind` 无 `--apply` 预检 | `TX_BIND_DRY_RUN_READY`，无归属冲突 | 否 |
| 5 | `mkt:txPrivateBind ... --apply` 明确执行 | `TX_BIND_APPLIED`，按需新建两条关联记录 | **是**（`mkt_account`、`mkt_advertiser`） |
| 6 | SQL 核验与 `mkt:txPrivatePreflight` | 两表归属正确、全部本地 `checks=true` | 否 |
| 7 | 检查调度进程、腾讯点击与真实 Callback | 运行时证据和真实点击/回传逐项验证 | 后续独立流程 |

**不可跳过的边界**：不同 App、平台或 OAuth 用户的现有数据不允许覆盖；不允许把开发者 `client_id` 当作广告账户 `account_id`；不允许通过手工插入虚构 OAuth 身份绕过绑定守卫；绑定命令不会开启转化回传。

## 1. 三类身份和四张表

### 1.1 身份名词

| 名词 | 说明 | 本次 `lzgjf` 实例 |
| --- | --- | --- |
| 业务 App `app_code` | 系统业务 App 的唯一标识，不是腾讯开发者应用 | `lzgjf` |
| 腾讯开发者 `client_id` | 腾讯开发者平台应用编号，关联 `mkt_app.app_id` | `1112045194` |
| 腾讯广告主 `account_id` | 真实广告投放账户编号，要求当前 Token 有权查询 | **`94079761`（已验证）** |
| 内部私有凭证 `user_id` | 按 `txapp_` + `sha1(app_code)` 生成 | `txapp_efe92812424f541678515e43e08a4f55ac447121` |

腾讯后台还可能显示「广告主主体」「广告业务单元」「广告主」三级实体。必须先找到**实际可通过腾讯 `advertiser/get` 读到的广告主账户**，不能仅根据公司名称或下拉框当前选项猜测。

本次联调中，后台曾出现「广告主 `94946190`」「广告业务单元 `82638052`」「广告主主体 `82638051`」；对 `94946190` 的请求返回 `12201`，而最终通过真实 API 验证并绑定的是 **`94079761`**。这是两个账户 ID，不可混为一谈。

### 1.2 数据关系

~~~text
uc.app(app_code = 业务 App)
             │
             └── 独立选择腾讯私有凭证：txapp_ + sha1(app_code)
                    │
uc.mkt_app(app_id=腾讯 client_id, platform=tx)
                    │
uc.mkt_user(user_id=txapp_..., app_id=client_id, Token/到期时间)
                    │  新建账户时引用这个真实的私有凭证 ID
uc.mkt_account(account_id=真实腾讯 account_id, user_id=txapp_..., is_valid=1)
                    │
uc.mkt_advertiser(advertiser_id=account_id, account_id=account_id,
                  app_code=业务 App, platform=tx, status=1)
~~~

- `uc.mkt_app`：存腾讯开发者应用信息及 `client_secret`。
- `uc.mkt_user`：每个业务 App 一条腾讯私有凭证，含 Access/Refresh Token 及实际到期时间。它是**私有凭证记录，不是冒充的 OAuth 用户**。
- `uc.mkt_account`：绑定真实广告账户；新建时 `account_role=PRIVATE_APP`、`is_valid=1`、`user_id` 指向对应私有凭证。
- `uc.mkt_advertiser`：绑定真实广告主、平台 `tx`、业务 App；创建时使用腾讯接口返回的 `corporation_name`，不复制其他平台名称。
- `nls.app_lzgjf_tx`：腾讯点击数据表，属于**业务数据库**；并非上述授权四张表之一，Callback 后续验收使用。
- 保持原有巨量 `platform=jl` 广告主不动。当前账户/广告主的主键是全局使用的，出现 ID 归属冲突须停止并人工处理。

## 2. 准备工作

1. 在腾讯广告开发者平台创建/确认私有应用，记录 `client_id`、`client_secret`，用具有目标广告账户权限的身份取得 **Refresh Token**。同一 QQ 具有多个身份时，须特别核实生成 Token 所选身份。
2. 确认业务 `app_code` 存在、`uc.app.status=1`，`admin_server` 与目标 `uc` 库连接正确。
3. 在服务器部署已包含 `mkt:txPrivateImport`、`mkt:txPrivateAccountProbe`、`mkt:txPrivateBind`、`mkt:txPrivatePreflight` 的代码；可先执行 `php bin/hyperf.php list` 查看命令是否注册。
4. 先保证 `TX_DATANEXUS_CALLBACK_APPS` **未开启目标 App**；导入和账户绑定时不要顺带切换转化协议。

服务器代码更新示例（仅适用于部署目录本身是目标 Git 仓库且允许快进更新）：

~~~bash
cd /opt/jingling
git switch master
git pull --ff-only origin master
cd admin_server
php bin/hyperf.php list
~~~

如果部署系统使用发布包、容器或另一个工作目录，应使用该环境的正式部署流程，不能将上述 Git 命令视为自动重启服务。

## 3. 导入腾讯私有凭证

### 3.1 首次导入

~~~bash
cd /opt/jingling/admin_server
php bin/hyperf.php mkt:txPrivateImport \
  lzgjf \
  "1112045194" \
  "实际_client_secret" \
  "实际_refresh_token"
~~~

这里必须使用真实 Secret 和 Refresh Token，**不需要也不要手填 Access Token 或过期日期**。导入器会调用腾讯 `/oauth/token` 的 `grant_type=refresh_token` 获取新的 Access Token 及有效期，通过校验后在数据库事务中登记。腾讯侧刚生成的私有 Token 可能存在生效延迟；刷新失败时不应通过虚构有效期强行入库。

若同一业务 App 的凭证确需替换，在核实身份一致且新凭证有效后显式添加 `--replace`：

~~~bash
php bin/hyperf.php mkt:txPrivateImport \
  lzgjf "1112045194" "实际_client_secret" "实际_refresh_token" --replace
~~~

`--replace` 仅允许同业务 App、同开发者应用的受控替换，不能借此迁移到另一个 `client_id`，也不能覆盖属于其他业务 App 的凭证。

**保密提示**：目前实现从命令行参数读取 Secret/Refresh Token；参数可能进入 Shell 历史、`ps` 或操作审计，不要在 CI、公开终端或问题工单中执行/粘贴真实值；不要把值写进仓库。命令本身不会打印 Token。

### 3.2 导入后只读检查

~~~sql
SELECT
    u.user_id,
    u.app_id AS developer_client_id,
    a.platform,
    FROM_UNIXTIME(u.access_time) AS access_expires_at,
    CASE WHEN u.refresh_time = 0
        THEN NULL ELSE FROM_UNIXTIME(u.refresh_time) END AS refresh_expires_at
FROM uc.mkt_user AS u
JOIN uc.mkt_app AS a ON a.app_id = u.app_id
WHERE u.user_id = CONCAT('txapp_', SHA1('lzgjf'));
~~~

应仅查询到预期内部凭证、`app_id` 和 `platform=tx`。**不要**把 `access_token`、`refresh_token`、`app_secret` 加进对外共享的 SQL 输出。

在账户尚未绑定时执行 `mkt:txPrivatePreflight lzgjf`，广告主相关检查可能为 `false`，这是预期的阶段性状态，不表示 Token 导入失败。

## 4. 取得真实腾讯广告账户 ID

1. 登录腾讯广告投放平台/账户管理，查看目标广告主的**广告账户 ID**，与公司主体/业务单元编号区分。
2. 确认当前私有 Token 的生成身份拥有该账户权限。
3. **必须用下面的真实腾讯只读接口验证**。不能仅凭下拉框文本、开发者 `client_id`、同名公司或别的平台 ID 认定正确。

~~~bash
cd /opt/jingling/admin_server
php bin/hyperf.php mkt:txPrivateAccountProbe lzgjf 94079761
~~~

内部只读请求为 `GET https://api.e.qq.com/v3.0/advertiser/get`，包含 `account_id`、`pagination_mode=PAGINATION_MODE_NORMAL`、`page=1`、`page_size=100`、`fields=["account_id"]`、Access Token、时间戳和 nonce。

首次未绑定时的成功形态：

~~~json
{
  "verified_account_id": "94079761",
  "remote_advertiser_accessible": true,
  "existing_mkt_account": false,
  "existing_advertiser_bound_to_this_app": false,
  "global_advertiser_id_conflict": false,
  "database_modified": false,
  "status": "REMOTE_ACCESS_PASS__MANUAL_BINDING_REQUIRED"
}
~~~

验证过的腾讯原始成功响应核心字段：

~~~json
{
  "code": 0,
  "data": {
    "page_info": {"page": 1, "page_size": 100, "total_number": 1, "total_page": 1},
    "list": [{"account_id": 94079761}]
  }
}
~~~

以上示例只表示**该 Access Token 有权读取指定广告账户**，不自动证明 DataNexus 行为上报接口权限。若已完成本地绑定，再运行 `mkt:txPrivateAccountProbe`，`existing_mkt_account`、`existing_advertiser_bound_to_this_app` 应随之反映已有记录。

## 5. 预览本地绑定（默认无写入）

~~~bash
cd /opt/jingling/admin_server
php bin/hyperf.php mkt:txPrivateBind lzgjf 94079761
~~~

该命令会再次访问腾讯，验证账户 ID、读取腾讯 `corporation_name`，并在数据库事务内执行归属检查；**没有 `--apply` 时不新增任何记录**。

首次绑定、两张表均无记录且无冲突的实际验收输出：

~~~json
{
  "schema": "tx_private_advertiser_binding_v1",
  "app_code": "lzgjf",
  "verified_account_id": "94079761",
  "remote_access_verified": true,
  "mode": "dry_run",
  "callback_enabled_by_command": false,
  "existing_oauth_owner_modified": false,
  "status": "TX_BIND_DRY_RUN_READY",
  "would_create_account": true,
  "would_create_advertiser": true,
  "created_account": false,
  "created_advertiser": false
}
~~~

进入 `--apply` 之前必须核查：目标 `app_code` 正确、Token 能访问对应账户、`would_create_account`/`would_create_advertiser` 与预期相符、没有任何 `..._HOLD`。若目标数据库存在特殊非空约束，可先查看 `SHOW CREATE TABLE uc.mkt_account;` 和 `SHOW CREATE TABLE uc.mkt_advertiser;`，不能靠编造字段或手工强插绕过所有权检查。

已绑定后重复预览预期为 `TX_BIND_ALREADY_PRESENT`（不是要求继续新增）。

## 6. 明确写入账户与广告主（唯一会建档的步骤）

确认上一步无冲突后，**由有权的运维显式执行**：

~~~bash
cd /opt/jingling/admin_server
php bin/hyperf.php mkt:txPrivateBind lzgjf 94079761 --apply
~~~

首次执行成功的实际输出核心字段：

~~~json
{
  "mode": "apply",
  "verified_account_id": "94079761",
  "remote_access_verified": true,
  "status": "TX_BIND_APPLIED",
  "created_account": true,
  "created_advertiser": true,
  "callback_enabled_by_command": false,
  "existing_oauth_owner_modified": false
}
~~~

写入行为由代码保护：

- 在同一数据库事务中，仅创建**缺失**的 `mkt_account` 和 `mkt_advertiser`；如果后一步插入失败，事务回滚前一步。
- 新建账户 `account_id=94079761`、`user_id=txapp_...`、`account_role=PRIVATE_APP`、`is_valid=1`；新建广告主 `advertiser_id=account_id=94079761`、`app_code=lzgjf`、`platform=tx`、`status=1`。
- 对同一私有身份/业务 App 再次执行 `--apply` 是幂等路径；`status` 仍可能为 `TX_BIND_APPLIED`，但 `created_account=false`、`created_advertiser=false`。
- 发现不同 OAuth `user_id`、不同 `app_code`、不同平台 `advertiser_id`，或原记录已停用，都会拒绝覆盖/自动恢复。
- 本命令**不会**创建虚构的 OAuth 用户，不会修改原有巨量广告账户，不会重置 Token，也不会自动打开 DataNexus Callback。

## 7. 绑定后的双重验收

### 7.1 SQL 核对真实关联

~~~sql
SELECT
    a.advertiser_id,
    a.advertiser_name,
    a.account_id,
    a.platform,
    a.app_code,
    a.status AS advertiser_status,
    b.account_role,
    b.is_valid AS account_valid,
    b.user_id AS credential_user_id,
    u.app_id AS developer_client_id
FROM uc.mkt_advertiser AS a
JOIN uc.mkt_account AS b ON b.account_id = a.account_id
JOIN uc.mkt_user AS u ON u.user_id = b.user_id
WHERE a.advertiser_id = '94079761'
  AND a.app_code = 'lzgjf'
  AND a.platform = 'tx';
~~~

验收要求**恰好一条**符合预期：`account_id=advertiser_id=94079761`、`app_code=lzgjf`、`platform=tx`、广告主 `status=1`、账户 `is_valid=1`、`account_role=PRIVATE_APP`、`credential_user_id=CONCAT('txapp_',SHA1('lzgjf'))`、`developer_client_id=1112045194`。

### 7.2 本地预检

~~~bash
php bin/hyperf.php mkt:txPrivatePreflight lzgjf
~~~

本次实际返回：

- `status=LOCAL_PREFLIGHT_PASS__EXTERNAL_INTEGRATION_HOLD`
- `checks.app_active`、`private_credential_exists`、`developer_platform_tx`、`access_token_valid`、`refresh_token_usable_locally`、`has_enabled_tx_advertiser`、`every_advertiser_account_valid`、`crontab_config_enabled` 均为 `true`
- `advertisers.enabled_count=1`，`valid_account_count=1`，`invalid_or_missing_account_count=0`
- `warnings=["REFRESH_EXPIRES_NOT_LATER_THAN_ACCESS"]`

这代表**本地存储、到期检查和关联检查通过**。返回结构中 `unverified` 列出的仍是命令没有实际验证的项目，不是运行失败。`LOCAL_PREFLIGHT_PASS__EXTERNAL_INTEGRATION_HOLD` **不是转化回传验收通过**。

## 8. Token 到期与定时刷新

本次实际导入的两个过期时间均为 `2026-12-14 15:36:38 +08:00`；因此预检报告 `REFRESH_EXPIRES_NOT_LATER_THAN_ACCESS`。该项是风险提醒，不直接否定当前 Token 的有效性。

仓库现有调度策略：

- `admin_server/config/autoload/crontab.php`：`mkt:authToken` 每 10 分钟调度一次；`crontab.enable` 依赖 `CRONTAB_ENABLE` 配置。
- `AuthToken` 命令对 `txapp_...` 私有凭证使用**提前 24 小时**窗口，传统平台使用提前 20 分钟。
- `AuthToken` **只是把任务推入 Redis MKT 队列**；实际刷新由 `MktProcess` 消费后调用 `MktService::authToken`。
- 如果腾讯刷新响应未提供 Refresh Token 新有效期，数据库以 `refresh_time=0` 标记**未知**，不能误解为过期，也不能默认再延长 30 天。
- `crontab_config_enabled=true` 只证明当前 CLI 加载的配置启用；**不证明**生产 Crontab Dispatcher、Redis、MKT 队列消费者健康，也不证明未来刷新一定成功。

排查例：

~~~bash
cd /opt/jingling/admin_server
ps -ef | grep -E '[h]yperf|[M]ktProcess|[C]rontab'
grep -h 'authToken' runtime/logs/http*.log | tail -n 20
~~~

如距离到期仍超过 24 小时，尚未出现该私有用户刷新日志是正常情况；要在进入刷新窗口后核对实际队列消费、失败记录以及 `mkt_user.access_time` 是否更新，不能把“任务已配置”当成“任务已消费”。

## 9. 绑定之后仍需完成的回传上线关卡

### 9.1 点击表结构与真实点击

本次已经核验业务库 `nls.app_lzgjf_tx.callback_url` 为 `TEXT NOT NULL`，有设备/广告账户复合索引及时间索引。但字段/索引存在，**不代表真实点击已入库**。

只读统计（不展示设备标识或敏感 Callback URL）：

~~~sql
SELECT
    COUNT(*) AS total_clicks,
    SUM(CASE WHEN account_id = '94079761' THEN 1 ELSE 0 END) AS account_clicks,
    SUM(CASE WHEN account_id = '94079761' AND callback_url <> ''
        THEN 1 ELSE 0 END) AS clicks_with_callback,
    MAX(CASE WHEN account_id = '94079761'
        THEN FROM_UNIXTIME(ts) END) AS latest_click_time
FROM nls.app_lzgjf_tx;
~~~

真实点击应带上 Tencent 宏提供的 `account_id`、`adgroup_id`、`click_id`、`click_time`、设备归因字段和 `__CALLBACK__`；点击监测路径使用 **`/v1/ad/tx/lzgjf`（无尾斜杠）**，避免先收到 301。若无真实点击，不应用伪造 URL 冒充生产链路成功。

### 9.2 DataNexus Callback 鉴权（独立灰度）

只有同时确认以下条件才考虑启用：

1. `uc` 账户/广告主绑定有效；私有 Token 未过期，且调度/队列实际运行。
2. 腾讯 DataNexus 「广告效果监测」与 App 的转化上报协议一致，已为目标链路确认鉴权要求与权限。
3. 真实点击已入库，`callback_url` 完整且可通过 `TencentCallbackUrl::assertPostTarget`：HTTPS `api.e.qq.com`，允许路径 `/conv` 或 `/v3.0/user_actions/add`，含 `cb`、`conv_id` 参数；不允许其他主机或重定向。
4. 真实点击的 `click_id`、事件时间、设备标识及腾讯要求的行为类型已按当前 `TencentService` 生成，确认重试幂等。
5. 已完成一次受控真实转化 POST 观察，腾讯回包 `HTTP 200` 且 `code=0`，并核验本地成功标记/重试行为。

`api_server` 通过进程环境变量 `TX_DATANEXUS_CALLBACK_APPS` 选择灰度业务 App，默认应保持**不包含 `lzgjf`**。添加时需按部署流程重启/重新加载对应 API 进程；变更腾讯后台配置与部署时机要同步。

**回滚灰度**：将 `lzgjf` 从该环境变量移除并重启 `api_server`；如腾讯后台已切换为鉴权 Callback，还必须协调将平台端转化方式恢复到匹配的协议。此回滚**不删除账户绑定及点击数据**。

详细监测 URL 宏、转化 payload 和 Callback 协议见 [腾讯营销接入与联调](./tencent-marketing.md)。

## 10. 常见错误与处理

| 错误 / 状态 | 说明 | 处理 |
| --- | --- | --- |
| `12201`，`Parameter 'account_id' is invalid` | 该 Token 对当前账户无权操作、账户 ID 无效等 | 核对身份与真实账户 ID；不要把主体/业务单元/开发者 ID 代入 |
| `132100`，缺 `pagination_mode` / `page_size` | 查询请求必填字段缺失；不是授权结论 | 部署包含分页参数修复的代码；`PAGINATION_MODE_NORMAL`、`page=1`、`page_size=100` |
| `TX_ACCOUNT_PROBE_API_REJECTED` | 腾讯返回非零业务代码 | 查 `API_CODE`，必要时单次开启原始日志 |
| `TX_ACCOUNT_PROBE_NETWORK_FAILED` | 网络/HTTP 客户端异常 | 检查 DNS、TLS、代理、出网；不应重置 Token 作为首选 |
| `TX_ACCOUNT_PROBE_REMOTE_IDENTITY_MISMATCH` | 实际返回账户与请求 ID 不一致 | 停止绑定并核验广告账户身份 |
| `TX_BIND_DRY_RUN_READY` | 当前可安全创建缺失的绑定 | 审核预览内容；需要写入时才单独执行 `--apply` |
| `TX_BIND_ALREADY_PRESENT` | 同一身份/业务 App 已有完整绑定 | 不需再新建 |
| `TX_BIND_EXISTING_ACCOUNT_OWNER_HOLD` | `mkt_account.user_id` 属于另一个身份 | 不得覆盖 OAuth 用户，核对账户归属 |
| `TX_BIND_EXISTING_ADVERTISER_OWNER_HOLD` | 广告主 ID 已属于其他平台/App/账户 | 不得覆盖全局主键 |
| `TX_BIND_EXISTING_ACCOUNT_DISABLED_HOLD` / `TX_BIND_EXISTING_ADVERTISER_DISABLED_HOLD` | 已存在但已停用 | 人工复核，命令不会自动恢复 |
| `TX_BIND_REMOTE_ADVERTISER_NAME_INVALID` | 腾讯接口未提供可入库的名称 | 核对 `advertiser/get` 的 `corporation_name`；不从其他平台填假名称 |
| `TX_BIND_DB_TRANSACTION_FAILED` | 事务或数据库执行失败 | 检查生产数据库结构、默认值、权限和错误日志，不手工绕过守卫 |
| `REFRESH_EXPIRES_NOT_LATER_THAN_ACCESS` | Refresh 到期不晚于 Access | 监控实际刷新窗口与刷新结果，不虚构 Refresh 有效期 |
| `LOCAL_PREFLIGHT_PASS__EXTERNAL_INTEGRATION_HOLD` | 本地检查通过，外部链路尚未验收 | 继续检查队列进程、点击、Callback 权限和真实转化 |

### 单次开启探测原始日志

~~~bash
cd /opt/jingling/admin_server
TX_ACCOUNT_PROBE_RAW_LOG=1 \
  php bin/hyperf.php mkt:txPrivateAccountProbe lzgjf 94079761
tail -n 100 runtime/logs/tencent-account-probe-raw.log
~~~

记录完整请求 URL/Query、Access Token、HTTP 状态、Headers、原始 Body 和网络异常，写入 `admin_server/runtime/logs/tencent-account-probe-raw.log`（文件权限 0600）；默认不开启。**不要将完整日志发送到聊天、GitHub 或公开工单**。联调完成后清理：

~~~bash
rm -f /opt/jingling/admin_server/runtime/logs/tencent-account-probe-raw.log
~~~

## 11. 本次 `lzgjf` 已完成的实际验收记录

| 关卡 | 结果 |
| --- | --- |
| 私有腾讯应用 Token 导入 | 成功，内部 `txapp_...` 记录存在 |
| 使用 `94946190` 请求广告主 | 腾讯 `12201`，未绑定该账户 |
| 使用 `94079761` 探测广告主 | 腾讯 `code=0`，唯一返回账户 `94079761` |
| `mkt:txPrivateBind lzgjf 94079761` | `TX_BIND_DRY_RUN_READY`，拟创建账户/广告主各 1 |
| `mkt:txPrivateBind lzgjf 94079761 --apply` | `TX_BIND_APPLIED`，已创建账户/广告主各 1 |
| `mkt:txPrivatePreflight lzgjf` | 所有本地 `checks=true`，`enabled_count=1`、`valid_account_count=1` |
| Callback 灰度开关 | 绑定命令未启用 |
| Crontab / MKT 队列实际健康度 | **待运行时核验** |
| 真实腾讯点击 `callback_url` 入库 | **待核验** |
| DataNexus 鉴权及真实转化上报 | **待核验** |

因此，这次项目的准确阶段为：**腾讯私有应用凭证与真实广告主绑定已验收，本地预检通过；DataNexus 实际转化闭环尚未验收**。

## 12. 相关实现位置

- `admin_server/app/Command/Mkt/TencentPrivateImport.php`：导入私有凭证；`--replace`。
- `admin_server/app/Service/Mkt/TencentPrivateCredentialService.php`：腾讯刷新验证、凭证事务持久化。
- `admin_server/app/Command/Mkt/TencentPrivateAccountProbe.php`：账户级只读权限验证。
- `admin_server/app/Library/Mkt/MktTx.php`：`advertiser/get`、分页、腾讯 API 封装。
- `admin_server/app/Command/Mkt/TencentPrivateBind.php`：默认 dry-run、显式 `--apply` 的绑定。
- `admin_server/app/Service/Mkt/TencentPrivateBindingGuard.php`：阻止覆盖已有账户归属。
- `admin_server/app/Command/Mkt/TencentPrivatePreflight.php`：本地状态/到期/绑定预检。
- `admin_server/app/Command/Mkt/AuthToken.php`、`admin_server/config/autoload/crontab.php`、`admin_server/app/Process/MktProcess.php`：定时/排队/消费。
- `api_server/app/Services/Mkt/TencentClick.php`、`TencentCallbackUrl.php`、`TencentService.php`：点击字段、Callback URL 约束和转化回传。
- `docs/tencent-marketing.md`：腾讯营销接入与完整回传协议。
