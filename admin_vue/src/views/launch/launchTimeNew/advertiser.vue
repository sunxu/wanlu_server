<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">日期：</span>
          <el-date-picker
            :clearable = "false"
            v-model="searchDate"
            @change="handleTimeChange"
            type="datetimerange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期">
          </el-date-picker>
        </el-col>
        <el-col :span="6">
          <span class="el-col-span">应用：</span>
          <el-select
            v-model="listQuery.app_codes"
            placeholder="应用"
            class="filter-item"
            filterable
            clearable
            multiple
            collapse-tags
            style="width: 240px;"
            @change="initFilter"
          >
            <el-option
              v-for="item in appList"
              :key="item.value"
              :label="item.label"
              :value="item.value">
            </el-option>
          </el-select>
        </el-col>
        <el-col :span="5">
          <span class="el-col-span">投放渠道：</span>
          <el-select v-model="listQuery.platform" placeholder="投放渠道" clearable style="width: 180px" class="filter-item">
            <el-option v-for="(value, key) in source_map" :label="value" :value="key"></el-option>
          </el-select>
        </el-col>
        <el-col :span="5">
          <span class="el-col-span">投放方式：</span>
          <el-select v-model="listQuery.launch_type" placeholder="投放方式" clearable style="width: 180px"
            class="filter-item">
            <el-option v-for="(value, key) in launch_type_map" :label="value" :value="key"></el-option>
          </el-select>
        </el-col>
      </el-row>
      <el-row>
        <el-col :span="8">
          <div class="el-col-span">广告主：</div>
          <el-cascader ref="cascader" style="width: 200px" class="filter-item" :options="advertiserList"
            :props="{ multiple: true, checkStrictly: true }" clearable filterable multiple collapse-tags></el-cascader>
        </el-col>
        <el-col :span="5">
          <span class="el-col-span">报警状态：</span>
          <el-select
            v-model="listQuery.alert_status"
            placeholder="报警状态"
            clearable
            style="width: 180px"
            class="filter-item">
            <el-option label="未知" :value="0"></el-option>
            <el-option label="正常" :value="1"></el-option>
            <el-option label="报警" :value="2"></el-option>
          </el-select>
        </el-col>
        <el-col :span="4">
          <span class="el-col-span">当前时间：</span>
          <el-switch
            style="margin-right: 30px;"
            @change="handleChange"
            v-model="nowTime">
          </el-switch>
        </el-col>
        <el-col :span="6">
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
            搜索
          </el-button>
          <el-button class="filter-item" type="primary" icon="el-icon-download" :loading="downloadLoading"
            @click="handleDownload">
            导出
          </el-button>
        </el-col>
      </el-row>

    </div>
    <el-table v-loading="listLoading" :data="list" element-loading-text="Loading" border highlight-current-row
      @sort-change="handleSort">
      <!-- <el-table-column label="账户" align="center" prop="id" width="100" fixed></el-table-column> -->
      <el-table-column label="账户ID" align="center" prop="advertiser_id" width="100" fixed sortable></el-table-column>
      <el-table-column label="账户名" align="center" prop="advertiser_name" width="100" fixed></el-table-column>
      <el-table-column align="center" label="投放方式" prop="launch_type" width="100" >
        <template slot-scope="scope">
          {{ launch_type_map[scope.row.launch_type] }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="预算" prop="budget" sortable :sort-method="(a, b) => parseFloat(a.budget || 0) - parseFloat(b.budget || 0)"></el-table-column>
      <el-table-column align="center" label="消耗" prop="cost" sortable :sort-method="(a, b) => parseFloat(a.cost || 0) - parseFloat(b.cost || 0)"></el-table-column>
      <el-table-column align="center" label="激活后24小时变现ROI（巨量）" prop="attribution_app_24h_roi"></el-table-column>
      <el-table-column align="center" label="roi（自有后端数据）" prop="roi"></el-table-column>
      <el-table-column
        align="center"
        label="报警状态"
        prop="alert_status"
        width="100"
        sortable>
        <template slot-scope="scope">
          <el-tag :type="alert_status_type_map[scope.row.alert_status]">
            {{ alert_status_map[scope.row.alert_status] }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column align="center" label="展示数" prop="show"></el-table-column>
      <el-table-column align="center" label="点击数" prop="click"></el-table-column>
      <el-table-column align="center" label="点击率(%)" prop="click_rate"></el-table-column>
      <el-table-column align="center" label="转化数" prop="convert"></el-table-column>
      <el-table-column align="center" label="平均点击单价(元)" prop="avg_click_cost"></el-table-column>
      <el-table-column align="center" label="平均千次展现费用(元)" prop="avg_show_cost"></el-table-column>
      <el-table-column align="center" label="转化成本" prop="convert_cost"></el-table-column>
      <el-table-column align="center" label="转化率(%)" prop="convert_rate"></el-table-column>
      <el-table-column align="center" label="激活后24小时变现ROI上一个小时（巨量）" prop="attribution_app_24h_roi_1hour"></el-table-column>
      <el-table-column align="center" label="同时段激活后24小时变现ROI（巨量）-1 Day" prop="attribution_app_24h_roi_1day"></el-table-column>
      <el-table-column align="center" label="同时段激活后24小时变现ROI（巨量）-2 Day" prop="attribution_app_24h_roi_2day"></el-table-column>
      <el-table-column align="center" label="同时段激活后24小时变现ROI（巨量）-3 Day" prop="attribution_app_24h_roi_3day"></el-table-column>
      <el-table-column align="center" label="健康度" prop="health_score" sortable>
        <!--"健康度指标判别:1. roi数值<最低值, 危险(红色);2. 最低值<roi数值<优秀值, 待观察(黄色);3. roi数值>优秀值, 优秀(绿色)"-->
          <template slot-scope="scope">
            <el-tag :type="health_score_type_map[scope.row.health_score]">
              {{ health_score_map[scope.row.health_score] }}
            </el-tag>
        </template>
      </el-table-column>
       <el-table-column align="center" label="广告状态" prop="put_status" width="100">
        <template slot-scope="scope">
          <el-tag  v-if="scope.row.status"  :type="put_status_type_map[scope.row.status]">
            {{ put_status_map[scope.row.status] }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column align="center" label="账户余额" prop="balance"></el-table-column>
      <el-table-column label="记录的时间" align="center" prop="record_time" width="150">
        <template slot-scope="scope">
          {{ parseTime(scope.row.record_time, '{y}-{m}-{d} {h}:{i}:{s}') }}
        </template>
      </el-table-column>
      <el-table-column align="center" label="启动时间" prop="launch_time" width="150">
        <template slot-scope="scope">
          {{ parseTime(scope.row.launch_time, '{y}-{m}-{d} {h}:{i}:{s}') }}
        </template>
      </el-table-column>

    </el-table>
    <pagination  :page-sizes="[500, 300, 100, 10]" v-show="total > 0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page"
      @pagination="fetchData" />
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import { getLaunchAdvertiserTimeNew,getSearchFilterNew } from '@/api/launch/lad'
import { advertisers } from '@/api/common'
import Pagination from '@/components/Pagination'
import { Message } from 'element-ui'

export default {
  label: '广告主投放数据',
  name: 'AdvertiserList',
  components: { Pagination },
  data() {
    return {
      source_map: _.clone(this.$global.SOURCE_MAP),
      launch_type_map: _.clone(this.$global.LAUNCH_TYPE_MAP_ROI),
      put_status_map: {
        1: '启用',
        0: '停用'
      },
      put_status_type_map: {
        1: 'success',
        0: 'danger'
      },
      health_score_map: {
        1: '危险',
        2: '待观察',
        3: '优秀'
      },
      health_score_type_map: {
        1: 'danger',
        2: 'warning',
        3: 'success'
      },
      // 报警状态映射
      alert_status_map: {
        0: '未知',
        1: '正常',
        2: '报警'
      },
      // 报警状态类型映射
      alert_status_type_map: {
        0: 'info',      // 灰色
        1: 'success',   // 绿色
        2: 'danger'     // 红色
      },
      nowTime: true,
      appList: [],
      advertiserList: [],
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
        per_page: 500,
        app_code: undefined,
        date_begin: undefined,
        date_end: undefined,
        platform: undefined,
        launch_type: undefined,
        advertiser_ids: [],
        sort_column: undefined,
        sort_type: undefined,
        alert_status: undefined  // 新增
      }
    }
  },
  created() {
    //获取当前时间年月日时 然后 时 减去一个小时
    this.searchDate = []
    const now = new Date()
    const year = now.getFullYear()
    const month = now.getMonth() + 1
    const day = now.getDate()
    const hour = now.getHours() - 1
    this.searchDate[0] = new Date(year, month - 1, day, hour, 0, 0)
    this.searchDate[1] = new Date(year, month - 1, day, hour, 59, 59)
    // this.listQuery.app_code = getAppCode()
    this.searchOptions()
  },
  mounted() {
    this.fetchData()
  },
  methods: {
    parseTime,
    handleTimeChange() {
     this.nowTime = false
    },
    handleChange() {
        const now = new Date()
        const year = now.getFullYear()
        const month = now.getMonth() + 1
        const day = now.getDate()
        const hour = now.getHours() - 1
      if (this.nowTime) {
        //获取当前时间年月日时 然后 时 减去一个小时
        let nd = []
        nd[0] = new Date(year, month - 1, day, hour, 0, 0)
        nd[1] = new Date(year, month - 1, day, hour, 59, 59)
        this.listQuery.date_begin = parseTime(nd[0], '{y}-{m}-{d} {h}:{i}:{s}')
        this.listQuery.date_end = parseTime(nd[1], '{y}-{m}-{d} {h}:{i}:{s}')  
        this.searchDate = nd
        console.log(this.listQuery.date_begin,"-",this.listQuery.date_end)
        this.handleFilter()
      }else{
        //今天0点到当前时间
          let nd = []
          nd[0] = new Date(year, month - 1, day, 0, 0, 0)
          nd[1] = new Date()
          this.listQuery.date_begin = parseTime(nd[0], '{y}-{m}-{d} {h}:{i}:{s}')
          this.listQuery.date_end = parseTime(nd[1], '{y}-{m}-{d} {h}:{i}:{s}')  
          this.searchDate = nd
          this.handleFilter()
      }
    },
    fetchData() {
      this.listLoading = true
      if (!this.searchDate) {
          this.listQuery.date_begin = ""
          this.listQuery.date_end = ""
      }else{
          this.listQuery.date_begin = parseTime(this.searchDate[0], '{y}-{m}-{d} {h}:{i}:{s}')
          this.listQuery.date_end = parseTime(this.searchDate[1], '{y}-{m}-{d} {h}:{i}:{s}')
      }
    
      const that = this
      that.listQuery.advertiser_ids = []
      this.$refs['cascader'].getCheckedNodes().forEach(function (item) {
        switch (item.level) {
          case 1:
            that.listQuery.advertiser_ids.push(item.value)
            break
        }
      })
      getLaunchAdvertiserTimeNew(this.listQuery).then(response => {
        this.total = response.data.total
        this.list = response.data.list
        // this.total_data = response.data.total_data
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
      this.initFilter()
      // advertisers(getAppCode()).then(response => {
      //   const advertiserArr = []
      //   response.data.records.forEach(function (item) {
      //     advertiserArr.push({ value: item.advertiser_id, label: item.advertiser_id + '-' + item.advertiser_name })
      //   })
      //   this.advertiserList = advertiserArr
      // })


    },
    initFilter() {
     
      let data = {
          page:'hourly_project',
          type:'advertiser_id',
          app_codes:this.listQuery.app_codes,
      }
      getSearchFilterNew(data).then(response => {
         const advertiserArr = []
        response.data.filter.forEach(function (item) {
          advertiserArr.push({ value: item.advertiser_id, label: item.advertiser_id + '-' + item.advertiser_name })
        })
        this.advertiserList = advertiserArr
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
      getLaunchAdvertiserTimeNew(query).then(response => {
        import('@/vendor/Export2Excel').then(excel => {
          const tHeader = ['账户', '账户ID', '账户名', '投放方式', '记录时间', '启动时间', '预算', '消耗', '账户余额', '展示数', '点击数', '点击率(%)', '昨日点击率(%)', '转化数', '平均点击单价(元)', '平均千次展现费用(元)', '转化成本', '转化率(%)', '激活后24小时变现ROI（巨量）', '激活后24小时变现ROI上一个小时（巨量）', '同时段激活后24小时变现ROI（巨量）-1 Day', '同时段激活后24小时变现ROI（巨量）-2 Day', '同时段激活后24小时变现ROI（巨量）-3 Day', 'roi（自有后端数据）', '报警状态', '健康度','广告状态']
          const filterVal = ['id', 'advertiser_id', 'advertiser_name',  'launch_type','record_time', 'launch_time', 'budget', 'cost', 'balance', 'show', 'click', 'click_rate', 'yesterday_ctr', 'convert', 'avg_click_cost', 'avg_show_cost', 'convert_cost', 'convert_rate', 'attribution_app_24h_roi', 'attribution_app_24h_roi_1hour', 'attribution_app_24h_roi_1day', 'attribution_app_24h_roi_2day', 'attribution_app_24h_roi_3day', 'roi', 'alert_status', 'health_score','put_status']
          const data = response.data.list.map(v => filterVal.map(j => {
            if (j === 'put_status') {
              return that.put_status_map[v[j]]
            }
            if (j === 'launch_type') {
              return that.launch_type_map[v[j]]
            }
            if (j === 'record_time') {
              return parseTime(v[j], '{y}-{m}-{d} {h}:{i}:{s}')
            }
            if (j === 'launch_time') {
              return parseTime(v[j], '{y}-{m}-{d} {h}:{i}:{s}')
            }
            if (j === 'health_score') {
              return that.health_score_map[v[j]]
            }
            if (j === 'alert_status') {
              return that.alert_status_map[v[j]]
            }
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

          let date = new Date()
          let time = date.getFullYear() + '' + (date.getMonth() + 1) + '' + date.getDate() + '' + date.getHours() + '' + date.getMinutes() + '' + date.getSeconds();
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

.el-col-span .el-col {
  display: inline-block;
  width: 80px;
  margin: 6px 0;
  font-size: 14px;
}
</style>
