<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">日期：</span>
          <el-date-picker v-model="searchDate" type="daterange" style="width: 240px;" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期"></el-date-picker>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">投放平台：</span>
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
          <span class="el-col-span">应用：</span>
          <el-select
            v-model="listQuery.app_code"
            placeholder="应用"
            class="filter-item"
            filterable
            multiple
            size="small"
            style="width: 240px;"
          >
            <el-option
              v-for="item in appList"
              :key="item.value"
              :label="item.label"
              :value="item.value">
            </el-option>
          </el-select>
        </el-col>
        <el-col :span="8">
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
            搜索
          </el-button>
          <el-button class="filter-item" type="primary" icon="el-icon-download" :loading="downloadLoading" @click="handleDownload" >
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
      <el-table-column align="center" label="合计" width="200" fixed>
        <el-table-column label="日期" align="center" prop="date" width="100" fixed></el-table-column>
        <el-table-column label="产品名称" align="center" prop="app_name" width="100" fixed></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.cost + ' '">
        <el-table-column align="center" label="消耗" prop="cost" ></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_num + ' '">
        <el-table-column align="center" label="新增安装" prop="install_num" ></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_pre_cost + ' '">
        <el-table-column align="center" label="安装成本" prop="install_pre_cost" ></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_arpu + ' '">
        <el-table-column align="center" label="新增安装 ARPU" prop="install_arpu" ></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_roi + ' '">
        <el-table-column align="center" label="新增安装 ROI" prop="install_roi" ></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.install_roi_24h + ' '">
        <el-table-column align="center" label="24H 新增安装 ROI" prop="install_roi_24h" ></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.active_cost + ' '">
        <el-table-column align="center" label="激活成本" prop="active_cost" ></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.key_action_cost + ' '">
        <el-table-column align="center" label="关键行为成本" prop="key_action_cost"></el-table-column>
      </el-table-column>
      <el-table-column align="center" :label="total_data.advertiser_num + ' '">
        <el-table-column align="center" label="在投帐户数" prop="advertiser_num" ></el-table-column>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" :page-sizes="[10, 20, 50, 100, 200]" @pagination="fetchData" />
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import { getLaunchTotal } from '@/api/launch/lad'
import Pagination from '@/components/Pagination'
import { Message } from 'element-ui'

export default {
  label: '投放基础数据',
  name: 'Total',
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
        install_num: 0,
        install_pre_cost: 0,
        install_roi: 0,
        install_roi_24h: 0,
        active_cost: 0,
        key_action_cost: 0,
        advertiser_num: 0
      },
      listQuery: {
        page: 1,
        per_page: 100,
        app_code: undefined,
        date_begin: undefined,
        date_end: undefined,
        source: undefined,
        launch_type: undefined,
        // sort_column: undefined,
        // sort_type: undefined
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
      getLaunchTotal(this.listQuery).then(response => {
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
      const apps = this.$store.getters.userInfo.apps
      this.appList = []
      apps.forEach(app => {
        this.appList.push({ value: app.app_code, label: app.app_name })
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
      const that = this
      getLaunchTotal(query).then(response => {
        import('@/vendor/Export2Excel').then(excel => {
          const tHeader = ['日期', '产品名称','消耗',  '新增安装', '安装成本', '新增安装 ARPU','新增安装 ROI', '24h新增安装 ROI','激活成本', '关键行为成本', '在投帐户数']
          const filterVal = ['date', 'app_name','cost', 'install_num', 'install_pre_cost', 'install_arpu','install_roi','install_roi_24h', 'active_cost', 'key_action_cost', 'advertiser_num']
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
            filename: '投放=' + time
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
