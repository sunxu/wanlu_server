<template>
  <div>
    <el-dialog :visible.sync="dialogVisible" title="编辑" :before-close="cancel">
      <el-form ref="dataForm" :rules="rules" :model="variables" label-width="150px" label-position="left">
        <el-form-item label="预估留存次日" prop="variable1">
          <el-input-number v-model="variables.variable1" :min="0" :max="100"></el-input-number>
        </el-form-item>
        <el-form-item label="预估留存2日" prop="variable2">
          <el-input-number v-model="variables.variable2" :min="0" :max="100"></el-input-number>
        </el-form-item>
        <el-form-item label="预估留存3日" prop="variable3">
          <el-input-number v-model="variables.variable3" :min="0" :max="100"></el-input-number>
        </el-form-item>
        <el-form-item label="预估留存4日" prop="variable4">
          <el-input-number v-model="variables.variable4" :min="0" :max="100"></el-input-number>
        </el-form-item>
        <el-form-item label="预估留存5日" prop="variable5">
          <el-input-number v-model="variables.variable5" :min="0" :max="100"></el-input-number>
        </el-form-item>
        <el-form-item label="预估留存6日" prop="variable6">
          <el-input-number v-model="variables.variable6" :min="0" :max="100"></el-input-number>
        </el-form-item>
        <el-form-item label="预估留存7日" prop="variable7">
          <el-input-number v-model="variables.variable7" :min="0" :max="100"></el-input-number>
        </el-form-item>
      </el-form>
      <div style="text-align:right;">
        <el-button type="danger" @click="cancel">取消</el-button>
        <el-button type="primary" @click="confirm">保存</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { modifyRevenueMockVariables } from '@/api/cash/list'
import { Message } from 'element-ui'

export default {
  name: 'RevenueMockVariablesEdit',
  props: ['dialogVisible', 'initVariables'],
  mounted() {
    this.variables = this.initVariables
  },
  data() {
    return {
      rules: {},
      variables: this.initVariables
    }
  },
  methods: {
    cancel() {
      this.$emit('cancelEdit')
    },
    confirm() {
      modifyRevenueMockVariables(getAppCode(), this.variables).then(response => {
        Message({
          showClose: true,
          message: 'success',
          type: 'success',
          duration: 5 * 1000
        })
      })
      this.$emit('confirmEdit')
    }
  }
}
</script>

<style scoped>
.el-row {
  margin-bottom: 10px;
}

.el-radio {
  margin: 0
}

.el-col-span {
  display: inline-block;
  width: 80px;
  padding: 6px 0;
  font-size: 14px;
}
</style>
