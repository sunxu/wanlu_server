<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">日期：</span>
          <el-date-picker v-model="searchDate" type="daterange" style="width: 240px;" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" :clearable="false"></el-date-picker>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">投放渠道：</span>
          <el-select v-model="listQuery.platform" placeholder="投放渠道" clearable style="width: 240px" class="filter-item">
            <el-option v-for="(value,key) in source_map" :label="value" :value="key"></el-option>
          </el-select>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">投放方式：</span>
          <el-select v-model="listQuery.launch_type" placeholder="投放渠道" clearable style="width: 240px" class="filter-item">
            <el-option v-for="(value,key) in launch_type_map" :label="value" :value="key"></el-option>
          </el-select>
        </el-col>
      </el-row>
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">广告主</span>
          <el-cascader
            ref="cascader"
            style="width: 240px"
            class="filter-item"
            :options="advertiserPlanList"
            :props="{ multiple: true, checkStrictly: true }"
            clearable
            filterable
            collapse-tags></el-cascader>
        </el-col>
        <el-col :span="8">
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
          搜索
          </el-button>
          <el-button class="filter-item" type="primary" icon="el-icon-download" :loading="downloadLoading" @click="handleDownload">
            导出
          </el-button>
        </el-col>
      </el-row>
    </div>
    <el-table
      v-loading="listLoading"
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
    >
      <el-table-column align="center" label="合计" width="500" fixed>
        <el-table-column label="日期" align="center" prop="date" width="100" fixed></el-table-column>
        <el-table-column label="广告计划 ID" align="center" prop="plan_id" width="100" fixed></el-table-column>
        <el-table-column label="广告计划名称" align="center" prop="plan_name" width="100" fixed></el-table-column>
        <el-table-column label="广告主 ID" align="center" prop="advertiser_id" width="100" fixed></el-table-column>
        <el-table-column label="广告主名称" align="center" prop="advertiser_name" width="100" fixed></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_num + ' '">
        <el-table-column align="center" label="新增安装数" prop="install_num"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.activate_num + ' '">
        <el-table-column align="center" label="新增激活数" prop="activate_num"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.active + ' '">
        <el-table-column align="center" label="激活回传数" prop="active"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.keep1_rate + ' '">
        <el-table-column align="center" label="次留率" prop="keep1_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.keep2_rate + ' '">
        <el-table-column align="center" label="2留率" prop="keep2_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.keep3_rate + ' '">
        <el-table-column align="center" label="3留率" prop="keep3_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.keep4_rate + ' '">
        <el-table-column align="center" label="4留率" prop="keep4_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.keep5_rate + ' '">
        <el-table-column align="center" label="5留率" prop="keep5_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.keep6_rate + ' '">
        <el-table-column align="center" label="6留率" prop="keep6_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.keep7_rate + ' '">
        <el-table-column align="center" label="7留率" prop="keep7_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.recovery0_rate + ' '">
        <el-table-column align="center" label="回本率" prop="recovery0_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.recovery1_rate + ' '">
        <el-table-column align="center" label="次日回本率" prop="recovery1_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.recovery2_rate + ' '">
        <el-table-column align="center" label="2日回本率" prop="recovery2_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.recovery3_rate + ' '">
        <el-table-column align="center" label="3日回本率" prop="recovery3_rate"></el-table-column>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import { getLaunchPlanKeep } from '@/api/launch/lad'
import Pagination from '@/components/Pagination'
import { Message } from 'element-ui'
import { advertiserPlan } from '@/api/common'

export default {
  label: '广告计划投放数据',
  name: 'PlanList',
  components: { Pagination },
  data() {
    return {
      source_map: _.clone(this.$global.SOURCE_MAP),
      launch_type_map: _.clone(this.$global.LAUNCH_TYPE_MAP),
      advertiserPlanList: [],
      searchDate: undefined,
      listLoading: false,
      downloadLoading: false,
      total: 0,
      list: undefined,
      total_data: {
        install_num: 0,
        activate_num: 0,
        active: 0,
        keep1_rate: '0%',
        keep2_rate: '0%',
        keep3_rate: '0%',
        keep4_rate: '0%',
        keep5_rate: '0%',
        keep6_rate: '0%',
        keep7_rate: '0%',
        recovery0_rate: '0%',
        recovery1_rate: '0%',
        recovery2_rate: '0%',
        recovery3_rate: '0%',
        recovery4_rate: '0%',
        recovery5_rate: '0%',
        recovery6_rate: '0%',
        recovery7_rate: '0%'
      },
      listQuery: {
        page: 1,
        per_page: 10,
        app_code: undefined,
        date_begin: undefined,
        date_end: undefined,
        platform: undefined,
        launch_type: undefined,
        advertiser_ids: [],
        plan_ids: []
      }
    }
  },
  created() {
    this.searchDate = [new Date(), new Date()]
    this.listQuery.app_code = getAppCode()
    this.searchOptions()
  },
  mounted() {
    this.fetchData()
  },
  methods: {
    fetchData() {
      this.listLoading = true
      this.listQuery.date_begin = parseTime(this.searchDate[0], '{y}-{m}-{d}')
      this.listQuery.date_end = parseTime(this.searchDate[1], '{y}-{m}-{d}')
      const that = this
      that.listQuery.advertiser_ids = []
      that.listQuery.plan_ids = []
      this.$refs['cascader'].getCheckedNodes().forEach(function(item) {
        switch (item.level) {
          case 1:
            that.listQuery.advertiser_ids.push(item.value)
            break
          case 2:
            that.listQuery.plan_ids.push(item.value)
            break
        }
      })
      getLaunchPlanKeep(this.listQuery).then(response => {
        this.total = response.data.total
        this.list = response.data.records
        this.total_data = response.data.total_data
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
    },
    searchOptions() {
      advertiserPlan(getAppCode()).then(response => {
        const advertiserArr = []
        response.data.records.forEach(function(item) {
          const planArr = []
          item.plan.forEach(function(item) {
            planArr.push({ value: item.plan_id, label: item.plan_id + '-' + item.plan_name })
          })
          advertiserArr.push({ value: item.advertiser_id, label: item.advertiser_id + '-' + item.advertiser_name, children: planArr })
        })
        this.advertiserPlanList = advertiserArr
      })
    },
    handleDownload() {
      this.downloadLoading = true
      const query = Object.assign({}, this.listQuery)
      query.per_page = this.total
      const that = this
      getLaunchPlanKeep(query).then(response => {
        import('@/vendor/Export2Excel').then(excel => {
          const tHeader = ['日期', '广告计划 ID', '广告计划名称', '广告主 ID', '广告主名称', '新增安装数', '新增激活数', '激活回传数', '次留率', '2留率', '3留率', '4留率', '5留率', '6留率', '7留率', '回本率', '次日回本率', '2日回本率', '3日回本率']
          const filterVal = ['date', 'plan_id', 'plan_name', 'advertiser_id', 'advertiser_name', 'install_num', 'activate_num', 'active', 'keep1_rate', 'keep2_rate', 'keep3_rate', 'keep4_rate', 'keep5_rate', 'keep6_rate', 'keep7_rate', 'recovery0_rate', 'recovery1_rate', 'recovery2_rate', 'recovery3_rate']
          const data = response.data.records.map(v => filterVal.map(j => {
            return v[j]
          }))
          if (data.length <= 0) {
            that.downloadLoading = false
            Message({
              showClose: true,
              message: '没有导出的数据',
              type: 'warning',
              duration: 5 * 1000
            })
            return
          }

          let date= new Date()
          let time = date.getFullYear() +''+ (date.getMonth() + 1) +''+  date.getDate() +''+  date.getHours() +''+  date.getMinutes() +''+  date.getSeconds();
          excel.export_json_to_excel({
            header: tHeader,
            data,
            filename: '留存-' + time
          })
          that.downloadLoading = false
        })
      })
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

.el-col-span .el-col {
  display: inline-block;
  width: 80px;
  margin: 6px 0;
  font-size: 14px;
}
</style>
