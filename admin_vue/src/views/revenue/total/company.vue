<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">日期：</span>
          <el-date-picker v-model="searchDate" type="daterange" style="width: 240px;" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期"></el-date-picker>
        </el-col>
        <el-col :span="6">
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
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
      <el-table-column label="日期" align="center" prop="date" width="100" fixed></el-table-column>
      <el-table-column label="合计" header-align="center" width="200">
        <template slot-scope="scope">
          <div style="display: inline-table;margin-left: 10px;min-width:200px;">
            <div><span><b>合计：</b>{{ scope.row.total_revenue }}</span></div>
            <div v-for="item in scope.row.total_media">
              <span><b>{{item.media_name}}：</b>{{ item.revenue }}（{{ scope.row.total_revenue > 0 ? (item.revenue * 100 / scope.row.total_revenue).toFixed(2) : 0}}%）</span>
            </div>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="详情" header-align="center" min-width="200">
        <template slot-scope="scope">
          <div v-for="detail in scope.row.detail" style="display: inline-table;margin-left: 10px;min-width:200px;">
            <div><span><b>{{ detail.company_name }}</b></span></div>
            <div><span>合计：{{ detail.company_revenue }}</span></div>
            <div v-for="item in detail.detail">
              <span>{{item.media_name}}：{{ item.revenue }}（{{detail.company_revenue > 0 ? (item.revenue * 100 / detail.company_revenue).toFixed(2) : 0}}%）</span>
            </div>
          </div>
        </template>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />
  </div>
</template>

<script>
import Pagination from '@/components/Pagination'
import { parseTime } from '@/utils'
import { totalCompany } from '@/api/revenue/total'

export default {
  name: 'DailyBase',
  components: { Pagination },
  data() {
    return {
      searchDate: undefined,
      listLoading: false,
      list: [],
      total: 0,
      listQuery: {
        app_code: [],
        page: 1,
        per_page: 10,
        date_begin: undefined,
        date_end: undefined
      }
    }
  },
  watch: {
    searchDate(searchDate) {
      if (!searchDate) {
        return
      }
      this.listQuery.date_begin = parseTime(searchDate[0], '{y}-{m}-{d}')
      this.listQuery.date_end = parseTime(searchDate[1], '{y}-{m}-{d}')
    }
  },
  created() {
    this.searchDate = [new Date() - 86400000 * 7, new Date() - 86400000]
    this.searchOptions()
    this.fetchData()
  },
  methods: {
    searchOptions() {
      const apps = this.$store.getters.userInfo.apps
      this.appList = []
      apps.forEach(app => {
        this.appList.push({ value: app.app_code, label: app.app_name })
      })
    },
    fetchData() {
      this.listLoading = true
      totalCompany(this.listQuery).then(response => {
        this.list = response.data.records
        this.total = response.data.total
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
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
