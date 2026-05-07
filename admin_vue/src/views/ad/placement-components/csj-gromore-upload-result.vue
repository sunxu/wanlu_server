<template>
  <el-dialog :visible.sync="uploadResultVisible" title="导入代码位结果" :before-close="closeResultDialog">
    <el-collapse v-model="activeName" accordion>
      <el-collapse-item :title="successTitle" name="1">
        <el-table
          :data="uploadResult.success"
          border
          style="width: 100%"
        >
          <el-table-column
            prop="ad_unit_id"
            label="广告位 ID"
            width="180">
          </el-table-column>
          <el-table-column
            prop="message"
            label="说明">
          </el-table-column>
        </el-table>
      </el-collapse-item>
      <el-collapse-item :title="failedTitle" name="2">
        <el-table
          :data="uploadResult.failed"
          border
          style="width: 100%"
        >
          <el-table-column
            prop="ad_unit_id"
            label="广告位 ID"
            width="180">
          </el-table-column>
          <el-table-column
            prop="message"
            label="说明">
          </el-table-column>
        </el-table>
      </el-collapse-item>
    </el-collapse>
  </el-dialog>
</template>

<script>
export default {
  name: 'GroMoreUploadResult',
  props: ['uploadResultVisible', 'uploadResult'],
  watch: {
    uploadResult(uploadResult) {
      this.successTitle = '绑定成功【' + uploadResult.success.length + '】'
      this.failedTitle = '绑定失败【' + uploadResult.failed.length + '】'
    }
  },
  data() {
    return {
      activeName: '1',
      successTitle: '',
      failedTitle: ''
    }
  },
  methods: {
    closeResultDialog() {
      this.$emit('closeResultDialog')
    }
  }
}
</script>

<style scoped>
.el-table .warning-row {
  background: oldlace;
}

.el-table .success-row {
  background: #f0f9eb;
}
</style>
