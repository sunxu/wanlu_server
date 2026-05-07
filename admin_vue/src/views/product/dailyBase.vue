<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">日期：</span>
          <el-date-picker v-model="searchDate" type="daterange" style="width: 240px;" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期"></el-date-picker>
        </el-col>
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
        <el-col :span="6">
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
    >
      <el-table-column label="日期" align="center" prop="created_date" width="100" fixed></el-table-column>
        <el-table-column label="产品名称" align="center" prop="app_name" min-width="100" fixed></el-table-column>
        <el-table-column label="整体 ROI" align="center" :formatter="roiFormat"></el-table-column>
        <el-table-column label="新增安装" align="center" prop="increment_install"></el-table-column>
        <el-table-column label="新增次留" align="center" :formatter="incrementNextDayFormat"></el-table-column>
        <el-table-column label="新增人均" align="center" :formatter="incrementShowFormat"></el-table-column>
        <el-table-column label="新增 ECPM" align="center" :formatter="incrementEcpmFormat"></el-table-column>
        <el-table-column label="当日 DEU" align="center" prop="deu"></el-table-column>
        <el-table-column label="DEU 人均" align="center" :formatter="deuShowFormat"></el-table-column>
        <el-table-column label="DEU ECPM" align="center" :formatter="ecpmFormat"></el-table-column>
        <el-table-column label="华为新增" align="center" :formatter="installHuaweiFormat"></el-table-column>
        <el-table-column label="小米新增" align="center" :formatter="installXiaomiFormat"></el-table-column>
        <el-table-column label="OPPO 新增" align="center" :formatter="installOppoFormat"></el-table-column>
        <el-table-column label="VIVO 新增" align="center" :formatter="installVivoFormat"></el-table-column>
        <el-table-column label="归因" align="center" :formatter="guiyinFormat"></el-table-column>
        <el-table-column label="回收 GAP" align="center" :formatter="gapFormat"></el-table-column>
        <el-table-column label="回收占比" header-align="center" min-width="150">
        <template slot-scope="scope">
          <div v-for="item in scope.row.media_detail" style="display: inline-table;margin-left: 10px;">
              <span>{{item.media_name}}：{{scope.row.media_revenue > 0 ? (item.revenue * 100 / scope.row.media_revenue).toFixed(2) : 0}}%</span>
          </div>
        </template>
      </el-table-column>
    </el-table>
    <pagination v-show="total>0" :total="total" :page.sync="listQuery.page" :limit.sync="listQuery.per_page" @pagination="fetchData" />
  </div>
</template>

<script>
import Pagination from '@/components/Pagination'
import { parseTime } from '@/utils'
import { dailyBase } from '@/api/product/dailyBase'

export default {
  name: 'DailyBase',
  components: { Pagination },
  data() {
    return {
      searchDate: undefined,
      listLoading: false,
      list: [],
      total: 0,
      downloadLoading: false,
      listQuery: {
        app_code: [],
        page: 1,
        per_page: 10,
        date_begin: undefined,
        date_end: undefined
      }
    }
  },
  watch: {
    searchDate(searchDate) {
      if (!searchDate) {
        return
      }
      this.listQuery.date_begin = parseTime(searchDate[0], '{y}-{m}-{d}')
      this.listQuery.date_end = parseTime(searchDate[1], '{y}-{m}-{d}')
    }
  },
  created() {
    this.searchDate = [new Date() - 86400000, new Date() - 86400000]
    this.searchOptions()
    this.fetchData()
  },
  methods: {
    searchOptions() {
      const apps = this.$store.getters.userInfo.apps
      this.appList = []
      apps.forEach(app => {
        this.appList.push({ value: app.app_code, label: app.app_name })
      })
    },
    fetchData() {
      this.listLoading = true
      dailyBase(this.listQuery).then(response => {
        this.list = response.data.records
        this.total = response.data.total
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
    },
    handleDownload() {
      this.downloadLoading = true
      const query = Object.assign({}, this.listQuery)
      query.per_page = this.total
      query.page = 1
      const that = this
      dailyBase(query).then(response => {
        import('@/vendor/Export2Excel').then(excel => {
          const tHeader = ['日期', '产品名称', '整体 ROI', '新增安装', '新增次留', '新增人均', '新增 ECPM', '当日 DEU', 'DEU 人均', 'DEU ECPM', '华为新增', '小米新增', 'OPPO 新增', 'VIVO 新增', '归因', '回收 GAP', '回收占比']
          const filterVal = ['created_date','app_name','roiFormat','increment_install','incrementNextDayFormat','incrementShowFormat','incrementEcpmFormat','deu','deuShowFormat','ecpmFormat','installHuaweiFormat','installXiaomiFormat','installOppoFormat','installVivoFormat','guiyinFormat','gapFormat','mediaFormat']
          const data = response.data.records.map(v => filterVal.map(j => {
            if (j.includes('Format')) {
              let methods = this.$options.methods
              return methods[j](v)
            } else {
              return v[j]
            }
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
            filename: '产品整体基础数据-' + time
          })
          that.downloadLoading = false
        })
      })
    },
    costFormat(row, column) {
      return parseFloat(row.cost).toFixed(2)
    },
    activeCostFormat(row, column) {
      if (row.active <= 0) {
        return 0
      }
      return (row.cost / row.active).toFixed(2)
    },
    convertCostFormat(row, column) {
      if (row.convert <= 0) {
        return 0
      }
      return (row.cost / row.convert).toFixed(2)
    },
    activeNextDayFormat(row, column) {
      if (row.active <= 0) {
        return '0%'
      }
      return (row.next_day_open / row.active * 100).toFixed(2) + '%'
    },
    roiFormat(row, column) {
      if (row.cost <= 0) {
        return 0
      }
      return (row.active_revenue / row.cost).toFixed(2)
    },
    arpuFormat(row, column) {
      if (row.increment_install <= 0) {
        return 0
      }
      return (row.imputed_revenue / row.increment_install).toFixed(2)
    },
    incrementEcpmFormat(row, column) {
      if (row.increment_show <= 0) {
        return 0
      }
      return (row.imputed_revenue / row.increment_show * 1000).toFixed(2)
    },
    ecpmFormat(row, column) {
      if (row.show <= 0) {
        return 0
      }
      return (row.active_revenue / row.show * 1000).toFixed(2)
    },
    incrementNextDayFormat(row, column) {
      if (row.increment_install <= 0) {
        return '0%'
      }
      return (row.increment_next_day_open / row.increment_install * 100).toFixed(2) + '%'
    },
    guiyinFormat(row, column) {
      if (row.version_install <= 0) {
        return '0%'
      }
      return (row.version_guiyin / row.version_install * 100).toFixed(2) + '%'
    },
    incrementShowFormat(row, column) {
      if (row.increment_install <= 0) {
        return 0
      }
      return (row.increment_show / row.increment_install).toFixed(2)
    },
    deuShowFormat(row, column) {
      if (row.deu <= 0) {
        return 0
      }
      return (row.show / row.deu).toFixed(2)
    },
    installHuaweiFormat(row, column) {
      if (row.increment_install <= 0) {
        return '0%'
      }
      return (row.install_huawei / row.increment_install * 100).toFixed(2) + '%'
    },
    installXiaomiFormat(row, column) {
      if (row.increment_install <= 0) {
        return '0%'
      }
      return (row.install_xiaomi / row.increment_install * 100).toFixed(2) + '%'
    },
    installOppoFormat(row, column) {
      if (row.increment_install <= 0) {
        return '0%'
      }
      return (row.install_oppo / row.increment_install * 100).toFixed(2) + '%'
    },
    installVivoFormat(row, column) {
      if (row.increment_install <= 0) {
        return '0%'
      }
      return (row.install_vivo / row.increment_install * 100).toFixed(2) + '%'
    },
    gapFormat(row, column) {
      if (row.media_revenue <= 0) {
        return '0%'
      }
      return ((row.active_revenue - row.media_revenue) / row.media_revenue * 100).toFixed(2) + '%'
    },
    mediaFormat(row, column) {
      if (row.media_revenue <= 0) {
        return ''
      }
      let str = ''
      for (let index in row.media_detail) {
        str += row.media_detail[index].media_name + ':' + ((row.media_detail[index].revenue * 100 / row.media_revenue).toFixed(2)) + '%\n'
      }
      return str
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
