<template>
  <el-tabs type="card" v-model="platform" @tab-click="handleClick">
    <el-tab-pane label="快手" name="ks"><Ks/></el-tab-pane>
    <el-tab-pane label="穿山甲" name="csj"><Csj/></el-tab-pane>
    <el-tab-pane label="优量汇" name="ylh"><Ylh/></el-tab-pane>
    <el-tab-pane label="Topon" name="topon"><Topon/></el-tab-pane>
    <el-tab-pane label="穿山甲 GroMore" name="csjGroMore"><CsjGroMore/></el-tab-pane>
  </el-tabs>
</template>
<style>

</style>
<script>
import Ks from './placement-components/ks'
import Csj from './placement-components/csj'
import Ylh from './placement-components/ylh'
import Topon from './placement-components/topon'
import CsjGroMore from './placement-components/csj-gromore'

export default {
  components: { Ks, Csj, Ylh, Topon, CsjGroMore },
  data() {
    return {
      'platform': 'ks'
    }
  },
  created() {
  },
  methods: {
    handleClick() {
    }
  }
}
</script>
