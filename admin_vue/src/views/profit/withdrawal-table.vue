<template>
  <div class="app-container">
    <div class="filter-container">
      <el-select v-model="listQuery.source" placeholder="投放渠道" clearable style="width: 130px" class="filter-item">
        <el-option v-for="item in sources" :key="item.code" :label="item.name" :value="item.code" />
      </el-select>
      <el-select v-model="listQuery.launch_type" placeholder="投放方式" clearable class="filter-item" style="width: 130px">
        <el-option v-for="item in launch_types" :key="item.code" :label="item.name" :value="item.code" />
      </el-select>
      <el-select v-model="listQuery.is_new" placeholder="用户类型" clearable class="filter-item" style="width: 130px">
        <el-option v-for="item in is_news" :key="item.code" :label="item.name" :value="item.code" />
      </el-select>
      <el-date-picker
        v-model="queryDate"
        type="daterange"
        range-separator="至"
        start-placeholder="开始日期"
        end-placeholder="结束日期">
      </el-date-picker>
      <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
        搜索
      </el-button>
      <el-button :loading="downloadLoading" class="filter-item" type="primary" icon="el-icon-download" @click="handleDownload">
        导出
      </el-button>
    </div>
    <el-tag>提现总金额（元）{{ withdrawal_amount }}</el-tag>
    <el-tag type="success">打款总金额（元）{{ withdrawal_success }}</el-tag>
    <el-tag type="info" @click="openWechatCode">微信流水</el-tag>
    <el-table
      v-loading="listLoading"
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
    >
      <el-table-column label="日期"  align="center">
        <template slot-scope="scope">
          {{ scope.row.expend_date }}
        </template>
      </el-table-column>
      <el-table-column label="投放渠道" align="center">
        <template slot-scope="scope">
          {{ scope.row.source }}
        </template>
      </el-table-column>
      <el-table-column label="投放方式" align="center">
        <template slot-scope="scope">
          {{ scope.row.launch_type }}
        </template>
      </el-table-column>
      <el-table-column label="投放计划" align="center">
        <template slot-scope="scope">
          {{ scope.row.plan_id }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="提现人数">
        <template slot-scope="scope">
          {{ scope.row.user_count }}
        </template>
      </el-table-column>
      <el-table-column label="当天提现人数"  align="center">
        <template slot-scope="scope">
          {{ scope.row.user_count_daily }}
        </template>
      </el-table-column>
      <el-table-column label="提现金额（元）"  align="center">
        <template slot-scope="scope">
          {{ scope.row.withdrawal_amount }}
        </template>
      </el-table-column>
      <el-table-column label="打款金额（元）"  align="center">
        <template slot-scope="scope">
          {{ scope.row.withdrawal_success }}
        </template>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />
  </div>
</template>

<script>
import { getList, exportStatistics } from '@/api/profit/withdrawal'
import Pagination from '@/components/Pagination'
import { getCurrentApp } from '@/utils/auth'

export default {
  components: { Pagination },
  data() {
    return {
      list: null,
      withdrawal_amount: 0,
      withdrawal_success: 0,
      total: 0,
      listLoading: true,
      downloadLoading: false,
      queryDate: undefined,
      listQuery: {
        page: 1,
        per_page: 10,
        appcode: undefined,
        source: undefined,
        launch_type: undefined,
        expend_date_begin: undefined,
        expend_date_end: undefined,
        is_new: undefined
      },
      sources: [
        { code: 'ks', name: '快手' },
        { code: 'jl', name: '巨量' },
        { code: 'tx', name: '腾讯营销' }
      ],
      launch_types: [
        { code: '0', name: '双出' },
        { code: '1', name: '关键行为' }
      ],
      is_news: [
        { code: '0', name: '否' },
        { code: '1', name: '是' }
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
        this.withdrawal_amount = response.data.withdrawal_amount
        this.withdrawal_success = response.data.withdrawal_success
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
        this.listQuery.expend_date_begin = this.queryDate[0].valueOf() / 1000
        this.listQuery.expend_date_end = this.queryDate[1].valueOf() / 1000 + 60 * 60 * 24 - 1
      }
      return this.listQuery
    },
    openWechatCode() {
      const { href } = this.$router.resolve({ name: '/external/wechat-code' })
      window.open(href, '_blank')
    }
  }
}
</script>
