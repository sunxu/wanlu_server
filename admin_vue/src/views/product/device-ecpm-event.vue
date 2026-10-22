<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="7">
          <el-date-picker v-model="searchDate" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" :clearable="false"></el-date-picker>
        </el-col>
        <el-col :span="4">
          <el-select v-model="listQuery.hour_step" placeholder="指标" class="filter-item">
            <el-option v-for="(value, key) in hourCondition" :key="key" :label="value" :value="key" />
          </el-select>
        </el-col>
        <el-col :span="4">
          <el-select v-model="listQuery.vendor" placeholder="厂商" clearable class="filter-item">
            <el-option v-for="item in vendors" :key="item.code" :label="item.name" :value="item.code" />
          </el-select>
        </el-col>
        <el-col :span="4">
          <el-select v-model="listQuery.ad_type" placeholder="广告类型" clearable class="filter-item">
            <el-option v-for="item in adTypes" :key="item.code" :label="item.name" :value="item.code" />
          </el-select>
        </el-col>
        <el-col :span="4">
          <el-select v-model="listQuery.source" placeholder="投放渠道" clearable class="filter-item">
            <el-option v-for="item in sources" :key="item.code" :label="item.name" :value="item.code" />
          </el-select>
        </el-col>
      </el-row>
      <el-row style="margin-top: 20px;">
        <el-col :span="6">
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="fetchData">
            搜索
          </el-button>
        </el-col>
      </el-row>
    </div>
    <el-table
      v-loading="listLoading"
      :data="tableList"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
      style="margin-top: 20px;"
      size="mini"
      max-height="800"
    >
      <el-table-column align="center" label="日期" prop="date" min-width="70" fixed></el-table-column>
      <el-table-column align="left" label="ARPU" prop="ecpm_section" min-width="55" fixed></el-table-column>
      <el-table-column v-for="num in timesColumns" :key="num" header-align="center" align="left" :label="num+'+次'" prop="device_num_ratio_2" min-width="65">
        <template slot-scope="scope">
          {{ scope.row['device_num_ratio_' + num] }}
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import { getCondition, getList } from '@/api/product/deviceEcpmEvent'

export default {
  name: 'DeviceEcpmEvent',
  data() {
    return {
      searchDate: undefined,
      listQuery: {
        date_begin: undefined,
        date_end: undefined,
        vendor: undefined,
        ad_type: undefined,
        hour_step: '1',
        ecpm_step: undefined,
        source: undefined
      },
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
      sources: [
        { code: '1', name: '巨量' },
        { code: '2', name: '快手' },
        { code: '7', name: '荣耀' },
        { code: '8', name: '腾讯营销' }
      ],
      hourCondition: undefined,
      ecpmCondition: undefined,
      timesColumns: [],
      listLoading: false,
      tableList: []
    }
  },
  created() {
    this.searchDate = this.defaultDate()
    this.condition()
    this.fetchData()
  },
  methods: {
    defaultDate() { // 默认时间
      const currentDate = new Date()
      currentDate.setTime(currentDate.getTime() - 24 * 60 * 60 * 1000)
      const format = '{y}-{m}-{d}'
      return [parseTime(currentDate, format), parseTime(currentDate, format)]
    },
    condition() {
      getCondition(getAppCode()).then(response => {
        this.hourCondition = response.data.hour
        this.ecpmCondition = response.data.ecpm
        const timesColumns = []
        for (let i = 1; i <= response.data.times_max; i++) {
          if (i % 2 !== 0) {
            continue
          }
          timesColumns.push(i)
        }
        this.timesColumns = timesColumns
      })
    },
    fetchData() {
      this.listLoading = true
      this.listQuery.date_begin = parseTime(this.searchDate[0], '{y}-{m}-{d}')
      this.listQuery.date_end = parseTime(this.searchDate[1], '{y}-{m}-{d}')
      getList(getAppCode(), this.listQuery).then(response => {
        this.listLoading = false
        this.tableList = response.data.records
      })
    }
  }
}
</script>

<style scoped>

</style>
