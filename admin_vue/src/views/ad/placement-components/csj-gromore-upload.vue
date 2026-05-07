<template>
  <el-dialog :visible.sync="dialogUploadVisible" title="导入代码位" :before-close="cancelUpload">
    <el-form ref="dataForm" :rules="uploadDataRules" :model="uploadData" label-width="100px" label-position="left">
      <el-form-item label="账号" prop="company_id">
        <el-select v-model="uploadData.company_id" placeholder="请选择账号" style="width: 250px;">
          <el-option
            v-for="a in accounts"
            :key="a.company_id"
            :label="a.company_name"
            :value="a.company_id"
          >
          </el-option>
        </el-select>
      </el-form-item>
      <el-form-item label="APP" prop="app_code">
        <el-select v-model="uploadData.app_code" placeholder="请选择 APP" style="width: 250px;">
          <el-option
            v-for="app in appsData"
            :key="app.app_code"
            :label="app.app_name"
            :value="app.app_code"
          >
          </el-option>
        </el-select>
      </el-form-item>
      <el-form-item label="选择文件" prop="fileName">
        <el-input v-model="uploadData.fileName" placeholder="请选择文件" style="width: 250px;" :disabled="true">
          <el-button
            slot="append"
            icon="el-icon-folder-opened"
            @click="openFile"
          ></el-button>
        </el-input>
        <input
          ref="excel-upload-input"
          type="file"
          name="filename"
          style="display: none"
          accept=".xlsx"
          @change="changeFile"
        />
      </el-form-item>
    </el-form>
    <div style="text-align:right;">
      <el-button type="danger" @click="cancelUpload">取消</el-button>
      <el-button type="primary" @click="confirm">
        确认
      </el-button>
    </div>
  </el-dialog>
</template>

<script>
import { appsAll } from '@/api/common'
import { accountCsj, csjExport } from '@/api/ad/placement'

export default {
  name: 'GroMoreUpload',
  props: ['dialogUploadVisible'],
  created() {
    this.fetchOptions()
  },
  data() {
    return {
      uploadDataRules: {
        company_id: [{ required: true, message: '请选择账户', trigger: 'blur' }],
        app_code: [{ required: true, message: '请选择 APP', trigger: 'blur' }],
        fileName: [{ required: true, message: '请选择文件', trigger: 'blur' }]
      },
      uploadData: {
        company_id: undefined,
        app_code: undefined,
        fileName: undefined
      },
      appsData: [],
      accounts: [],
      excelFile: undefined
    }
  },
  methods: {
    fetchOptions() {
      appsAll().then(response => {
        this.appsData = response.data.records
      })
      accountCsj().then(response => {
        this.accounts = response.data.records
      })
    },
    confirm() {
      this.$refs.dataForm.validate((valid) => {
        if (valid) {
          const loading = this.$loading({
            lock: true,
            text: 'Loading',
            spinner: 'el-icon-loading',
            background: 'rgba(0, 0, 0, 0.7)'
          });
          const formData = new FormData()
          formData.append('excel', this.excelFile)
          formData.append('company_id', this.uploadData.company_id)
          csjExport(formData).then(response => {
            loading.close()
            this.$emit('confirmUpload', { excelData: response.data.records, appCode: this.uploadData.app_code, companyId: this.uploadData.company_id })
            this.emptyUploadData()
          })
        }
      })
    },
    cancelUpload() {
      this.$emit('cancelUpload')
      this.emptyUploadData()
    },
    openFile() {
      this.$refs['excel-upload-input'].click()
    },
    changeFile(e) {
      const files = e.target.files
      this.excelFile = files[0] // only use files[0]
      this.uploadData.fileName = this.excelFile.name
    },
    emptyUploadData() {
      this.uploadData = {
        company_id: undefined,
        app_code: undefined,
        fileName: undefined
      }
      this.$refs['excel-upload-input'].value = null
    }
  }
}
</script>

<style scoped>

</style>
