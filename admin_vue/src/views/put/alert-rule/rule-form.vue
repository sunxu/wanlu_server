<template>
  <div class="app-container">
    <el-dialog
      :title="dialogStatus === 'create'
        ? '新增报警规则'
        : dialogStatus === 'update'
          ? '编辑报警规则'
          : '规则预览'"
      :visible.sync="formVisible"
      width="80%"
      :before-close="closeDialog"
    >
      <el-form ref="dataForm" :rules="formRules" :model="formData" label-width="100px" label-position="left" :disabled="isPreview">
        <el-form-item label="规则模板" prop="template" v-if="!isPreview">
          <el-select v-model="formData.template" placeholder="选择模板" clearable filterable @change="handleTemplateChange">
            <el-option v-for="row in templateList" :key="row.rule_id" :label="row.rule_name" :value="row.rule_id"></el-option>
          </el-select>
        </el-form-item>

        <el-form-item label="规则名称" prop="rule_name">
          <el-input v-model="formData.rule_name" placeholder="规则名称" style="min-width: 200px; max-width: 400px;"/>
        </el-form-item>

        <el-form-item label="规则类型" prop="rule_type">
          <el-radio-group v-model="formData.rule_type">
            <el-radio label="advertiser">广告主报警</el-radio>
            <el-radio label="project">项目报警</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="应用" prop="app_code">
          <el-select v-model="formData.app_code" placeholder="不选则为全部应用" clearable multiple filterable>
            <el-option v-for="row in appList" :key="row.app_code" :label="row.app_name" :value="row.app_code"></el-option>
          </el-select>
          <el-button v-if="!isPreview" type="primary" style="margin-left: 10px" @click="clearAppList">清空</el-button>
        </el-form-item>

        <el-form-item label="规则设置" prop="time_points">
          <div class="rule-setting-actions">
            <el-button v-if="!isPreview" type="primary" @click="addTimePoint">新增时间点</el-button>
            <span style="margin-left: 10px; color: #909399; font-size: 12px;">
              系统会在每日配置的时间点进行检查，支持半点（00:00、00:30...）
            </span>
          </div>
        </el-form-item>

        <div v-if="(formData.time_points || []).length" class="rule-setting-table">
          <div class="rule-setting-header">
            <div class="rule-setting-col-time">时间点</div>
            <div class="rule-setting-col-threshold">规则</div>
            <div class="rule-setting-col-actions"></div>
          </div>
          <div v-for="(point, index) in formData.time_points" :key="point.id || index" class="rule-setting-row">
            <div class="rule-setting-col-time">
              <el-select
                v-model="point.time"
                placeholder="选择时间"
                style="width: 120px"
                filterable
                :disabled="isPreview"
              >
                <el-option
                  v-for="time in getAvailableTimeOptions(index)"
                  :key="time"
                  :label="time"
                  :value="time"
                ></el-option>
              </el-select>
            </div>
            <div class="rule-setting-col-threshold">
              <div class="threshold-grid">
                <div v-for="item in thresholdItems" :key="item.key" class="threshold-item" v-if="!isPreview || point.threshold[item.key].checked">
                  <el-checkbox v-model="point.threshold[item.key].checked" :disabled="isPreview">{{ item.label }}</el-checkbox>
                  <el-select v-model="point.threshold[item.key].operator" :disabled="!point.threshold[item.key].checked || isPreview" style="width: 80px; margin-left: 8px;">
                    <el-option v-for="(value, key) in operators" :key="key" :label="value" :value="key"></el-option>
                  </el-select>
                  <el-input-number
                    v-model="point.threshold[item.key].value"
                    :disabled="!point.threshold[item.key].checked || isPreview"
                    :precision="item.precision"
                    :step="item.step"
                    :min="item.min"
                    :max="item.max"
                    controls-position="right"
                    style="width: 120px; margin-left: 8px;"
                  ></el-input-number>
                  <span v-if="item.unit" class="threshold-unit">{{ item.unit }}</span>
                </div>
              </div>
            </div>
            <div class="rule-setting-col-actions">
              <el-button v-if="!isPreview" type="danger" size="mini" @click="removeTimePoint(index)">删除</el-button>
            </div>
          </div>
        </div>

        <el-form-item label="代投配置" prop="delegate_ids">
          <el-select v-model="formData.delegate_ids" placeholder="选择代投配置" clearable multiple filterable>
            <el-option v-for="row in delegateList" :key="row.delegate_id" :label="row.delegate_name" :value="row.delegate_id"></el-option>
          </el-select>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="closeDialog">{{ isPreview ? '关闭' : '取消' }}</el-button>
        <el-button v-if="!isPreview" type="primary" @click="confirmDialog">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { alertRuleInfo, saveAlertRule, updateAlertRule, alertRuleList } from '@/api/put/alert-rule'
import { alertDelegateList } from '@/api/put/alert-rule'
import { Message } from 'element-ui'

export default {
  name: 'RuleForm',
  props: ['dialogStatus', 'formVisible', 'ruleId'],
  data() {
    // 生成时间点选项（00:00 - 23:30，每30分钟一个）
    const generateTimeOptions = () => {
      const options = []
      for (let h = 0; h < 24; h++) {
        for (let m = 0; m < 60; m += 30) {
          const hour = h.toString().padStart(2, '0')
          const minute = m.toString().padStart(2, '0')
          options.push(`${hour}:${minute}`)
        }
      }
      return options
    }

    // 创建空的时间点对象
    const createEmptyTimePoint = () => {
      const threshold = {}
      this.thresholdItems.forEach(item => {
        threshold[item.key] = {
          checked: false,
          operator: 'ge',
          value: 0
        }
      })
      return threshold
    }

    const checkTimePoints = (rule, value, callback) => {
      const timePoints = value || []
      if (timePoints.length <= 0) {
        return callback(new Error('请至少添加一个时间点'))
      }
      const timeList = timePoints.map(p => p.time).filter(Boolean)
      if (timeList.length !== timePoints.length) {
        return callback(new Error('请完善每个时间点的时间'))
      }
      if (new Set(timeList).size !== timeList.length) {
        return callback(new Error('时间点不能重复'))
      }
      for (let i = 0; i < timePoints.length; i++) {
        const item = timePoints[i]
        const threshold = item.threshold || {}
        const hasThreshold = Object.keys(threshold).some(key => threshold[key] && threshold[key].checked)
        if (!hasThreshold) {
          return callback(new Error(`请为时间点 ${item.time} 至少选择一个规则`))
        }
      }
      return callback()
    }

    return {
      appList: [],
      delegateList: [],
      templateList: [],
      timeOptions: generateTimeOptions(),
      previousTemplates: [],
      formRules: {
        rule_name: [
          { required: true, message: '请输入规则名称', trigger: 'blur' }
        ],
        rule_type: [
          { required: true, message: '请选择规则类型', trigger: 'change' }
        ],
        time_points: [
          { required: true, validator: checkTimePoints, trigger: 'blur' }
        ],
        delegate_ids: [
          { required: true, message: '请选择代投配置', trigger: 'change', type: 'array' }
        ]
      },
      operators: {
        'lt': '<',
        'le': '<=',
        'gt': '>',
        'ge': '>=',
        'eq': '='
      },
      thresholdItems: [
        { key: 'cost_percent', label: '消耗占比', unit: '', precision: 2, step: 0.01, min: 0, max: 1 },
        { key: 'roi', label: 'ROI', unit: '', precision: 2, step: 0.1, min: 0 },
        { key: 'attribution_app_24h_roi', label: '巨量ROI', unit: '', precision: 2, step: 0.1, min: 0 }
      ],
      formData: {
        template: null,
        rule_name: '',
        rule_type: 'advertiser',
        app_code: [],
        delegate_ids: [],
        time_points: []
      },
      createEmptyTimePoint
    }
  },
  computed: {
    isPreview() {
      return this.dialogStatus === 'preview'
    }
  },
  created() {
    this.getAppList()
    this.getDelegateList()
    this.fetchTemplateList()
    if (this.dialogStatus === 'update' || this.dialogStatus === 'preview') {
      this.getAlertRuleInfo()
    }
  },
  methods: {
    getAppList() {
      this.appList = this.$store.getters.userInfo.apps
    },
    getDelegateList() {
      const search = {
        page: 1,
        per_page: 999999
      }
      alertDelegateList(search).then(response => {
        this.delegateList = response.data.records || []
      })
    },
    // 获取模板列表
    fetchTemplateList() {
      const search = {
        page: 1,
        per_page: 999999,
        rule_enable: 1
      }
      alertRuleList(getAppCode(), search).then(response => {
        this.templateList = response.data.records || []
      })
    },
    clearAppList() {
      this.formData.app_code = []
    },
    // 获取可用的时间选项（排除已选择的）
    getAvailableTimeOptions(currentIndex) {
      const timePoints = this.formData.time_points || []
      return this.timeOptions.filter(time => {
        const usedByOther = timePoints.some((point, idx) => idx !== currentIndex && point.time === time)
        return !usedByOther
      })
    },
    // 添加时间点
    addTimePoint() {
      const timePoints = this.formData.time_points || []
      const usedTimes = timePoints.map(p => p.time)
      const availableTime = this.timeOptions.find(t => !usedTimes.includes(t)) || this.timeOptions[0]

      this.formData.time_points = timePoints.concat([{
        id: `${Date.now()}_${Math.random().toString(16).slice(2)}`,
        time: availableTime,
        threshold: this.createEmptyTimePoint()
      }])
    },
    // 删除时间点
    removeTimePoint(index) {
      this.formData.time_points = this.formData.time_points.filter((_, i) => i !== index)
    },
    // 构建时间点数据（提交时使用）
    buildTimePoints() {
      const timePoints = this.formData.time_points || []
      return timePoints.map(point => {
        const threshold = {}
        const t = point.threshold || {}
        Object.keys(t).forEach(key => {
          if (!t[key] || !t[key].checked) return
          threshold[key] = {
            operator: t[key].operator,
            value: t[key].value
          }
        })
        return {
          time: point.time,
          threshold: threshold
        }
      }).sort((a, b) => a.time.localeCompare(b.time))
    },
    buildRequestPayload() {
      return {
        rule_name: this.formData.rule_name,
        rule_type: this.formData.rule_type,
        app_code: this.formData.app_code,
        delegate_ids: this.formData.delegate_ids,
        time_points: this.buildTimePoints()
      }
    },
    closeDialog() {
      if (this.isPreview) {
        this.$emit('closeDialog')
        return
      }
      this.$confirm('是否取消？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.$emit('closeDialog')
      }).catch(() => {
        this.$message({
          type: 'info',
          message: '已取消操作'
        })
      })
    },
    confirmDialog() {
      this.$refs.dataForm.validate((valid) => {
        if (!valid) {
          return
        }

        this.$confirm('是否保存？', '提示', {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        }).then(() => {
          const payload = this.buildRequestPayload()
          if (this.dialogStatus === 'create') {
            saveAlertRule(getAppCode(), payload).then(response => {
              this.$emit('closeDialog')
              Message({
                showClose: true,
                message: 'success',
                type: 'success',
                duration: 5 * 1000
              })
            })
          } else {
            updateAlertRule(getAppCode(), this.ruleId, payload).then(response => {
              this.$emit('closeDialog')
              Message({
                showClose: true,
                message: 'success',
                type: 'success',
                duration: 5 * 1000
              })
            })
          }
        }).catch(() => {
          this.$message({
            type: 'info',
            message: '已取消操作'
          })
        })
      })
    },
    // 解析阈值对象（用于编辑）
    parseThreshold(threshold) {
      const result = {}
      this.thresholdItems.forEach(item => {
        result[item.key] = {
          checked: !!(threshold && threshold[item.key]),
          operator: (threshold && threshold[item.key] && threshold[item.key].operator) || 'ge',
          value: (threshold && threshold[item.key] && threshold[item.key].value) || 0
        }
      })
      return result
    },
    // 规范化时间点数据（从后端获取的数据转换为前端格式）
    normalizeTimePoints(data) {
      const rawTimePoints = data.time_points || []

      // 兼容旧版本：如果有 threshold 且没有 time_points，转换成一个时间点
      if (rawTimePoints.length === 0 && data.threshold && Object.keys(data.threshold).length > 0) {
        return [{
          id: `${Date.now()}_${Math.random().toString(16).slice(2)}`,
          time: this.timeOptions[0],
          threshold: this.parseThreshold(data.threshold)
        }]
      }

      return rawTimePoints.map(point => {
        return {
          id: point.id || `${Date.now()}_${Math.random().toString(16).slice(2)}`,
          time: point.time || this.timeOptions[0],
          threshold: this.parseThreshold(point.threshold || {})
        }
      }).sort((a, b) => a.time.localeCompare(b.time))
    },
    // 模板选择变化
    handleTemplateChange(val) {
      const oldVal = this.previousTemplates || []
      this.previousTemplates = val || []

      if (!val) return

      const added = val

      alertRuleInfo(getAppCode(), added).then(response => {
        const data = response.data
        const timePoints = this.normalizeTimePoints(data)

        this.formData.time_points = timePoints
        // 保持当前选择的模板
        this.formData.template = added
      })
    },
    getAlertRuleInfo() {
      alertRuleInfo(getAppCode(), this.ruleId).then(response => {
        const data = response.data
        const timePoints = this.normalizeTimePoints(data)

        // 获取模板信息（如果有）
        const templateIds = data.template_ids || []

        this.formData = {
          template: templateIds.length > 0 ? templateIds[0] : null,
          rule_name: data.rule_name,
          rule_type: data.rule_type || 'advertiser',
          app_code: data.app_code || [],
          delegate_ids: data.delegate_ids || [],
          time_points: timePoints
        }

        if (templateIds.length > 0) {
          this.previousTemplates = templateIds
        }
      })
    }
  }
}
</script>

<style scoped>
.rule-setting-actions {
  text-align: right;
}

.rule-setting-table {
  margin-top: 10px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  overflow: hidden;
}

.rule-setting-header,
.rule-setting-row {
  display: flex;
  align-items: flex-start;
}

.rule-setting-header {
  background: #f5f7fa;
  color: #606266;
  font-weight: 500;
  padding: 10px;
}

.rule-setting-row {
  padding: 12px 10px;
  border-top: 1px solid #ebeef5;
}

.rule-setting-col-time {
  width: 140px;
  padding-right: 10px;
}

.rule-setting-col-threshold {
  flex: 1;
  padding-right: 10px;
  min-width: 0;
}

.rule-setting-col-actions {
  width: 80px;
  text-align: right;
}

.threshold-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 14px;
  width: 100%;
  align-items: center;
}

.threshold-item {
  display: flex;
  align-items: center;
  flex: 0 0 auto;
}

.threshold-unit {
  margin-left: 6px;
  color: #909399;
}
</style>
