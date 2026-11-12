# 腾讯营销接入与联调

## 接入范围和数据关系

平台代码为 `tx`，归因来源为 `8`，广点通安装渠道仍为 `11`。优量汇属于变现平台，配置不变。

腾讯直客账户对应 `mkt_advertiser`，营销单元对应 `mkt_plan`。授权和报表复用已有表，金额统一存储为元；腾讯 API 返回的分在适配器内转换。

本期支持 Android 应用的点击、激活、留存和关键行为。营销单元状态、预算只读；广告创建、启停、预算修改和自动规则不开放。

## 授权配置

在现有 `mkt_app` 中配置腾讯开发者应用的 `app_id`、`app_name`、`app_secret`、`platform=tx`。不要将真实密钥提交到仓库。`auth_url` 沿用现有字段，填写包含回调地址的授权 URL，例如：

```text
https://developers.e.qq.com/oauth/authorize?client_id=开发者应用ID&redirect_uri=https%3A%2F%2F后台域名%2Fuc%2Fmkt%2Ftencent
```

回调域名需要与腾讯开发者后台登记一致。广告主管理页面的“腾讯营销授权”按钮会读取已配置应用，并由后端生成绑定发起后台用户、带一次性 `state` 的授权地址；不要直接使用上述模板 URL 完成授权。腾讯公开回调仅暂存授权码，原后台页面通过已登录的 `/uc/v1/mkt/tx/complete` 完成授权；跨后台用户不允许确认。

新增内部接口：

- `GET /uc/v1/mkt/tx/applications`：返回开发者应用 ID、名称，需要登录和现有请求签名。
- `POST /uc/v1/mkt/tx/authorize`：提交 `app_id`，返回授权地址，需要登录和现有请求签名。
- `GET /uc/mkt/tencent`：腾讯回调，接收 `authorization_code`、`state`；state 有效期十分钟，一次性消费后将授权码短暂保存在 Redis，不直接写入授权关系。
- `POST /uc/v1/mkt/tx/complete`：原后台登录用户提交 `state` 确认，回调未到时返回 `completed=false`，成功后返回 `completed=true`；仅发起授权的用户可确认，确认只能使用一次。

授权应用需要已开通查询账户、营销单元、报表和用户行为上报的相应权限。授权关系中的用户 ID 为 `tx_{开发者应用ID}_{授权账户ID}`；同一个投放账户已有其他授权关系时拒绝覆盖，需先人工核对归属。

授权完成后使用现有广告主管理功能绑定应用。默认激活、留存及关键行为触发条件沿用现有账户设置。公共定时任务负责令牌刷新、广告主和计划同步、报表及预算查询。

## 点击分表与监测地址

新建 App 沿用巨量、快手已有的模板复制机制：后台使用 `default`，`create-app` 和 `create-app-new` 使用指定的来源应用。创建入口不增加腾讯专用判断，也不在运行时从其他平台补表。

上线时在配置好生产环境数据库连接的 `api_server` 目录执行全量脚本：

```sh
cd api_server
# 预览：只校验并输出 SQL，不建表
php artisan mkt:tx-tables --all --dry-run
# 正式执行：为全部注册 App 补齐腾讯点击表
php artisan mkt:tx-tables --all
```

全量范围是当前环境 `uc.app` 中的所有应用，包含停用应用和 `default` 模板，不要求绑定腾讯广告主。在当前业务库创建 `app_{应用代码}_tx`；已存在的表直接跳过，不修改结构和数据。不会跨环境连接其他数据库。

默认优先复制该应用已有的百度点击表；缺少完整模板时，依次选择 `app_default_bd` 或其他注册应用的完整百度点击表。所有模板都检查 `click_id`、设备匹配及回传状态字段，复制其现有结构和索引，不复制数据，不增加字段。预览会列出每张新表对应的源表。

也可为本次操作统一指定现有模板，或只补指定 App：

```sh
php artisan mkt:tx-tables --all --template=已有点击表名
php artisan mkt:tx-tables 应用代码
```

全部应用和模板先校验；任一校验失败，不执行建表。正式执行使用 `CREATE TABLE IF NOT EXISTS ... LIKE ...`，逐表输出结果和汇总。执行途中失败的表会记录错误并返回非零退出码，其他表继续处理；可重复执行补齐失败项，已有表不会重建。MySQL DDL 不作事务回滚。

全量补齐后，`default` 和其他来源应用也包含腾讯表，后续新建 App 继续通过原有遍历逻辑与巨量、快手一起复制，无需新增创建入口判断或单独初始化模板。点击请求不会自动建表。腾讯点击表进入现有未归因点击清理和历史点击归档流程。

在腾讯点击监测工具中填写监测地址页面生成的 Feedback URL：

```text
https://应用所属域名/v1/ad/tx/应用代码/
```

选择下发 `advertiser_id`、`adgroup_id`、`creative_id`、`campaign_id`、`click_id`、`click_time`、`muid`、`android_id`、`oaid`、`ip`、`user_agent`。参数由腾讯附加，不在地址里拼其他平台的宏。

接收接口将 `advertiser_id → account_id`、`adgroup_id → aid`、`creative_id → cid`；保留 `click_id`，规范化点击时间和设备标识。账户须为已启用且绑定当前应用的腾讯广告主。成功应答 `{"ret":0,"msg":"OK"}`；缺表或非法参数不返回成功。

## 使用已有扩展配置完成回传

不新增专用表单。在现有广告主“扩展配置”中填写实际参数，并保留该广告主其他配置：

```json
{
  "user_action_set_id": "腾讯为该应用分配的ANDROID行为源ID",
  "key_action_type": "CUSTOM",
  "custom_action": "腾讯转化目标中配置的自定义事件名"
}
```

`user_action_set_id` 必须是有效数字 ID。激活使用 `ACTIVATE_APP`；留存触发时使用 `START_APP`，具体触发时机由现有自然日或 24 小时定义决定。关键行为使用 `key_action_type`，值必须与腾讯已配置且支持的转化目标一致；选择 `CUSTOM` 时必须提供 `custom_action`。如现有关键行为采用加桌，上报时同样可按账户配置映射，未配置时使用 `ADD_DESKTOP`。

上报接口为 `user_actions/add`，包含设备哈希和原始点击 ID。事件幂等标识由应用、账户、点击、事件类型和发生时间生成；重试保持不变。失败继续使用现有通知次数及重试流程，不将失败标成成功。

报表默认获取消耗、展现、点击、转化、激活。关键行为和留存的腾讯报表指标需根据实际账户、目标及接口支持情况，在原有扩展配置中指定对应计数字段：

```json
{
  "tx_report_fields": {
    "key_action": "该账户目标对应的腾讯计数字段",
    "next_day_open": "该账户支持的腾讯留存计数字段"
  }
}
```

不要将 `conversions_count` 默认解释为关键行为数。未配置的特殊指标不请求、不覆盖已有值；本地安装、收益和留存统计沿用现有链路。配置的字段必须是腾讯接口支持的字段，接口报错会明确失败，不会伪装成空报表。同步报表超过腾讯的两万行查询上限时明确报错，本期不新增异步报表任务体系。

## P1 修复与上线门槛（2026-11-12）

- 点击归因：原始腾讯 `muid` 按小写规范化后 MD5；已经是 32 位 MD5 的值不重复哈希。历史记录若曾以非规范化原文入库，需核对受影响点击。
- 报表：广告主日报返回多行时按同账户、同日期汇总基础指标并重算比率；小时报表保留逐小时记录；不同账户或日期禁止混合。
- 安全：本分支已将 `admin_server/.env` 与 `api_server/.env` 从 Git 追踪中移除，为两端提供无真实值的 `.env.example`，同时增加忽略规则和 CI 防误提交检查。**合并或部署前必须确认两端生产环境都有独立持久化 `.env` 或外部密钥注入，否则检出更新可能删除运行所需配置。** Git 历史仍包含旧文件，需轮换曾暴露的数据库、OSS 等凭据，评估历史清理；不要将真实值贴入工单。
- OAuth：已增加登录态二阶段确认，公开回调不进行 token 交换，只有原发起用户经已登录接口才能完成授权。上线前需要执行跨用户、过期、重放及真实浏览器端到端验证。

## 验证

页面筛选沿用已有“投放平台／投放渠道”下拉框，只追加“腾讯营销”选项；公共字典使用 `tx`，旧版数字来源使用 `8`。不新增平台筛选控件或腾讯专用报表列。公共广告主、计划查询统一返回所有平台，自动规则页面自行排除腾讯，后端规则保存和执行继续校验限制。

计划游标分页由腾讯适配器内部转换，公共同步继续使用现有 `list / page_info` 返回结构。报表沿用各平台已有的消耗判断，不为腾讯增加零消耗入库例外。广告主扩展配置继续使用完整 JSON 编辑与保存，保留提交内容中的其他配置，也允许主动删除不再使用的键。

原平台兼容检查以 `d787217` 为接入前基准。腾讯报表扩展指标仅传给腾讯适配器，原平台的应用配置、小时报表搜索参数、授权续期和计划分页参数保持原样。隔离回归覆盖六个原投放平台的续期成功／失败、分页、空结果和报表更新，以及包含荣耀的七个平台正常归因和激活、留存、关键行为、付费回传的成功／失败／重试判定。

归因部分保留已确认的共有缺陷修复：比较真实点击时间、外部归因处理中仍将本地命中落库，以及认领事务失败时不再派发激活。上述异常场景会改变旧版本行为；原有匹配顺序、归因窗口和各平台回传协议不作调整。

无需项目 PHP 依赖即可运行隔离协议测试：

```sh
php tests/tencent_marketing_test.php
php tests/tencent_persistence_test.php
php tests/tencent_attribution_test.php
php tests/tencent_app_creation_test.php
```

前端验证：

```sh
cd admin_vue
NODE_ENV=test node node_modules/jest/bin/jest.js tests/unit/tencent-marketing.spec.js --runInBand --watchman=false
NODE_OPTIONS=--openssl-legacy-provider npm run build:prod
```

在具备项目依赖、Swoole、MySQL、Redis 的联调环境，使用一个实际 Android 应用和直客广告主验证：首次及重复授权、令牌续期、分表重复初始化、真实点击下发、设备归因、激活/留存/关键行为成功回传及失败重试，最后按同日期和口径核对腾讯后台报表。

还需核对全部广告主规则和直接修改请求均不能作用于腾讯，原有平台行为不变。投放、计划、小时趋势、历史报表及归因查询的筛选均应显示“腾讯营销”，选中后结果只包含腾讯数据。

参考：[腾讯 API 清单](https://developers.e.qq.com/v3.0/docs/apilist)、[OAuth](https://developers.e.qq.com/v3.0/docs/api/oauth/token)、[日报](https://developers.e.qq.com/v3.0/docs/api/daily_reports/get)、[用户行为上报](https://developers.e.qq.com/v3.0/docs/api/user_actions/add)。
