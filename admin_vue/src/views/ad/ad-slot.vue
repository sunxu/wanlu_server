<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="3">
          <el-popover
            placement="top-start"
            trigger="hover"
            width="60"
          >
            <el-link href="/template/创建绑定代码位导入示例.xlsx" style="margin-left: 30px">下载示例</el-link>
            <el-button
              slot="reference"
              v-loading.fullscreen.lock="uploadLoading"
              :element-loading-text="message"
              element-loading-spinner="el-icon-loading"
              element-loading-background="rgba(0, 0, 0, 0.8)"
              class="filter-item"
              type="primary"
              icon="el-icon-upload"
              :disabled="uploadDisabled"
              @click="handleUpload"
            >
              导入代码位
            </el-button>
          </el-popover>
        </el-col>
        <el-col :span="3">
          <el-button class="filter-item" type="primary" icon="el-icon-download" :loading="downloadLoading" @click="handleDownload">
            导出
          </el-button>
        </el-col>
        <el-col :span="3">
          <router-link to="/ad/media-app-bd">
            <el-button class="filter-item" type="primary" icon="el-icon-setting">
              百度 APP配置
            </el-button>
          </router-link>
        </el-col>
      </el-row>
      <input
        ref="excel-upload-input"
        type="file"
        name="filename"
        style="display: none"
        accept=".xlsx"
        @change="changeFile"
      />
    </div>
    <el-table
      v-loading="bindListLoading"
      :data="bindList"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
      style="margin-top: 20px;"
    >
      <el-table-column align="center" label="GMID" prop="gmid" min-width="50"></el-table-column>
      <el-table-column align="center" label="媒体应用 ID" prop="media_app_id" min-width="55"></el-table-column>
      <el-table-column align="center" label="代码位类型" prop="ad_type" min-width="45"></el-table-column>
      <el-table-column align="center" label="价格" prop="price" min-width="50"></el-table-column>
      <el-table-column align="center" label="代码位名称" prop="ad_name" min-width="120"></el-table-column>
      <el-table-column align="center" label="代码位 ID" prop="ad_id"></el-table-column>
      <el-table-column align="center" label="Bidding" prop="is_bidding" min-width="40">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.is_bidding === 1" type="success"><i class="el-icon-check"></i></el-tag>
        </template>
      </el-table-column>
      <el-table-column align="center" label="创建" prop="is_create" min-width="40">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.is_create === 0" type="danger">失败</el-tag>
          <el-tag v-if="scope.row.is_create === 1" type="success">成功</el-tag>
        </template>
      </el-table-column>
      <el-table-column align="center" label="绑定" prop="is_bind" min-width="40">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.is_bind === 0 && scope.row.gmid !== ''" type="danger">失败</el-tag>
          <el-tag v-if="scope.row.is_bind === 1 && scope.row.gmid !== ''" type="success">成功</el-tag>
        </template>
      </el-table-column>
      <el-table-column align="center" label="备注" prop="remark" min-width="80" show-overflow-tooltip></el-table-column>
      <el-table-column align="center" label="操作" min-width="50">
        <template slot-scope="scope">
          <el-button v-if="scope.row.is_create === 1 && scope.row.is_bind === 0 && scope.row.gmid !== ''" size="mini" type="primary" @click="bindTry(scope.row)">绑定重试</el-button>
          <el-button v-if="scope.row.is_create === 0" type="primary" size="mini" @click="createTry(scope.row)">创建重试</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script>
import {
  bindRetry,
  createBindAdSlot,
  createRetry,
  getCreateBindAdSlotResult,
  getCreateBindAdSlotStatus
} from '@/api/ad/placement'
import { getAppCode } from '@/utils/auth'
import { Message } from 'element-ui'

export default {
  name: 'AdSlot',
  data() {
    return {
      downloadLoading: false,
      uploadLoading: false,
      uploadDisabled: false,
      execKey: 'execkey',
      message: '执行中',
      timer: undefined,
      bindListLoading: false,
      bindList: []
    }
  },
  methods: {
    handleUpload() {
      this.$refs['excel-upload-input'].value = null
      this.$refs['excel-upload-input'].click()
    },
    changeFile(e) {
      this.uploadLoading = true
      this.uploadDisabled = true
      const files = e.target.files
      const excelFile = files[0] // only use files[0]
      const formData = new FormData()
      formData.append('excel', excelFile)
      createBindAdSlot(getAppCode(), formData).then(response => {
        this.execKey = response.data.exec_key
        this.pollingStatus()
      })
    },
    pollingStatus() {
      this.timer = setInterval(() => {
        getCreateBindAdSlotStatus(getAppCode(), this.execKey).then(response => {
          switch (response.data.status) {
            case 'doing':
              this.message = response.data.message
              break
            case 'fail':
              this.clearPolling('error', response.data.message)
              break
            case 'success':
              this.clearPolling('success', '执行成功')
              break
            default:
              this.clearPolling('error', response.data.message)
          }
          console.log(response.data)
        })
      }, 1000)
    },
    clearPolling(type, message) {
      clearInterval(this.timer)
      this.message = '执行中'
      this.uploadLoading = false
      this.uploadDisabled = false
      this.$message({
        showClose: true,
        message: message || '执行失败',
        type: type
      })
      if (type === 'success') {
        this.refreshList()
      }
    },
    refreshList() {
      this.bindListLoading = true
      getCreateBindAdSlotResult(getAppCode(), this.execKey).then(response => {
        this.bindList = response.data.records
        this.bindListLoading = false
      })
    },
    createTry(row) {
      createRetry(getAppCode(), row.id).then(response => {
        this.refreshList()
      })
    },
    bindTry(row) {
      bindRetry(getAppCode(), row.id).then(response => {
        this.refreshList()
      })
    },
    handleDownload() {
      this.downloadLoading = true
      const query = Object.assign({}, this.listQuery)
      query.per_page = this.total
      const that = this
      import('@/vendor/Export2Excel').then(excel => {
        const tHeader = ['GMID', '媒体应用 ID', '代码位类型', '价格', '代码位名称', '代码位 ID', 'Bidding', '创建', '绑定', 'ExecKey']
        const filterVal = ['gmid', 'media_app_id', 'ad_type', 'price', 'ad_name', 'ad_id', 'is_bidding', 'is_create', 'is_bind', 'exec_key']
        const keyVal = ['is_bidding', 'is_create', 'is_bind']
        const data = that.bindList.map(v => filterVal.map(j => {
          if (keyVal.includes(j)) {
            return v[j] === 1 ? 'T' : 'F'
          }
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
          filename: '创建绑定结果-' + time
        })
        that.downloadLoading = false
      })
    }
  }
}
</script>

<style scoped>

</style>
