<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">日期：</span>
          <el-date-picker v-model="searchDate" type="daterange" style="width: 240px;" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" :clearable="false"></el-date-picker>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">版本：</span>
          <el-input v-model="listQuery.version" placeholder="版本" size="small" style="width: 240px;" class="filter-item" />
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">产品渠道：</span>
          <el-select v-model="listQuery.channel" placeholder="产品渠道" clearable style="width: 240px" class="filter-item">
            <el-option v-for="(value,key) in $global.CHANNEL_MAP" :label="value" :value="key" />
          </el-select>
        </el-col>
      </el-row>
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">厂商：</span>
          <el-select v-model="listQuery.vendor" placeholder="厂商" clearable style="width: 240px" class="filter-item">
            <el-option v-for="(value,key) in $global.VENDOR_MAP" :label="value" :value="key" />
          </el-select>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">广告类型：</span>
          <el-select v-model="listQuery.ad_type" placeholder="广告类型" clearable style="width: 240px" class="filter-item">
            <el-option v-for="item in adTypes" :key="item.code" :label="item.name" :value="item.code" />
          </el-select>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">投放渠道：</span>
          <el-select v-model="listQuery.platform" placeholder="投放渠道" clearable style="width: 240px" class="filter-item">
            <el-option v-for="(value,key) in source_map" :label="value" :value="key"></el-option>
          </el-select>
        </el-col>
      </el-row>
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">投放方式：</span>
          <el-select v-model="listQuery.launch_type" placeholder="投放方式" clearable style="width: 240px" class="filter-item">
            <el-option v-for="(value,key) in launch_type_map" :label="value" :value="key"></el-option>
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
      <el-table-column align="center" label="日期" prop="date"></el-table-column>
      <el-table-column align="center" label="DEU" prop="deu"></el-table-column>
      <el-table-column align="center" label="DEU-ECPM" prop="deu_ecpm"></el-table-column>
      <el-table-column align="center" label="新增安装数" prop="installed_num"></el-table-column>
      <el-table-column align="center" label="ECPM" prop="installed_ecpm"></el-table-column>
      <el-table-column align="center" label="ECPM(次日)" prop="installed_ecpm_1"></el-table-column>
      <el-table-column align="center" label="ECPM(2日)" prop="installed_ecpm_2"></el-table-column>
      <el-table-column align="center" label="ECPM(3日)" prop="installed_ecpm_3"></el-table-column>
      <el-table-column align="center" label="ECPM(4日)" prop="installed_ecpm_4"></el-table-column>
      <el-table-column align="center" label="ECPM(5日)" prop="installed_ecpm_5"></el-table-column>
      <el-table-column align="center" label="ECPM(6日)" prop="installed_ecpm_6"></el-table-column>
      <el-table-column align="center" label="ECPM(7日)" prop="installed_ecpm_7"></el-table-column>
      <el-table-column align="center" label="ECPM(14日)" prop="installed_ecpm_14"></el-table-column>
      <el-table-column align="center" label="ECPM(30日)" prop="installed_ecpm_30"></el-table-column>
    </el-table>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import { Message } from 'element-ui'
import { ecpmList } from '@/api/cash/list'

export default {
  label: '广告 ECPM',
  name: 'Ecpm',
  data() {
    return {
      source_map: _.clone(this.$global.SOURCE_MAP),
      launch_type_map: _.clone(this.$global.LAUNCH_TYPE_MAP),
      adTypes: [
        { code: 'native', name: '信息流' },
        { code: 'inter', name: '插屏' },
        { code: 'full', name: '全屏' },
        { code: 'reword_video', name: '激励视频' },
        { code: 'splash', name: '开屏' }
      ],
      searchDate: undefined,
      listLoading: false,
      list: undefined,
      downloadLoading: false,
      listQuery: {
        page: 1,
        per_page: 10,
        date_begin: undefined,
        date_end: undefined,
        version: undefined,
        channel: undefined,
        vendor: undefined,
        ad_type: undefined
      }
    }
  },
  created() {
    this.searchDate = this.defaultDate()
  },
  mounted() {
    this.fetchData()
  },
  methods: {
    defaultDate() {//默认时间 - 过去7天
      const end = new Date()
      const start = new Date()
      end.setTime(end.getTime())
      start.setTime(start.getTime() - 86400 * 1000 * 7)
      const format = '{y}-{m}-{d}'
      return [parseTime(start, format), parseTime(end, format)]
    },
    fetchData() {
      this.listLoading = true
      this.listQuery.date_begin = parseTime(this.searchDate[0], '{y}-{m}-{d}')
      this.listQuery.date_end = parseTime(this.searchDate[1], '{y}-{m}-{d}')
      ecpmList(getAppCode(), this.listQuery).then(response => {
        this.list = response.data.records
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
    },
    handleDownload() {
      this.downloadLoading = true
      const that = this
      ecpmList(getAppCode(), this.listQuery).then(response => {
        import('@/vendor/Export2Excel').then(excel => {
          const tHeader = [
            '日期', 'DEU', 'DEU-ECPM', '新增安装数', 'ECPM', 'ECPM(次日)', 'ECPM(2日)', 'ECPM(3日)', 'ECPM(4日)', 'ECPM(5日)',
            'ECPM(6日)', 'ECPM(7日)', 'ECPM(14日)', 'ECPM(30日)'
          ]
          const filterVal = [
            'date', 'deu', 'deu_ecpm', 'installed_num', 'installed_ecpm', 'installed_ecpm_1', 'installed_ecpm_2', 'installed_ecpm_3',
            'installed_ecpm_4', 'installed_ecpm_5', 'installed_ecpm_6', 'installed_ecpm_7', 'installed_ecpm_14', 'installed_ecpm_30'
          ]
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

          const date = new Date()
          const time = date.getFullYear() + '' + (date.getMonth() + 1) + '' + date.getDate() + '' + date.getHours() + '' + date.getMinutes() + '' + date.getSeconds()
          excel.export_json_to_excel({
            header: tHeader,
            data,
            filename: '广告ECPM-' + time
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
