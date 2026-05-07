<template>
  <div class="app-container">
    <div class="filter-container">
      <el-input v-model="listQuery.user_id" placeholder="用户 ID" style="width: 200px;" class="filter-item" />
      <el-select v-model="listQuery.status" placeholder="状态" clearable style="width: 130px" class="filter-item">
        <el-option v-for="item in withdrawalStatus" :key="item.code" :label="item.name" :value="item.code" />
      </el-select>
      <el-date-picker
        v-model="queryDate"
        type="daterange"
        range-separator="至"
        start-placeholder="申请开始日期"
        end-placeholder="申请结束日期">
      </el-date-picker>
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
        搜索
      </el-button>
      <el-button :loading="downloadLoading" class="filter-item" type="primary" icon="el-icon-download" @click="handleDownload">
        导出
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
      <el-table-column label="用户 ID"  align="center">
        <template slot-scope="scope">
          {{ scope.row.user_id }}
        </template>
      </el-table-column>
      <el-table-column label="渠道" align="center">
        <template slot-scope="scope">
          {{ scope.row.channel_id }}
        </template>
      </el-table-column>
      <el-table-column label="注册时间" align="center">
        <template slot-scope="scope">
          {{ scope.row.created_time }}
        </template>
      </el-table-column>
      <el-table-column label="累计登录时长（天）" align="center">
        <template slot-scope="scope">
          {{ scope.row.login_count }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="账号等级">
        <template slot-scope="scope">
          {{ scope.row.grade }}
        </template>
      </el-table-column>
      <el-table-column label="提现金额（元）"  align="center">
        <template slot-scope="scope">
          {{ scope.row.amount }}
        </template>
      </el-table-column>
      <el-table-column label="申请时间"  align="center">
        <template slot-scope="scope">
          {{ scope.row.withdrawal_time }}
        </template>
      </el-table-column>
      <el-table-column label="状态"  align="center">
        <template slot-scope="scope">
          {{ scope.row.status_des }}
        </template>
      </el-table-column>
      <el-table-column label="打款时间"  align="center">
        <template slot-scope="scope">
          {{ scope.row.pay_time }}
        </template>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />
  </div>
</template>

<script>
import { getList, exportStatistics } from '@/api/profit/withdrawalItem'
import Pagination from '@/components/Pagination'
import { getCurrentApp } from '@/utils/auth'

export default {
  components: { Pagination },
  data() {
    return {
      list: null,
      total: 0,
      listLoading: true,
      downloadLoading: false,
      queryDate: undefined,
      listQuery: {
        page: 1,
        per_page: 10,
        appcode: undefined,
        user_id: undefined,
        status: undefined,
        withdrawal_begin: undefined,
        withdrawal_end: undefined
      },
      withdrawalStatus: [
        { code: '1', name: '审核中' },
        { code: '2', name: '提现中' },
        { code: '3', name: '未通过审核' },
        { code: '4', name: '提现成功' },
        { code: '5', name: '提现失败' }
      ]
    }
  },
  created() {
    this.fetchData()
  },
  methods: {
    fetchData() {
      this.listLoading = true
      getList(this.getListQuery()).then(response => {
        this.list = response.data.records
        this.total = response.data.total
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
    },
    handleDownload() {
      this.downloadLoading = true
      exportStatistics(this.getListQuery()).then(response => {
        var blobUrl = response.data.path
        var a = document.createElement('a')
        a.href = blobUrl
        a.click()
        window.URL.revokeObjectURL(blobUrl)
        this.downloadLoading = false
      })
    },
    getListQuery() {
      this.listQuery.appcode = getCurrentApp().app_code
      if (this.queryDate) {
        this.listQuery.withdrawal_begin = this.queryDate[0].valueOf() / 1000
        this.listQuery.withdrawal_end = this.queryDate[1].valueOf() / 1000 + 60 * 60 * 24 - 1
      }
      return this.listQuery
    }
  }
}
</script>
