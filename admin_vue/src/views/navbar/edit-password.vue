<template>
  <div class="app-container">
    <el-dialog :visible.sync="dialogFormVisible" title="修改密码" :before-close="cancelBack">
      <el-form ref="dataForm" :rules="rules" :model="temp" label-width="80px" label-position="left">
        <el-form-item label="原密码" prop="old_password">
          <el-input v-model="temp.old_password" type="password" placeholder="原密码" />
        </el-form-item>
        <el-form-item label="密码" prop="password">
          <el-input v-model="temp.password" type="password" placeholder="密码" />
        </el-form-item>
        <el-form-item label="确认密码" prop="retry_password">
          <el-input v-model="temp.retry_password" type="password" placeholder="确认密码" />
        </el-form-item>
      </el-form>
      <div style="text-align:right;">
        <el-button type="danger" @click="cancelBack">取消</el-button>
        <el-button type="primary" @click="createData">
          保存
        </el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import { Message } from 'element-ui'
import { editPassword } from '@/api/user'
import store from '@/store'

const defaultTemp = {
  old_password: undefined,
  password: undefined,
  retry_password: undefined
}
export default {
  data() {
    return {
      rules: {
        old_password: [
          { required: true, message: '请输入原密码', trigger: 'blur' },
          { min: 6, max: 20, message: '长度在 6 到 20 个字符', trigger: 'blur' }
        ],
        password: [
          { required: true, message: '请输入密码', trigger: 'blur' },
          { min: 6, max: 20, message: '长度在 6 到 20 个字符', trigger: 'blur' }
        ],
        retry_password: [
          { required: true, message: '请输入确认密码', trigger: 'blur' },
          { min: 6, max: 20, message: '长度在 6 到 20 个字符', trigger: 'blur' }
        ]
      },
      dialogFormVisible: false,
      temp: Object.assign({}, defaultTemp)
    }
  },
  created() {
    this.handleCreate()
  },
  methods: {
    resetTemp() {
      this.temp = {
        old_password: undefined,
        password: undefined,
        retry_password: undefined
      }
    },
    handleCreate() {
      this.resetTemp()
      this.dialogFormVisible = true
    },
    createData() {
      this.$refs.dataForm.validate((valid) => {
        if (valid) {
          editPassword(store.getters.userInfo.user_id, this.temp).then(response => {
            Message({
              showClose: true,
              message: 'success',
              type: 'success',
              duration: 5 * 1000
            })
            this.dialogFormVisible = false
            store.dispatch('user/resetToken').then(() => {
              this.$router.back()
            })
          })
        }
      })
    },
    cancelBack() {
      this.resetTemp()
      this.dialogFormVisible = false
      this.$router.back()
    }
  }
}
</script>
