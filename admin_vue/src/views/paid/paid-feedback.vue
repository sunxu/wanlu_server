<template>
  <div class="app-container">
    <div class="filter-container">
      <el-input v-model="listQuery.contact" placeholder="联系方式" style="width: 200px;" class="filter-item" />
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
      <el-table-column align="center" label="ID">
        <template slot-scope="scope">
          {{ scope.$index + 1}}
        </template>
      </el-table-column>
      <el-table-column label="Account"  align="center">
        <template slot-scope="scope">
          {{ scope.row.account }}
        </template>
      </el-table-column>
      <el-table-column label="联系方式"  align="center">
        <template slot-scope="scope">
          {{ scope.row.contact }}
        </template>
      </el-table-column>
      <el-table-column label="内容"  align="center" show-overflow-tooltip>
        <template slot-scope="scope">
          {{ scope.row.content }}
        </template>
      </el-table-column>
      <el-table-column label="创建时间" align="center">
        <template slot-scope="scope">
          {{ parseTime(scope.row.created_time) }}
        </template>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />

  </div>
</template>

<script>
import { getList } from '@/api/paid/paidFeedback'
import Pagination from '@/components/Pagination'
import { parseTime } from '@/utils'
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
        contact: undefined,
        app_code: undefined
      }
    }
  },
  created() {
    this.fetchData()
  },
  methods: {
    fetchData() {
      this.listLoading = true
      this.listQuery.app_code = getCurrentApp().app_code
      getList(this.listQuery).then(response => {
        this.list = response.data.records
        this.total = response.data.total
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
    },
    parseTime(time) {
      return parseTime(time)
    }
  }
}
</script>

<style>
.el-tooltip__popper {
  max-width: 30%;
}
</style>
