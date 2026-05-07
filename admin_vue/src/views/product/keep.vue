<template>
  <div class="app-container">
    <el-row>
      <el-col :span="8">
        <span class="el-col-span">日期：</span>
        <el-date-picker
          v-model="search.date_picker"
          type="daterange"
          align="right"
          :clearable="false"
          unlink-panels
          range-separator="~"
          value-format="yyyy-MM-dd"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          style="width: 240px;"
          :picker-options="pickerOptions">
        </el-date-picker>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">投放渠道：</span>
        <el-select v-model="search.source" placeholder="请选择投放渠道" clearable style="width: 240px;">
          <el-option
            v-for="(value,key) in $global.SOURCE_MAP"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">投放方式：</span>
        <el-select v-model="search.launch_type" placeholder="请选择投放方式" clearable style="width: 240px;">
          <el-option
            v-for="(value,key) in $global.LAUNCH_TYPE_MAP"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
    </el-row>
    <el-row>
      <el-col :span="8">
        <span class="el-col-span">产品版本：</span>
        <el-input v-model="search.app_version" placeholder="请填写产品版本" clearable style="width: 240px;"></el-input>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">产品渠道：</span>
        <el-select v-model="search.channel" placeholder="请选择产品渠道" clearable style="width: 240px;">
          <el-option
          <el-option
            v-for="(value,key) in $global.CHANNEL_MAP"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>

      <el-col :span="8">
        <span class="el-col-span">设备厂商：</span>
        <el-select v-model="search.vendor" placeholder="请选择设备厂商" clearable style="width: 240px;">
          <el-option
            v-for="(value,key) in $global.VENDOR_MAP"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
    </el-row>

    <el-row>
      <el-col :span="8">
        <span class="el-col-span">系统版本：</span>
        <el-select v-model="search.os_version" placeholder="请选择系统版本" style="width: 240px;">
          <el-option label="Android 8以下" value="lt-8"></el-option>
          <el-option label="Android 8" value="8"></el-option>
          <el-option label="Android 9" value="9"></el-option>
          <el-option label="Android 10" value="10"></el-option>
          <el-option label="Android 11" value="11"></el-option>
          <el-option label="Android 12" value="12"></el-option>
          <el-option label="Android 13" value="13"></el-option>
          <el-option label="Android 14" value="14"></el-option>
          <el-option label="Android 15" value="15"></el-option>
          <el-option label="Android 16" value="16"></el-option>
        </el-select>
      </el-col>
    </el-row>
    <el-row>
      <el-col :span="12">
        <el-radio-group v-model="search.type" @change="onSearch">
          <el-radio-button
            v-for="(value,key) in type_map"
            :label="key"
          >{{ value }}
          </el-radio-button>
        </el-radio-group>
      </el-col>
      <el-col :span="12">
        <el-button type="primary" icon="el-icon-download" @click="onExport" style="float: right">导出</el-button>
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
      <el-table-column align="center" label="日期">
        <template slot-scope="scope">
          {{ scope.row.day }}
        </template>
      </el-table-column>
      <el-table-column label="新增设备数" align="center">
        <template slot-scope="scope">
          {{ scope.row.active_num }}
        </template>
      </el-table-column>
      <el-table-column label="次留率(数)" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.keep_rate1 }}</div>
          <div>( {{ scope.row.keep_num1 }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="2留率(数)" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.keep_rate2 }}</div>
          <div>( {{ scope.row.keep_num2 }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="3留率(数)" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.keep_rate3 }}</div>
          <div> ( {{ scope.row.keep_num3 }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="4留率(数)" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.keep_rate4 }}</div>
          <div> ( {{ scope.row.keep_num4 }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="5留率(数)" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.keep_rate5 }}</div>
          <div> ( {{ scope.row.keep_num5 }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="6留率(数)" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.keep_rate6 }}</div>
          <div> ( {{ scope.row.keep_num6 }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="7留率(数)" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.keep_rate7 }}</div>
          <div> ( {{ scope.row.keep_num7 }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="14留率(数)" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.keep_rate14 }}</div>
          <div> ( {{ scope.row.keep_num14 }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="30留率(数)" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.keep_rate30 }}</div>
          <div> ( {{ scope.row.keep_num30 }} )</div>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>
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
</style>
<script>
import {getList} from '@/api/product/keep'
import shortcuts from '@/utils/date-picker'
import {parseTime} from '@/utils'
import {getCurrentApp} from '@/utils/auth'
import {Message} from "element-ui";

export default {
  data() {
    return {
      pickerOptions: {
        shortcuts
      },
      app_info: getCurrentApp(),
      type_map: {
        'install': '新增安装留存',
        'activate': '新增激活留存'
      },
      search: {
        date_picker: this.defaultDate(),
        source: undefined,
        launch_type: undefined,
        app_version: undefined,
        channel: undefined,
        vendor: undefined,
        os_version: undefined,
        app_code: undefined,
        type: 'install'
      },
      list: null,
      listLoading: false
    }
  },
  created() {
    this.search.app_code = this.app_info.app_code
    this.onSearch()
  },
  methods: {
    defaultDate() {//默认时间 - 过去7天
      const end = new Date()
      const start = new Date()
      end.setTime(end.getTime())
      start.setTime(start.getTime() - 86400 * 1000 * 7)
      const format = '{y}-{m}-{d}'
      return [parseTime(start, format), parseTime(end, format)]
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
      this.fetchData()
    },
    onExport() {
      if (!this.list) {
        Message({
          message: '暂无数据',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      import('@/utils/Export2Excel').then(excel => {
        const tHeader = ['日期', '新增设备数', '次留数', '次留率', '2留数', '2留率', '3留数', '3留率', '4留数', '4留率', '5留数', '5留率', '6留数', '6留率', '7留数', '7留率', '14留数', '14留率', '30留数', '30留率']
        const filterVal = ['day', 'active_num', 'keep_num1', 'keep_rate1', 'keep_num2', 'keep_rate2', 'keep_num3', 'keep_rate3', 'keep_num4', 'keep_rate4', 'keep_num5', 'keep_rate5', 'keep_num6', 'keep_rate6', 'keep_num7', 'keep_rate7', 'keep_num14', 'keep_rate14', 'keep_num30', 'keep_rate30']
        const list = this.list
        const data = this.formatJson(filterVal, list)
        const type_name = this.type_map[this.search.type]
        const app_name = this.app_info.app_name
        const start = this.search.date_picker[0]
        const end = this.search.date_picker[1]
        const filename = app_name + '_' + type_name + '_' + start + '_' + end
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
    },
    fetchData() {
      this.listLoading = true
      getList(this.search).then(response => {
        this.list = response.data.list
        this.listLoading = false
      }).catch(() => {
        this.listLoading = false
      })
    }
  }
}
</script>
