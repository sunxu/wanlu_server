<template>
  <div class="app-container">
    <el-dialog
      title="与正在执行的规则有冲突,是否继续执行"
      :visible.sync="dialogVisible"
      width="30%"
      :before-close="closeDialog"
    >
      <el-collapse v-model="activeNames">
        <el-collapse-item v-for="row in conflictList" :key="row.plan_id" :title="row.plan_name + ':' + row.plan_id" :name="row.plan_id">
          <div v-for="item in row.advertisers" :key="item.advertiser_id">{{ item.advertiser_name }}【{{ item.advertiser_id }}】</div>
        </el-collapse-item>
      </el-collapse>
      <span slot="footer" class="dialog-footer">
        <el-button @click="closeDialog">取 消</el-button>
        <el-button type="primary" @click="confirmDialog">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'Conflict',
  props: ['dialogVisible', 'conflictList', 'conflictRow'],
  data() {
    return {
      activeNames: []
    }
  },
  created() {
    this.activeNames = this.conflictList[0].plan_id
  },
  methods: {
    closeDialog() {
      this.$emit('closeDialog')
    },
    confirmDialog() {
      this.$emit('confirmDialog', this.conflictRow)
    }
  }
}
</script>

<style scoped>

</style>
