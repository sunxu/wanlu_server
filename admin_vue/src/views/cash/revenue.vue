<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">日期：</span>
          <el-date-picker v-model="searchDate" type="daterange" style="width: 240px;" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" :clearable="false"></el-date-picker>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">投放渠道：</span>
          <el-select v-model="listQuery.platform" placeholder="投放渠道" clearable style="width: 240px" class="filter-item">
            <el-option v-for="(value,key) in source_map" :label="value" :value="key"></el-option>
          </el-select>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">投放方式：</span>
          <el-select v-model="listQuery.launch_type" placeholder="投放方式" clearable style="width: 240px" class="filter-item">
            <el-option v-for="(value,key) in launch_type_map" :label="value" :value="key"></el-option>
          </el-select>
        </el-col>
      </el-row>
      <el-row>
        <el-col :span="8">
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
            搜索
          </el-button>
          <el-button class="filter-item" type="primary" icon="el-icon-download" :loading="downloadLoading" @click="handleDownload">
            导出
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
      <el-table-column align="center" label="日期" prop="date"></el-table-column>
      <el-table-column align="center" label="消耗" prop="cost"></el-table-column>
      <el-table-column align="center" label="新增安装数" prop="installed_num"></el-table-column>
      <el-table-column align="center" label="回本率" prop="revenue_rate_0"></el-table-column>
      <el-table-column align="center" label="回本率(次日)" prop="revenue_rate_1"></el-table-column>
      <el-table-column align="center" label="回本率(2日)" prop="revenue_rate_2"></el-table-column>
      <el-table-column align="center" label="回本率(3日)" prop="revenue_rate_3"></el-table-column>
      <el-table-column align="center" label="回本率(4日)" prop="revenue_rate_4"></el-table-column>
      <el-table-column align="center" label="回本率(5日)" prop="revenue_rate_5"></el-table-column>
      <el-table-column align="center" label="回本率(6日)" prop="revenue_rate_6"></el-table-column>
      <el-table-column align="center" label="回本率(7日)" prop="revenue_rate_7"></el-table-column>
      <el-table-column align="center" label="回本率(14日)" prop="revenue_rate_14"></el-table-column>
      <el-table-column align="center" label="回本率(30日)" prop="revenue_rate_30"></el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import { Message } from 'element-ui'
import { revenueList } from '@/api/cash/list'
import Pagination from '@/components/Pagination'

export default {
  label: '广告 回收率',
  name: 'Revenue',
  components: { Pagination },
  data() {
    return {
      source_map: _.clone(this.$global.SOURCE_MAP),
      launch_type_map: _.clone(this.$global.LAUNCH_TYPE_MAP),
      searchDate: undefined,
      listLoading: false,
      total: 0,
      list: undefined,
      downloadLoading: false,
      listQuery: {
        page: 1,
        per_page: 10,
        date_begin: undefined,
        date_end: undefined
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
    defaultDate() {//默认时间 - 过去7天
      const end = new Date()
      const start = new Date()
      end.setTime(end.getTime())
      start.setTime(start.getTime() - 86400 * 1000 * 7)
      const format = '{y}-{m}-{d}'
      return [parseTime(start, format), parseTime(end, format)]
    },
    fetchData() {
      this.listLoading = true
      if (this.searchDate) {
        this.listQuery.date_begin = parseTime(this.searchDate[0], '{y}-{m}-{d}')
        this.listQuery.date_end = parseTime(this.searchDate[1], '{y}-{m}-{d}')
      }
      revenueList(getAppCode(), this.listQuery).then(response => {
        this.total = response.data.total
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
      const that = this
      revenueList(getAppCode(), this.listQuery).then(response => {
        import('@/vendor/Export2Excel').then(excel => {
          const tHeader = [
            '日期', '消耗', '新增安装数', '回本率', '回本率(次日)', '回本率(2日)', '回本率(3日)', '回本率(4日)', '回本率(5日)',
            '回本率(6日)', '回本率(7日)', '回本率(14日)', '回本率(30日)'
          ]
          const filterVal = [
            'date', 'cost', 'installed_num', 'revenue_rate_0', 'revenue_rate_1', 'revenue_rate_2', 'revenue_rate_3',
            'revenue_rate_4', 'revenue_rate_5', 'revenue_rate_6', 'revenue_rate_7', 'revenue_rate_14', 'revenue_rate_30'
          ]
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

          const date = new Date()
          const time = date.getFullYear() + '' + (date.getMonth() + 1) + '' + date.getDate() + '' + date.getHours() + '' + date.getMinutes() + '' + date.getSeconds()
          excel.export_json_to_excel({
            header: tHeader,
            data,
            filename: '广告回收率-' + time
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
