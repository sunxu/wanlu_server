<template>
  <div class="app-container">
    <Variables v-if="isRecalculate" @recalculate="recalculate"></Variables>
    <el-divider></el-divider>
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">日期：</span>
          <el-date-picker v-model="searchDate" type="daterange" style="width: 240px;" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" :clearable="false"></el-date-picker>
        </el-col>
        <el-col :span="16">
          <el-button class="filter-item" type="primary" icon="el-icon-monitor" @click="calculate">
            计算
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
      <el-table-column align="center" label="新增安装成本" prop="installed_cost_avg"></el-table-column>
      <el-table-column align="center" label="首日 ROI" prop="installed_roi"></el-table-column>
      <el-table-column align="center" label="ARPU（新）" prop="installed_arpu"></el-table-column>
      <el-table-column align="center" label="ARPU（旧）" prop="old_arpu"></el-table-column>
      <el-table-column align="center" label="预估回本">
        <el-table-column align="center" label="次日" prop="mock_revenue1"></el-table-column>
        <el-table-column align="center" label="2日" prop="mock_revenue2"></el-table-column>
        <el-table-column align="center" label="3日" prop="mock_revenue3"></el-table-column>
        <el-table-column align="center" label="4日" prop="mock_revenue4"></el-table-column>
        <el-table-column align="center" label="5日" prop="mock_revenue5"></el-table-column>
        <el-table-column align="center" label="6日" prop="mock_revenue6"></el-table-column>
        <el-table-column align="center" label="7日" prop="mock_revenue7"></el-table-column>
      </el-table-column>
    </el-table>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import Variables from './variables'
import { revenueMock } from '@/api/cash/list'

export default {
  label: '预估回本周期',
  name: 'RevenueMock',
  components: { Variables },
  data() {
    return {
      isRecalculate: true,
      searchDate: undefined,
      listLoading: false,
      list: [],
      listQuery: {
        date_begin: undefined,
        date_end: undefined
      }
    }
  },
  created() {
    this.searchDate = this.defaultDate()
  },
  mounted() {
    this.calculate()
  },
  methods: {
    defaultDate() { // 默认时间 - 当天
      const format = '{y}-{m}-{d}'
      return [parseTime(new Date(), format), parseTime(new Date(), format)]
    },
    recalculate() {
      this.isRecalculate = false
      this.isRecalculate = true
      this.calculate()
    },
    calculate() {
      this.listLoading = true
      if (this.searchDate) {
        this.listQuery.date_begin = parseTime(this.searchDate[0], '{y}-{m}-{d}')
        this.listQuery.date_end = parseTime(this.searchDate[1], '{y}-{m}-{d}')
      }
      revenueMock(getAppCode(), this.listQuery).then(response => {
        this.list = response.data.records
        this.listLoading = false
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
