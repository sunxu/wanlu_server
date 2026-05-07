<template>
  <div>
    <el-descriptions class="margin-top" title="预估留存" :column="7" border>
      <template slot="extra">
        <el-button type="primary" size="small" @click="editVariable">编辑</el-button>
      </template>
      <el-descriptions-item label="预估留存次日">{{ variables.variable1 }}%</el-descriptions-item>
      <el-descriptions-item label="预估留存2日">{{ variables.variable2 }}%</el-descriptions-item>
      <el-descriptions-item label="预估留存3日">{{ variables.variable3 }}%</el-descriptions-item>
      <el-descriptions-item label="预估留存4日">{{ variables.variable4 }}%</el-descriptions-item>
      <el-descriptions-item label="预估留存5日">{{ variables.variable5 }}%</el-descriptions-item>
      <el-descriptions-item label="预估留存6日">{{ variables.variable6 }}%</el-descriptions-item>
      <el-descriptions-item label="预估留存7日">{{ variables.variable7 }}%</el-descriptions-item>
    </el-descriptions>
    <EditDialog v-if="dialogVisible" :dialog-visible="dialogVisible" :init-variables="variables" @confirmEdit="confirmEdit" @cancelEdit="cancelEdit"></EditDialog>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { revenueMockVariables } from '@/api/cash/list'
import EditDialog from './variables-edit'

export default {
  name: 'RevenueMockVariables',
  components: { EditDialog },
  data() {
    return {
      dialogVisible: false,
      variables: {}
    }
  },
  mounted() {
    this.fetchVariables()
  },
  methods: {
    fetchVariables() {
      revenueMockVariables(getAppCode()).then(response => {
        this.variables = response.data
      })
    },
    editVariable() {
      this.dialogVisible = true
    },
    cancelEdit() {
      this.dialogVisible = false
    },
    confirmEdit() {
      this.dialogVisible = false
      this.$emit('recalculate')
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
