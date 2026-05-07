<template>
  <div class="tab-container">
    <el-tabs v-model="activeName" style="margin-top:15px;" type="border-card" @tab-click="handleClick">
      <el-tab-pane v-for="item in tabMapOptions" :key="item.key" :label="item.label" :name="item.key">
        <components :is="item.key" v-if="item.key === nowName"></components>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script>
import adInstalled from './show-v2/installed'
import adActive from './show-v2/active'
import adDeu from './show-v2/deu'

export default {
  components: { adInstalled, adActive, adDeu },
  data() {
    return {
      tabMapOptions: [
        { label: '新增安装', key: 'adInstalled' },
        { label: '新增激活', key: 'adActive' },
        { label: '活跃', key: 'adDeu' }
      ],
      activeName: 'adInstalled',
      nowName: 'adInstalled'
    }
  },
  created() {

  },
  methods: {
    handleClick(tab) {
      this.nowName = tab.name
    }
  }
}
</script>

<style scoped>
.tab-container {
  margin: 30px;
}
</style>
