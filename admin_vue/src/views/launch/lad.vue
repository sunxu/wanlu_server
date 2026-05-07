
<template>
    <div class="tab-container">
        <el-tabs v-model="activeName" style="margin-top:15px;" @tab-click="handleClick" type="border-card">
            <el-tab-pane v-for="item in tabMapOptions" :key="item.key" :label="item.label" :name="item.key">
                <components :is="item.key" v-if="item.key == nowName"></components>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script>
import ad from './components/advertisere'
import plan from './components/plan'

export default {
    components: { ad, plan },
    data() {
        return {
            tabMapOptions: [
                { label: '广告主', key: 'ad' },
                { label: '广告计划', key: 'plan' },
            ],
            activeName: 'ad',
            nowName: "ad",
        }
    },
    created() {

    },
    methods: {
        handleClick(tab) {
            this.nowName = tab.name;
        }
    }
}
</script>

<style scoped>
.tab-container {
    margin: 30px;
}
</style>
