<template>
  <div class="app-container">
    <el-dialog
      :title="dialogStatus === 'create' ? '新增代投配置' : '编辑代投配置'"
      :visible.sync="formVisible"
      width="50%"
      :before-close="closeDialog"
    >
      <el-form ref="dataForm" :rules="formRules" :model="formData" label-width="120px" label-position="left">
        <el-form-item label="代投名称" prop="delegate_name">
          <el-input v-model="formData.delegate_name" placeholder="代投名称" style="min-width: 200px; max-width: 400px;"/>
        </el-form-item>

        <el-form-item label="钉钉Token" prop="ding_token">
          <el-input v-model="formData.ding_token" placeholder="钉钉Token" style="min-width: 200px; max-width: 600px;"/>
        </el-form-item>

        <el-form-item label="钉钉Secret" prop="ding_secret">
          <el-input v-model="formData.ding_secret" placeholder="钉钉Secret" style="min-width: 200px; max-width: 600px;"/>
        </el-form-item>

        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="formData.status">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="closeDialog">取消</el-button>
        <el-button type="primary" @click="confirmDialog">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { alertDelegateInfo, saveAlertDelegate, updateAlertDelegate } from '@/api/put/alert-rule'
import { Message } from 'element-ui'

export default {
  name: 'DelegateForm',
  props: ['dialogStatus', 'formVisible', 'delegateId'],
  data() {
    return {
      formRules: {
        delegate_name: [
          { required: true, message: '请输入代投名称', trigger: 'blur' }
        ],
        ding_token: [
          { required: true, message: '请输入钉钉Token', trigger: 'blur' }
        ],
        ding_secret: [
          { required: true, message: '请输入钉钉Secret', trigger: 'blur' }
        ],
        status: [
          { required: true, message: '请选择状态', trigger: 'change' }
        ]
      },
      formData: {
        delegate_name: '',
        ding_token: '',
        ding_secret: '',
        status: 1
      }
    }
  },
  created() {
    if (this.dialogStatus === 'update') {
      this.getDelegateInfo()
    }
  },
  methods: {
    getDelegateInfo() {
      alertDelegateInfo(this.delegateId).then(response => {
        const data = response.data
        this.formData = {
          delegate_name: data.delegate_name,
          ding_token: data.ding_token || '',
          ding_secret: data.ding_secret || '',
          status: data.status !== undefined ? data.status : 1
        }
      })
    },
    closeDialog() {
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
          const payload = {
            delegate_name: this.formData.delegate_name,
            ding_token: this.formData.ding_token,
            ding_secret: this.formData.ding_secret,
            status: this.formData.status
          }

          if (this.dialogStatus === 'create') {
            saveAlertDelegate(payload).then(response => {
              this.$emit('closeDialog')
              Message({
                showClose: true,
                message: 'success',
                type: 'success',
                duration: 5 * 1000
              })
            })
          } else {
            updateAlertDelegate(this.delegateId, payload).then(response => {
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
    }
  }
}
</script>

<style scoped>
</style>
