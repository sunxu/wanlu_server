<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row :gutter="20">
        <el-col :span="20">
          <el-button class="filter-item" type="primary" icon="el-icon-edit" @click="addDelegate">
            新增
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
      style="margin-top: 20px;"
    >
      <el-table-column align="center" prop="delegate_id" label="ID" min-width="80">
        <template slot-scope="scope">
          {{ scope.row.delegate_id }}
        </template>
      </el-table-column>
      <el-table-column align="center" prop="delegate_name" label="代投名称" min-width="120">
        <template slot-scope="scope">
          {{ scope.row.delegate_name }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="钉钉配置" min-width="200">
        <template slot-scope="scope">
          <div>Token: {{ scope.row.ding_token || '-' }}</div>
          <div>Secret: {{ scope.row.ding_secret || '-' }}</div>
        </template>
      </el-table-column>
      <el-table-column align="center" prop="status" label="状态" min-width="90">
        <template slot-scope="scope">
          <el-button v-if="scope.row.status === 0" type="danger" plain disabled>禁用</el-button>
          <el-button v-if="scope.row.status === 1" type="success" plain disabled>启用</el-button>
        </template>
      </el-table-column>
      <el-table-column align="center" prop="updated_time" label="更新时间" :formatter="formatterTime" min-width="90"></el-table-column>
      <el-table-column align="center" label="操作" min-width="200">
        <template slot-scope="scope">
          <el-button size="mini" type="primary" @click="editDelegate(scope.row)">编辑</el-button>
          <el-button size="mini" type="success" @click="testDing(scope.row)">测试钉钉</el-button>
          <el-button size="mini" type="danger" @click="deleteDelegate(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="search.page" :limit.sync="search.per_page" @pagination="fetchQuery" />
    <DelegateForm v-if="formVisible" :form-visible="formVisible" :dialog-status="formStatus" :delegate-id="formDelegateId" @closeDialog="closeForm"></DelegateForm>
  </div>
</template>

<script>
import { alertDelegateList, deleteAlertDelegate, testAlertDelegateDing } from '@/api/put/alert-rule'
import { parseTime } from '@/utils'
import { Message } from 'element-ui'
import Pagination from '@/components/Pagination'
import DelegateForm from './delegate-form'

export default {
  name: 'AlertDelegate',
  components: { Pagination, DelegateForm },
  data() {
    return {
      search: {
        page: 1,
        per_page: 10
      },
      listLoading: false,
      list: [],
      total: 0,
      formVisible: false,
      formStatus: 'create',
      formDelegateId: 0
    }
  },
  created() {
    this.fetchQuery()
  },
  methods: {
    fetchQuery() {
      this.listLoading = true
      alertDelegateList(this.search).then(response => {
        this.list = response.data.records || []
        this.total = response.data.total || 0
        this.listLoading = false
      })
    },
    formatterTime(row, column, cellValue, index) {
      if (column.property === 'updated_time') {
        return parseTime(cellValue)
      }
      return ''
    },
    addDelegate() {
      this.formDelegateId = 0
      this.formStatus = 'create'
      this.formVisible = true
    },
    editDelegate(row) {
      this.formDelegateId = row.delegate_id
      this.formStatus = 'update'
      this.formVisible = true
    },
    deleteDelegate(row) {
      this.$confirm(
        '确认要删除该代投配置吗？',
        '提示',
        {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )
        .then(() => {
          deleteAlertDelegate(row.delegate_id).then(response => {
            this.fetchQuery()
            Message({
              showClose: true,
              message: '删除成功',
              type: 'success',
              duration: 5 * 1000
            })
          })
        })
        .catch(() => {
        })
    },
    testDing(row) {
      this.$confirm(
        '确认要测试钉钉通知吗？',
        '提示',
        {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'info'
        }
      )
        .then(() => {
          testAlertDelegateDing(row.delegate_id).then(response => {
            Message({
              showClose: true,
              message: '测试通知已发送',
              type: 'success',
              duration: 5 * 1000
            })
          }).catch(err => {
            Message({
              showClose: true,
              message: '测试失败：' + (err.message || '未知错误'),
              type: 'error',
              duration: 5 * 1000
            })
          })
        })
        .catch(() => {
        })
    },
    closeForm() {
      this.formVisible = false
      this.fetchQuery()
    }
  }
}
</script>

<style scoped>
</style>
