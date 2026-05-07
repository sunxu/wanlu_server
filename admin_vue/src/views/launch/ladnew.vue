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

import adNew from './launch/advertiser'
// import ad from './components/advertisere_new'
import planNew from './launch/plan'
// import plan from './components/plan_new'
import AdList from './launch/ad'
import AdProject from './launch/ad-project'

export default {
  components: { AdList, AdProject, adNew, planNew },
  data() {
    return {
      tabMapOptions: [
        { label: '广告主', key: 'adNew' },
        { label: '项目', key: 'AdProject' },
        { label: '广告', key: 'AdList' },
        { label: '计划', key: 'planNew' },
        // { label: '广告主-旧版', key: 'ad' },
        // { label: '广告计划-旧版', key: 'plan' },
      ],
      activeName: 'adNew',
      nowName: 'adNew'
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
