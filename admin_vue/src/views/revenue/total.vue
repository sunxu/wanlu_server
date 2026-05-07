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
import app from './total/app'
import company from './total/company'

export default {
  components: { app, company },
  data() {
    return {
      tabMapOptions: [
        { label: '应用回收', key: 'app' },
        { label: '主体回收', key: 'company' }
      ],
      activeName: 'app',
      nowName: 'app'
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
