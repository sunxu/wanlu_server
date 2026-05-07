<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row :gutter="20">
        <el-col :span="5">
          <el-select v-model="search.app_code" placeholder="不选则为全部应用" clearable multiple filterable>
            <el-option v-for="row in appList" :key="row.app_code" :label="row.app_name" :value="row.app_code"></el-option>
          </el-select>
        </el-col>
        <el-col :span="5">
          <el-select v-model="search.rule_enable" placeholder="规则状态" clearable>
            <el-option v-for="row in ruleEnableMap" :key="row.key" :label="row.value" :value="row.key"></el-option>
          </el-select>
        </el-col>
        <el-col :span="5">
          <el-input v-model="search.rule_id" placeholder="规则 ID"></el-input>
        </el-col>
        <el-col :span="5">
          <el-input v-model="search.rule_name" placeholder="规则名称"></el-input>
        </el-col>
        <el-col :span="4">
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
            搜索
          </el-button>
          <el-button class="filter-item" type="primary" icon="el-icon-edit" @click="addAlertRule">
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
      <el-table-column align="center" prop="rule_id" label="规则 ID" min-width="90">
        <template slot-scope="scope">
          <span class="clickable-cell"
                @click.stop="previewRule(scope.row)">
            {{ scope.row.rule_id }}
          </span>
        </template>
      </el-table-column>
      <el-table-column align="center" prop="rule_name" label="规则名称" min-width="100">
        <template slot-scope="scope">
          <span class="clickable-cell"
                @click.stop="previewRule(scope.row)">
            {{ scope.row.rule_name }}
          </span>
        </template>
      </el-table-column>
      <el-table-column align="center" label="规则类型" min-width="90">
        <template slot-scope="scope">
          <el-tag
            v-if="scope.row.rule_type === 'advertiser'"
            type="success"
            effect="plain">
            广告主报警
          </el-tag>
          <el-tag
            v-else-if="scope.row.rule_type === 'project'"
            type="warning"
            effect="plain">
            项目报警
          </el-tag>
          <el-tag v-else type="info" effect="plain">
            未知
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column align="center" label="时间点" min-width="100">
        <template slot-scope="scope">
          <el-popover
            v-if="scope.row.time_points && scope.row.time_points.length > 0"
            placement="right"
            width="400"
            trigger="click">
            <el-table :data="scope.row.time_points" max-height="400" fit>
              <el-table-column width="80" property="time" label="时间点"></el-table-column>
              <el-table-column label="规则">
                <template slot-scope="timeScope">
                  <div style="font-size: 12px;">
                    <span v-if="timeScope.row.threshold.cost_percent" style="margin-right: 8px;">
                      <el-tag size="mini" type="info">消耗</el-tag>
                      {{ timeScope.row.threshold.cost_percent.operator }} {{ timeScope.row.threshold.cost_percent.value }}
                    </span>
                    <span v-if="timeScope.row.threshold.roi" style="margin-right: 8px;">
                      <el-tag size="mini" type="success">ROI</el-tag>
                      {{ timeScope.row.threshold.roi.operator }} {{ timeScope.row.threshold.roi.value }}
                    </span>
                    <span v-if="timeScope.row.threshold.attribution_app_24h_roi">
                      <el-tag size="mini" type="warning">巨量ROI</el-tag>
                      {{ timeScope.row.threshold.attribution_app_24h_roi.operator }} {{ timeScope.row.threshold.attribution_app_24h_roi.value }}
                    </span>
                  </div>
                </template>
              </el-table-column>
            </el-table>
            <el-button slot="reference" type="primary" size="mini">
              {{ scope.row.time_points.length }} 个时间点
            </el-button>
          </el-popover>
          <span v-else style="color: #909399;">-</span>
        </template>
      </el-table-column>
      <el-table-column align="center" label="应用">
        <template slot-scope="scope">
          <el-popover
            placement="right"
            width="300"
            trigger="click">
            <el-table :data="scope.row.app_info" max-height="500" scroll-y="100" fit>
              <el-table-column width="150" property="app_code" label="应用 ID"></el-table-column>
              <el-table-column width="150" property="app_name" label="应用名称"></el-table-column>
            </el-table>
            <el-button slot="reference" type="info">{{ scope.row.app_code && scope.row.app_code.length > 0 ? '部分应用' : '全部应用' }}</el-button>
          </el-popover>
        </template>
      </el-table-column>
      <el-table-column align="center" prop="rule_enable" label="规则状态" min-width="110">
        <template slot-scope="scope">
          <el-row>
            <el-col :span="scope.row.rule_enable === 1 ? 24 : 24">
              <el-button v-if="scope.row.rule_enable === 0" type="danger" plain disabled>关闭</el-button>
              <el-button v-if="scope.row.rule_enable === 1" type="success" plain disabled>开启</el-button>
            </el-col>
          </el-row>
        </template>
      </el-table-column>
      <el-table-column align="center" prop="updated_time" label="更新时间" :formatter="formatterTime" min-width="90"></el-table-column>
      <el-table-column align="center" label="操作">
        <template slot-scope="scope">
          <el-dropdown @command="handleCommand">
            <el-button type="primary">
              操作<i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item :command="{ row:scope.row, type: 'edit' }">编辑</el-dropdown-item>
              <el-dropdown-item :command="{ row:scope.row, type: 'enable' }">{{ scope.row.rule_enable === 1 ? '关闭' : '开启' }}</el-dropdown-item>
              <el-dropdown-item :command="{ row:scope.row, type: 'delete' }">删除</el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown>
        </template>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="search.page" :limit.sync="search.per_page" @pagination="fetchQuery" />
    <RuleForm v-if="formVisible" :form-visible="formVisible" :dialog-status="formStatus" :rule-id="formRuleId" @closeDialog="closeForm"></RuleForm>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { alertRuleList, enableAlertRule, deleteAlertRule } from '@/api/put/alert-rule'
import { parseTime } from '@/utils'
import { Message } from 'element-ui'
import Pagination from '@/components/Pagination'
import RuleForm from './rule-form'

export default {
  name: 'AlertRule',
  components: { Pagination, RuleForm },
  data() {
    return {
      ruleEnableMap: [
        { key: 0, value: '关闭' },
        { key: 1, value: '开启' }
      ],
      appList: this.$store.getters.userInfo.apps,
      search: {
        rule_enable: undefined,
        rule_id: undefined,
        rule_name: undefined,
        app_code: undefined,
        page: 1,
        per_page: 10
      },
      listLoading: false,
      list: [],
      total: 0,
      formVisible: false,
      formStatus: 'create',
      formRuleId: 0
    }
  },
  created() {
    this.fetchQuery()
  },
  methods: {
    handleFilter() {
      this.search.page = 1
      this.fetchQuery()
    },
    fetchQuery() {
      this.listLoading = true
      alertRuleList(getAppCode(), this.search).then(response => {
        let listRaw = response.data.records
        if (!listRaw || listRaw.length <= 0) {
          this.list = []
          this.total = 0
          this.listLoading = false
          return
        }
        listRaw.forEach(item => {
          let codes = item.app_code || []
          // app_code 是数组格式，判断是否为空数组
          if (codes.length > 0) {
            // 有指定应用，从 appList 中筛选出匹配的应用
            item.app_info = this.appList.filter(app => codes.includes(app.app_code))
          } else {
            // 没有指定应用，显示所有应用
            item.app_info = this.appList
          }
        })
        this.list = listRaw
        this.total = response.data.total
        this.listLoading = false
      })
    },
    formatterTime(row, column, cellValue, index) {
      if (column.property === 'updated_time') {
        return parseTime(cellValue)
      }
      return ''
    },
    previewRule(row) {
      this.formRuleId = row.rule_id
      this.formStatus = 'preview'
      this.formVisible = true
    },
    handleCommand(item) {
      switch (item.type) {
        case 'edit':
          this.formRuleId = item.row.rule_id
          this.formStatus = 'update'
          this.formVisible = true
          break
        case 'enable':
          this.editEnable(item.row)
          break
        case 'delete':
          this.deleteRule(item.row)
          break
      }
    },
    editEnable(row) {
      const isEnable = row.rule_enable === 1
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
      const newEnable = row.rule_enable === 1 ? 0 : 1
      enableAlertRule(getAppCode(), row.rule_id, newEnable).then(response => {
        this.fetchQuery()
        Message({
          showClose: true,
          message: 'success',
          type: 'success',
          duration: 5 * 1000
        })
      })
    },
    deleteRule(row) {
      this.$confirm(
        '确认要删除该规则吗？',
        '提示',
        {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )
        .then(() => {
          deleteAlertRule(getAppCode(), row.rule_id).then(response => {
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
    addAlertRule() {
      this.formRuleId = 0
      this.formStatus = 'create'
      this.formVisible = true
    },
    closeForm() {
      this.formVisible = false
      this.fetchQuery()
    }
  }
}
</script>

<style scoped>
.clickable-cell {
  color: #409EFF;
  cursor: pointer;
}
</style>
