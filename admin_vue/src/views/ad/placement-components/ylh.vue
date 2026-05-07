<template>
  <div class="app-container">
    <el-row>
      <el-col :span="8">
        <span class="el-col-span">账号主体：</span>
        <el-select v-model="account_id" placeholder="请选择账号主体" style="width: 240px;" @change="fetchApp">
          <el-option
            v-for="row in account_list"
            :label="row.subject"
            :value="row.id"
          ></el-option>
        </el-select>
      </el-col>
      <el-col :span="16">
        <span class="el-col-span">应用列表：</span>
        <el-select v-model="app_id" placeholder="请选择应用" style="width: 240px;" :disabled="app_list_loading">
          <el-option
            v-for="row in app_list"
            :label="row.app_name"
            :value="row.app_id"
          ></el-option>
        </el-select>
        <el-tooltip class="item" effect="dark" content="若应用未在此下拉列表中可尝试点此刷新" placement="top-start">
          <el-button @click="queryApp" :loading="app_list_loading">刷新应用</el-button>
        </el-tooltip>
      </el-col>
    </el-row>
    <el-row>
      <el-col :span="24">
        <el-popover
          placement="top-start"
          trigger="hover"
          width="180"
          style="margin-right: 10px"
        >
          <el-link href="/template/ad.csv" style="margin-left: 55px">下载示例</el-link>
          <input ref="excel-upload-input" class="excel-upload-input" type="file" accept=".csv"
                 @change="handleClick">
          <el-button type="info" icon="el-icon-upload" style="width: 180px" slot="reference" @click="handleUpload"
                     :loading="upload_loading" :disabled="upload_disabled">
            导入代码位(.csv格式)
          </el-button>
        </el-popover>
        <el-button type="primary" icon="el-icon-s-open" style="width: 180px" :loading="create_loading" @click="create">
          批量创建代码位
        </el-button>
        <el-button type="success" icon="el-icon-download" style="width: 180px" :loading="export_loading"
                   @click="onExport">导出已创建代码位
        </el-button>
      </el-col>
    </el-row>

    <el-table
      :data="placement_list"
      max-height="482"
      border
      fit
      highlight-current-row
      :stripe="true"
      style="width: 100%"
    >
      <el-table-column align="center" label="序号" width="50">
        <template slot-scope="scope">
          {{ scope.$index }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="代码位名称">
        <template slot-scope="scope">
          {{ scope.row.name }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="代码位类型">
        <template slot-scope="scope">
          {{ scope.row.type_name }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="代码位价格">
        <template slot-scope="scope">
          {{ scope.row.price }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="代码位ID">
        <template slot-scope="scope">
          {{ scope.row.placement_id }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="状态">
        <template slot-scope="scope">
          {{ create_status_map[scope.row.create_status] }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="操作">
        <template slot-scope="scope">
          <el-popconfirm
            title="确定删除吗？"
            @onConfirm="deleteRow(scope.$index)"
          >
            <el-button slot="reference" type="text" :disabled="scope.row.delete_disabled">删除</el-button>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<style>
.el-row {
  margin-bottom: 10px;
}

.el-col-span {
  display: inline-block;
  width: 80px;
  padding: 6px 0;
  font-size: 14px;
}
</style>
<script>

import {getAccount, getApp, queryApp, createPlacement, upload} from '@/api/ad/placement'
import {uuid} from '@/utils/uuid'
import {Message} from "element-ui"
import {parseTime} from "@/utils";

export default {
  data() {
    return {
      platform: 'ylh',
      baseUrl: process.env.VUE_APP_BASE_API,
      ad_type: _.invert(this.$global.AD_TYPE_MAP),
      create_status_map: {
        '0': '未创建',
        '1': '等待创建',
        '2': '创建中',
        '3': '创建成功',
        '4': '创建失败'
      },
      account_list: [],
      app_list: [],
      account_id: '',
      app_id: '',
      created_key: uuid(),
      placement_list: [],
      create_placement_list: [],
      app_list_loading: false,
      upload_loading: false,
      upload_disabled: false,
      create_loading: false,
      export_loading: false
    }
  },
  created() {
    this.fetchAccount()
  },
  methods: {
    fetchAccount() {
      getAccount({platform: this.platform}).then(response => {
        this.account_list = response.data.account_list
        this.account_id = this.account_list[0]['id']
        this.fetchApp()
      })
    },
    fetchApp() {
      if (!this.account_id) return
      getApp({account_id: this.account_id}).then(response => {
        this.app_list = response.data.app_list
        this.app_id = this.app_list[0]['app_id']
      })
    },
    queryApp() {
      if (!this.account_id) {
        Message({
          message: '未选择主体！',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      this.app_list_loading = true
      queryApp({account_id: this.account_id}).then(response => {
        this.app_list = response.data.app_list
        this.app_id = this.app_list[0]['app_id']
        this.app_list_loading = false
      }).catch(() => {
        this.app_list_loading = false
      })
    },
    deleteRow(index) {
      this.placement_list.splice(index, 1)
    },
    create() {
      if (!this.placement_list.length) {
        Message({
          message: '未添加代码位信息！',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      this.create_loading = true
      this.upload_disabled = true
      this.placement_list.map(row => {
        if (row.create_status != 3) {
          row.create_status = 1
          row.delete_disabled = true
        }
      })
      this.doCreate(0)
    },
    doCreate(index) {
      if (index >= this.placement_list.length) {
        this.create_loading = false
        this.upload_disabled = false
        return
      }
      var placement_data = this.placement_list[index]
      if (placement_data.create_status == 3) {
        index++
        this.doCreate(index)
        return
      }
      placement_data.create_status = 2
      placement_data.type = this.ad_type[placement_data.type_name]
      placement_data.index = index
      const body = {
        account_id: this.account_id,
        app_id: this.app_id,
        create_key: this.created_key,
        placement_data: placement_data
      }
      createPlacement(body).then(response => {
        placement_data.placement_id = response.data.placement_id
        placement_data.create_status = 3
        if (response.data.set_cpm_result == 'fail') {
          placement_data.price += '【设置失败】'
        }
        index++
        this.doCreate(index)
      }).catch(error => {
        placement_data.placement_id = '【' + error + '】'
        placement_data.create_status = 4
        placement_data.delete_disabled = false
        index++
        this.doCreate(index)
      })
    },
    handleUpload() {
      this.$refs['excel-upload-input'].click()
    },
    handleClick(e) {
      const files = e.target.files
      const rawFile = files[0] // only use files[0]
      if (!rawFile) return
      this.upload(rawFile)
    },
    upload(rawFile) {
      this.$refs['excel-upload-input'].value = null // fix can't select the same excel
      this.upload_loading = true
      const formData = new FormData()
      formData.append('file', rawFile)
      upload(formData).then(response => {
        this.placement_list = []
        response.data.list.map(item => {
          this.placement_list.push(
            {
              name: item[0],
              type_name: item[1],
              price: item[2],
              placement_id: '',
              create_status: 0,
              delete_disabled: false
            }
          )
        })
        this.upload_loading = false
      }).catch(() => {
        this.upload_loading = false
      })
    },
    confirm() {
      this.visible = false
      this.$emit('onConfirm')
    },
    onExport() {
      var list = []
      var subject
      var app_name
      this.account_list.map(row => {
        if (row.id == this.account_id) {
          subject = row.subject
        }
      })
      this.app_list.map(row => {
        if (row.app_id == this.app_id) {
          app_name = row.app_name
        }
      })

      this.placement_list.map(row => {
        if (row.create_status == 3) {
          var data = row
          data._placement_id = 'id:' + data.placement_id
          data.subject = subject
          data.platform_name = this.platform
          data.app_name = app_name
          data.app_id = this.app_id
          list.push(data)
        }
      })
      if (!list) {
        Message({
          message: '暂无数据',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      import('@/utils/Export2Excel').then(excel => {
        const tHeader = ['公司主体', '广告平台', '应用名称', '应用ID', '代码位类型', '代码位名称', '代码位价格', '代码位ID']
        const filterVal = ['subject', 'platform_name', 'app_name', 'app_id', 'type_name', 'name', 'price', '_placement_id']
        const data = this.formatJson(filterVal, list)
        const filename = this.platform + '_' + subject + '_' + app_name + '_代码位'
        excel.export_json_to_excel({
          header: tHeader, //表头 必填
          data, //具体数据 必填
          filename: filename, //非必填
          autoWidth: true, //非必填
          bookType: 'csv' //非必填
        })
      })
    },
    formatJson(filterVal, jsonData) {
      return jsonData.map(v => filterVal.map(j => {
        if (j === 'timestamp') {
          return parseTime(v[j])
        } else {
          return v[j]
        }
      }))
    }
  }
}
</script>
<style scoped>
.excel-upload-input {
  display: none;
  z-index: -9999;
}
</style>
