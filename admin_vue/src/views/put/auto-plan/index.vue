<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row :gutter="20">
        <el-col :span="5">
          <el-select v-model="search.advertiser_ids" placeholder="选择广告主" clearable multiple filterable>
            <el-option v-for="row in advertiserList" :key="row.value" :label="row.label" :value="row.value"></el-option>
          </el-select>
        </el-col>
        <el-col :span="5">
          <el-select v-model="search.plan_enable" placeholder="规则状态" clearable>
            <el-option v-for="row in planEnableMap" :key="row.key" :label="row.value" :value="row.key"></el-option>
          </el-select>
        </el-col>
        <el-col :span="5">
          <el-input v-model="search.plan_id" placeholder="规则 ID"></el-input>
        </el-col>
        <el-col :span="5">
          <el-input v-model="search.plan_name" placeholder="规则名称"></el-input>
        </el-col>
        <el-col :span="4">
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
            搜索
          </el-button>
          <el-button class="filter-item" type="primary" icon="el-icon-edit" @click="addAutoPlan">
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
      :row-class-name="tableRowClassName"
    >
      <el-table-column align="center" prop="plan_id" label="规则 ID" min-width="90">
        <template slot-scope="scope">
        <span class="clickable-cell"
              @click.stop="previewRule(scope.row)">
              {{ scope.row.plan_id }}
        </span>
        </template>
      </el-table-column>
      <el-table-column align="center" prop="plan_name" label="规则名称" min-width="100">
        <template slot-scope="scope">
          <span class="clickable-cell"
                @click.stop="previewRule(scope.row)">
                {{ scope.row.plan_name }}
          </span>
        </template>
      </el-table-column>
      <el-table-column
          align="center"
          label="规则类型"
          min-width="90">
        <template slot-scope="scope">
          <el-tag
              v-if="scope.row.execute_operate === 'close_plan'"
              type="danger"
              effect="plain">
            关闭广告
          </el-tag>
          <el-tag
              v-else-if="scope.row.execute_operate === 'open_plan'"
              type="success"
              effect="plain">
            开启广告
          </el-tag>
          <el-tag v-else type="info" effect="plain">
            未知
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column align="center" label="广告主">
        <template slot-scope="scope">
          <el-popover
            placement="right"
            width="300"
            trigger="click">
            <el-table :data="scope.row.advertiser_info">
              <el-table-column width="150" property="advertiser_id" label="广告主 ID"></el-table-column>
              <el-table-column width="150" property="advertiser_name" label="广告主名称"></el-table-column>
            </el-table>
            <el-button slot="reference" type="info">{{ scope.row.advertisers.isAll === 1 ? '全部广告主': '部分广告主' }}</el-button>
          </el-popover>
        </template>
      </el-table-column>
      <el-table-column align="center" prop="plan_enable" label="规则状态" min-width="110">
        <template slot-scope="scope">
          <el-row>
            <el-col :span="scope.row.plan_enable === 1 ? 24 : 24">
              <el-button v-if="scope.row.plan_enable === 0" type="danger" plain disabled>关闭</el-button>
              <el-button v-if="scope.row.plan_enable === 1" type="success" plain disabled>开启</el-button>
            </el-col>
            <!-- <el-col :span="scope.row.plan_enable === 1 ? 12 : 0">
              <el-button v-if="scope.row.plan_status === 1" type="success" :loading="true" plain disabled>运行中</el-button>
              <el-button v-if="scope.row.plan_status === 0" type="primary" plain disabled>待运行</el-button>
            </el-col> -->
          </el-row>
        </template>
      </el-table-column>
      <el-table-column align="center" prop="last_start_time" label="最后运行时间" :formatter="formatterTime" min-width="90"></el-table-column>
      <el-table-column align="center" prop="updated_time" label="更新时间" :formatter="formatterTime" min-width="90"></el-table-column>
      <el-table-column align="center" label="操作">
        <template slot-scope="scope">
          <el-dropdown @command="handleCommand">
            <el-button type="primary">
              操作<i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item :command="{ row:scope.row, type: 'edit' }">编辑</el-dropdown-item>
<!--              <el-dropdown-item :command="{ row:scope.row, type: 'log' }">查看日志</el-dropdown-item>-->
              <el-dropdown-item :command="{ row:scope.row, type: 'enable' }">{{ scope.row.plan_enable === 1 ? '关闭' : '开启' }}</el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown>
        </template>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="search.page" :limit.sync="search.per_page" @pagination="fetchQuery" />
    <Conflict v-if="conflictVisible" :dialog-visible="conflictVisible" :conflict-list="conflictList" :conflict-row="conflictRow" @closeDialog="closeConflictDialog" @confirmDialog="confirmConflictDialog"></Conflict>
    <PlanForm v-if="formVisible" :form-visible="formVisible" :dialog-status="formStatus" :plan-id="formPlanId" :advertiser-list="advertiserList" @closeDialog="closeForm"></PlanForm>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { advertisers } from '@/api/common'
import { autoPlanList, checkoutConflict, editAutoPlanEnable } from '@/api/put/auto-planV2'
import { parseTime } from '@/utils'
import { Message } from 'element-ui'
import Pagination from '@/components/Pagination'
import Conflict from './conflict'
import PlanForm from './plan-form'

export default {
  name: 'AutoPlan',
  components: { Pagination, Conflict, PlanForm },
  data() {
    return {
      planEnableMap: [
        { key: 0, value: '关闭' },
        { key: 1, value: '开启' }
      ],
      advertiserList: [],
      search: {
        plan_enable: undefined,
        advertiser_ids: undefined,
        plan_id: undefined,
        plan_name: undefined,
        page: 1,
        per_page: 10
      },
      listLoading: false,
      list: [],
      total: 0,
      conflictVisible: false,
      conflictList: [],
      conflictRow: {
        row: {}
      },
      formVisible: false,
      formStatus: 'create',
      formPlanId: 0
    }
  },
  created() {
    this.getAdvertiserList()
    this.fetchQuery()
  },
  methods: {
    getAdvertiserList() {
      advertisers(getAppCode()).then(response => {
        const advertiserArr = []
        response.data.records.forEach(function(item) {
          advertiserArr.push({ value: item.advertiser_id, label: item.advertiser_id + '-' + item.advertiser_name })
        })
        this.advertiserList = advertiserArr
      })
    },
    handleFilter() {
      this.search.page = 1
      this.fetchQuery()
    },
    fetchQuery() {
      this.listLoading = true
      autoPlanList(getAppCode(), this.search).then(response => {
        this.list = response.data.records
        this.total = response.data.total
        this.listLoading = false
      })
    },
    formatterTime(row, column, cellValue, index) {
      switch (column.property) {
        case 'last_start_time':
          if (row.plan_status === 1) {
            return ''
          }
          return parseTime(cellValue)
        case 'updated_time':
          return parseTime(cellValue)
        case 'next_start_time':
          if (row.plan_enable === 0 || row.plan_status === 1) {
            return ''
          }
          return parseTime(cellValue)
        case 'running_time':
          if (row.plan_status === 1) {
            return ''
          }
          return (row.last_end_time - row.last_start_time) + '秒'
      }
      return ''
    },
    previewRule(row) {
      this.formPlanId = row.plan_id
      this.formStatus = 'preview'
      this.formVisible = true
    },
    handleCommand(item) {
      switch (item.type) {
        case 'edit':
          this.formPlanId = item.row.plan_id
          this.formStatus = 'update'
          this.formVisible = true
          break
        case 'log':
          break
        case 'enable':
          this.editEnable(item.row)
          break
      }
    },
    editEnable(row) {
      const isEnable = row.plan_enable === 1
      const actionText = isEnable ? '关闭' : '开启'
      this.$confirm(
          `确认要${actionText}该规则吗？`,
          '提示',
          {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning'
          }
      )
          .then(() => {
            this.doEditEnable(row)
          })
          .catch(() => {
          })
    },
    doEditEnable(row) {
      const planId = row.plan_id
      const advertiserIds = row.advertisers.ids
      const keys = new Set()
      if (row.threshold) {
        Object.keys(row.threshold).forEach(k => {
          const val = row.threshold[k]
          if (val && typeof val === 'object' && ('value' in val || 'operator' in val)) {
            keys.add(k)
          } else if (val && typeof val === 'object') {
            Object.keys(val).forEach(subKey => keys.add(subKey))
          }
        })
      }
      const thresholdKeys = Array.from(keys)
      if (row.plan_enable === 1) {
        this.planEnable(planId)
        return
      }
      checkoutConflict(getAppCode(), { advertiser_ids: advertiserIds, threshold_keys: thresholdKeys }).then(response => {
        if (response.data.records.length <= 0) {
          this.planEnable(planId)
          return
        }
        this.conflictList = response.data.records
        this.conflictVisible = true
        this.conflictRow = {
          row: { planId: planId }
        }
      })
    },
    planEnable(planId) {
      editAutoPlanEnable(getAppCode(), planId).then(response => {
        this.fetchQuery()
        Message({
          showClose: true,
          message: 'success',
          type: 'success',
          duration: 5 * 1000
        })
      })
    },
    closeConflictDialog() {
      this.conflictVisible = false
    },
    confirmConflictDialog(row) {
      this.conflictVisible = false
      this.planEnable(row.row.planId)
    },
    addAutoPlan() {
      this.formPlanId = 0
      this.formStatus = 'create'
      this.formVisible = true
    },
    closeForm() {
      this.formVisible = false
      this.fetchQuery()
    },
    tableRowClassName(row) {
      if (row.row.last_execute_result === 0) {
        return 'warning-row'
      }
      return ''
    }
  }
}
</script>

<style scoped>
.el-table >>> .warning-row {
  color: red;
}
.clickable-cell {
  color: #409EFF;
  cursor: pointer;
}
</style>
