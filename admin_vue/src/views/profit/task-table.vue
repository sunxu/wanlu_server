<template>
  <div class="app-container">
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
      <el-table-column label="总支出（元）" align="center">
        <template slot-scope="scope">
          {{ scope.row.expend_amount }}
        </template>
      </el-table-column>
      <el-table-column label="做任务人数" align="center">
        <template slot-scope="scope">
          {{ scope.row.user_count }}
        </template>
      </el-table-column>
      <el-table-column label="人均支出（元）" align="center">
        <template slot-scope="scope">
          {{ scope.row.average }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="支出金币（元）">
        <template slot-scope="scope">
          {{ scope.row.expend_coin }}
        </template>
      </el-table-column>
      <el-table-column label="支出元宝（元）"  align="center">
        <template slot-scope="scope">
          {{ scope.row.expend_ingot }}
        </template>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />
  </div>
</template>

<script>
import { getList } from '@/api/profit/task'
import Pagination from '@/components/Pagination'
import { getCurrentApp } from '@/utils/auth'

export default {
  components: { Pagination },
  data() {
    return {
      list: null,
      total: 0,
      listLoading: true,
      listQuery: {
        page: 1,
        per_page: 10,
        appcode: undefined
      }
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
    getListQuery() {
      this.listQuery.appcode = getCurrentApp().app_code
      return this.listQuery
    }
  }
}
</script>
