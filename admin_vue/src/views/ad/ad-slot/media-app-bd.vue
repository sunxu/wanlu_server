<template>
  <div class="app-container">
    <div class="filter-container">
      <el-button class="filter-item" type="primary" icon="el-icon-edit" @click="handleCreate">
        新增
      </el-button>
    </div>
    <el-table
      v-loading="listLoading"
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
      style="margin-top: 20px;"
    >
      <el-table-column align="center" label="应用 ID" prop="app_id" min-width="50"></el-table-column>
      <el-table-column align="center" label="应用名称" prop="app_name" min-width="55"></el-table-column>
      <el-table-column align="center" label="包名" prop="package_name" min-width="45"></el-table-column>
      <el-table-column align="center" label="账号主体" prop="company_name" min-width="50"></el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchList" />
    <MediaAppBdForm :form-visible="formVisible" @closeDialog="closeFormDialog"></MediaAppBdForm>
  </div>
</template>

<script>
import Pagination from '@/components/Pagination'
import { getMediaApp } from '@/api/ad/placement'
import { getAppCode } from '@/utils/auth'
import MediaAppBdForm from '@/views/ad/ad-slot/media-app-bd-form'

export default {
  name: 'MediaAppBd',
  components: { Pagination, MediaAppBdForm },
  data() {
    return {
      listLoading: false,
      list: [],
      total: 0,
      listQuery: {
        page: 1,
        per_page: 10,
        platform: 'bd'
      },
      formVisible: false
    }
  },
  created() {
    this.fetchList()
  },
  methods: {
    fetchList() {
      this.listLoading = true
      getMediaApp(getAppCode(), this.listQuery).then(response => {
        this.listLoading = false
        this.list = response.data.records
        this.total = response.data.total
      })
    },
    handleCreate() {
      this.formVisible = true
    },
    closeFormDialog() {
      this.formVisible = false
      this.fetchList()
    }
  }
}
</script>

<style scoped>

</style>
