<template>
  <div class="app-container">
    <el-row>
      <el-col :span="8">
        <span class="el-col-span">状态：</span>
        <el-select v-model="search.status" clearable style="width: 240px;">
          <el-option
            v-for="(value,key) in status_map"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
      <el-col :span="16">
        <el-button type="success" icon="el-icon-document-add" @click="saveShow" style="float: right;">添加
        </el-button>
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
          {{ scope.row.id }}
        </template>
      </el-table-column>
      <el-table-column label="信息" align="center">
        <template slot-scope="scope">
          <div><b>{{ task_type_map[scope.row.info.type] }}</b></div>
          <div>{{ scope.row.info.time_start}}～{{ scope.row.info.time_end}}</div>
        </template>
      </el-table-column>
      <el-table-column label="状态" align="center" width="80">
        <template slot-scope="scope">
          <el-tag :type="scope.row.status == 1 ?'success':(scope.row.status == 2 ? 'danger' : '')">
            {{ status_map[scope.row.status] }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="创建时间" align="center">
        <template slot-scope="scope">
          {{scope.row.create_time}}
        </template>
      </el-table-column>
      <el-table-column align="center" label="操作" width="260">
        <template slot-scope="scope">
          <el-button type="success" size="mini" icon="el-icon-download"  @click="detail(scope.row.id)" :disabled="(scope.row.status !=1)">导出</el-button>
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

    <el-dialog :visible.sync="save_dialog_visible" title="报表" width="500px">
      <el-form label-position="left" label-width="50px" :model="save_data">
        <el-form-item label="时间">
          <el-date-picker
            v-model="save_data.date_time"
            type="datetimerange"
            align="right"
            unlink-panels
            range-separator="~"
            start-placeholder="开始时间"
            end-placeholder="结束时间"
            style="width: 380px;">
          </el-date-picker>
        </el-form-item>
        <el-form-item label="类型">
          <el-radio-group v-model="save_data.type">
            <el-radio
              v-for="(value,key) in task_type_map"
              :label="key"
              border
            >{{ value }}
            </el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer" >
        <el-button @click="save_dialog_visible = false">取 消</el-button>
        <el-button type="primary" @click="saveData" :loading="save_loading">确 定</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>

import Pagination from '@/components/Pagination'
import {Message} from "element-ui"
import {parseTime} from '@/utils'
import {getDetail, saveData,getTask} from "@/api/product/task"
import { getAppCode } from '@/utils/auth'

export default {
  components: {Pagination},
  data() {
    return {
      app_code:getAppCode(),
      search: {
        status:null,
        page: 1,
        page_size: 10
      },
      save_data: {},
      total: 0,
      list: null,
      listLoading: false,
      save_loading: false,
      task_type_map: {
        'ad': '广告',
        'dialog': '外弹',
        'strategy': '策略'
      },
      status_map: {
        '0': '进行中',
        '1': '已完成',
        '2': '已失败'
      },
      save_dialog_visible: false,
      save_dialog_title: '',
    }
  },
  created() {
    this.onSearch()
  },
  
  methods: {
    onSearch() {
      if (!this.app_code) {
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
      getTask(this.app_code,this.search).then(response => {
        this.list = response.data.records
        this.total = response.data.total
        this.listLoading = false
      }).catch(() => {
        this.listLoading = false
      })
    },
    saveShow() {
      this.save_dialog_visible = true
    },
    saveData() {
      if (!this.save_data.type || !this.save_data.date_time) {
        Message({
          message: !this.save_data.date_time ? '请选择时间':'请选择类型',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      
      this.save_loading = true
      saveData(this.app_code,{
        'type':this.save_data.type,
        'time_start':parseTime(this.save_data.date_time[0], '{y}-{m}-{d} {h}:{i}:{s}'),
        'time_end':parseTime(this.save_data.date_time[1], '{y}-{m}-{d} {h}:{i}:{s}'),
      }).then(response => {
        Message({
          message: '提交成功',
          type: 'success',
          duration: 5 * 1000
        })
        this.save_dialog_visible = false
        this.save_loading = false

        this.search.status = null
        this.onSearch()
      }).catch(() => {
        this.save_loading = false
      })
    },
    detail(id){
      getDetail(this.app_code,{'id':id}).then(response=>{
        this.onExport(response.data)
      }).catch(() => {
        
      })
    },
    onExport(info) {
      if (!info.header || !info.lists) {
        Message({
          message: '暂无数据',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      import('@/utils/Export2Excel').then(excel => {
       
        const tHeader = []
        const filterVal = []
        for (const key in info.header) {
          tHeader.push(info.header[key])
          filterVal.push(key)
        }
        const list = info.lists
        const filename = parseTime(new Date(),'{y}{m}{d}{h}{i}{s}') 
        const data = list.map(v => filterVal.map(j => {
            return v[j]
          }))
        if (data.length <= 0) {
          Message({
            showClose: true,
            message: '没有导出的数据',
            type: 'warning',
            duration: 5 * 1000
          })
          return
        }
        excel.export_json_to_excel({
            header: tHeader,
            data,
            filename: filename
          })
      })
    },
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
