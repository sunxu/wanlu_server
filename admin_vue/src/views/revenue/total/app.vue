<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">日期：</span>
          <el-date-picker v-model="searchDate" type="daterange" style="width: 240px;" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期"></el-date-picker>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">投放：</span>
          <el-select v-model="listQuery.put">
            <el-option v-for="(value,key) in putMap" :label="value" :value="key"></el-option>
          </el-select>
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
      <!-- <el-table-column label="媒体" align="center" prop="media_type" width="100"></el-table-column> -->
      <el-table-column label="合计" header-align="center" width="180">
        <template slot-scope="scope">
          <div style="display: inline-table;min-width:150px;">
            <span>消耗：{{ scope.row.total_cost }}</span>
          </div>
          <div style="display: inline-table;min-width:150px;">
            <span>回收：{{ scope.row.media_revenue }}</span>
          </div>
          <div style="display: inline-table;min-width:150px;">
            <span>毛利：{{ scope.row.total_revenue }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="详情" header-align="center" min-width="400">
        <template slot-scope="scope">
          <div v-for="item in scope.row.detail" style="display: inline-table;margin-right: 10px;min-width:350px;">
            <span><b>{{ item.app_name }}</b></span>
            <span style="margin-left:10px">消耗：{{ item.cost }}</span>
            <span style="margin-left:10px">回收：{{ item.revenue }}</span>
            <span style="margin-left:10px">ROI：{{ item.roi }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="余量回收（停投>=15天）" align="center" prop="other_revenue" width="170"></el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />
  </div>
</template>

<script>
import Pagination from '@/components/Pagination'
import { parseTime } from '@/utils'
import { totalApp } from '@/api/revenue/total'

export default {
  name: 'DailyBase',
  components: { Pagination },
  data() {
    return {
      searchDate: undefined,
      listLoading: false,
      list: [],
      total: 0,
      putMap: {
        'self': '自投',
        'agency': '代投'
      },
      listQuery: {
        put: 'self',
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
      totalApp(this.listQuery).then(response => {
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
