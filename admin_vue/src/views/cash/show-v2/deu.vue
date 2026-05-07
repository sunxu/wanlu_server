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
            <el-option v-for="item in vendors" :key="item.code" :label="item.name" :value="item.code" />
          </el-select>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">系统版本：</span>
          <el-select v-model="listQuery.os_version" placeholder="系统版本" clearable style="width: 240px" class="filter-item">
            <el-option v-for="item in osVersions" :key="item.code" :label="item.name" :value="item.code" />
          </el-select>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">广告类型：</span>
          <el-select v-model="listQuery.ad_type" placeholder="广告类型" clearable style="width: 240px" class="filter-item">
            <el-option v-for="item in adTypes" :key="item.code" :label="item.name" :value="item.code" />
          </el-select>
        </el-col>
      </el-row>
      <el-row>
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
      <el-table-column align="center" label="DAU" prop="dau"></el-table-column>
      <el-table-column align="center" label="DAU(人均)" prop="dau_avg"></el-table-column>
      <el-table-column align="center" label="DEU" prop="deu"></el-table-column>
      <el-table-column align="center" label="DEU(人均)" prop="deu_avg"></el-table-column>
      <el-table-column align="center" label="DEU-老" prop="deu_old"></el-table-column>
      <el-table-column align="center" label="DEU-老(人均)" prop="deu_old_avg"></el-table-column>
      <el-table-column align="center" label="渗透率" prop="rate"></el-table-column>
    </el-table>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import { Message } from 'element-ui'
import { showList } from '@/api/cash/list'

export default {
  label: '广告 活跃',
  name: 'AdDeu',
  data() {
    return {
      source_map: _.clone(this.$global.SOURCE_MAP),
      launch_type_map: _.clone(this.$global.LAUNCH_TYPE_MAP),
      vendors: [
        { code: 'huawei', name: '华为' },
        { code: 'honor', name: '荣耀' },
        { code: 'oppo', name: 'OPPO' },
        { code: 'realme', name: '真我' },
        { code: 'vivo', name: 'VIVO' },
        { code: 'xiaomi', name: '小米' },
        { code: 'other', name: '其他' }
      ],
      adTypes: [
        { code: 'native', name: '信息流' },
        { code: 'inter', name: '插屏' },
        { code: 'full', name: '全屏' },
        { code: 'reword_video', name: '激励视频' },
        { code: 'splash', name: '开屏' }
      ],
      osVersions: [
        { code: 'os_lt_8', name: 'Android 8以下' },
        { code: 'os_ge_8_lt_9', name: 'Android 8' },
        { code: 'os_ge_9_lt_10', name: 'Android 9' },
        { code: 'os_ge_10_lt_11', name: 'Android 10' },
        { code: 'os_ge_11_lt_12', name: 'Android 11' },
        { code: 'os_ge_12_lt_13', name: 'Android 12' },
        { code: 'os_ge_13_lt_14', name: 'Android 13' },
        { code: 'os_ge_14_lt_15', name: 'Android 14' },
        { code: 'os_ge_15_lt_16', name: 'Android 15' },
        { code: 'os_ge_16', name: 'Android 16' }
      ],
      searchDate: undefined,
      listLoading: false,
      list: undefined,
      downloadLoading: false,
      type: 'deu',
      listQuery: {
        page: 1,
        per_page: 10,
        date_begin: undefined,
        date_end: undefined,
        version: undefined,
        channel: undefined,
        vendor: undefined,
        ad_type: undefined,
        os_version: undefined
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
    defaultDate() { // 默认时间 - 过去7天
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
      showList(getAppCode(), this.type, this.listQuery).then(response => {
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
      showList(getAppCode(), this.type, this.listQuery).then(response => {
        import('@/vendor/Export2Excel').then(excel => {
          const tHeader = [
            '日期', 'DAU', 'DAU(人均)', 'DEU', 'DEU(人均)', 'DEU-老', 'DEU-老(人均)', '渗透率'
          ]
          const filterVal = [
            'date', 'dau', 'dau_avg', 'deu', 'deu_avg', 'deu_old', 'deu_old_avg', 'rate'
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
            filename: '广告活跃-' + time
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
