# 荣耀投放迁移说明

## 配置与令牌刷新

沿用 `server-new` 的数据库配置和既有刷新流程，授权关系和令牌字段结构不变；存量应用缺失的荣耀点击表由下面的补表命令生成。

现有配置需要保持以下关联：`mkt_user.app_id → mkt_app.app_id`、`mkt_account.user_id → mkt_user.user_id`、`mkt_advertiser.account_id → mkt_account.account_id`。`mkt_app` 配置荣耀客户端ID、密钥及 `platform=honor`；用户ID和账户绑定沿用环境已有值，不强制用户ID等于客户端ID，也不新增凭证与广告主的一对一约定。

原有 `mkt:authToken` 读取已配置用户对应的应用凭证，调用荣耀 `authRefreshToken()`，使用 `client_credentials` 获取并保存令牌，不依赖 `refresh_token`。刷新接口若返回可选的 `refresh_token` 则按原适配器保存。定时任务继续提前20分钟筛选令牌，也可用现有命令指定用户：

```sh
php bin/hyperf.php mkt:authToken 已配置的授权用户ID
```

该命令沿用既有过期时间筛选及队列机制，需要正常运行 `mkt` 队列消费者。

## 同步和报表

平台代码 `honor`、来源编号 `7`，荣耀推广安装渠道仍为 `14`，腾讯仍为 `tx / 8`。

荣耀广告活动映射为现有项目，广告组映射为现有广告；复用项目／广告同步及日报，广告主报表支持日和小时粒度，荣耀不进入计划链路。金额从百万分之一元转换为元，继续按当前工程正消耗规则入库。

现有任务的调用方式不变，例如：

```sh
php bin/hyperf.php mkt:advertiserList 已配置的账户ID
php bin/hyperf.php mkt:advertiserItemList project 应用代码 荣耀广告主ID
php bin/hyperf.php mkt:advertiserItemList ad 应用代码 荣耀广告主ID
php bin/hyperf.php mkt:reportDailyItem advertiser today 应用代码 荣耀广告主ID
php bin/hyperf.php mkt:reportHourlyItem advertiser today 应用代码 荣耀广告主ID
php bin/hyperf.php mkt:reportDailyItem project today 应用代码 荣耀广告主ID
php bin/hyperf.php mkt:reportDailyItem ad today 应用代码 荣耀广告主ID
```

应用、广告主启用状态和自投筛选条件沿用当前工程。项目、广告及小时报表仍按既有任务要求先有广告主日报。前端复用现有投放来源配置和项目／广告页面。

荣耀适配器保留参考工程的批量状态接口；公共业务层仍保留原有平台限制，不额外开放荣耀单广告启停、自动规则、预算、时段或广告创建能力。

## 点击与数据保留

沿用现有 `/v1/ad/honor/{appCode}/` 点击接收、归因和事件回传协议。点击请求不自动建表，存量实际应用可在配置好当前环境数据库连接的 `api_server` 目录执行：

```sh
# 预览：校验并输出待建表SQL
php artisan mkt:honor-tables --all --dry-run
# 正式补齐缺失表
php artisan mkt:honor-tables --all
# 也可只处理指定应用，不能与 --all 同时使用
php artisan mkt:honor-tables 应用代码1 应用代码2
```

应用列表取当前环境 `uc.app`，全量模式包含停用和未绑定广告主的实际应用，跳过 `default`。命令直接创建缺失的 `app_{应用代码}_honor`，包含当前点击表公共字段、荣耀 `request_id`／`track_id`、事件回传状态和匹配索引，不依赖任何模板表。已有表直接跳过，不修改其结构或数据；不创建默认荣耀模板，不修改令牌字段，不处理授权关系。

全部应用标识先校验，预检失败不执行DDL。执行中某张表失败会记录错误、继续处理其他目标并返回非零状态；可重复执行补齐失败表。补表只创建空表，不补造历史点击。真实数据库执行需具备创建表权限，隔离测试不代替数据库联调。

新建应用的表复制沿用现有流程，来源应用缺少荣耀表时可对新应用再次执行补表命令。荣耀表沿用现有点击清理和归档周期。

荣耀监测参数不含项目／广告组ID，不补造项目、广告层级的归因收益。旧网页抓取定时任务按参考工程停用，旧命令和服务保持原有行为。

## 验证

```sh
php tests/honor_marketing_test.php
php tests/honor_persistence_test.php
php tests/honor_tables_test.php
php tests/honor_click_tables_test.php
php tests/tencent_marketing_test.php
php tests/tencent_persistence_test.php
php tests/tencent_attribution_test.php
php tests/tencent_app_creation_test.php
```

前端在 `admin_vue` 目录执行：

```sh
NODE_ENV=test node node_modules/jest/bin/jest.js tests/unit/honor-marketing.spec.js tests/unit/tencent-marketing.spec.js --runInBand --watchman=false
NODE_OPTIONS=--openssl-legacy-provider npm run build:prod
```

隔离测试不连接真实数据库或荣耀接口；真实账户的配置、令牌刷新及同口径报表仍需在联调环境核对。
