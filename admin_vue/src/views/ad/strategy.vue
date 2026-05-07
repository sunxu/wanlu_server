<template>
  <div class="app-container">
    <el-row>
      <el-col :span="24">
        <el-button type="success" icon="el-icon-document-add" @click="addShow"
                   style="float: right; margin-right: 10px;">
          添加
        </el-button>
        <el-button type="primary" icon="el-icon-refresh" @click="onSearch" style="float: right; margin-right: 10px;">
          刷新
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
          {{ scope.row.id }}
        </template>
      </el-table-column>
      <el-table-column label="策略名称" align="center">
        <template slot-scope="scope">
          {{ scope.row.name }}
        </template>
      </el-table-column>
      <el-table-column label="投放渠道" align="center">
        <template slot-scope="scope">
          {{ scope.row.source_name }}
        </template>
      </el-table-column>
      <el-table-column label="投放方式" align="center" width="80">
        <template slot-scope="scope">
          {{ scope.row.launch_type_name }}
        </template>
      </el-table-column>
      <el-table-column label="策略" align="center">
        <template slot-scope="scope">
          {{ scope.row.key_name }}
        </template>
      </el-table-column>
      <el-table-column label="渠道" align="center">
        <template slot-scope="scope">
          {{ scope.row.channel_name }}
        </template>
      </el-table-column>
      <el-table-column label="厂商" align="center">
        <template slot-scope="scope">
          {{ scope.row.vendor_name }}
        </template>
      </el-table-column>
      <el-table-column label="状态" align="center" width="80">
        <template slot-scope="scope">
          <el-tag :type="scope.row.status === 1?'success':'danger'">
            {{ status_map[scope.row.status] }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column align="center" label="操作" width="230">
        <template slot-scope="scope">
          <el-button type="success" size="mini" @click="updateShow(scope.$index)">编辑
          </el-button>
          <el-button type="warning" size="mini" style="margin-right:10px" @click="updateStatus(scope.row.id)">{{ status_map[(scope.row.status+1)%2] }}</el-button>
          <el-popconfirm
            title="确定删除吗？"
            @onConfirm="deleteRow(scope.row.id)"
          >
            <el-button slot="reference" type="danger" size="mini" :disabled="scope.row.delete_disabled">删除</el-button>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog :visible.sync="save_dialog_visible" :title="save_dialog_title" width="800px">
      <el-form label-position="left" label-width="80px" :model="save_data">
        <el-form-item label="投放渠道">
          <el-checkbox-group v-model="save_data.source">
            <el-checkbox
              v-for="(value,key) in source_map"
              :label="key"
            >{{ value }}
            </el-checkbox>
          </el-checkbox-group>
        </el-form-item>

        <el-form-item label="投放方式">
          <el-checkbox-group v-model="save_data.launch_type">
            <el-checkbox
              v-for="(value,key) in launch_type_map"
              :label="key"
            >{{ value }}
            </el-checkbox>
          </el-checkbox-group>
        </el-form-item>

        <el-form-item label="策略">
          <el-checkbox-group v-model="save_data.key">
            <el-checkbox
              v-for="(value,key) in key_map"
              :label="key"
            >{{ value }}
            </el-checkbox>
          </el-checkbox-group>
        </el-form-item>

        <el-form-item label="渠道">
          <el-checkbox-group v-model="save_data.channel">
            <el-checkbox
              v-for="(value,key) in channel_map"
              :label="key"
            >{{ value }}
            </el-checkbox>
          </el-checkbox-group>
        </el-form-item>

        <el-form-item label="厂商">
          <el-checkbox-group v-model="save_data.vendor">
            <el-checkbox
              v-for="(value,key) in vendor_map"
              :label="key"
            >{{ value }}
            </el-checkbox>
          </el-checkbox-group>
        </el-form-item>

        <el-form-item label="代码位">
          <el-input placeholder="" v-model="save_data.native" style="width: 45%">
            <template slot="prepend">信息流：</template>
          </el-input>
          <el-input placeholder="请输入" v-model="save_data.splash" style="width: 45%;margin-left:10px;">
            <template slot="prepend">开屏：</template>
          </el-input>
        </el-form-item>
          <el-form-item label="">
          <el-input placeholder="请输入" v-model="save_data.inter" style="width: 45%">
            <template slot="prepend">插屏：</template>
          </el-input>
          <el-input placeholder="请输入" v-model="save_data.full_video" style="width: 45%;margin-left:10px;">
            <template slot="prepend">全屏：</template>
          </el-input>
        </el-form-item>

        <el-form-item label="策略json">
        </el-form-item>
        <json-editor ref="textarea" v-model="save_data.info" style="height: 400px; overflow: scroll"/>

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
import {getList, saveData,deleteData,updateData} from "@/api/ad/strategy"
import JsonEditor from '@/components/JsonEditor'

export default {
  components: {JsonEditor},
  data() {
    return {
      app_info: getCurrentApp(),
      search: {
        app_code: getCurrentApp().app_code
      },
      status_map: {
        '0': '停用',
        '1': '启用'
      },
      save_data: {},
      list: null,
      listLoading: false,
      save_loading: false,
      source_map: _.extend(_.clone(this.$global.SOURCE_MAP), {ly: '联运'}),
      launch_type_map: _.clone(this.$global.LAUNCH_TYPE_MAP),
      vendor_map:  _.clone(this.$global.VENDOR_MAP),
      channel_map: _.clone(this.$global.CHANNEL_MAP),
      key_map: _.clone(this.$global.AD_STRATEGY_KEY_MAP),
      save_dialog_visible: false,
      save_dialog_title: ''
    }
  },
  created() {
    delete this.vendor_map.other
    this.onSearch()
  },
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
      getList(this.search).then(response => {
        this.list = response.data.list
        this.list.map(row => {
          row.channel_name = row.channel.map(o => {
            return this.channel_map[o]
          }).toString()
          row.key_name = row.key.map(o => {
            return this.key_map[o]
          }).toString()
          row.vendor_name = row.vendor.map(o => {
            return this.vendor_map[o]
          }).toString()
          row.launch_type_name = row.launch_type.map(o => {
            return this.launch_type_map[o]
          }).toString()
          row.source_name = row.source.map(o => {
            return this.source_map[o]
          }).toString()
        })
        this.listLoading = false
      }).catch(() => {
        this.listLoading = false
      })
    },
    deleteRow(id) {
      deleteData({id}).then(response => {
        Message({
          message: '删除成功',
          type: 'success',
          duration: 5 * 1000
        })
        this.onSearch()
      })
    },
    updateStatus(id) {
      updateData({id}).then(response => {
        Message({
          message: '更新成功',
          type: 'success',
          duration: 5 * 1000
        })
        this.onSearch()
      })
    },
    addShow() {
      this.save_data = {
        channel: [],
        key: [],
        vendor: [],
        source: [],
        launch_type: [],
        info: ''
      }
      this.save_dialog_visible = true
      this.save_dialog_title = '添加'
    },
    updateShow(index) {
      this.save_data = JSON.parse(JSON.stringify(this.list[index]))
      this.save_data.launch_type = this.save_data.launch_type.map(o => {
        return String(o)
      })
      this.save_data.channel = this.save_data.channel.map(o => {
        return String(o)
      })
      
      this.save_dialog_visible = true
      this.save_dialog_title = '修改'
    },
    Save() {
      this.save_loading = true
      this.save_data.app_code = this.app_info.app_code
      saveData(this.save_data).then(() => {
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
