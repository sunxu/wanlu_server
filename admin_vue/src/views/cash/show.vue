<template>
  <div class="app-container">
    <el-row>
      <el-col :span="8">
        <span class="el-col-span">日期：</span>
        <el-date-picker
          v-model="listQuery.startTime"
          type="daterange"
          align="right"
          :clearable="false"
          unlink-panels
          range-separator="~"
          style="width: 240px;"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="yyyy-MM-dd"
          :picker-options="startTimeOptions"
        >
        </el-date-picker>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">版本：</span>
        <el-input v-model="listQuery.all_version" placeholder="版本" style="width: 240px;"
                  class="filter-item"/>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">产品渠道：</span>
        <el-select v-model="listQuery.channel" placeholder="产品渠道" clearable style="width: 240px;"
                   class="filter-item">
          <el-option label="全部" value=""></el-option>
          <el-option
            v-for="(value,key) in $global.CHANNEL_MAP"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
    </el-row>
    <el-row>
      <el-col :span="8">
        <span class="el-col-span">产品厂商：</span>
        <el-select v-model="listQuery.vendor" style="width: 240px;" class="filter-item">
          <el-option label="全部" value=""></el-option>
          <el-option
            v-for="(value,key) in $global.VENDOR_MAP"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">广告类型：</span>
        <el-select v-model="listQuery.adType" placeholder="广告所有类型" style="width: 240px;"
                   class="filter-item">
          <el-option label="全部" value=""></el-option>
          <el-option
            v-for="(value,key) in $global.AD_TYPE_MAP"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
    </el-row>
    <el-row>
      <el-col :span="12">
        <el-radio-group v-model="listQuery.type" @change="handleFilter">
          <el-radio-button label="1">新增安装</el-radio-button>
          <el-radio-button label="3">新增激活</el-radio-button>
          <el-radio-button label="2">DEU</el-radio-button>
          <el-radio-button label="4">DEU老</el-radio-button>
          <el-radio-button label="5">DAU</el-radio-button>
        </el-radio-group>
      </el-col>
      <el-col :span="12">
        <el-button v-waves :loading="downloadLoading" class="filter-item" style="float: right; " type="primary"
                   icon="el-icon-download" @click="handleDownload">导出
        </el-button>
        <el-button v-waves class="filter-item" type="primary" style="float: right; margin-right: 10px "
                   @click="reviewer">重置
        </el-button>
        <el-button v-waves class="filter-item" type="primary" icon="el-icon-search" style="float: right; "
                   @click="handleFilter">搜索
        </el-button>
      </el-col>
    </el-row>
    <el-table :key="tableKey" v-loading="listLoading" :data="list" border fit highlight-current-row
              style="width: 100%;">
      <el-table-column label="日期" fixed align="center">
        <template slot-scope="scope">
          {{ scope.row.day }}
        </template>
      </el-table-column>
      <el-table-column :label="label" fixed align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.user_num }}</span>
        </template>
      </el-table-column>

      <el-table-column label="填充率" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.total_full_rate }}</span>
        </template>
      </el-table-column>
      <el-table-column label="展示数" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.total_show_num }}</span>
        </template>
      </el-table-column>
      <el-table-column label="展示率" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.total_show_rate }}</span>
        </template>
      </el-table-column>
      <el-table-column label="人均" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.total_person_rate }}</span>
        </template>
      </el-table-column>
      <el-table-column label="体内填充率" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.in_full_rate }}</span>
        </template>
      </el-table-column>
      <el-table-column label="体内展示数" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.in_show_num }}</span>
        </template>
      </el-table-column>
      <el-table-column label="体内展示率" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.in_show_rate }}</span>
        </template>
      </el-table-column>
      <el-table-column label="人均" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.in_person_rate }}</span>
        </template>
      </el-table-column>
      <el-table-column label="体外填充率" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.out_full_rate }}</span>
        </template>
      </el-table-column>
      <el-table-column label="体外展示数" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.out_show_num }}</span>
        </template>
      </el-table-column>
      <el-table-column label="体外展示率" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.out_show_rate }}</span>
        </template>
      </el-table-column>
      <el-table-column label="人均" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.out_person_rate }}</span>
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
import waves from '@/directive/waves' // waves directive
import {parseTime} from '@/utils'
import shortcuts from '@/utils/date-picker'
import Pagination from '@/components/Pagination' // secondary package based on el-pagination
import {show} from '@/api/cash/list'
import {getAppCode} from '@/utils/auth'

export default {
  name: 'installTable',
  components: {Pagination},
  directives: {waves},
  data() {
    return {
      tableKey: 0,
      label: '新增安装',
      dataText: '暂无数据',
      list: null,
      listLoading: false,
      listQuery: {
        startTime: this.defaultDate(),
        all_version: '',
        channel: '',
        vendor: '',
        appcode: getAppCode(),
        type: '1',
        adType: ''
      },
      appcodeOption: undefined,
      channelOption: undefined,
      startTimeOptions: {shortcuts},
      showReviewer: false,
      downloadLoading: false
    }
  },
  created() {
    this.label = '新增安装'
    this.getList()
  },
  methods: {
    defaultDate() {//默认时间 - 过去7天
      const end = new Date()
      const start = new Date()
      end.setTime(end.getTime() - 86400 * 1000)
      start.setTime(start.getTime() - 86400 * 1000 * 7)
      const format = '{y}-{m}-{d}'
      return [parseTime(start, format), parseTime(end, format)]
    },
    getList() {
      this.listLoading = true
      show(this.listQuery).then(response => {
        this.list = response.data.list;
        this.listLoading = false;
      }).catch(() => {
        this.listLoading = false
      })
    },
    handleFilter() {
      switch (this.listQuery.type) {
        case "1":
          this.label = '新增安装';
          break;
        case "3":
          this.label = '新增激活';
          break;
        case "2":
          this.label = 'DEU';
          break;
        case "4":
          this.label = 'DEU老';
          break;
        case "5":
          this.label = 'DAU';
          break;
      }
      this.getList()
    },
    reviewer() {
      this.listQuery.all_version = ''
      this.listQuery.channel = ''
      this.listQuery.vendor = ''
      this.listQuery.adScene = ''
    },
    handleDownload() {
      this.downloadLoading = true
      import('@/vendor/Export2Excel').then(excel => {
        const tHeader = [
          '日期',
          this.label,
          '总请求数',
          '总填充数',
          '总展示数',
          '总填充率',
          '总展示率',
          '总人均展示数',
          '体内请求数',
          '体内填充数',
          '体内展示数',
          '体内填充率',
          '体内展示率',
          '体内人均展示数',
          '体外请求数',
          '体外填充数',
          '体外展示数',
          '体外填充率',
          '体外展示率',
          '体外人均展示数'
        ]
        const filterVal = [
          'day',
          'user_num',
          'total_request_num',
          'total_full_num',
          'total_show_num',
          'total_full_rate',
          'total_show_rate',
          'total_person_rate',
          'in_request_num',
          'in_full_num',
          'in_show_num',
          'in_full_rate',
          'in_show_rate',
          'in_person_rate',
          'in_request_num',
          'out_full_num',
          'out_show_num',
          'out_full_rate',
          'out_show_rate',
          'out_person_rate'
        ]
        const data = this.formatJson(filterVal)
        excel.export_json_to_excel({
          header: tHeader,
          data,
          filename: this.label + this.listQuery.startTime[0]
        })
        this.downloadLoading = false
      })
    },
    formatJson(filterVal) {
      return this.list.map(v => filterVal.map(j => {
        if (j === 'timestamp') {
          return parseTime(v[j])
        } else {
          return v[j]
        }
      }))
    },
  }
}
</script>
