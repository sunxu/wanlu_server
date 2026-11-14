<template>
  <div class="app-container">
    <el-row>
      <el-col :span="8">
        <span class="el-col-span">状态：</span>
        <el-select v-model="search.status" style="width: 240px;" clearable>
          <el-option
            v-for="(value,key) in status_map"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">投放方式：</span>
        <el-select v-model="search.launch_type" style="width: 240px;" clearable>
          <el-option
            v-for="(value,key) in launch_type_map"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">ID搜索：</span>
        <el-input v-model="search.search_id" placeholder="广告主ID/广告主名称" clearable
                  style="width: 240px"></el-input>
      </el-col>
    </el-row>
    <el-row>
      <el-col :span="12">
        <el-radio-group v-model="search.is_bind_app" @change="onSearch">
          <el-radio-button label="1">已关联应用</el-radio-button>
          <el-radio-button label="0">未关联应用</el-radio-button>
        </el-radio-group>
      </el-col>
      <el-col :span="12">
        <el-button @click="showTencentAuth" style="float: right;">腾讯营销 OAuth 授权</el-button>
        <el-button type="primary" icon="el-icon-search" @click="onSearch" style="float: right; margin-right: 10px;">搜索
        </el-button>
      </el-col>
    </el-row>

    <el-table
      v-loading="listLoading"
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
      :stripe="true"
    >
      <el-table-column align="center" label="ID" width="80">
        <template slot-scope="scope">
          {{ scope.row.advertiser_id }}
        </template>
      </el-table-column>
      <el-table-column label="所属应用" align="center" width="100">
        <template slot-scope="scope">
          {{ scope.row.app_name }}
        </template>
      </el-table-column>
      <el-table-column label="广告主" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.advertiser_name }}</div>
          <!-- <div><b>ID：{{ scope.row.advertiser_id }}</b></div> -->
        </template>
      </el-table-column>
      <el-table-column label="状态" align="center" width="80">
        <template slot-scope="scope">
          <el-tag :type="scope.row.status === 1?'success':'danger'">
            {{ status_map[scope.row.status] }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="投放方式" align="center" width="80">
        <template slot-scope="scope">
          {{ launch_type_map[scope.row.launch_type] }}
        </template>
      </el-table-column>
      <el-table-column label="激活定义" align="center">
        <template slot-scope="scope">
          {{ active_type_map[scope.row.active_type] }}
        </template>
      </el-table-column>
      <el-table-column label="关键行为定义" align="center">
        <template slot-scope="scope">
          {{ action_type_map[scope.row.action_type] }}
        </template>
      </el-table-column>
      <el-table-column label="扣量比例" align="center">
        <template slot-scope="scope">
          {{ scope.row.notify_discount }}%
        </template>
      </el-table-column>
      <el-table-column label="上次更新时间" align="center">
        <template slot-scope="scope">
          {{ scope.row.update_time }}
        </template>
      </el-table-column>
      <el-table-column label="创建时间" align="center">
        <template slot-scope="scope">
          {{ scope.row.create_time }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="操作" width="260">
        <template slot-scope="scope">
          <el-button type="success" size="mini"  @click="updateShow(scope.$index)">编辑</el-button>
          <el-button type="warning" size="mini" style="margin-right:10px" @click="updateStatus(scope.row.advertiser_id)">{{ status_map[(scope.row.status+1)%2] }}</el-button>
          <el-popconfirm
            :title="'确定绑定到此（'+app_info.app_name+'）应用吗？'"
            @confirm="bindApp(scope.row.advertiser_id)"
          >
            <el-button
              slot="reference"
              type="primary"
              size="mini"
              :disabled="search.is_bind_app == 1"
            >绑定
            </el-button>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>
    <pagination
      v-show="total > 0"
      :total="total"
      :page.sync="search.page"
      :limit.sync="search.page_size"
      @pagination="fetchData"
    />

    <el-dialog :visible.sync="tencentAuthVisible" title="腾讯营销 OAuth 授权" width="480px">
      <el-select v-model="tencentAppId" placeholder="请选择已配置的腾讯开放平台应用" style="width: 100%;">
        <el-option v-for="app in tencentApps" :key="app.app_id" :label="app.app_name + ' (' + app.app_id + ')'" :value="app.app_id" />
      </el-select>
      <p>此入口仅适用于 OAuth 授权。腾讯私有应用已有 Token 时，请使用服务器端安全导入命令，不要点击“前往授权”。</p>
      <p v-if="!tencentApps.length">暂无腾讯开放平台应用，请先完成应用配置。</p>
      <span slot="footer">
        <el-button @click="tencentAuthVisible = false">取消</el-button>
        <el-button type="primary" :disabled="!tencentAppId" :loading="tencentAuthLoading" @click="authorizeTencent">前往授权</el-button>
      </span>
    </el-dialog>

    <el-dialog :visible.sync="save_dialog_visible" :title="save_dialog_title" width="600px">
      <el-form label-position="left" label-width="150px" :model="save_data">
        <el-form-item label="广告主ID">
          <el-input v-model="save_data.advertiser_id" disabled style="width: 90%"></el-input>
        </el-form-item>
        <el-form-item label="广告主名称">
          <el-input v-model="save_data.advertiser_name" style="width: 90%"></el-input>
        </el-form-item>
        <el-form-item label="扣量百分比">
          <el-input-number v-model="save_data.notify_discount" :min="0" :max="100"></el-input-number>
        </el-form-item>
        <el-form-item label="被扣补量">
          <el-radio-group v-model="save_data.notify_fill">
            <el-radio
              v-for="(value,key) in notify_fill_map"
              :label="key"
              border
            >{{ value }}
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="投放方式">
          <el-radio-group v-model="save_data.launch_type">
            <el-radio
              v-for="(value,key) in launch_type_map"
              :label="key"
              border
            >{{ value }}
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="激活定义">
          <el-radio-group v-model="save_data.active_type">
            <el-radio
              v-for="(value,key) in active_type_map"
              :label="key"
              border
            >{{ value }}
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="激活-回收" v-show="save_data.active_type == 3">
          <el-radio-group v-model="save_data.active_value_type">
            <el-radio label="31">开屏ecpm</el-radio>
            <el-input v-model="save_data.active_value_ecpm" size="mini" :disabled="save_data.active_value_type !=31"
                      placeholder="ecpm值" style="width: 100px; margin: 0 10px"></el-input>
            <el-radio label="32">回收率</el-radio>
            <el-input v-model="save_data.active_value_rate" size="mini" :disabled="save_data.active_value_type !=32"
                      placeholder="回收率（%）" style="width: 100px; margin: 0 10px"></el-input>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="激活-自定义" v-show="save_data.active_type == 4">
          <el-input
            type="textarea"
            :rows="10"
            placeholder="请输入内容"
            v-model="save_data.active_value">
          </el-input>
        </el-form-item>

        <el-form-item label="次留定义">
          <el-radio-group v-model="save_data.keep_type">
            <el-radio
              v-for="(value,key) in keep_type_map"
              :label="key"
              border
            >{{ value }}
            </el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="关键行为定义">
          <el-radio-group v-model="save_data.action_type">
            <el-radio
              v-for="(value,key) in action_type_map"
              :label="key"
              border
            >{{ value }}
            </el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="关键行为-回收率" v-show="save_data.action_type == 1">
          <el-input v-model="save_data.action_revenue" placeholder="回收率（%）" style="width: 100px;"></el-input>
        </el-form-item>

        <!-- <el-form-item label="关键行为-回收金额" v-show="save_data.action_type == 2">
          <el-input v-model="save_data.action_revenue" placeholder="回收金额" style="width: 100px;"></el-input>
        </el-form-item> -->

        <el-form-item label="关键行为-自定义" v-show="save_data.action_type == 3">
          <el-input
            type="textarea"
            :rows="10"
            placeholder="请输入内容"
            v-model="save_data.action_value">
          </el-input>
        </el-form-item>
        <el-form-item label="扩展配置">
          <el-input
            type="textarea"
            :rows="10"
            placeholder="请输入内容"
            v-model="save_data.extra">
          </el-input>
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer" style="margin-right: 8%">
        <el-button @click="save_dialog_visible = false">取 消</el-button>
        <el-button type="primary" @click="updateSave" :loading="save_loading">确 定</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>

import {getCurrentApp} from "@/utils/auth"
import Pagination from '@/components/Pagination'
import JsonEditor from '@/components/JsonEditor'
import {Message} from "element-ui"
import {getList, bindApp, saveData,updateStatus, tencentApplications, tencentAuthorize, tencentComplete} from "@/api/put/advertiser"

export default {
  components: {Pagination, JsonEditor},
  data() {
    return {
      app_info: getCurrentApp(),
      search: {
        app_code: getCurrentApp().app_code,
        launch_type: '',
        search_id: '',
        is_bind_app: 1,
        page: 1,
        page_size: 10
      },
      save_data: {},
      total: 0,
      list: null,
      listLoading: false,
      save_loading: false,
      tencentAuthVisible: false,
      tencentAuthLoading: false,
      tencentApps: [],
      tencentAppId: '',
      source_map: _.clone(this.$global.SOURCE_MAP),
      launch_type_map: _.clone(this.$global.LAUNCH_TYPE_MAP),
      status_map: {
        '0': '停用',
        '1': '启用'
      },
      active_type_map: {
        '1': '开屏',
        '2': '归因',
        // '3': '回收',
        '4': '自定义'
      },
      keep_type_map: {
        '0': '自然日',
        '1': '24小时'
      },
      notify_fill_map: {
        '0': '不触发',
        '1': '次留'
      },
      action_type_map: {
        '0': '不触发',
        '2': '加桌',
        '1': '回收率',
        '3': '自定义'
      },
      save_dialog_visible: false,
      save_dialog_title: ''
    }
  },
  created() {
    this.onSearch()
  },
  methods: {
    async showTencentAuth() {
      const response = await tencentApplications()
      this.tencentApps = response.data.records || []
      this.tencentAppId = this.tencentApps.length === 1 ? this.tencentApps[0].app_id : ''
      this.tencentAuthVisible = true
    },
    async authorizeTencent() {
      // 同步创建标签页以避免异步请求结束后被浏览器当成弹窗拦截。
      const authWindow = window.open('about:blank', '_blank')
      if (authWindow) authWindow.opener = null
      this.tencentAuthLoading = true
      try {
        const response = await tencentAuthorize(this.tencentAppId)
        const state = new URL(response.data.url).searchParams.get('state')
        if (!state || !/^[a-f0-9]{64}$/.test(state)) throw new Error('腾讯授权 state 无效')
        if (authWindow) {
          authWindow.location.href = response.data.url
        } else {
          window.location.assign(response.data.url)
          return
        }
        // 只由当前已登录后台发起确认，腾讯公开回调不直接写入授权关系。
        let completed = false
        for (let attempt = 0; attempt < 300; attempt++) {
          await new Promise(resolve => setTimeout(resolve, 2000))
          const result = await tencentComplete(state)
          if (result.data.completed) {
            completed = true
            break
          }
        }
        if (!completed) throw new Error('腾讯授权等待超时，请重新授权')
        this.tencentAuthVisible = false
        Message({ message: '腾讯营销授权成功', type: 'success' })
        this.fetchData()
      } catch (error) {
        if (authWindow && !authWindow.closed) authWindow.close()
        Message({ message: error.message || '腾讯营销授权确认失败', type: 'error' })
      } finally {
        this.tencentAuthLoading = false
      }
    },
    onSearch() {
      if (!this.search.app_code) {
        Message({
          message: '请选择应用',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      this.search.page = 1
      this.fetchData()
    },
    fetchData() {
      this.listLoading = true
      getList(this.search).then(response => {
        this.list = response.data.records
        this.total = response.data.total
        this.listLoading = false
      }).catch(() => {
        this.listLoading = false
      })
    },
    updateShow(index) {
      this.save_data = JSON.parse(JSON.stringify(this.list[index]))

      this.save_data.launch_type = String(this.save_data.launch_type)
      this.save_data.active_type = String(this.save_data.active_type)
      this.save_data.keep_type = String(this.save_data.keep_type)
      this.save_data.notify_fill = String(this.save_data.notify_fill)
      this.save_data.action_type = String(this.save_data.action_type)
      this.save_data.action_revenue = parseFloat(this.save_data.action_revenue)
      this.save_data.action_value = this.save_data.action_value ? JSON.stringify(JSON.parse(this.save_data.action_value), null, 2) : ''
      this.save_data.active_value = this.save_data.active_value ? JSON.stringify(JSON.parse(this.save_data.active_value), null, 2) : ''
      this.save_data.extra = this.save_data.extra ? JSON.stringify(JSON.parse(this.save_data.extra), null, 2) : ''
      this.save_dialog_visible = true
      this.save_dialog_title = '修改'
    },
    updateSave() {
      this.save_loading = true

      saveData(this.save_data).then(response => {
        Message({
          message: '修改成功',
          type: 'success',
          duration: 5 * 1000
        })
        this.save_dialog_visible = false
        this.save_loading = false
        this.save_dialog_title = ''
        this.onSearch()
      }).catch(() => {
        this.save_loading = false
      })
    },
    bindApp(advertiser_id) {
      bindApp({ advertiser_id: advertiser_id, app_code: this.search.app_code }).then(response => {
        Message({
          message: '绑定成功',
          type: 'success',
          duration: 5 * 1000
        })
        this.onSearch()
      })
    },
    updateStatus(advertiser_id) {
      updateStatus({ advertiser_id: advertiser_id, app_code: this.search.app_code }).then(response => {
        this.fetchData()
      })
    },
    confirm() {
      this.visible = false
      this.$emit('onConfirm')
    }


  }
}
</script>
<style>
.el-row {
  margin-bottom: 10px;
}

.el-radio {
  margin: 0
}

.el-col-span {
  display: inline-block;
  width: 80px;
  padding: 6px 0;
  font-size: 14px;
}

.excel-upload-input {
  display: none;
  z-index: -9999;
}
</style>
