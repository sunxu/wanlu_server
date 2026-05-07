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
            :options="advertiserProjectList"
            :props="{ multiple: true, checkStrictly: true }"
            clearable
            filterable
            collapse-tags></el-cascader>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">代投：</span>
          <el-select v-model="listQuery.agency_proxy" placeholder="代投" clearable style="width: 240px" class="filter-item">
            <el-option label="yk" value="yk"></el-option>
            <el-option label="xt" value="xt"></el-option>
            <el-option label="xxs" value="xxs"></el-option>
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
      @sort-change="handleSort"
    >
      <el-table-column align="center" label="合计" width="500" fixed>
        <el-table-column label="日期" align="center" prop="date" width="100" fixed></el-table-column>
        <el-table-column label="广告项目 ID" align="center" prop="project_id" width="100" fixed></el-table-column>
        <el-table-column label="广告项目名称" align="center" prop="project_name" width="100" fixed></el-table-column>
        <el-table-column label="广告主 ID" align="center" prop="advertiser_id" width="100" fixed></el-table-column>
        <el-table-column label="广告主名称" align="center" prop="advertiser_name" width="100" fixed></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.cost + ' '">
        <el-table-column align="center" label="消耗" prop="cost" sortable="custom"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.withdraw + ' '">
        <el-table-column align="center" label="提现金额(当日)" prop="withdraw" sortable="custom"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_payment + ' '">
        <el-table-column align="center" label="充值(当日)" prop="install_payment" sortable="custom"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_num + ' '">
        <el-table-column align="center" label="新增安装" prop="install_num" sortable="custom"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_pre_cost + ' '">
        <el-table-column align="center" label="安装成本" prop="install_pre_cost" sortable="custom"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_withdraw + ' '">
        <el-table-column align="center" label="提现安装成本" prop="install_withdraw" sortable="custom"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_avg_revenue + ' '">
        <el-table-column align="center" label="新增安装 ARPU" prop="install_avg_revenue"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_roi + ' '">
        <el-table-column align="center" label="新增安装 ROI" prop="install_roi" sortable="custom"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_roi_withdraw + ' '">
        <el-table-column align="center" label="(提现)新增安装 ROI" prop="install_roi_withdraw" sortable="custom"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_roi_24h + ' '">
        <el-table-column align="center" label="24H 新增安装 ROI" prop="install_roi_24h" sortable="custom"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_roi_24h_withdraw + ' '">
        <el-table-column align="center" label="(提现)24H 新增安装 ROI" prop="install_roi_24h_withdraw" sortable="custom"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.active_cost + ' '">
        <el-table-column align="center" label="激活成本" prop="active_cost" sortable="custom"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.key_action_cost + ' '">
        <el-table-column align="center" label="关键行为成本" prop="key_action_cost"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.key_action_rate + ' '">
        <el-table-column align="center" label="关键行为率" prop="key_action_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.active + ' '">
        <el-table-column align="center" label="新增激活回传数" prop="active" sortable="custom"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.key_action + ' '">
        <el-table-column align="center" label="关键行为数" prop="key_action"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.recovery3_rate + ' '">
        <el-table-column align="center" label="3日回本" prop="recovery3_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.recovery7_rate + ' '">
        <el-table-column align="center" label="7日回本" prop="recovery7_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.recovery10_rate + ' '">
        <el-table-column align="center" label="10日回本" prop="recovery10_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.keep1_rate + ' '">
        <el-table-column align="center" label="激活次留率" prop="keep1_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.keep3_rate + ' '">
        <el-table-column align="center" label="激活3留率" prop="keep3_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.keep7_rate + ' '">
        <el-table-column align="center" label="激活7留率" prop="keep7_rate"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.keep10_rate + ' '">
        <el-table-column align="center" label="激活10留率" prop="keep10_rate"></el-table-column>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import { getLaunchAdProject } from '@/api/launch/lad'
import Pagination from '@/components/Pagination'
import { advertiserProject } from '@/api/common'
import { Message } from 'element-ui'

export default {
  label: '广告项目投放数据',
  name: 'AdProject',
  components: { Pagination },
  data() {
    return {
      source_map: _.clone(this.$global.SOURCE_MAP),
      launch_type_map: _.clone(this.$global.LAUNCH_TYPE_MAP),
      advertiserProjectList: [],
      searchDate: undefined,
      listLoading: false,
      total: 0,
      list: undefined,
      downloadLoading: false,
      total_data: {
        cost: 0,
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
        project_ids: [],
        sort_column: undefined,
        sort_type: undefined,
        agency_proxy: undefined
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
      that.listQuery.project_ids = []
      this.$refs['cascader'].getCheckedNodes().forEach(function(item) {
        switch (item.level) {
          case 1:
            that.listQuery.advertiser_ids.push(item.value)
            break
          case 2:
            that.listQuery.project_ids.push(item.value)
            break
        }
      })
      getLaunchAdProject(this.listQuery).then(response => {
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
      advertiserProject(getAppCode()).then(response => {
        const advertiserArr = []
        response.data.records.forEach(function(item) {
          const projectArr = []
          item.project.forEach(function(item) {
            projectArr.push({ value: item.project_id, label: item.project_id + '-' + item.project_name })
          })
          advertiserArr.push({ value: item.advertiser_id, label: item.advertiser_id + '-' + item.advertiser_name, children: projectArr })
        })
        this.advertiserProjectList = advertiserArr
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
      getLaunchAdProject(query).then(response => {
        import('@/vendor/Export2Excel').then(excel => {
          const tHeader = ['日期', '广告项目 ID', '广告项目名称', '广告主 ID', '广告主名称', '消耗', '提现金额(当日)', '新增安装', '安装成本', '提现安装成本', '新增安装arpu', '新增安装roi', '(充值)新增安装roi', '(提现)新增安装roi', '24h新增安装roi', '(提现)24h新增安装roi', '激活成本', '关键行为成本', '达成率', '新增激活回传数', '关键行为数', '3日回本', '7日回本', '10日回本', '激活回传次留率', '激活回传3留率', '激活回传7留率', '激活回传10留率']
          const filterVal = ['date', 'project_id', 'project_name', 'advertiser_id', 'advertiser_name', 'cost', 'withdraw', 'install_num', 'install_pre_cost', 'install_withdraw', 'install_avg_revenue', 'install_roi', 'install_roi_payment', 'install_roi_withdraw', 'install_roi_24h', 'install_roi_24h_withdraw', 'active_cost', 'key_action_cost', 'key_action_rate', 'avtive', 'key_action', 'recovery3_rate', 'recovery7_rate', 'recovery10_rate', 'keep1_rate', 'keep3_rate', 'keep7_rate', 'keep10_rate']
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
            filename: '投放-' + time
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

.el-col-span {
  display: inline-block;
  width: 80px;
  padding: 6px 0;
  font-size: 14px;
}
</style>
