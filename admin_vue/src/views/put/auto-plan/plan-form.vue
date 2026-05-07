<template>
  <div class="app-container">
    <el-dialog
        :title="dialogStatus === 'create'
          ? '新增计划规则'
          : dialogStatus === 'update'
            ? '编辑计划规则'
            : '规则预览'"
      :visible.sync="formVisible"
      width="70%"
      :before-close="closeDialog"
    >
      <el-form ref="dataForm" :rules="formRules" :model="formData" label-width="100px" label-position="left" :disabled="isPreview">
        <el-form-item label="计划规则模板" prop="advertiser_ids" v-if="!isPreview">
          <el-select v-model="formData.template" placeholder="选择模板" clearable filterable @change="handleTemplateChange">
            <el-option v-for="row in  tempList" :key="row.plan_id" :label="row.plan_name" :value="row.plan_id"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="广告主" prop="advertiser_ids">
          <el-select v-model="formData.advertiser_ids" placeholder="不选则为全部广告主" clearable multiple filterable>
            <el-option v-for="row in advertiserList" :key="row.value" :label="row.label" :value="row.value"></el-option>
          </el-select>
          <el-button v-if="!isPreview" type="primary" style="margin-left: 10px" @click="clearAdvertiserList">清空</el-button>
        </el-form-item>
        <el-form-item label="规则名称" prop="plan_name">
          <el-input v-model="formData.plan_name" placeholder="规则名称" style="min-width: 200px; max-width: 400px;"/>
        </el-form-item>
      
        <el-form-item label="操作类型" prop="execute_operate">
          <el-select v-model="formData.execute_operate">
            <el-option label="关闭" value="close_plan"></el-option>
            <el-option label="开启" value="open_plan"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="规则设置" prop="execute_rule">
          <div class="rule-setting-actions">
            <el-button v-if="!isPreview" type="primary" @click="openRuleSetting">新增规则</el-button>
          </div>
        </el-form-item>
          <div v-if="(formData.execute_rule.rules || []).length" class="rule-setting-table">
            <div class="rule-setting-header">
              <div class="rule-setting-col-time">时间点</div>
              <div class="rule-setting-col-threshold">规则</div>
              <div class="rule-setting-col-actions"></div>
            </div>
            <div v-for="(rule, index) in formData.execute_rule.rules" :key="rule.id" class="rule-setting-row">
              <div class="rule-setting-col-time">
                <el-select v-model="rule.time" placeholder="选择时间" style="width: 120px" filterable>
                  <el-option v-for="item in getAvailableHourOptions(index)" :key="item" :label="item" :value="item"></el-option>
                </el-select>
              </div>
              <div class="rule-setting-col-threshold">
                <div class="threshold-grid">
                  <div v-for="item in thresholdItems" :key="item.key" class="threshold-item" v-if="!isPreview || rule.threshold[item.key].checked">
                    <el-checkbox v-model="rule.threshold[item.key].checked">{{ item.label }}</el-checkbox>
                    <el-select v-model="rule.threshold[item.key].operator" :disabled="!rule.threshold[item.key].checked" style="width: 80px; margin-left: 8px;">
                      <el-option v-for="(value, key) in operators" :key="key" :label="value" :value="key"></el-option>
                    </el-select>
                    <el-input-number
                      v-model="rule.threshold[item.key].value"
                      :disabled="!rule.threshold[item.key].checked"
                      :precision="item.precision"
                      :step="item.step"
                      :min="0"
                      :max="item.max"
                      controls-position="right"
                      style="width: 120px; margin-left: 8px;"
                    ></el-input-number>
                    <span v-if="item.unit" class="threshold-unit">{{ item.unit }}</span>
                  </div>
                </div>
              </div>
              <div class="rule-setting-col-actions">
                <el-button v-if="!isPreview" type="danger" size="mini" @click="removeRule(index)">删除</el-button>
              </div>
            </div>
          </div>
          <!-- <el-input-number v-model="formData.execute_rule.step" placeholder="频率" style="width: 100px;" :min="0" controls-position="right"></el-input-number>
          <el-select v-model="formData.execute_rule.type" placeholder="周期" style="width: 100px;margin-left: 10px;">
            <el-option v-for="row in executeType" :key="row.key" :label="row.value" :value="row.key" :disabled="row.disabled"></el-option>
          </el-select> -->
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="closeDialog">{{ isPreview ? '关闭' : '取消' }}</el-button>
        <el-button v-if="!isPreview" type="primary" @click="confirmDialog">确 定</el-button>
      </span>
    </el-dialog>
    <Conflict v-if="conflictVisible" :dialog-visible="conflictVisible" :conflict-list="conflictList" @closeDialog="closeConflictDialog" @confirmDialog="confirmConflictDialog"></Conflict>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { autoPlanInfo, autoPlanList, checkoutConflict, editAutoPlan, saveAutoPlan } from '@/api/put/auto-planV2'
import { Message } from 'element-ui'
import Conflict from './conflict'

export default {
  name: 'PlanForm',
  components: { Conflict },
  props: ['dialogStatus', 'formVisible', 'planId', 'advertiserList'],
  data() {
    const checkExecuteRule = (rule, value, callback) => {
      const rules = (value && value.rules) || []
      if (rules.length <= 0) {
        return callback(new Error('请新增至少一条规则'))
      }
      const timeList = rules.map(r => r.time).filter(Boolean)
      if (timeList.length !== rules.length) {
        return callback(new Error('请完善每条规则的时间点'))
      }
      if (new Set(timeList).size !== timeList.length) {
        return callback(new Error('时间点不能重复'))
      }
      for (let i = 0; i < rules.length; i++) {
        const item = rules[i]
        // if (!item.execute_operate) {
        //   return callback(new Error('请完善每条规则的操作'))
        // }
        const threshold = item.threshold || {}
        const hasThreshold = Object.keys(threshold).some(key => threshold[key] && threshold[key].checked)
        if (!hasThreshold) {
          return callback(new Error('请为每条规则至少选择一个阈值'))
        }
      }
      return callback()
    }
    return {
      formRules: {
        plan_name: [
          { required: true, message: '请输入计划名称', trigger: 'blur' }
        ],
        execute_operate: [
          { required: true, message: '请选择执行操作', trigger: 'blur' }
        ],
        execute_rule: [
          { required: true, validator: checkExecuteRule, trigger: 'blur' }
        ]
      },
      tempList: [],
      total: 0,
      formData: {
        template: [],
        advertiser_ids: [],
        plan_name: '',
        threshold: {},
        execute_operate: '',
        execute_rule: {
          step: 0,
          type: 'hour',
          rules: []
        }
      },
      operators: {
        'lt': '<',
        'le': '<=',
        'gt': '>',
        'ge': '>=',
        'eq': '='
      },
      thresholdItems: [
        { key: 'roi', label: 'ROI', unit: '', precision: 2, step: 0.1 },
        { key: 'arpu', label: 'ARPU', unit: '', precision: 2, step: 0.1 },
        { key: 'cost', label: '消耗', unit: '', precision: 2, step: 0.1 },
        { key: 'active_cost', label: '激活成本', unit: '', precision: 2, step: 0.1 },
        { key: 'key_action_cost', label: '关键行为成本', unit: '', precision: 2, step: 0.1 },
        { key: 'attribution_app_24h_roi', label: '巨量ROI', unit: '', precision: 2, step: 0.1 }
      ],
      hourOptions: Array.from({ length: 48 }, (_, i) => {
        const h = Math.floor(i / 2)
        const m = i % 2 === 0 ? '00' : '30'
        return `${String(h).padStart(2, '0')}:${m}`
      }),
      executeType: [
        { key: 'hour', value: '小时', disabled: false },
        { key: 'day', value: '天', disabled: true }
      ],
      conflictVisible: false,
      conflictList: []
    }
  },
  computed: {
    isPreview() {
      return this.dialogStatus === 'preview'
    }
  },
  created() {
    this.fetchQuery()
    if (this.dialogStatus === 'update' || this.dialogStatus === 'preview') {
      this.getAutoPlanInfo()
    }
  },
  methods: {
    clearAdvertiserList() {
      this.formData.advertiser_ids = []
    },
    fetchQuery() {
      const search = {
        plan_enable: undefined,
        advertiser_ids: undefined,
        plan_id: undefined,
        plan_name: undefined,
        type:"all",
        page: 1,
        per_page: 9999999
      }
      autoPlanList(getAppCode(), search).then(response => {
        this.tempList = response.data.records
        this.total = response.data.total
      })
    },
    openRuleSetting() {
      this.addRule()
    },
    createEmptyThreshold() {
      const result = {}
      this.thresholdItems.forEach(item => {
        result[item.key] = {
          checked: false,
          operator: 'eq',
          value: 0
        }
      })
      return result
    },
    addRule() {
      const rules = this.formData.execute_rule.rules || []
      const defaultTime = this.hourOptions.find(t => !rules.some(r => r.time === t)) || this.hourOptions[0]
      this.formData.execute_rule.rules = rules.concat([{
        id: `${Date.now()}_${Math.random().toString(16).slice(2)}`,
        time: defaultTime,
        // execute_operate: this.formData.execute_operate || '',
        threshold: this.createEmptyThreshold()
      }])
    },
    removeRule(index) {
      const rules = this.formData.execute_rule.rules || []
      this.formData.execute_rule.rules = rules.filter((_, i) => i !== index)
    },
    getAvailableHourOptions(currentIndex) {
      const rules = this.formData.execute_rule.rules || []
      return this.hourOptions.filter(time => {
        const usedByOther = rules.some((rule, idx) => idx !== currentIndex && rule.time === time)
        return !usedByOther
      })
    },
    buildThresholdFromRules() {
      const threshold = {}
      const thresholdKeys = new Set()
      const rules = (this.formData.execute_rule && this.formData.execute_rule.rules) || []
      //rules 排序
      rules.sort((a, b) => {
        return a.time.localeCompare(b.time)
      })
      rules.forEach(rule => {
        const timeKey = rule.time || '00:00'
        const t = rule.threshold || {}
        const ruleThreshold = {}
        let hasMetrics = false
        Object.keys(t).forEach(key => {
          if (!t[key] || !t[key].checked) return
          thresholdKeys.add(key)
          ruleThreshold[key] = {
            value: t[key].value,
            operator: t[key].operator
          }
          hasMetrics = true
        })
        if (hasMetrics) {
          threshold[timeKey] = ruleThreshold
        }
      })
      return { threshold, threshold_keys: Array.from(thresholdKeys) }
    },
    buildRequestPayload() {
      const { threshold } = this.buildThresholdFromRules()
      const rules = ((this.formData.execute_rule && this.formData.execute_rule.rules) || []).map(rule => {
        const t = rule.threshold || {}
        const thresholdPayload = {}
        Object.keys(t).forEach(key => {
          if (!t[key] || !t[key].checked) return
          thresholdPayload[key] = {
            operator: t[key].operator,
            value: t[key].value
          }
        })
        return {
          time: rule.time,
          time_point: rule.time,
          // execute_operate: rule.execute_operate,
          threshold: thresholdPayload
        }
      })
      rules.sort((a, b) => {
        return a.time.localeCompare(b.time)
      })
      const executeOperate = this.formData.execute_operate || (rules[0] && rules[0].execute_operate) || ''
      return {
        template: this.formData.template,
        advertiser_ids: this.formData.advertiser_ids,
        plan_name: this.formData.plan_name,
        threshold,
        execute_operate: executeOperate,
        execute_rule: {
          step: this.formData.execute_rule.step,
          type: this.formData.execute_rule.type,
          rules
        }
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
      const { threshold, threshold_keys } = this.buildThresholdFromRules()
      this.formData.threshold = threshold
      this.$refs.dataForm.validate((valid) => {
        if (!valid) {
          return
        }
        checkoutConflict(getAppCode(), { advertiser_ids: this.formData.advertiser_ids, threshold_keys: threshold_keys, plan_id: this.planId }).then(response => {
          if (response.data.records.length <= 0) {
            this.$confirm('是否保存？', '提示', {
              confirmButtonText: '确定',
              cancelButtonText: '取消',
              type: 'warning'
            }).then(() => {
              this.confirmConflictDialog()
            }).catch(() => {
              this.$message({
                type: 'info',
                message: '已取消操作'
              })
            })
            return
          }
          this.conflictList = response.data.records
          this.conflictVisible = true
        })
      })
    },
    normalizeRules(data) {
      const rawExecuteRule = data.execute_rule || {}
      let rules = []
      if (Array.isArray(rawExecuteRule)) {
        rules = rawExecuteRule
      } else if (Array.isArray(rawExecuteRule.rules)) {
        rules = rawExecuteRule.rules
      }
      const normalizedRules = (rules || []).map(item => {
        const threshold = this.createEmptyThreshold()
        const rawThreshold = item.threshold || {}
        Object.keys(rawThreshold).forEach(key => {
          if (!threshold[key]) return
          threshold[key] = {
            checked: true,
            operator: rawThreshold[key].operator || 'eq',
            value: rawThreshold[key].value || 0
          }
        })
        return {
          id: item.id || `${Date.now()}_${Math.random().toString(16).slice(2)}`,
          time: item.time || item.time_point || this.hourOptions[0],
          // execute_operate: item.execute_operate || data.execute_operate || '',
          threshold
        }
      })
      const legacyThreshold = data.threshold || {}
      if (normalizedRules.length <= 0 && Object.keys(legacyThreshold).length > 0) {
        const keys = Object.keys(legacyThreshold)
        const firstKey = keys[0]
        const firstVal = legacyThreshold[firstKey]
        const isFlat = firstVal && typeof firstVal === 'object' && ('value' in firstVal || 'operator' in firstVal)

        if (isFlat) {
          const threshold = this.createEmptyThreshold()
          Object.keys(legacyThreshold).forEach(key => {
            if (!threshold[key]) return
            threshold[key] = {
              checked: true,
              operator: legacyThreshold[key].operator || 'eq',
              value: legacyThreshold[key].value || 0
            }
          })
          normalizedRules.push({
            id: `${Date.now()}_${Math.random().toString(16).slice(2)}`,
            time: this.hourOptions[0],
            // execute_operate: data.execute_operate || '',
            threshold
          })
        } else {
          keys.forEach(timeKey => {
            const subThreshold = legacyThreshold[timeKey]
            const threshold = this.createEmptyThreshold()
            Object.keys(subThreshold).forEach(key => {
              if (!threshold[key]) return
              threshold[key] = {
                checked: true,
                operator: subThreshold[key].operator || 'eq',
                value: subThreshold[key].value || 0
              }
            })
            let timeStr = String(timeKey)
            if (!timeStr.includes(':')) {
              timeStr = `${timeStr.padStart(2, '0')}:00`
            }
            normalizedRules.push({
              id: `${Date.now()}_${Math.random().toString(16).slice(2)}`,
              time: timeStr,
              // execute_operate: data.execute_operate || '',
              threshold
            })
          })
        }
      }
      normalizedRules.sort((a, b) => {
        return a.time.localeCompare(b.time)
      })
      return {
        normalizedRules,
        step: rawExecuteRule.step || 0,
        type: rawExecuteRule.type || 'hour'
      }
    },
    handleTemplateChange(val) {
      if (!val) return
      autoPlanInfo(getAppCode(), val).then(response => {
        const data = response.data
        const { normalizedRules, step, type } = this.normalizeRules(data)
        this.formData.execute_operate = data.execute_operate
        this.formData.execute_rule = {
          step,
          type,
          rules: normalizedRules
        }
      })
    },
    getAutoPlanInfo() {
      autoPlanInfo(getAppCode(), this.planId).then(response => {
        const { normalizedRules, step, type } = this.normalizeRules(response.data)
        this.formData = {
          template: response.data.template || [],
          advertiser_ids: (response.data.advertisers && response.data.advertisers.ids) || [],
          plan_name: response.data.plan_name,
          threshold: response.data.threshold || {},
          execute_operate: response.data.execute_operate,
          execute_rule: {
            step,
            type,
            rules: normalizedRules
          }
        }
      })
    },
    closeConflictDialog() {
      this.conflictVisible = false
    },
    confirmConflictDialog() {
      this.conflictVisible = false
      const payload = this.buildRequestPayload()
      if (this.dialogStatus === 'create') {
        saveAutoPlan(getAppCode(), payload).then(response => {
          this.$emit('closeDialog')
          Message({
            showClose: true,
            message: 'success',
            type: 'success',
            duration: 5 * 1000
          })
        })
      } else {
        editAutoPlan(getAppCode(), this.planId, payload).then(response => {
          this.$emit('closeDialog')
          Message({
            showClose: true,
            message: 'success',
            type: 'success',
            duration: 5 * 1000
          })
        })
      }
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
.rule-setting-col-operate {
  width: 140px;
  padding-right: 10px;
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
