<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="24">
          <el-popover
            placement="top-start"
            trigger="hover"
            width="60"
          >
            <el-link href="/template/穿山甲代码位导入示例.xlsx" style="margin-left: 30px">下载示例</el-link>
            <el-button
              slot="reference"
              class="filter-item"
              type="primary"
              icon="el-icon-upload"
              :loading="uploadLoading"
              :disabled="uploadDisabled"
              @click="handleUpload"
            >
              导入代码位
            </el-button>
          </el-popover>
        </el-col>
      </el-row>
    </div>
    <GroMoreUpload :dialog-upload-visible="groMoreUploadDisabled" @confirmUpload="handleInspection" @cancelUpload="cancelUpload"></GroMoreUpload>
    <ExcelInspection :excel-data="excelData" :excel-company-id="excelCompanyId" :dialog-form-visible="excelInspectionDisabled" @saveExcelData="bindData" @cancelUpload="cancelBind"></ExcelInspection>
    <GroMoreUploadResult :upload-result-visible="uploadResultDisabled" :upload-result="uploadResult" @closeResultDialog="closeResultDialog"></GroMoreUploadResult>
    <el-table
      v-loading="bindListLoading"
      :data="bindList"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
    >
      <el-table-column align="center" label="广告位 ID" prop="ad_unit_id"></el-table-column>
      <el-table-column align="center" label="竞价类型" prop="bidding_type" :formatter="biddingTypeFormat"></el-table-column>
      <el-table-column align="center" label="代码位 ID" prop="ad_slot_id"></el-table-column>
      <el-table-column align="center" label="代码位名称" prop="ad_slot_name"></el-table-column>
      <el-table-column align="center" label="网络类型" prop="network_name"></el-table-column>
      <el-table-column align="center" label="价格" prop="price"></el-table-column>
      <el-table-column align="center" label="排序方式" prop="sort_type" :formatter="sortTypeFormat"></el-table-column>
      <el-table-column align="center" label="状态" prop="status" :formatter="statusFormat"></el-table-column>
      <el-table-column align="center" label="渲染类型" prop="origin_type" :formatter="originTypeFormat"></el-table-column>
      <el-table-column align="center" label="代码位类型" prop="sub_ad_type" :formatter="subAdTypeFormat"></el-table-column>
      <el-table-column align="center" label="自动排序" prop="smart_sort_switch" :formatter="smartSortSwitchFormat"></el-table-column>
      <el-table-column align="center" label="自动排序周期" prop="smart_sort_fetch_days" :formatter="smartSortFetchDaysFormat"></el-table-column>
      <el-table-column align="center" label="创建时间" prop="created_time" :formatter="createdTimeFormat"></el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />
  </div>
</template>

<style>
.el-row {
  margin-bottom: 10px;
}

.el-col-span {
  display: inline-block;
  width: 80px;
  padding: 6px 0;
  font-size: 14px;
}
</style>
<script>

import { csjBind, placementCsj } from '@/api/ad/placement'
import ExcelInspection from '@/views/ad/placement-components/csj-gromore-excel-inspection'
import GroMoreUpload from '@/views/ad/placement-components/csj-gromore-upload'
import GroMoreUploadResult from '@/views/ad/placement-components/csj-gromore-upload-result'
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import Pagination from '@/components/Pagination'

export default {
  components: { ExcelInspection, Pagination, GroMoreUpload, GroMoreUploadResult },
  data() {
    return {
      appCode: '',
      groMoreUploadDisabled: false,
      uploadLoading: false,
      uploadDisabled: false,
      excelInspectionDisabled: false,
      excelData: [],
      excelAppCode: undefined,
      excelCompanyId: undefined,
      bindListLoading: false,
      uploadResultDisabled: false,
      uploadResult: {},
      bindList: [],
      listQuery: {
        page: 1,
        per_page: 10
      },
      total: 0,
      columnMap: {
        biddingType: {
          0: '标准',
          1: '服务端竞价',
          2: '客户端竞价'
        },
        sortType: {
          1: '价格',
          2: '置顶',
          3: '兜底'
        },
        status: {
          1: '启用',
          4: '关闭'
        },
        originType: {
          0: '不区分',
          1: '优量汇模板渲染1.0',
          2: '自渲染',
          3: '优量汇模板渲染2.0'
        },
        subAdType: {
          1: '插屏或半屏',
          2: '全屏'
        },
        smartSortSwitch: {
          0: '不适用',
          1: '开启',
          2: '关闭'
        }
      }
    }
  },
  created() {
    this.fetchData()
  },
  methods: {
    fetchData() {
      this.bindListLoading = true
      placementCsj(getAppCode(), this.listQuery).then(response => {
        this.bindList = response.data.records
        this.total = response.data.total
        this.bindListLoading = false
      })
    },
    biddingTypeFormat(row, column) {
      return this.columnMap.biddingType[row.bidding_type]
    },
    sortTypeFormat(row, column) {
      if (row.sort_type <= 0) {
        return ''
      }
      return this.columnMap.sortType[row.sort_type]
    },
    statusFormat(row, column) {
      return this.columnMap.status[row.status]
    },
    originTypeFormat(row, column) {
      return this.columnMap.originType[row.origin_type]
    },
    subAdTypeFormat(row, column) {
      if (row.sub_ad_type <= 0) {
        return ''
      }
      return this.columnMap.subAdType[row.sub_ad_type]
    },
    smartSortSwitchFormat(row, column) {
      return this.columnMap.smartSortSwitch[row.smart_sort_switch]
    },
    smartSortFetchDaysFormat(row, column) {
      if (row.smart_sort_fetch_days <= 0) {
        return ''
      }
      return row.smart_sort_fetch_days
    },
    createdTimeFormat(row, column) {
      if (row.created_time <= 0) {
        return ''
      }
      return parseTime(row.created_time)
    },
    handleUpload() {
      this.uploadLoading = true
      this.uploadDisabled = true
      this.groMoreUploadDisabled = true
    },
    cancelUpload() {
      this.uploadLoading = false
      this.uploadDisabled = false
      this.groMoreUploadDisabled = false
    },
    handleInspection(data) {
      this.groMoreUploadDisabled = false
      this.excelData = data.excelData
      this.excelAppCode = data.appCode
      this.excelCompanyId = data.companyId
      this.excelInspectionDisabled = true
    },
    bindData(event) {
      const data = {
        company_id: this.excelCompanyId,
        app_code: this.excelAppCode,
        bind_data: event.data
      }
      const loading = this.$loading({
        lock: true,
        text: 'Loading',
        spinner: 'el-icon-loading',
        background: 'rgba(0, 0, 0, 0.7)'
      });
      csjBind(data).then(response => {
        this.uploadLoading = false
        this.uploadDisabled = false
        this.excelInspectionDisabled = false
        loading.close()
        this.uploadResultDisabled = true
        this.uploadResult = response.data
      })
    },
    cancelBind() {
      this.uploadLoading = false
      this.uploadDisabled = false
      this.excelInspectionDisabled = false
    },
    closeResultDialog() {
      this.uploadResultDisabled = false
    }
  }
}
</script>
<style scoped>
.excel-upload-input {
  display: none;
  z-index: -9999;
}
</style>

