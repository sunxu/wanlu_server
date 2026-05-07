<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">日期：</span>
          <el-date-picker v-model="searchDate" type="daterange" style="width: 240px;" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期"></el-date-picker>
        </el-col>
      </el-row>
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
        搜索
      </el-button>
    </div>

    <el-table
      v-loading="listLoading"
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
    >
      <el-table-column align="center" label="日期" prop="report_date"></el-table-column>
      <el-table-column align="center" label="总充值" :formatter="exchangeRate"></el-table-column>
      <el-table-column
        v-for="item in goodsList"
        :key="item.goods_no"
        :label="item.goods_name"
        align="center"
        :formatter="rechargeInfo"
        :column-key="item.goods_no + ''"
      ></el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />

  </div>
</template>

<script>
import { reportDailyRechargeList } from '@/api/paid/paid'
import Pagination from '@/components/Pagination'
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'

export default {
  components: { Pagination },
  data() {
    return {
      searchDate: null,
      list: null,
      total: 0,
      listLoading: true,
      goodsList: [],
      listQuery: {
        page: 1,
        per_page: 10,
        date_begin: undefined,
        date_end: undefined,
        app_code: undefined
      }
    }
  },
  created() {
    this.listQuery.app_code = getAppCode()
    this.fetchData()
  },
  methods: {
    fetchData() {
      this.listLoading = true
      this.listQuery.date_begin = undefined
      this.listQuery.date_end = undefined
      if(this.searchDate !== null) {
        this.listQuery.date_begin = parseTime(this.searchDate[0], '{y}-{m}-{d}')
        this.listQuery.date_end = parseTime(this.searchDate[1], '{y}-{m}-{d}')
      }
      reportDailyRechargeList(this.listQuery).then(response => {
        this.list = response.data.records
        this.total = response.data.total
        this.listLoading = false
        this.goodsList = response.data.goods
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
    },
    exchangeRate(row) {
      return row.total_amount / 100
    },
    rechargeInfo(row, column) {
      return (row.info[column.columnKey].amount ? row.info[column.columnKey].amount : 0) / 100
    }
  }
}
</script>

<style>
.el-tooltip__popper {
  max-width: 30%;
}
</style>
