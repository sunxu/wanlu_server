<template>
  <div class="app-container">
    <el-dialog
      title="新增"
      :visible.sync="formVisible"
      width="40%"
      :before-close="closeDialog"
    >
      <el-form ref="dataForm" :rules="formRules" :model="formData" label-width="100px" label-position="left">
        <el-form-item label="账户主体" prop="account_id">
          <el-select v-model="formData.account_id" placeholder="请选择账户主体" filterable>
            <el-option v-for="row in accountList" :key="row.company_id" :label="row.company_name" :value="row.company_id"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="APP ID" prop="app_id">
          <el-input v-model="formData.app_id" placeholder="APP ID" />
        </el-form-item>
        <el-form-item label="APP 名称" prop="app_name">
          <el-input v-model="formData.app_name" placeholder="APP 名称" />
        </el-form-item>
        <el-form-item label="APP 包名" prop="package_name">
          <el-input v-model="formData.package_name" placeholder="APP 包名" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="closeDialog">取 消</el-button>
        <el-button type="primary" @click="confirmDialog">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { companyList } from '@/api/common'
import { checkoutConflict } from '@/api/put/auto-plan'
import { getAppCode } from '@/utils/auth'
import { saveMediaApp } from '@/api/ad/placement'

export default {
  name: 'MediaAppBdForm',
  props: ['formVisible'],
  data() {
    return {
      formRules: {
        app_id: [
          { required: true, message: '请输入 APP ID', trigger: 'blur' }
        ],
        app_name: [
          { required: true, message: '请输入 APP 名称', trigger: 'blur' }
        ],
        package_name: [
          { required: true, message: '请输入 APP 包名', trigger: 'blur' }
        ],
        account_id: [
          { required: true, message: '请选择账户主体', trigger: 'blur' }
        ],
      },
      formData: {
        account_id: undefined,
        app_id: undefined,
        platform: 'bd',
        app_name: undefined,
        package_name: undefined
      },
      accountList: []
    }
  },
  created() {
    companyList().then(response => {
      this.accountList = response.data.records
    })
  },
  methods: {
    closeDialog() {
      this.$emit('closeDialog')
    },
    confirmDialog() {
      this.$refs.dataForm.validate((valid) => {
        if (!valid) {
          return
        }
        saveMediaApp(getAppCode(), this.formData).then(response => {
          this.closeDialog()
        })
      })
    }
  }
}
</script>

<style scoped>

</style>
