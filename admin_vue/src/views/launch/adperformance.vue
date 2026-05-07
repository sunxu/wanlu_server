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
          <el-select v-model="listQuery.launch_type" placeholder="投放方式" clearable style="width: 240px" class="filter-item">
            <el-option v-for="(value,key) in launch_type_map" :label="value" :value="key"></el-option>
          </el-select>
        </el-col>
      </el-row>
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">广告主：</span>
          <el-cascader
            ref="cascader"
            style="width: 240px"
            class="filter-item"
            :options="advertiserList"
            :props="{ multiple: true, checkStrictly: true }"
            clearable
            filterable
            multiple
            collapse-tags></el-cascader>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">代理商：</span>
          <el-select v-model="listQuery.project_ids" placeholder="代理商" clearable filterable multiple style="width: 240px" class="filter-item">
            <el-option v-for="value in agencyList" :key="value.agency_name" :label="value.agency_name" :value="value.agency_name"></el-option>
          </el-select>
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
        <el-table-column label="时间" align="center" prop="时间" width="100" >
          <template slot-scope="scope">
            <span>
            {{ scope.row.时间?scope.row.时间.split(' ')[0]:'' }}
            </span>
          </br>
            <span>
            时间：{{ scope.row.时间?scope.row.时间.split(' ')[1]:'' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="广告主 ID" align="center" prop="广告主ID" width="100"></el-table-column>
        <el-table-column label="广告主名称" align="center" prop="广告主名称" width="100" ></el-table-column>
        <el-table-column label="代理商" align="center" prop="代理商" width="100" ></el-table-column>


        <el-table-column label="AppCode" align="center" prop="app_code" width="100"></el-table-column>
        <el-table-column label="应用名" align="center" prop="app_name" width="100"></el-table-column>
        <el-table-column label="ROI" align="center" prop="roi" width="100" ></el-table-column>
        <el-table-column label="上周期roi" align="center" prop="上周期roi" width="100" ></el-table-column>
        <el-table-column label="前一天留存率" align="center" prop="前一天留存率" width="100" ></el-table-column>
        <el-table-column label="近三天留存率" align="center" prop="近三天留存率" width="100" ></el-table-column>

        <el-table-column label="当日ecpm" align="center" prop="当日ecpm" width="100" ></el-table-column>
        <el-table-column label="近三天ecpm" align="center" prop="近三天ecpm" width="100" ></el-table-column>
        <el-table-column label="当日预算" align="center" prop="当日预算" width="100" ></el-table-column>
        <el-table-column label="消耗" align="center" prop="消耗" width="100" ></el-table-column>
        <el-table-column label="消耗达成率" align="center" prop="消耗达成率" width="100" ></el-table-column>
        <el-table-column label="上周期消耗达成率" align="center" prop="上周期消耗达成率" width="100" ></el-table-column>

         <el-table-column label="上小时消耗百分比" align="center" prop="上小时消耗百分比" width="100"></el-table-column>
        <el-table-column label="a1" align="center" prop="a1" width="100" ></el-table-column>
        <el-table-column label="a2" align="center" prop="a2" width="100" ></el-table-column>
        <el-table-column label="a3" align="center" prop="a3" width="100" ></el-table-column>
        <el-table-column label="a4" align="center" prop="a4" width="100" ></el-table-column>
        <el-table-column label="summing" align="center" prop="summing" width="100" ></el-table-column>
        <el-table-column label="记录时间" align="center" prop="记录时间" width="100" ></el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import { getAdPerformance } from '@/api/launch/lad'
import { advertisers, agencys } from '@/api/common'
import Pagination from '@/components/Pagination'
import { Message } from 'element-ui'

export default {
  label: '投放策算',
  name: 'Adperformance',
  components: { Pagination },
  data() {
    return {
      source_map: _.clone(this.$global.SOURCE_MAP),
      launch_type_map: _.clone(this.$global.LAUNCH_TYPE_MAP),
      advertiserList: [],
      agencyList: [],
      searchDate: undefined,
      listLoading: false,
      downloadLoading: false,
      total: 0,
      list: undefined,
      total_data: {
        cost: 0,
        before_budget: 0,
        budget: 0,
        budget_finish: '0%',
        withdraw: 0,
        install_payment: 0,
        install_num: 0,
        install_pre_cost: 0,
        install_withdraw: 0,
        install_avg_revenue: 0,
        install_roi: 0,
        install_roi_withdraw: 0,
        install_roi_24h: 0,
        install_roi_24h_withdraw: 0,
        active_cost: 0,
        key_action_cost: 0,
        key_action_rate: '0%',
        active: 0,
        key_action: 0,
        recovery3_rate: '0%',
        recovery7_rate: '0%',
        recovery10_rate: '0%',
        keep1_rate: '0%',
        keep3_rate: '0%',
        keep7_rate: '0%',
        keep10_rate: '0%'
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
        sort_column: undefined,
        sort_type: undefined,
        project_ids: undefined
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
      this.$refs['cascader'].getCheckedNodes().forEach(function(item) {
        switch (item.level) {
          case 1:
            that.listQuery.advertiser_ids.push(item.value)
            break
        }
      })
      return getAdPerformance(this.listQuery).then(response => {
        that.total = response.data.total
        that.list = response.data.list
        // this.total_data = {}
        that.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
    },
    searchOptions() {
      advertisers(getAppCode()).then(response => {
        const advertiserArr = []
        response.data.records.forEach(function(item) {
          advertiserArr.push({ value: item.advertiser_id, label: item.advertiser_id + '-' + item.advertiser_name })
        })
        this.advertiserList = advertiserArr
      })
      agencys(getAppCode()).then(response => {
        this.agencyList = response.data.records
      })
    },
    handleSort(sort) {
      if (sort.order === null) {
        this.listQuery.page = 1
        this.listQuery.sort_column = undefined
        this.listQuery.sort_type = undefined
        this.fetchData()
        return
      }
      this.listQuery.sort_type = sort.order
      this.listQuery.sort_column = sort.prop
      this.listQuery.page = 1
      this.fetchData()
    },
    handleDownload() {
      this.downloadLoading = true
      const query = Object.assign({}, this.listQuery)
      query.per_page = this.total
      query.page = 1
      const that = this
      getAdPerformance(query).then(response => {
        import('@/vendor/Export2Excel').then(excel => {
          const tHeader = ['时间', '广告主ID', '广告主名称', '代理商', 'AppCode', '应用名', 'ROI', '上周期roi', '前一天留存率', '近三天留存率', '当日ecpm', '近三天ecpm', '当日预算', '消耗', '消耗达成率', '上周期消耗达成率', '上小时消耗百分比', 'a1', 'a2', 'a3', 'a4', 'summing', '记录时间']
          const filterVal = ['时间', '广告主ID', '广告主名称', '代理商', 'app_code', 'app_name', 'roi', '上周期roi', '前一天留存率', '近三天留存率', '当日ecpm', '近三天ecpm', '当日预算', '消耗', '消耗达成率', '上周期消耗达成率', '上小时消耗百分比', 'a1', 'a2', 'a3', 'a4', 'summing', '记录时间']
          const data = response.data.list.map(v => filterVal.map(j => {
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
            filename: '投放策略-' + time
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
