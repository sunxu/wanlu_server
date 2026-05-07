<template>
  <div class="app-container">
    <el-row>
      <el-col :span="8">
        <span class="el-col-span">投放媒体：</span>
        <el-select v-model="search.source" style="width: 240px;">
          <el-option label="全部" value=""></el-option>
          <el-option
            v-for="(value,key) in source_map"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">投放方式：</span>
        <el-select v-model="search.launch_type" style="width: 240px;">
          <el-option label="全部" value=""></el-option>
          <el-option
            v-for="(value,key) in launch_type_map"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">广告平台：</span>
        <el-select v-model="search.ad_platform" style="width: 240px;">
          <el-option label="全部" value=""></el-option>
          <el-option
            v-for="(value,key) in ad_platform_map"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
    </el-row>
    <el-row>
      <el-col :span="8">
        <span class="el-col-span">ID搜索：</span>
        <el-input v-model="search.search_id" placeholder="Topon/GroMore/代码位ID" clearable
                  style="width: 240px"></el-input>
      </el-col>
    </el-row>
    <el-row>
      <el-col :span="24">
        <el-button type="primary" icon="el-icon-download" @click="onExport" :loading="export_loading" style="float: right">
          导出
        </el-button>
        <el-popover
          placement="top-start"
          trigger="hover"
          width="180"
          style="margin-right: 10px"
        >
          <el-link href="/template/保价代码位示例.csv" style="margin-left: 55px">下载示例</el-link>
          <input ref="excel-upload-input" class="excel-upload-input" type="file" accept=".csv"
                 @change="handleClick">
          <el-button type="primary" icon="el-icon-upload" style="float: right; margin-right: 10px;" slot="reference"
                     @click="handleUpload">
            导入(.csv)
          </el-button>
        </el-popover>
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
      <el-table-column align="center" label="ID" width="50">
        <template slot-scope="scope">
          {{ scope.row.id }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="层级" width="50">
        <template slot-scope="scope">
          {{ scope.row.level }}
        </template>
      </el-table-column>
      <el-table-column label="应用名称" align="center" width="80">
        <template slot-scope="scope">
          {{ scope.row.app_name }}
        </template>
      </el-table-column>
      <el-table-column label="投放媒体" align="center" width="80">
        <template slot-scope="scope">
          {{ scope.row.source_name }}
        </template>
      </el-table-column>
      <el-table-column label="广告平台" align="center" width="80">
        <template slot-scope="scope">
          {{ scope.row.ad_platform_name }}
        </template>
      </el-table-column>
      <el-table-column label="投放方式" align="center" width="80">
        <template slot-scope="scope">
          {{ scope.row.launch_type_name }}
        </template>
      </el-table-column>
      <el-table-column label="广告场景" align="center">
        <template slot-scope="scope">
          {{ scope.row.ad_scene }}
        </template>
      </el-table-column>
      <el-table-column label="代码位类型" align="center" width="90">
        <template slot-scope="scope">
          {{ scope.row.placement_type_name }}
        </template>
      </el-table-column>
      <el-table-column label="代码位名称" align="center">
        <template slot-scope="scope">
          {{ scope.row.placement_name }}
        </template>
      </el-table-column>
      <el-table-column label="聚合ID" align="center">
        <template slot-scope="scope">
          {{ scope.row.topon_id }}
        </template>
      </el-table-column>
      <el-table-column label="代码位ID" align="center">
        <template slot-scope="scope">
          {{ scope.row.placement_id }}
        </template>
      </el-table-column>
      <el-table-column label="保价ecpm" align="center">
        <template slot-scope="scope">
          {{ scope.row.ecpm }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="操作" width="150">
        <template slot-scope="scope">
          <el-button type="success" size="mini" style="margin-right: 10px" @click="updateShow(scope.$index)">编辑
          </el-button>
          <el-popconfirm
            title="确定删除吗？"
            @onConfirm="deleteRow(scope.row.id)"
          >
            <el-button slot="reference" type="danger" size="mini" :disabled="scope.row.delete_disabled">删除</el-button>
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

    <el-dialog :visible.sync="save_dialog_visible" :title="save_dialog_title" width="500px">
      <el-form label-position="left" label-width="90px" :model="save_data">
        <el-form-item label="投放媒体">
          <el-select v-model="save_data.source" style="width: 90%">
            <el-option
              v-for="(value,key) in source_map"
              :label="value"
              :value="key"
            ></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="投放方式">
          <el-select v-model="save_data.launch_type" style="width: 90%">
            <el-option
              v-for="(value,key) in launch_type_map"
              :label="value"
              :value="key"
            ></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="广告平台">
          <el-select v-model="save_data.ad_platform" style="width: 90%">
            <el-option
              v-for="(value,key) in ad_platform_map"
              :label="value"
              :value="key"
            ></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="广告场景">
          <el-input v-model="save_data.ad_scene" style="width: 90%"></el-input>
        </el-form-item>
        <el-form-item label="代码位类型">
          <el-select v-model="save_data.placement_type" style="width: 90%">
            <el-option
              v-for="(value,key) in placement_type_map"
              :label="value"
              :value="key"
            ></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="代码位名称">
          <el-input v-model="save_data.placement_name" style="width: 90%"></el-input>
        </el-form-item>
        <el-form-item label="代码位ID">
          <el-input v-model="save_data.placement_id" style="width: 90%"></el-input>
        </el-form-item>
        <el-form-item label="聚合ID">
          <el-input v-model="save_data.topon_id" style="width: 90%"></el-input>
        </el-form-item>
        <el-form-item label="保价ecpm">
          <el-input v-model="save_data.ecpm" style="width: 90%"></el-input>
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer" style="margin-right: 8%">
        <el-button @click="save_dialog_visible = false">取 消</el-button>
        <el-button type="primary" @click="updateSave" :loading="save_loading">确 定</el-button>
      </div>
    </el-dialog>

    <el-dialog :visible.sync="upload_dialog_visible" title="导入预览" width="80%">
      <el-table
        :data="upload_data"
        border
        fit
        height="400px"
        highlight-current-row
        :stripe="true"
      >
        <el-table-column align="center" label="序号" width="50">
          <template slot-scope="scope">
            {{ scope.$index + 1 }}
          </template>
        </el-table-column>
        <el-table-column align="center" label="应用名称" width="100">
            {{ app_info.app_name }}
        </el-table-column>
        <el-table-column align="center" label="投放媒体" width="80">
          <template slot-scope="scope">
            {{ scope.row.source }}
          </template>
        </el-table-column>
        <el-table-column align="center" label="投放方式" width="80">
          <template slot-scope="scope">
            {{ scope.row.launch_type }}
          </template>
        </el-table-column>
        <el-table-column align="center" label="广告平台" width="80">
          <template slot-scope="scope">
            {{ scope.row.ad_platform }}
          </template>
        </el-table-column>
        <el-table-column align="center" label="广告场景">
          <template slot-scope="scope">
            {{ scope.row.ad_scene }}
          </template>
        </el-table-column>
        <el-table-column align="center" label="代码位类型" width="90">
          <template slot-scope="scope">
            {{ scope.row.placement_type }}
          </template>
        </el-table-column>
        <el-table-column align="center" label="代码位名称">
          <template slot-scope="scope">
            {{ scope.row.placement_name }}
          </template>
        </el-table-column>
        <el-table-column align="center" label="聚合ID">
          <template slot-scope="scope">
            {{ scope.row.topon_id }}
          </template>
        </el-table-column>
        <el-table-column align="center" label="代码位ID">
          <template slot-scope="scope">
            {{ scope.row.placement_id }}
          </template>
        </el-table-column>
        <el-table-column align="center" label="保价ecpm" width="80">
          <template slot-scope="scope">
            {{ scope.row.ecpm }}
          </template>
        </el-table-column>
      </el-table>
      <div slot="footer" class="dialog-footer">
        <el-button type="primary" @click="onImport" :loading="import_loading">确认导入</el-button>
      </div>
    </el-dialog>
  </div>
</template>
<style>

</style>
<script>

import {getCurrentApp} from "@/utils/auth"
import Pagination from '@/components/Pagination'
import {Message} from "element-ui"
import {getList, deleteData, saveData, exportData, upload, importData} from "@/api/ad/placement-map";
import {parseTime} from "@/utils";

export default {
  components: {Pagination},
  data() {
    return {
      app_info: getCurrentApp(),
      search: {
        source: '',
        app_code: getCurrentApp().app_code,
        launch_type: '',
        ad_platform: '',
        search_id: '',
        page: 1,
        page_size: 10
      },
      save_data: {},
      upload_data: [],
      total: 0,
      list: null,
      listLoading: false,
      save_loading: false,
      export_loading: false,
      import_loading: false,
      source_map: _.clone(this.$global.SOURCE_MAP),
      launch_type_map: _.clone(this.$global.LAUNCH_TYPE_MAP),
      ad_platform_map: _.clone(this.$global.AD_PLATFORM_MAP),
      placement_type_map: _.clone(this.$global.AD_TYPE_MAP),
      save_dialog_visible: false,
      save_dialog_title: '',
      upload_dialog_visible: false
    }
  },
  created() {
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
    onExport() {
      this.export_loading = true
      exportData(this.search).then(response => {
        const list = response.data.list
        list.map(row => {
          row.placement_id = 'id:' + row.placement_id
          // row.source = this.source_map[row.source]
          // row.ad_platform = this.ad_platform_map[row.ad_platform]
          // row.launch_type = this.launch_type_map[row.launch_type]
          // row.placement_type = this.placement_type_map[row.placement_type]
        })
        this.doExport(list)
      }).catch(() => {
        this.export_loading = false
      })
    },
    doExport(list) {
      import('@/utils/Export2Excel').then(excel => {
        const tHeader = ['层级', '应用名称', '投放媒体', '广告平台', '投放方式', '广告场景', '代码位类型', '代码位名称', '聚合ID', '代码位ID', '保价ecpm']
        const filterVal = ['level', 'app_name', 'source_name', 'ad_platform_name', 'launch_type_name', 'ad_scene', 'placement_type_name', 'placement_name', 'topon_id', 'placement_id', 'ecpm']
        const data = this.formatJson(filterVal, list)
        const filename = this.app_info.app_name + '_代码位'
        excel.export_json_to_excel({
          header: tHeader, //表头 必填
          data, //具体数据 必填
          filename: filename, //非必填
          autoWidth: true, //非必填
          bookType: 'csv' //非必填
        })
        this.export_loading = false
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
      const formData = new FormData()
      formData.append('file', rawFile)
      upload(formData).then(response => {
        this.upload_data = []
        response.data.list.map(item => {
          this.upload_data.push(
            {
              source: item[0],
              launch_type: item[1] == '关键行为' ? '关键行为' : '次留双出',
              ad_platform: item[2],
              ad_scene: item[3],
              placement_type: item[4],
              placement_name: item[5],
              topon_id: item[6],
              placement_id: item[7].replace('id:', '').replace('id：', ''),
              // placement_id: item[7].replace(/[^0-9]/ig, ''),
              ecpm: item[8]
            }
          )
        })
        this.upload_dialog_visible = true
      })
    },
    onImport() {
      if (!this.upload_data.length) {
        Message({
          message: '没有要导入的数据',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      this.import_loading = true
      importData({
        app_code: this.app_info.app_code,
        data_list: this.upload_data
      }).then(response => {
        Message({
          message: '数据导入完成【成功：' + response.data.success + '个，失败：' + response.data.fail + '个】',
          type: 'success',
          duration: 5 * 1000
        })
        this.import_loading = false
        this.upload_dialog_visible = false
        this.upload_data = []
        this.onSearch()
      }).catch(() => {
        this.import_loading = false
      })
    },
    confirm() {
      this.visible = false
      this.$emit('onConfirm')
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
    updateShow(index) {
      this.save_data = JSON.parse(JSON.stringify(this.list[index]))
      this.save_data.launch_type = String(this.save_data.launch_type)
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
