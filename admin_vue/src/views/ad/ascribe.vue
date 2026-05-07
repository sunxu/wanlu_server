<template>
  <div class="app-container">
    <el-row>
      <el-col :span="24">
        <span class="el-col-span">ID搜索：</span>
        <el-input v-model="search.user_id" placeholder="用户ID/安卓ID/OAID" clearable
                  style="width: 240px"></el-input>
        <el-button type="primary" icon="el-icon-search" @click="onSearch" style="margin-left: 10px;">
          搜索
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
      <el-table-column label="用户/设备ID" align="center" >
        <template slot-scope="scope">
          {{ scope.row.user_id }}
        </template>
      </el-table-column>
      <el-table-column label="OAID" align="center" >
        <template slot-scope="scope">
          {{ scope.row.oaid }}
        </template>
      </el-table-column>
      <el-table-column label="设备厂商" align="center">
        <template slot-scope="scope">
          {{ scope.row.vendor }}
        </template>
      </el-table-column>
      <el-table-column label="安装时间" align="center">
        <template slot-scope="scope">
          {{ scope.row.created_time }}
        </template>
      </el-table-column>
      <el-table-column label="安装版本" align="center">
        <template slot-scope="scope">
          {{ scope.row.app_version }}
        </template>
      </el-table-column>
      <el-table-column label="安装渠道" align="center">
        <template slot-scope="scope">
          {{ $global.CHANNEL_MAP[scope.row.channel_id] }}
        </template>
      </el-table-column>
      <el-table-column label="异常设备" align="center">
        <template slot-scope="scope">
          {{ except_map[scope.row.is_except] }}
        </template>
      </el-table-column>
      <el-table-column label="投放渠道" align="center">
        <template slot-scope="scope">
          {{ scope.row.source_name }}
        </template>
      </el-table-column>
      <el-table-column label="投放方式" align="center">
        <template slot-scope="scope">
          {{ scope.row.launch_type_name }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="操作" width="150">
        <template slot-scope="scope">
          <el-button type="success" size="mini" style="margin-right: 10px" @click="updateShow(scope.$index)">编辑
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog :visible.sync="save_dialog_visible" :title="save_dialog_title" width="800px">
      <el-form label-position="left" label-width="80px" :model="save_data">
        <el-form-item label="投放渠道">
          <el-radio-group v-model="save_data.source">
            <el-radio label="">未归因</el-radio>
            <el-radio
              v-for="(value,key) in source_map"
              :label="key"
            >{{ value }}
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="投放方式"  v-show="save_data.source != ''">
          <el-radio-group v-model="save_data.launch_type">
            <el-radio
              v-for="(value,key) in launch_type_map"
              :label="key"
            >{{ value }}
            </el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer" style="margin-right: 8%">
        <el-button @click="save_dialog_visible = false">取 消</el-button>
        <el-button type="primary" @click="Save" :loading="save_loading">保 存</el-button>
      </div>
    </el-dialog>
  </div>


</template>

<script>

import {getCurrentApp} from "@/utils/auth"
import {Message} from "element-ui"
import {Query, Save} from "@/api/ad/ascribe"

export default {
  data() {
    return {
      app_info: getCurrentApp(),
      search: {
        app_code: getCurrentApp().app_code
      },
      save_data: {},
      list: null,
      listLoading: false,
      save_loading: false,
      source_map: _.clone(this.$global.SOURCE_MAP),
      launch_type_map: _.clone(this.$global.LAUNCH_TYPE_MAP),
      except_map: {
        0: "异常",
        1: "正常"
      },
      save_dialog_visible: false,
      save_dialog_title: ''
    }
  },
  created() {},
  methods: {
    onSearch() {
      if (!this.search.app_code) {
        Message({
          message: '请选择应用',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      this.fetchData()
    },
    fetchData() {
      this.listLoading = true
      Query(this.search).then(response => {
        this.list = _.isEmpty(response.data) ? [] : [response.data]
        this.list.map(row => {
          row.source_name = row.advertiser_id == '' ? '未归因' : this.$global.SOURCE_MAP[row.source]
          row.launch_type_name = row.advertiser_id == '' ? '未归因' : this.$global.LAUNCH_TYPE_MAP[row.launch_type]
        })
        this.listLoading = false
      }).catch(() => {
        this.listLoading = false
      })
    },
    updateShow(index) {
      this.save_data = JSON.parse(JSON.stringify(this.list[index]))
      this.save_data.launch_type = String(this.save_data.launch_type)
      this.save_dialog_visible = true
      this.save_dialog_title = '修改'
    },
    Save() {
      this.save_loading = true
      this.save_data.app_code = this.app_info.app_code
      Save(this.save_data).then(() => {
        Message({
          message: '保存成功',
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
    }
  }
}
</script>
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

.excel-upload-input {
  display: none;
  z-index: -9999;
}

.editor-container {
  position: relative;
  height: 100%;
}
</style>
