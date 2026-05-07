<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">日期：</span>
          <el-date-picker v-model="searchDate" type="daterange" style="width: 240px;" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期"></el-date-picker>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">版本：</span>
          <el-input v-model="listQuery.version" placeholder="版本" size="small" style="width: 240px;" class="filter-item" />
        </el-col>
        <el-col :span="6">
          <el-button class="filter-item" type="primary" icon="el-icon-download" :loading="downloadLoading" @click="handleDownload" style="float: right;">
            导出
          </el-button>
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter" style="float: right; margin-right: 10px;">
            搜索
          </el-button>
        </el-col>
      </el-row>
    </div>
    <el-table
      v-loading="listLoading"
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
    >
      <el-table-column align="center" label="日期" prop="date" width="100"></el-table-column>
      <el-table-column align="center" label="总新增">
        <el-table-column align="center" label="安装" prop="install"></el-table-column>
        <el-table-column align="center" label="激活" prop="active"></el-table-column>
        <el-table-column align="center" label="归因(%)" prop="rate_guiyin"></el-table-column>
      </el-table-column>
      <el-table-column align="center" label="新增">
        <el-table-column align="center" label="安装" prop="install_new"></el-table-column>
        <el-table-column align="center" label="激活" prop="active_new"></el-table-column>
        <el-table-column align="center" label="归因(%)" prop="rate_guiyin_new"></el-table-column>
      </el-table-column>
      <el-table-column align="center" label="黑名单(%)" prop="rate_black"></el-table-column>
      <el-table-column align="center" label="客户端审核(%)" prop="rate_client"></el-table-column>
      <el-table-column align="center" label="自取参数(%)" prop="rate_self"></el-table-column>
      <el-table-column align="center" label="巨量参数(%)" prop="rate_jl"></el-table-column>
      <el-table-column align="center" label="快手参数(%)" prop="rate_ks"></el-table-column>
    </el-table>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import { getList } from '@/api/product/guiyin'
import { Message } from 'element-ui'

export default {
  data() {
    return {
      searchDate: undefined,
      listLoading: false,
      total: 0,
      list: undefined,
      downloadLoading: false,
      listQuery: {
        page: 1,
        page_size: 10,
        date_begin: undefined,
        date_end: undefined,
        version: undefined
      }
    }
  },
  created() {
    this.searchDate = this.defaultDate()
  },
  mounted() {
    this.fetchData()
  },
  methods: {
    defaultDate() { // 默认时间 - 过去7天
      const end = new Date()
      const start = new Date()
      end.setTime(end.getTime())
      start.setTime(start.getTime() - 86400 * 1000 * 7)
      const format = '{y}-{m}-{d}'
      return [parseTime(start, format), parseTime(end, format)]
    },
    fetchData() {
      this.listLoading = true
      this.listQuery.date_begin = parseTime(this.searchDate[0], '{y}-{m}-{d}')
      this.listQuery.date_end = parseTime(this.searchDate[1], '{y}-{m}-{d}')
      getList(getAppCode(),this.listQuery).then(response => {
        this.list = response.data.records
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
    },
    handleDownload() {
      this.downloadLoading = true
      const query = Object.assign({}, this.listQuery)
      query.page_size = this.total
      query.page = 1
      const that = this
      getList(getAppCode(),query).then(response => {
        import('@/vendor/Export2Excel').then(excel => {
          const tHeader = ['日期', '总安装','总激活',  '总归因(%)', '安装', '激活','归因(%)', '黑名单(%)','客户端审核(%)', '自取参数(%)', '巨量参数(%)','快手参数(%)']
          const filterVal = ['date', 'install','active', 'rate_guiyin', 'install', 'active_new','rate_guiyin_new','rate_black', 'rate_client', 'rate_self', 'rate_jl','rate_ks']
          const data = response.data.records.map(v => filterVal.map(j => {
            return v[j]
          }))
          if (data.length <= 0) {
            that.downloadLoading = false
            Message({
              showClose: true,
              message: '没有导出的数据',
              type: 'warning',
              duration: 5 * 1000
            })
            return
          }

          let date= new Date()
          let time = date.getFullYear() +''+ (date.getMonth() + 1) +''+  date.getDate() +''+  date.getHours() +''+  date.getMinutes() +''+  date.getSeconds();
          excel.export_json_to_excel({
            header: tHeader,
            data,
            filename: '设备归因-' + time
          })
          that.downloadLoading = false
        })
      })
    }
  }
}
</script>

<style scoped>
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
